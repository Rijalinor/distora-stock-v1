<?php

namespace Tests\Feature;

use App\DTOs\CsvRowData;
use App\Enums\StockSessionItemStatus;
use App\Enums\StockSessionStatus;
use App\Enums\UserRole;
use App\Models\CsvUpload;
use App\Models\ItemMaster;
use App\Models\Principal;
use App\Models\StockSession;
use App\Models\StockSessionItem;
use App\Models\User;
use App\Services\CsvImportService;
use App\Services\StockScanningService;
use App\Services\StockSessionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StockOpnameServicesTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function it_correctly_parses_on_hand_display_formats()
    {
        // 2 levels
        $this->assertEquals('2 PCS', CsvImportService::parseOnHandDisplay('0.   2.  0. 0', 'CTN-PCS'));
        $this->assertEquals('5 CTN 62 PCS', CsvImportService::parseOnHandDisplay('5.  62.  0. 0', 'CTN-PCS'));
        
        // 3 levels
        $this->assertEquals('3 CTN 5 PCK 11 PCS', CsvImportService::parseOnHandDisplay('3.   5. 11. 0', 'CTN-PCK-PCS'));
        
        // Empty size default
        $this->assertEquals('3 CTN 1 PCS', CsvImportService::parseOnHandDisplay('3.   1.  0. 0', null));
        $this->assertEquals('0 PCS', CsvImportService::parseOnHandDisplay('0.   0.  0. 0', null));
    }

    /** @test */
    public function it_correctly_parses_conversion_factors_from_description()
    {
        $this->assertEquals([36], StockScanningService::parseConversionFactors('BAYGON COIL JMB MAX DB IS 10PSG (1X36)'));
        $this->assertEquals([12, 12], StockScanningService::parseConversionFactors('BYG LIQ REFIL 33ML (1X12X12)'));
        $this->assertEquals([4, 30], StockScanningService::parseConversionFactors('BAYGON KERTAS AKSI CEPAT FB (1X4X30)'));
        $this->assertEquals([], StockScanningService::parseConversionFactors('SIMPLE ITEM WITHOUT FACTORS'));
    }

    /** @test */
    public function it_correctly_calculates_base_quantity_from_levels_and_factors()
    {
        // 3 levels: CTN, PCK, PCS with factors [12, 12] (1 CTN = 144 PCS, 1 PCK = 12 PCS)
        // 3 CTN, 5 PCK, 11 PCS
        $base = StockScanningService::calculateBaseQuantity([3, 5, 11], [12, 12]);
        $this->assertEquals(503, $base);

        // 2 levels: CTN, PCS with factors [36] (1 CTN = 36 PCS)
        // 5 CTN, 10 PCS
        $base = StockScanningService::calculateBaseQuantity([5, 10], [36]);
        $this->assertEquals(190, $base);

        // No factors
        $base = StockScanningService::calculateBaseQuantity([15], []);
        $this->assertEquals(15, $base);
    }

    /** @test */
    public function it_correctly_splits_base_quantity_back_to_levels()
    {
        // 503 PCS with factors [12, 12] -> 3 CTN, 5 PCK, 11 PCS
        $levels = StockScanningService::splitBaseQuantity(503, [12, 12]);
        $this->assertEquals([3, 5, 11], $levels);

        // 190 PCS with factors [36] -> 5 CTN, 10 PCS
        $levels = StockScanningService::splitBaseQuantity(190, [36]);
        $this->assertEquals([5, 10], $levels);
    }

    /** @test */
    public function it_can_sync_database_and_generate_sessions_from_parsed_csv_data()
    {
        $officer = User::factory()->create(['role' => UserRole::StockOfficer]);
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        // Mock parsed rows
        $rows = [
            new CsvRowData(
                principalKode: '101',
                principalNama: 'PT. JOHNSON',
                itemKode: '696743',
                itemNama: 'BAYGON COIL (1X36)',
                satuan: 'CTN-PCS',
                qtySistemDisplay: '22 CTN 19 PCS',
                qtySistemBase: 811
            ),
            new CsvRowData(
                principalKode: '101',
                principalNama: 'PT. JOHNSON',
                itemKode: '688724',
                itemNama: 'BOS 600ML (1X12)',
                satuan: 'CTN-PCS',
                qtySistemDisplay: '2 PCS',
                qtySistemBase: 2
            ),
            new CsvRowData(
                principalKode: '136',
                principalNama: 'PT. IMPLORA',
                itemKode: 'PER801531',
                itemNama: 'PERFUME RED (1X48)',
                satuan: 'CTN-PCS',
                qtySistemDisplay: '1 CTN',
                qtySistemBase: 48
            )
        ];

        // 1. Sync database (Creates Principals and Item Masters)
        $importService = new CsvImportService();
        $importService->syncDatabase($rows);

        $this->assertDatabaseHas('principals', ['kode' => '101', 'nama' => 'PT. JOHNSON']);
        $this->assertDatabaseHas('principals', ['kode' => '136', 'nama' => 'PT. IMPLORA']);
        $this->assertDatabaseHas('item_masters', ['kode_barang' => '696743', 'nama_barang' => 'BAYGON COIL (1X36)']);

        // 2. Create CsvUpload record
        $csvUpload = CsvUpload::create([
            'filename' => 'uploads/test.csv',
            'original_filename' => 'test.csv',
            'upload_date' => now()->toDateString(),
            'uploaded_by' => $admin->id,
            'total_rows' => 3,
        ]);

        // 3. Generate sessions
        $sessionService = new StockSessionService();
        $previewResult = new \App\DTOs\CsvPreviewResult(3, [], $rows);
        $sessions = $sessionService->generateSessions($csvUpload, $previewResult);

        $this->assertCount(2, $sessions); // PT JOHNSON session and PT IMPLORA session
        
        $johnsonSession = $sessions->firstWhere('principal_id', Principal::where('kode', '101')->first()->id);
        $this->assertNotNull($johnsonSession);
        $this->assertEquals(2, $johnsonSession->total_items);

        $this->assertDatabaseHas('stock_session_items', [
            'stock_session_id' => $johnsonSession->id,
            'kode_barang' => '696743',
            'qty_sistem_base' => 811,
            'status' => StockSessionItemStatus::Pending->value
        ]);

        // 4. Assign officer
        $sessionService->assignOfficer($johnsonSession, $officer);
        $this->assertEquals(StockSessionStatus::InProgress, $johnsonSession->fresh()->status);
        $this->assertEquals($officer->id, $johnsonSession->fresh()->assigned_to);

        // 5. Scan & Record stock (Matched case)
        $scanningService = new StockScanningService($sessionService);
        
        // Setup a barcode for the item
        ItemMaster::where('kode_barang', '688724')->first()->update(['barcode' => '888724']);
        
        $item = $scanningService->findByBarcode($johnsonSession, '888724');
        $this->assertNotNull($item);
        $this->assertEquals('688724', $item->kode_barang);

        // Record stock: 0 CTN 2 PCS (base = 2). Expected: Matched
        $scanningService->recordStock($item, [0, 2], $officer);

        $item = $item->fresh();
        $this->assertEquals(2, $item->qty_aktual_base);
        $this->assertEquals('2 PCS', $item->qty_aktual_display);
        $this->assertEquals(0, $item->selisih);
        $this->assertEquals(StockSessionItemStatus::Matched, $item->status);
        
        // Recalculate progress checked
        $this->assertEquals(1, $johnsonSession->fresh()->checked_items);
        $this->assertEquals(1, $johnsonSession->fresh()->matched_items);

        // 6. Scan & Record stock (Mismatched case)
        $item2 = $scanningService->findByBarcode($johnsonSession, '696743'); // match by kode_barang directly
        $this->assertNotNull($item2);
        
        // System is 22 CTN 19 PCS (base = 811)
        // Officer inputs 22 CTN 15 PCS (base = 22 * 36 + 15 = 807). Expected: Mismatched, selisih = -4
        $scanningService->recordStock($item2, [22, 15], $officer);

        $item2 = $item2->fresh();
        $this->assertEquals(807, $item2->qty_aktual_base);
        $this->assertEquals(-4, $item2->selisih);
        $this->assertEquals(StockSessionItemStatus::Mismatched, $item2->status);

        $this->assertEquals(2, $johnsonSession->fresh()->checked_items);
        $this->assertEquals(1, $johnsonSession->fresh()->mismatched_items);

        // 7. Update stock with log
        $scanningService->updateStock($item2, [22, 19], $officer, 'Salah hitung awal');
        
        $item2 = $item2->fresh();
        $this->assertEquals(811, $item2->qty_aktual_base);
        $this->assertEquals(0, $item2->selisih);
        $this->assertEquals(StockSessionItemStatus::Matched, $item2->status);

        $this->assertDatabaseHas('stock_adjustment_logs', [
            'stock_session_item_id' => $item2->id,
            'qty_before_base' => 807,
            'qty_after_base' => 811,
            'reason' => 'Salah hitung awal'
        ]);

        // 8. Complete session
        $sessionService->completeSession($johnsonSession);
        $this->assertEquals(StockSessionStatus::Completed, $johnsonSession->fresh()->status);
    }
}
