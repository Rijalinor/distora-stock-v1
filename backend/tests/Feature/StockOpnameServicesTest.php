<?php

namespace Tests\Feature;

use App\DTOs\CsvRowData;
use App\Enums\StockSessionItemStatus;
use App\Enums\StockSessionStatus;
use App\Enums\UserRole;
use App\Filament\Resources\ItemMasters\ItemMasterResource;
use App\Filament\Resources\Principals\PrincipalResource;
use App\Models\Branch;
use App\Models\CsvUpload;
use App\Models\ItemMaster;
use App\Models\ItemBarcode;
use App\Models\Principal;
use App\Models\StockFoundItem;
use App\Models\StockSession;
use App\Models\StockSessionItem;
use App\Models\User;
use App\Services\CsvImportService;
use App\Services\ItemMasterBackupService;
use App\Services\ItemMasterTransferService;
use App\Services\ReportService;
use App\Services\StockFoundItemService;
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
    public function it_stores_ctn_and_pcs_actual_counts_separately_for_enabled_principals()
    {
        $branch = Branch::where('kode', 'PUSAT')->firstOrFail();
        $principal = Principal::create([
            'kode' => 'SEP',
            'nama' => 'Principal Separate',
            'separate_ctn_pcs_count' => true,
            'status' => true,
        ]);
        $officer = User::factory()->create(['role' => UserRole::StockOfficer, 'branch_id' => $branch->id]);
        $itemMaster = ItemMaster::create([
            'branch_id' => $branch->id,
            'kode_barang' => 'SEP-001',
            'nama_barang' => 'Barang Separate (1X24)',
            'principal_id' => $principal->id,
            'satuan' => 'CTN-PCS',
            'qty_structure' => [
                ['label' => 'CTN', 'factor' => 24],
                ['label' => 'PCS', 'factor' => 1],
            ],
            'status' => true,
        ]);
        $session = StockSession::create([
            'principal_id' => $principal->id,
            'branch_id' => $branch->id,
            'session_date' => today(),
            'status' => StockSessionStatus::InProgress,
            'total_items' => 1,
        ]);
        $item = StockSessionItem::create([
            'stock_session_id' => $session->id,
            'item_master_id' => $itemMaster->id,
            'kode_barang' => 'SEP-001',
            'nama_barang' => 'Barang Separate (1X24)',
            'satuan' => 'CTN-PCS',
            'qty_sistem_display' => '16 CTN 5 PCS',
            'qty_sistem_base' => 389,
            'status' => StockSessionItemStatus::Pending,
        ]);

        app(StockScanningService::class)->recordStock($item, [16, 5], $officer);

        $item = $item->fresh();
        $this->assertSame(16, $item->qty_aktual_ctn);
        $this->assertSame(5, $item->qty_aktual_pcs);
        $this->assertSame(389, $item->qty_aktual_base);
        $this->assertSame('16 CTN 5 PCS', $item->qty_aktual_display);
    }

    /** @test */
    public function separate_count_mode_can_mark_ctn_and_pcs_one_at_a_time()
    {
        $branch = Branch::where('kode', 'PUSAT')->firstOrFail();
        $principal = Principal::create([
            'kode' => 'SEP-MODE',
            'nama' => 'Principal Separate Mode',
            'separate_ctn_pcs_count' => true,
            'status' => true,
        ]);
        $officer = User::factory()->create(['role' => UserRole::StockOfficer, 'branch_id' => $branch->id]);
        $itemMaster = ItemMaster::create([
            'branch_id' => $branch->id,
            'kode_barang' => 'SEP-MODE-001',
            'barcode' => '899SEPMODE',
            'nama_barang' => 'Barang Separate Mode (1X12X24)',
            'principal_id' => $principal->id,
            'satuan' => 'CTN-PCK-PCS',
            'qty_structure' => [
                ['label' => 'CTN', 'factor' => 12],
                ['label' => 'PCK', 'factor' => 24],
                ['label' => 'PCS', 'factor' => 1],
            ],
            'status' => true,
        ]);
        $session = StockSession::create([
            'principal_id' => $principal->id,
            'branch_id' => $branch->id,
            'session_date' => today(),
            'status' => StockSessionStatus::InProgress,
            'total_items' => 1,
        ]);
        $item = StockSessionItem::create([
            'stock_session_id' => $session->id,
            'item_master_id' => $itemMaster->id,
            'kode_barang' => 'SEP-MODE-001',
            'barcode' => '899SEPMODE',
            'nama_barang' => 'Barang Separate Mode (1X12X24)',
            'satuan' => 'CTN-PCK-PCS',
            'qty_sistem_display' => '1 CTN 1 PCK 1 PCS',
            'qty_sistem_base' => 313,
            'status' => StockSessionItemStatus::Pending,
        ]);

        $this->actingAs($officer);

        \Livewire\Livewire::test(\App\Filament\Pages\StockScanning::class)
            ->set('selectedSessionId', $session->id)
            ->set('separateCountMode', 'ctn')
            ->call('scanBarcode', '899SEPMODE', true)
            ->call('markCurrentModeMatched');

        $item = $item->fresh();
        $this->assertSame(1, $item->qty_aktual_ctn);
        $this->assertSame(0, $item->qty_aktual_pcs);
        $this->assertSame('1 CTN', $item->qty_aktual_display);
        $this->assertEquals(StockSessionItemStatus::Mismatched, $item->status);

        \Livewire\Livewire::test(\App\Filament\Pages\StockScanning::class)
            ->set('selectedSessionId', $session->id)
            ->set('separateCountMode', 'pcs')
            ->call('scanBarcode', '899SEPMODE', true)
            ->call('markCurrentModeMatched');

        $item = $item->fresh();
        $this->assertSame(1, $item->qty_aktual_ctn);
        $this->assertSame(1, $item->qty_aktual_pcs);
        $this->assertSame(313, $item->qty_aktual_base);
        $this->assertSame('1 CTN 1 PCK 1 PCS', $item->qty_aktual_display);
        $this->assertEquals(StockSessionItemStatus::Matched, $item->status);
    }

    /** @test */
    public function inactive_branch_principal_items_are_hidden_from_scan_sessions()
    {
        $branch = Branch::where('kode', 'PUSAT')->firstOrFail();
        $principal = Principal::create([
            'kode' => 'SCAN-OFF',
            'nama' => 'Principal Scan Off',
            'status' => true,
        ]);
        $itemMaster = ItemMaster::create([
            'branch_id' => $branch->id,
            'kode_barang' => 'SCAN-OFF-001',
            'nama_barang' => 'Barang Scan Off',
            'principal_id' => $principal->id,
            'satuan' => 'PCS',
            'status' => true,
        ]);
        $session = StockSession::create([
            'principal_id' => $principal->id,
            'branch_id' => $branch->id,
            'session_date' => today(),
            'status' => StockSessionStatus::Open,
            'total_items' => 1,
        ]);
        StockSessionItem::create([
            'stock_session_id' => $session->id,
            'item_master_id' => $itemMaster->id,
            'kode_barang' => 'SCAN-OFF-001',
            'nama_barang' => 'Barang Scan Off',
            'satuan' => 'PCS',
            'qty_sistem_display' => '1 PCS',
            'qty_sistem_base' => 1,
            'status' => StockSessionItemStatus::Pending,
        ]);
        $admin = User::factory()->create(['role' => UserRole::Admin, 'branch_id' => $branch->id]);

        $this->actingAs($admin);
        $page = new \App\Filament\Pages\StockScanning();
        $this->assertSame([$session->id], $page->getAvailableSessions()->pluck('id')->all());

        $itemMaster->update(['status' => false]);

        $this->assertSame([], $page->getAvailableSessions()->pluck('id')->all());
        $this->assertTrue($principal->fresh()->status);
    }

    /** @test */
    public function it_can_sync_database_and_generate_sessions_from_parsed_csv_data()
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $branch = Branch::where('kode', 'PUSAT')->firstOrFail();
        $officer = User::factory()->create(['role' => UserRole::StockOfficer, 'branch_id' => $branch->id]);

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
        $this->assertDatabaseHas('stock_session_user', [
            'stock_session_id' => $johnsonSession->id,
            'user_id' => $officer->id,
        ]);

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
    public function it_allows_multiple_officers_on_one_session_and_locks_one_item_at_a_time()
    {
        $branch = Branch::where('kode', 'PUSAT')->firstOrFail();
        $principal = Principal::create([
            'kode' => 'LOCK',
            'nama' => 'Principal Lock',
            'status' => true,
        ]);
        $firstOfficer = User::factory()->create(['role' => UserRole::StockOfficer, 'branch_id' => $branch->id]);
        $secondOfficer = User::factory()->create(['role' => UserRole::StockOfficer, 'branch_id' => $branch->id]);
        $session = StockSession::create([
            'principal_id' => $principal->id,
            'branch_id' => $branch->id,
            'session_date' => today(),
            'status' => StockSessionStatus::Open,
            'total_items' => 1,
        ]);
        $item = StockSessionItem::create([
            'stock_session_id' => $session->id,
            'kode_barang' => 'LOCK-ITEM',
            'nama_barang' => 'Barang Lock',
            'satuan' => 'PCS',
            'qty_sistem_display' => '1 PCS',
            'qty_sistem_base' => 1,
            'status' => StockSessionItemStatus::Pending,
        ]);

        $sessionService = app(StockSessionService::class);
        $sessionService->assignOfficer($session, $firstOfficer);
        $sessionService->assignOfficer($session->fresh(), $secondOfficer);

        $this->assertEquals($firstOfficer->id, $session->fresh()->assigned_to);
        $this->assertEqualsCanonicalizing(
            [$firstOfficer->id, $secondOfficer->id],
            $session->fresh()->officers()->pluck('users.id')->all()
        );

        $scanningService = app(StockScanningService::class);
        $scanningService->acquireLock($item, $firstOfficer);

        $this->expectException(\RuntimeException::class);
        $scanningService->acquireLock($item->fresh(), $secondOfficer);
    }

    /** @test */
    public function it_rejects_assigning_an_officer_from_another_branch()
    {
        $branch = Branch::where('kode', 'PUSAT')->firstOrFail();
        $otherBranch = Branch::create(['kode' => 'SEC02', 'nama' => 'Cabang Security', 'status' => true]);
        $principal = Principal::create(['kode' => 'SEC', 'nama' => 'Principal Security', 'status' => true]);
        $session = StockSession::create([
            'principal_id' => $principal->id, 'branch_id' => $branch->id,
            'session_date' => today(), 'status' => StockSessionStatus::Open, 'total_items' => 0,
        ]);
        $otherOfficer = User::factory()->create(['role' => UserRole::StockOfficer, 'branch_id' => $otherBranch->id]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('cabang sesi yang sama');
        app(StockSessionService::class)->assignOfficer($session, $otherOfficer);
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

        $item = ItemMaster::create([
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
        ItemBarcode::create([
            'item_master_id' => $item->id, 'branch_id' => $branch->id,
            'barcode' => '8991234567890', 'unit_label' => 'PCS', 'qty_base' => 1, 'is_primary' => true,
        ]);
        ItemBarcode::create([
            'item_master_id' => $item->id, 'branch_id' => $branch->id,
            'barcode' => '18991234567890', 'unit_label' => 'CTN', 'qty_base' => 12, 'is_primary' => false,
        ]);

        $csv = app(ItemMasterBackupService::class)->buildCsv();

        $this->assertStringContainsString('"=""8991234567890"""', $csv);
        $this->assertStringContainsString('branch_kode', $csv);
        $this->assertStringContainsString('"=""ITEM001"""', $csv);
        $this->assertStringContainsString('CTN-PCS', $csv);
        $this->assertStringContainsString('qty_structure_json', $csv);
        $this->assertStringContainsString('barcodes_json', $csv);
        $this->assertStringContainsString('18991234567890', $csv);
        $this->assertStringContainsString('12', $csv);

        $item->delete();
        app(ItemMasterBackupService::class)->restoreCsv(UploadedFile::fake()->createWithContent('roundtrip.csv', $csv));
        $restored = ItemMaster::where('kode_barang', 'ITEM001')->firstOrFail();
        $this->assertEqualsCanonicalizing(
            ['8991234567890', '18991234567890'],
            $restored->barcodes()->pluck('barcode')->all()
        );
        $this->assertDatabaseHas('item_barcodes', ['item_master_id' => $restored->id, 'barcode' => '18991234567890', 'unit_label' => 'CTN', 'qty_base' => 12]);
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
        $preview = app(ItemMasterBackupService::class)->previewCsv($file);

        $this->assertEquals(1, $preview['created']);
        $this->assertEquals(0, $preview['updated']);
        $this->assertEquals('ITEM002', $preview['examples'][0]['code']);
        $this->assertDatabaseMissing('item_masters', ['kode_barang' => 'ITEM002']);

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
        $this->assertDatabaseHas('item_barcodes', [
            'item_master_id' => $item->id, 'barcode' => '8990002',
            'unit_label' => 'PCS', 'qty_base' => 1, 'is_primary' => true,
        ]);

        $previewAfterRestore = app(ItemMasterBackupService::class)->previewCsv($file);
        $this->assertEquals(0, $previewAfterRestore['created']);
        $this->assertEquals(1, $previewAfterRestore['updated']);
    }

    /** @test */
    public function it_copies_one_principal_item_master_between_branches()
    {
        $sourceBranch = Branch::where('kode', 'PUSAT')->firstOrFail();
        $targetBranch = Branch::create([
            'kode' => 'TRANSFER',
            'nama' => 'Cabang Transfer',
            'status' => true,
        ]);
        $principal = Principal::create([
            'kode' => 'PTRANSFER',
            'nama' => 'Principal Transfer',
            'status' => true,
        ]);
        $otherPrincipal = Principal::create([
            'kode' => 'POTHER',
            'nama' => 'Principal Lain',
            'status' => true,
        ]);

        ItemMaster::create([
            'branch_id' => $sourceBranch->id,
            'kode_barang' => 'COPY-001',
            'barcode' => '899COPY001',
            'nama_barang' => 'Barang Copy Baru',
            'principal_id' => $principal->id,
            'satuan' => 'CTN-PCS',
            'qty_structure' => [
                ['label' => 'CTN', 'factor' => 12],
                ['label' => 'PCS', 'factor' => 1],
            ],
            'status' => true,
        ]);
        ItemMaster::create([
            'branch_id' => $sourceBranch->id,
            'kode_barang' => 'COPY-002',
            'barcode' => '899COPY002',
            'nama_barang' => 'Barang Copy Update',
            'principal_id' => $principal->id,
            'satuan' => 'PCS',
            'status' => true,
        ]);
        ItemMaster::create([
            'branch_id' => $sourceBranch->id,
            'kode_barang' => 'SKIP-OTHER',
            'nama_barang' => 'Principal Lain',
            'principal_id' => $otherPrincipal->id,
            'satuan' => 'PCS',
            'status' => true,
        ]);
        ItemMaster::create([
            'branch_id' => $targetBranch->id,
            'kode_barang' => 'COPY-002',
            'nama_barang' => 'Nama Lama',
            'principal_id' => $principal->id,
            'satuan' => 'PCS',
            'status' => false,
        ]);

        $stats = app(ItemMasterTransferService::class)->copyPrincipal(
            $sourceBranch->id,
            $targetBranch->id,
            $principal->id
        );

        $this->assertSame(['total' => 2, 'created' => 1, 'updated' => 1], $stats);
        $this->assertDatabaseHas('item_masters', [
            'branch_id' => $targetBranch->id,
            'kode_barang' => 'COPY-001',
            'barcode' => '899COPY001',
            'nama_barang' => 'Barang Copy Baru',
        ]);
        $this->assertDatabaseHas('item_masters', [
            'branch_id' => $targetBranch->id,
            'kode_barang' => 'COPY-002',
            'nama_barang' => 'Barang Copy Update',
            'status' => true,
        ]);
        $this->assertDatabaseMissing('item_masters', [
            'branch_id' => $targetBranch->id,
            'kode_barang' => 'SKIP-OTHER',
        ]);
        $this->assertDatabaseHas('item_masters', [
            'branch_id' => $sourceBranch->id,
            'kode_barang' => 'COPY-001',
        ]);
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
    public function it_can_search_session_items_by_name_when_barcode_is_missing()
    {
        $branch = Branch::where('kode', 'PUSAT')->firstOrFail();
        $principal = Principal::create([
            'kode' => 'PSEARCH',
            'nama' => 'Principal Search',
            'status' => true,
        ]);

        $itemMaster = ItemMaster::create([
            'branch_id' => $branch->id,
            'kode_barang' => 'MILK-001',
            'barcode' => null,
            'nama_barang' => 'Susu Coklat Botol 250ML',
            'principal_id' => $principal->id,
            'satuan' => 'PCS',
            'status' => true,
        ]);

        $session = StockSession::create([
            'principal_id' => $principal->id,
            'branch_id' => $branch->id,
            'session_date' => today(),
            'status' => StockSessionStatus::Open,
            'total_items' => 1,
        ]);

        StockSessionItem::create([
            'stock_session_id' => $session->id,
            'item_master_id' => $itemMaster->id,
            'kode_barang' => 'MILK-001',
            'nama_barang' => 'Susu Coklat Botol 250ML',
            'satuan' => 'PCS',
            'qty_sistem_display' => '5 PCS',
            'qty_sistem_base' => 5,
            'status' => StockSessionItemStatus::Pending,
        ]);

        $items = app(StockScanningService::class)->findItemsByBarcode($session, 'coklat botol');

        $this->assertCount(1, $items);
        $this->assertEquals('MILK-001', $items->first()->kode_barang);
    }

    /** @test */
    public function it_ranks_the_closest_item_name_when_search_text_has_typos()
    {
        $branch = Branch::where('kode', 'PUSAT')->firstOrFail();
        $principal = Principal::create([
            'kode' => 'PFUZZY',
            'nama' => 'Principal Fuzzy Search',
            'status' => true,
        ]);
        $session = StockSession::create([
            'principal_id' => $principal->id,
            'branch_id' => $branch->id,
            'session_date' => today(),
            'status' => StockSessionStatus::Open,
            'total_items' => 3,
        ]);

        foreach ([
            ['MILK-001', 'Susu Coklat Botol 250ML'],
            ['MILK-002', 'Susu Vanilla Kotak 250ML'],
            ['SOAP-001', 'Sabun Mandi Batang'],
        ] as [$code, $name]) {
            StockSessionItem::create([
                'stock_session_id' => $session->id,
                'kode_barang' => $code,
                'nama_barang' => $name,
                'satuan' => 'PCS',
                'qty_sistem_display' => '5 PCS',
                'qty_sistem_base' => 5,
                'status' => StockSessionItemStatus::Pending,
            ]);
        }

        $items = app(StockScanningService::class)->findItemsByBarcode($session, 'cklat botl');

        $this->assertCount(1, $items);
        $this->assertEquals('MILK-001', $items->first()->kode_barang);
    }

    /** @test */
    public function it_does_not_suggest_similar_items_for_an_unknown_scanned_barcode()
    {
        $branch = Branch::where('kode', 'PUSAT')->firstOrFail();
        $principal = Principal::create([
            'kode' => 'PEXACTSCAN',
            'nama' => 'Principal Exact Scan',
            'status' => true,
        ]);
        $itemMaster = ItemMaster::create([
            'branch_id' => $branch->id,
            'kode_barang' => 'SCAN-ITEM-001',
            'barcode' => '8991234567890',
            'nama_barang' => 'Barang Dengan Barcode',
            'principal_id' => $principal->id,
            'satuan' => 'PCS',
            'status' => true,
        ]);
        $session = StockSession::create([
            'principal_id' => $principal->id,
            'branch_id' => $branch->id,
            'session_date' => today(),
            'status' => StockSessionStatus::Open,
            'total_items' => 1,
        ]);
        StockSessionItem::create([
            'stock_session_id' => $session->id,
            'item_master_id' => $itemMaster->id,
            'kode_barang' => 'SCAN-ITEM-001',
            'nama_barang' => 'Barang Dengan Barcode',
            'satuan' => 'PCS',
            'qty_sistem_display' => '5 PCS',
            'qty_sistem_base' => 5,
            'status' => StockSessionItemStatus::Pending,
        ]);
        $service = app(StockScanningService::class);

        $this->assertTrue($service->findItemsByBarcode($session, '8991234567899')->isEmpty());
        $this->assertTrue($service->findItemsByBarcode($session, 'SCAN-ITEM-002', true)->isEmpty());
        $this->assertEquals(
            $itemMaster->id,
            app(StockFoundItemService::class)->findItemMaster($session, '8991234567890')?->id
        );
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
        $otherPrincipal = Principal::create(['kode' => 'P021', 'nama' => 'Principal Cabang Lain', 'status' => true]);
        ItemMaster::create([
            'branch_id' => $otherBranch->id, 'kode_barang' => 'OTHER-PRINCIPAL-ITEM',
            'nama_barang' => 'Barang Principal Lain', 'principal_id' => $otherPrincipal->id, 'status' => true,
        ]);
        $branchAdmin = User::factory()->create(['role' => UserRole::Admin, 'branch_id' => $branch->id]);
        $centralAdmin = User::factory()->create(['role' => UserRole::Admin, 'branch_id' => null]);

        $this->actingAs($branchAdmin);
        $this->assertEquals(['BRANCH-ITEM'], ItemMasterResource::getEloquentQuery()->pluck('kode_barang')->all());
        $this->assertTrue(ItemMasterResource::canEdit($branchItem));
        $this->assertFalse(ItemMasterResource::canEdit($otherItem));
        $this->assertTrue(PrincipalResource::canEdit($principal));
        $this->assertFalse(PrincipalResource::canEdit($otherPrincipal));
        $this->assertEquals(['P020'], PrincipalResource::getEloquentQuery()->pluck('kode')->all());

        $this->actingAs($centralAdmin);
        $this->assertEqualsCanonicalizing(
            ['BRANCH-ITEM', 'OTHER-ITEM', 'OTHER-PRINCIPAL-ITEM'],
            ItemMasterResource::getEloquentQuery()->pluck('kode_barang')->all()
        );
        $this->assertTrue(ItemMasterResource::canEdit($branchItem));
        $this->assertTrue(ItemMasterResource::canEdit($otherItem));
        $this->assertTrue(PrincipalResource::canEdit($principal));
        $this->assertTrue(PrincipalResource::canEdit($otherPrincipal));
        $this->assertEqualsCanonicalizing(['P020', 'P021'], PrincipalResource::getEloquentQuery()->pluck('kode')->all());
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

        $forcedBranch = Branch::create([
            'kode' => 'CBG09',
            'nama' => 'Cabang 09',
            'status' => true,
        ]);
        $preview = app(ItemMasterBackupService::class)->previewCsv($file, $forcedBranch->id);
        $forcedStats = app(ItemMasterBackupService::class)->restoreCsv($file, $forcedBranch->id);

        $this->assertEquals('CBG09', $preview['examples'][0]['branch']);
        $this->assertEquals(['created' => 1, 'updated' => 0, 'skipped' => 0], $forcedStats);
        $this->assertDatabaseHas('item_masters', [
            'branch_id' => $forcedBranch->id,
            'kode_barang' => 'ITEM-CBG',
        ]);

        $branchBackup = app(ItemMasterBackupService::class)->buildCsv($forcedBranch->id);
        $this->assertStringContainsString('CBG09', $branchBackup);
        $this->assertStringNotContainsString('CBG03', $branchBackup);
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
    public function it_compares_discrepancies_between_two_dates()
    {
        $branch = Branch::where('kode', 'PUSAT')->firstOrFail();
        $principal = Principal::create([
            'kode' => 'PCMP',
            'nama' => 'Principal Compare',
            'status' => true,
        ]);

        foreach (['2026-07-04' => -2, '2026-07-05' => -3] as $date => $selisih) {
            $session = StockSession::create([
                'principal_id' => $principal->id,
                'branch_id' => $branch->id,
                'session_date' => $date,
                'status' => StockSessionStatus::Completed,
                'total_items' => 1,
                'checked_items' => 1,
                'mismatched_items' => 1,
            ]);

            StockSessionItem::create([
                'stock_session_id' => $session->id,
                'kode_barang' => 'ITEM-COMPARE',
                'nama_barang' => 'Barang Compare',
                'satuan' => 'PCS',
                'qty_sistem_display' => '10 PCS',
                'qty_sistem_base' => 10,
                'qty_aktual_display' => (10 + $selisih) . ' PCS',
                'qty_aktual_base' => 10 + $selisih,
                'selisih' => $selisih,
                'status' => StockSessionItemStatus::Mismatched,
            ]);
        }

        $rows = app(ReportService::class)->getSelisihComparison('2026-07-04', '2026-07-05');
        $row = $rows->first();

        $this->assertCount(1, $rows);
        $this->assertEquals(-2, $row['from_selisih']);
        $this->assertEquals(-3, $row['to_selisih']);
        $this->assertEquals(-1, $row['change']);
        $this->assertEquals('Berubah', $row['status']);

        $csv = app(ReportService::class)->buildSelisihComparisonCsv('2026-07-04', '2026-07-05');
        $this->assertStringContainsString('Perubahan', $csv);
        $this->assertStringContainsString('Berubah', $csv);
    }

    /** @test */
    public function it_uses_the_latest_previous_stock_session_date_as_comparison_date()
    {
        $branch = Branch::where('kode', 'PUSAT')->firstOrFail();
        $otherBranch = Branch::create([
            'kode' => 'CBG05',
            'nama' => 'Cabang 05',
            'status' => true,
        ]);
        $principal = Principal::create([
            'kode' => 'PLAST',
            'nama' => 'Principal Last Count',
            'status' => true,
        ]);

        foreach (['2026-07-01', '2026-07-18', '2026-07-25'] as $date) {
            StockSession::create([
                'principal_id' => $principal->id,
                'branch_id' => $branch->id,
                'session_date' => $date,
                'status' => StockSessionStatus::Completed,
                'total_items' => 1,
            ]);
        }

        StockSession::create([
            'principal_id' => $principal->id,
            'branch_id' => $otherBranch->id,
            'session_date' => '2026-07-24',
            'status' => StockSessionStatus::Completed,
            'total_items' => 1,
        ]);

        $date = app(ReportService::class)->findPreviousStockDate('2026-07-25', $principal->id, $branch->id);

        $this->assertEquals('2026-07-18', $date);
    }

    /** @test */
    public function it_can_include_unchanged_items_in_stock_comparison()
    {
        $branch = Branch::where('kode', 'PUSAT')->firstOrFail();
        $principal = Principal::create([
            'kode' => 'PALL',
            'nama' => 'Principal All Comparison',
            'status' => true,
        ]);

        foreach (['2026-07-20', '2026-07-25'] as $date) {
            $session = StockSession::create([
                'principal_id' => $principal->id,
                'branch_id' => $branch->id,
                'session_date' => $date,
                'status' => StockSessionStatus::Completed,
                'total_items' => 1,
            ]);

            StockSessionItem::create([
                'stock_session_id' => $session->id,
                'kode_barang' => 'ITEM-SAME',
                'nama_barang' => 'Barang Sama',
                'satuan' => 'PCS',
                'qty_sistem_display' => '10 PCS',
                'qty_sistem_base' => 10,
                'qty_aktual_display' => '10 PCS',
                'qty_aktual_base' => 10,
                'selisih' => 0,
                'status' => StockSessionItemStatus::Matched,
            ]);
        }

        $defaultRows = app(ReportService::class)->getSelisihComparison('2026-07-20', '2026-07-25', $principal->id, $branch->id);
        $allRows = app(ReportService::class)->getSelisihComparison('2026-07-20', '2026-07-25', $principal->id, $branch->id, true);

        $this->assertCount(0, $defaultRows);
        $this->assertCount(1, $allRows);
        $this->assertEquals('Tidak Berubah', $allRows->first()['status']);
    }

    /** @test */
    public function it_includes_found_items_in_selisih_csv()
    {
        $branch = Branch::where('kode', 'PUSAT')->firstOrFail();
        $principal = Principal::create([
            'kode' => 'FOUND',
            'nama' => 'Principal Found',
            'status' => true,
        ]);
        $officer = User::factory()->create(['role' => UserRole::StockOfficer]);
        $session = StockSession::create([
            'principal_id' => $principal->id,
            'branch_id' => $branch->id,
            'session_date' => '2026-07-25',
            'status' => StockSessionStatus::InProgress,
            'total_items' => 1,
        ]);

        StockFoundItem::create([
            'stock_session_id' => $session->id,
            'kode_barang' => 'FOUND-001',
            'barcode' => '899FOUND',
            'nama_barang' => 'Barang Temuan',
            'satuan' => 'PCS',
            'qty_aktual_display' => '3 PCS',
            'qty_aktual_base' => 3,
            'status' => 'unresolved',
            'note' => 'Dugaan tertukar',
            'found_by' => $officer->id,
            'found_at' => '2026-07-25 10:00:00',
        ]);

        $csv = app(ReportService::class)->buildSelisihCsv('2026-07-25');

        $this->assertStringContainsString('Barang Temuan', $csv);
        $this->assertStringContainsString('"=""FOUND-001"""', $csv);
        $this->assertStringContainsString('3 PCS', $csv);
    }

    /** @test */
    public function it_stores_found_item_physical_quantity_as_free_text_display()
    {
        $branch = Branch::where('kode', 'PUSAT')->firstOrFail();
        $principal = Principal::create([
            'kode' => 'FOUND-TEXT',
            'nama' => 'Principal Found Text',
            'status' => true,
        ]);
        $officer = User::factory()->create(['role' => UserRole::StockOfficer]);
        $session = StockSession::create([
            'principal_id' => $principal->id,
            'branch_id' => $branch->id,
            'session_date' => today(),
            'status' => StockSessionStatus::InProgress,
            'total_items' => 1,
        ]);

        $foundItem = app(StockFoundItemService::class)->recordFoundItem($session, [
            'kode_barang' => 'FOUND-TEXT-001',
            'nama_barang' => 'Barang Temuan Teks',
            'qty_aktual_display' => '1 CTN 1 PCK 1 PCS',
            'note' => 'Ditemukan di rak campur',
        ], $officer);

        $this->assertEquals('1 CTN 1 PCK 1 PCS', $foundItem->qty_aktual_display);
        $this->assertDatabaseHas('stock_found_items', [
            'kode_barang' => 'FOUND-TEXT-001',
            'qty_aktual_display' => '1 CTN 1 PCK 1 PCS',
            'qty_aktual_base' => 1,
        ]);

        $structuredItem = app(StockFoundItemService::class)->recordFoundItem($session, [
            'kode_barang' => 'FOUND-STRUCTURED-001',
            'nama_barang' => 'Barang Temuan Terstruktur',
            'qty_aktual_display' => '1 CTN 1 PCS',
            'qty_aktual_base' => 13,
        ], $officer);

        $this->assertDatabaseHas('stock_found_items', [
            'kode_barang' => 'FOUND-STRUCTURED-001',
            'qty_aktual_display' => '1 CTN 1 PCS',
            'qty_aktual_base' => 13,
        ]);
        $this->assertEquals('+1 CTN 1 PCS', app(ReportService::class)->formatFoundQty($structuredItem));
    }

    /** @test */
    public function it_rejects_duplicate_found_item_codes_in_the_same_session()
    {
        $branch = Branch::where('kode', 'PUSAT')->firstOrFail();
        $principal = Principal::create([
            'kode' => 'FOUND-DUP',
            'nama' => 'Principal Found Duplicate',
            'status' => true,
        ]);
        $officer = User::factory()->create(['role' => UserRole::StockOfficer]);
        $session = StockSession::create([
            'principal_id' => $principal->id,
            'branch_id' => $branch->id,
            'session_date' => today(),
            'status' => StockSessionStatus::InProgress,
            'total_items' => 1,
        ]);
        $service = app(StockFoundItemService::class);
        $data = [
            'kode_barang' => 'FOUND-DUP-001',
            'nama_barang' => 'Barang Temuan Duplikat',
            'qty_aktual_display' => '1 PCS',
        ];

        $service->recordFoundItem($session, $data, $officer);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('sudah tercatat sebagai barang temuan');

        $service->recordFoundItem($session, $data, $officer);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_can_update_and_delete_a_found_item(): void
    {
        $branch = Branch::where('kode', 'PUSAT')->firstOrFail();
        $principal = Principal::create(['kode' => 'FOUND-EDIT', 'nama' => 'Found Edit', 'status' => true]);
        $officer = User::factory()->create(['role' => UserRole::StockOfficer]);
        $session = StockSession::create([
            'principal_id' => $principal->id,
            'branch_id' => $branch->id,
            'session_date' => today(),
            'status' => StockSessionStatus::InProgress,
            'total_items' => 1,
        ]);
        $service = app(StockFoundItemService::class);
        $item = $service->recordFoundItem($session, [
            'kode_barang' => 'FOUND-EDIT-001',
            'nama_barang' => 'Barang Lama',
            'qty_aktual_display' => '1 PCS',
        ], $officer);

        $updated = $service->updateFoundItem($item, [
            'kode_barang' => 'FOUND-EDIT-002',
            'nama_barang' => 'Barang Baru',
            'qty_aktual_display' => '4 PCS',
            'note' => 'Dikoreksi',
        ]);

        $this->assertSame('FOUND-EDIT-002', $updated->kode_barang);
        $this->assertSame('Barang Baru', $updated->nama_barang);
        $this->assertSame(4, $updated->qty_aktual_base);

        $service->deleteFoundItem($updated);

        $this->assertModelMissing($updated);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function stock_officer_can_look_up_items_only_in_their_branch(): void
    {
        $branch = Branch::where('kode', 'PUSAT')->firstOrFail();
        $otherBranch = Branch::create(['kode' => 'LOOKUP-OTHER', 'nama' => 'Cabang Lain', 'status' => true]);
        $principal = Principal::create(['kode' => 'LOOKUP', 'nama' => 'Principal Lookup', 'status' => true]);
        $officer = User::factory()->create([
            'role' => UserRole::StockOfficer,
            'branch_id' => $branch->id,
        ]);

        ItemMaster::create([
            'branch_id' => $branch->id,
            'kode_barang' => 'LOOKUP-001',
            'barcode' => '899LOOKUP',
            'nama_barang' => 'Barang Cabang Petugas',
            'principal_id' => $principal->id,
            'satuan' => 'PCS',
            'status' => true,
        ]);
        ItemMaster::create([
            'branch_id' => $otherBranch->id,
            'kode_barang' => 'LOOKUP-002',
            'barcode' => '899LOOKUP',
            'nama_barang' => 'Barang Cabang Lain',
            'principal_id' => $principal->id,
            'satuan' => 'PCS',
            'status' => true,
        ]);

        $this->actingAs($officer);

        \Livewire\Livewire::test(\App\Filament\Pages\ItemLookup::class)
            ->set('barcode', '899LOOKUP')
            ->call('searchItem')
            ->assertSet('items.0.name', 'Barang Cabang Petugas')
            ->assertCount('items', 1);
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

        $reportService = app(ReportService::class);

        $this->assertEquals('13346 PCS', $reportService->formatBaseQty($item->qty_sistem_base, $item));
        $this->assertEquals('+5 PCS', $reportService->formatSignedBaseQty(5, $item));
        $this->assertEquals('-5 PCS', $reportService->formatSignedBaseQty(-5, $item));
        $this->assertEquals('0 PCS', $reportService->formatSignedBaseQty(0, $item));
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

    /** @test */
    public function branch_scoped_daily_close_only_completes_sessions_from_that_branch()
    {
        $branch = Branch::where('kode', 'PUSAT')->firstOrFail();
        $oldDate = today()->subDays(14)->toDateString();
        $otherBranch = Branch::create([
            'kode' => 'OTHER-CLOSE',
            'nama' => 'Other Close Branch',
            'status' => true,
        ]);
        $principal = Principal::create([
            'kode' => 'CLOSE-TEST',
            'nama' => 'Close Test Principal',
            'status' => true,
        ]);

        $ownSession = StockSession::create([
            'principal_id' => $principal->id,
            'branch_id' => $branch->id,
            'session_date' => $oldDate,
            'status' => StockSessionStatus::InProgress,
        ]);
        $otherSession = StockSession::create([
            'principal_id' => $principal->id,
            'branch_id' => $otherBranch->id,
            'session_date' => $oldDate,
            'status' => StockSessionStatus::InProgress,
        ]);

        $closed = app(StockSessionService::class)->closeSessions($oldDate, $branch->id);

        $this->assertSame(1, $closed);
        $this->assertSame(StockSessionStatus::Completed, $ownSession->fresh()->status);
        $this->assertSame(StockSessionStatus::InProgress, $otherSession->fresh()->status);
    }
}
