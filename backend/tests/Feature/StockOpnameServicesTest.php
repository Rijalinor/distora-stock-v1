<?php

namespace Tests\Feature;

use App\DTOs\CsvRowData;
use App\Enums\StockSessionItemStatus;
use App\Enums\StockSessionStatus;
use App\Enums\UserRole;
use App\Filament\Resources\ItemMasters\ItemMasterResource;
use App\Models\Branch;
use App\Models\CsvUpload;
use App\Models\ItemMaster;
use App\Models\Principal;
use App\Models\StockSession;
use App\Models\StockSessionItem;
use App\Models\User;
use App\Services\CsvImportService;
use App\Services\ItemMasterBackupService;
use App\Services\ReportService;
use App\Services\StockScanningService;
use App\Services\StockSessionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
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
        $this->assertEquals('13346 PCS', CsvImportService::parseOnHandDisplay('13346.   0.  0. 0', null, 'CN ULTRA PASTELS ASH SRP (1X1)'));
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
        $branch = Branch::where('kode', 'PUSAT')->firstOrFail();

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
        $this->assertDatabaseHas('item_masters', ['branch_id' => $branch->id, 'kode_barang' => '696743', 'nama_barang' => 'BAYGON COIL (1X36)']);

        // 2. Create CsvUpload record
        $csvUpload = CsvUpload::create([
            'filename' => 'uploads/test.csv',
            'original_filename' => 'test.csv',
            'upload_date' => now()->toDateString(),
            'branch_id' => $branch->id,
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
        $this->assertEquals($branch->id, $johnsonSession->branch_id);

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

        $this->assertDatabaseMissing('audit_logs', [
            'action' => 'stock_recorded',
        ]);

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
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'stock_corrected',
            'auditable_id' => $item2->id,
        ]);

        // 8. Complete session
        $sessionService->completeSession($johnsonSession);
        $this->assertEquals(StockSessionStatus::Completed, $johnsonSession->fresh()->status);
    }

    /** @test */
    public function it_can_backup_item_master_barcode_and_size_data()
    {
        $branch = Branch::where('kode', 'PUSAT')->firstOrFail();
        $principal = Principal::create([
            'kode' => 'P001',
            'nama' => 'Principal Test',
            'status' => true,
        ]);

        ItemMaster::create([
            'branch_id' => $branch->id,
            'kode_barang' => 'ITEM001',
            'barcode' => '8991234567890',
            'nama_barang' => 'Barang Test',
            'principal_id' => $principal->id,
            'satuan' => 'CTN-PCS',
            'qty_structure' => [
                ['label' => 'CTN', 'factor' => 12],
                ['label' => 'PCS', 'factor' => 1],
            ],
            'status' => true,
        ]);

        $csv = app(ItemMasterBackupService::class)->buildCsv();

        $this->assertStringContainsString('"=""8991234567890"""', $csv);
        $this->assertStringContainsString('branch_kode', $csv);
        $this->assertStringContainsString('"=""ITEM001"""', $csv);
        $this->assertStringContainsString('CTN-PCS', $csv);
        $this->assertStringContainsString('qty_structure_json', $csv);
        $this->assertStringContainsString('12', $csv);
    }

    /** @test */
    public function it_can_restore_item_master_backup_csv()
    {
        $csv = implode("\n", [
            '"principal_kode","principal_nama","kode_barang","barcode","nama_barang","satuan","qty_labels","qty_factors","qty_structure_json","status","updated_at"',
            '"=""P002""","Principal Restore","=""ITEM002""","=""8990002""","Barang Restore","CTN-PCS","CTN-PCS","24","[{""label"":""CTN"",""factor"":24},{""label"":""PCS"",""factor"":1}]","active","2026-07-20 10:00:00"',
            '',
        ]);

        $file = UploadedFile::fake()->createWithContent('backup-item-master.csv', $csv);
        $stats = app(ItemMasterBackupService::class)->restoreCsv($file);

        $this->assertEquals(['created' => 1, 'updated' => 0, 'skipped' => 0], $stats);
        $this->assertDatabaseHas('principals', ['kode' => 'P002', 'nama' => 'Principal Restore']);
        $this->assertDatabaseHas('item_masters', [
            'branch_id' => Branch::where('kode', 'PUSAT')->value('id'),
            'kode_barang' => 'ITEM002',
            'barcode' => '8990002',
            'nama_barang' => 'Barang Restore',
            'satuan' => 'CTN-PCS',
        ]);

        $item = ItemMaster::where('kode_barang', 'ITEM002')->first();

        $this->assertEquals(['CTN', 'PCS'], $item->getQtyLabelsArray());
        $this->assertEquals([24], $item->getQtyFactorsArray());
    }

    /** @test */
    public function it_returns_multiple_session_items_for_duplicate_barcode()
    {
        $branch = Branch::where('kode', 'PUSAT')->firstOrFail();
        $principal = Principal::create([
            'kode' => 'P003',
            'nama' => 'Principal Duplicate',
            'status' => true,
        ]);

        $first = ItemMaster::create([
            'branch_id' => $branch->id,
            'kode_barang' => 'ITEM-A',
            'barcode' => '899DUP',
            'nama_barang' => 'Barang A',
            'principal_id' => $principal->id,
            'satuan' => 'CTN-PCS',
            'status' => true,
        ]);

        $second = ItemMaster::create([
            'branch_id' => $branch->id,
            'kode_barang' => 'ITEM-B',
            'barcode' => '899DUP',
            'nama_barang' => 'Barang B',
            'principal_id' => $principal->id,
            'satuan' => 'CTN-PCS',
            'status' => true,
        ]);

        $session = StockSession::create([
            'principal_id' => $principal->id,
            'branch_id' => $branch->id,
            'session_date' => today(),
            'status' => StockSessionStatus::Open,
            'total_items' => 2,
        ]);

        StockSessionItem::create([
            'stock_session_id' => $session->id,
            'item_master_id' => $first->id,
            'kode_barang' => 'ITEM-A',
            'nama_barang' => 'Barang A',
            'satuan' => 'CTN-PCS',
            'qty_sistem_display' => '1 PCS',
            'qty_sistem_base' => 1,
            'status' => StockSessionItemStatus::Pending,
        ]);

        StockSessionItem::create([
            'stock_session_id' => $session->id,
            'item_master_id' => $second->id,
            'kode_barang' => 'ITEM-B',
            'nama_barang' => 'Barang B',
            'satuan' => 'CTN-PCS',
            'qty_sistem_display' => '2 PCS',
            'qty_sistem_base' => 2,
            'status' => StockSessionItemStatus::Pending,
        ]);

        $items = app(StockScanningService::class)->findItemsByBarcode($session, '899DUP');

        $this->assertCount(2, $items);
        $this->assertEquals(['ITEM-A', 'ITEM-B'], $items->pluck('kode_barang')->all());
    }

    /** @test */
    public function it_keeps_item_master_codes_and_scans_separate_per_branch()
    {
        $pusat = Branch::where('kode', 'PUSAT')->firstOrFail();
        $branch = Branch::create([
            'kode' => 'CBG01',
            'nama' => 'Cabang 01',
            'status' => true,
        ]);

        $rows = [
            new CsvRowData(
                principalKode: 'P010',
                principalNama: 'Principal Branch',
                itemKode: 'SAME001',
                itemNama: 'Barang Cabang (1X12)',
                satuan: 'CTN-PCS',
                qtySistemDisplay: '1 CTN',
                qtySistemBase: 12
            ),
        ];

        $importService = app(CsvImportService::class);
        $importService->syncDatabase($rows, $pusat->id);
        $importService->syncDatabase($rows, $branch->id);

        $this->assertDatabaseCount('item_masters', 2);
        $this->assertDatabaseHas('item_masters', ['branch_id' => $pusat->id, 'kode_barang' => 'SAME001']);
        $this->assertDatabaseHas('item_masters', ['branch_id' => $branch->id, 'kode_barang' => 'SAME001']);

        $principal = Principal::where('kode', 'P010')->firstOrFail();
        $pusatItem = ItemMaster::where('branch_id', $pusat->id)->where('kode_barang', 'SAME001')->firstOrFail();
        $branchItem = ItemMaster::where('branch_id', $branch->id)->where('kode_barang', 'SAME001')->firstOrFail();
        $pusatItem->update(['barcode' => '899SAME']);
        $branchItem->update(['barcode' => '899SAME']);

        $session = StockSession::create([
            'principal_id' => $principal->id,
            'branch_id' => $branch->id,
            'session_date' => today(),
            'status' => StockSessionStatus::Open,
            'total_items' => 1,
        ]);

        StockSessionItem::create([
            'stock_session_id' => $session->id,
            'item_master_id' => $branchItem->id,
            'kode_barang' => 'SAME001',
            'nama_barang' => 'Barang Cabang (1X12)',
            'satuan' => 'CTN-PCS',
            'qty_sistem_display' => '1 CTN',
            'qty_sistem_base' => 12,
            'status' => StockSessionItemStatus::Pending,
        ]);

        $items = app(StockScanningService::class)->findItemsByBarcode($session, '899SAME');

        $this->assertCount(1, $items);
        $this->assertEquals($branchItem->id, $items->first()->item_master_id);
    }

    /** @test */
    public function it_scopes_item_master_access_for_branch_admins()
    {
        $branch = Branch::where('kode', 'PUSAT')->firstOrFail();
        $otherBranch = Branch::create([
            'kode' => 'CBG02',
            'nama' => 'Cabang 02',
            'status' => true,
        ]);
        $principal = Principal::create([
            'kode' => 'P020',
            'nama' => 'Principal Access',
            'status' => true,
        ]);
        $branchItem = ItemMaster::create([
            'branch_id' => $branch->id,
            'kode_barang' => 'BRANCH-ITEM',
            'nama_barang' => 'Barang Cabang',
            'principal_id' => $principal->id,
            'status' => true,
        ]);
        $otherItem = ItemMaster::create([
            'branch_id' => $otherBranch->id,
            'kode_barang' => 'OTHER-ITEM',
            'nama_barang' => 'Barang Cabang Lain',
            'principal_id' => $principal->id,
            'status' => true,
        ]);
        $branchAdmin = User::factory()->create(['role' => UserRole::Admin, 'branch_id' => $branch->id]);
        $centralAdmin = User::factory()->create(['role' => UserRole::Admin, 'branch_id' => null]);

        $this->actingAs($branchAdmin);
        $this->assertEquals(['BRANCH-ITEM'], ItemMasterResource::getEloquentQuery()->pluck('kode_barang')->all());
        $this->assertTrue(ItemMasterResource::canEdit($branchItem));
        $this->assertFalse(ItemMasterResource::canEdit($otherItem));

        $this->actingAs($centralAdmin);
        $this->assertEqualsCanonicalizing(
            ['BRANCH-ITEM', 'OTHER-ITEM'],
            ItemMasterResource::getEloquentQuery()->pluck('kode_barang')->all()
        );
        $this->assertTrue(ItemMasterResource::canEdit($branchItem));
        $this->assertTrue(ItemMasterResource::canEdit($otherItem));
    }

    /** @test */
    public function it_restores_item_master_backup_into_the_branch_from_the_file()
    {
        $csv = implode("\n", [
            '"branch_kode","branch_nama","principal_kode","principal_nama","kode_barang","barcode","nama_barang","satuan","qty_labels","qty_factors","qty_structure_json","status","updated_at"',
            '"=""CBG03""","Cabang 03","=""P030""","Principal Cabang","=""ITEM-CBG""","=""899CBG""","Barang Cabang","PCS","PCS","","[{""label"":""PCS"",""factor"":1}]","active","2026-07-23 10:00:00"',
            '',
        ]);

        $file = UploadedFile::fake()->createWithContent('backup-item-master-cabang.csv', $csv);
        $stats = app(ItemMasterBackupService::class)->restoreCsv($file);
        $branchId = Branch::where('kode', 'CBG03')->value('id');

        $this->assertEquals(['created' => 1, 'updated' => 0, 'skipped' => 0], $stats);
        $this->assertDatabaseHas('branches', ['kode' => 'CBG03', 'nama' => 'Cabang 03']);
        $this->assertDatabaseHas('item_masters', [
            'branch_id' => $branchId,
            'kode_barang' => 'ITEM-CBG',
            'barcode' => '899CBG',
        ]);
    }

    /** @test */
    public function it_filters_daily_report_exports_by_branch()
    {
        $branch = Branch::where('kode', 'PUSAT')->firstOrFail();
        $otherBranch = Branch::create([
            'kode' => 'CBG04',
            'nama' => 'Cabang 04',
            'status' => true,
        ]);
        $principal = Principal::create([
            'kode' => 'P040',
            'nama' => 'Principal Branch Report',
            'status' => true,
        ]);

        foreach ([[$branch, 'ITEM-PUSAT'], [$otherBranch, 'ITEM-CBG04']] as [$currentBranch, $kode]) {
            $session = StockSession::create([
                'principal_id' => $principal->id,
                'branch_id' => $currentBranch->id,
                'session_date' => '2026-07-23',
                'status' => StockSessionStatus::InProgress,
                'total_items' => 1,
            ]);

            StockSessionItem::create([
                'stock_session_id' => $session->id,
                'kode_barang' => $kode,
                'nama_barang' => "Barang {$kode}",
                'satuan' => 'PCS',
                'qty_sistem_display' => '1 PCS',
                'qty_sistem_base' => 1,
                'qty_aktual_display' => '1 PCS',
                'qty_aktual_base' => 1,
                'selisih' => 0,
                'status' => StockSessionItemStatus::Matched,
            ]);
        }

        $csv = app(ReportService::class)->buildDailyCsv('2026-07-23', null, $branch->id);

        $this->assertStringContainsString('ITEM-PUSAT', $csv);
        $this->assertStringNotContainsString('ITEM-CBG04', $csv);
    }

    /** @test */
    public function it_exports_daily_report_quantities_as_display_units()
    {
        $principal = Principal::create([
            'kode' => 'P004',
            'nama' => 'Principal Report',
            'status' => true,
        ]);

        $itemMaster = ItemMaster::create([
            'branch_id' => Branch::where('kode', 'PUSAT')->value('id'),
            'kode_barang' => 'ITEM-R',
            'barcode' => '899R',
            'nama_barang' => 'Barang Report',
            'principal_id' => $principal->id,
            'satuan' => 'CTN-PCK-PCS',
            'qty_structure' => [
                ['label' => 'CTN', 'factor' => 12],
                ['label' => 'PCK', 'factor' => 30],
                ['label' => 'PCS', 'factor' => 1],
            ],
            'status' => true,
        ]);

        $session = StockSession::create([
            'principal_id' => $principal->id,
            'session_date' => '2026-07-22',
            'status' => StockSessionStatus::InProgress,
            'total_items' => 1,
        ]);

        StockSessionItem::create([
            'stock_session_id' => $session->id,
            'item_master_id' => $itemMaster->id,
            'kode_barang' => 'ITEM-R',
            'nama_barang' => 'Barang Report',
            'satuan' => 'CTN-PCK-PCS',
            'qty_sistem_display' => '1 CTN 3 PCK',
            'qty_sistem_base' => 450,
            'qty_aktual_display' => '1 CTN 2 PCK 15 PCS',
            'qty_aktual_base' => 435,
            'selisih' => -15,
            'status' => StockSessionItemStatus::Mismatched,
        ]);

        $csv = app(ReportService::class)->buildDailyCsv('2026-07-22');

        $this->assertStringNotContainsString('Plus', $csv);
        $this->assertStringNotContainsString('Minus', $csv);
        $this->assertStringContainsString('"=""899R"""', $csv);
        $this->assertStringContainsString('1 CTN 3 PCK', $csv);
        $this->assertStringContainsString('1 CTN 2 PCK 15 PCS', $csv);
        $this->assertStringContainsString('-15 PCS', $csv);

        $sessionCsv = app(ReportService::class)->buildSessionCsv($session);
        $selisihCsv = app(ReportService::class)->buildSelisihCsv('2026-07-22');

        $this->assertStringContainsString('"=""ITEM-R"""', $sessionCsv);
        $this->assertStringContainsString('-15 PCS', $sessionCsv);
        $this->assertStringContainsString('"=""ITEM-R"""', $selisihCsv);
        $this->assertStringContainsString('-15 PCS', $selisihCsv);
    }

    /** @test */
    public function it_formats_one_to_one_items_as_pcs_not_ctn()
    {
        $principal = Principal::create([
            'kode' => 'P005',
            'nama' => 'Principal PCS',
            'status' => true,
        ]);

        $itemMaster = ItemMaster::create([
            'branch_id' => Branch::where('kode', 'PUSAT')->value('id'),
            'kode_barang' => 'G98342A',
            'barcode' => '899PCS',
            'nama_barang' => 'CN ULTRA PASTELS ASH SRP (1X1)',
            'principal_id' => $principal->id,
            'satuan' => null,
            'status' => true,
        ]);

        $session = StockSession::create([
            'principal_id' => $principal->id,
            'session_date' => '2026-07-23',
            'status' => StockSessionStatus::InProgress,
            'total_items' => 1,
        ]);

        $item = StockSessionItem::create([
            'stock_session_id' => $session->id,
            'item_master_id' => $itemMaster->id,
            'kode_barang' => 'G98342A',
            'nama_barang' => 'CN ULTRA PASTELS ASH SRP (1X1)',
            'satuan' => null,
            'qty_sistem_display' => '13346 CTN',
            'qty_sistem_base' => 13346,
            'status' => StockSessionItemStatus::Pending,
        ]);

        $this->assertEquals('13346 PCS', app(ReportService::class)->formatBaseQty($item->qty_sistem_base, $item));
    }

    /** @test */
    public function it_distinguishes_central_admin_from_branch_admin()
    {
        $branch = Branch::where('kode', 'PUSAT')->firstOrFail();
        $centralAdmin = User::factory()->create(['role' => UserRole::Admin, 'branch_id' => null]);
        $branchAdmin = User::factory()->create(['role' => UserRole::Admin, 'branch_id' => $branch->id]);

        $this->assertTrue($centralAdmin->isCentralAdmin());
        $this->assertTrue($centralAdmin->managesBranch($branch->id));
        $this->assertFalse($branchAdmin->isCentralAdmin());
        $this->assertTrue($branchAdmin->managesBranch($branch->id));
        $this->assertFalse($branchAdmin->managesBranch($branch->id + 1));
    }
}
