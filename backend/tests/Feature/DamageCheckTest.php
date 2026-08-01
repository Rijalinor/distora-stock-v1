<?php

namespace Tests\Feature;

use App\Enums\DamageCheckStatus;
use App\Enums\UserRole;
use App\Models\Branch;
use App\Models\ItemMaster;
use App\Models\Principal;
use App\Models\User;
use App\Services\DamageCheckService;
use App\Services\DamageCheckReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\TestCase;

class DamageCheckTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function scanning_the_same_barcode_accumulates_damaged_pieces(): void
    {
        [$officer, $item] = $this->makeOfficerAndItem();
        $service = app(DamageCheckService::class);
        $check = $service->create([
            'check_date' => '2026-08-01',
            'branch_id' => $officer->branch_id,
            'principal_id' => $item->principal_id,
            'location' => 'Area Barang Rusak',
            'notes' => '',
            'join_pin' => '1234',
        ], $officer);

        $this->assertStringStartsWith('RUSAK-20260801-', $check->reference_number);
        $this->assertSame('1234', $check->join_pin);
        $this->assertNotSame('1234', \Illuminate\Support\Facades\DB::table('damage_checks')->where('id', $check->id)->value('join_pin'));
        $this->assertCount(1, $service->findItems($check, '899DAMAGE'));

        $service->scan($check, $item);
        $row = $service->scan($check, $item);

        $this->assertSame(2, $row->qty_rusak_base);
        $this->assertSame('2 PCS', $row->qty_rusak_display);
        $this->assertDatabaseCount('damage_check_items', 1);
    }

    #[Test]
    public function completed_check_is_locked_from_more_scans(): void
    {
        [$officer, $item] = $this->makeOfficerAndItem();
        $service = app(DamageCheckService::class);
        $check = $service->create([
            'check_date' => today()->toDateString(),
            'branch_id' => $officer->branch_id,
            'principal_id' => null,
            'location' => 'Gudang Retur',
            'notes' => null,
            'join_pin' => '1234',
        ], $officer);

        $service->scan($check, $item);
        $service->complete($check, $officer);

        $this->assertSame(DamageCheckStatus::Completed, $check->fresh()->status);
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('sudah selesai');

        $service->scan($check->fresh(), $item);
    }

    #[Test]
    public function item_search_is_limited_to_the_header_branch_and_principal(): void
    {
        [$officer, $item] = $this->makeOfficerAndItem();
        $otherPrincipal = Principal::create(['kode' => 'OTHER-DAMAGE', 'nama' => 'Other', 'status' => true]);
        ItemMaster::create([
            'branch_id' => $officer->branch_id,
            'principal_id' => $otherPrincipal->id,
            'kode_barang' => 'DAMAGE-OTHER',
            'barcode' => '899DAMAGE',
            'nama_barang' => 'Barang Principal Lain',
            'satuan' => 'PCS',
            'status' => true,
        ]);

        $service = app(DamageCheckService::class);
        $check = $service->create([
            'check_date' => today()->toDateString(),
            'branch_id' => $officer->branch_id,
            'principal_id' => $item->principal_id,
            'location' => 'Gudang',
            'notes' => null,
            'join_pin' => '1234',
        ], $officer);

        $results = $service->findItems($check, '899DAMAGE');

        $this->assertCount(1, $results);
        $this->assertSame($item->id, $results->first()->id);
    }

    #[Test]
    public function damage_report_summarizes_and_exports_checker_data(): void
    {
        [$officer, $item] = $this->makeOfficerAndItem();
        $checkService = app(DamageCheckService::class);
        $check = $checkService->create([
            'check_date' => '2026-08-01',
            'branch_id' => $officer->branch_id,
            'principal_id' => null,
            'location' => 'Gudang Rusak',
            'notes' => null,
            'join_pin' => '1234',
        ], $officer);
        $checkService->scan($check, $item);
        $checkService->scan($check, $item);

        $filters = [
            'date_from' => '2026-08-01',
            'date_to' => '2026-08-01',
            'branch_id' => $officer->branch_id,
            'principal_id' => $item->principal_id,
            'status' => 'open',
        ];
        $report = app(DamageCheckReportService::class);

        $this->assertSame(['checks' => 1, 'items' => 1, 'pieces' => 2], $report->summary($officer, $filters));
        $checkRows = $report->checksQuery($officer, $filters)->get();
        $this->assertCount(1, $checkRows);
        $this->assertSame($check->reference_number, $checkRows->first()->reference_number);

        $csv = $report->buildCsv($officer, $filters);
        $this->assertStringContainsString($check->reference_number, $csv);
        $this->assertStringContainsString('Barang Rusak Test', $csv);
        $this->assertStringContainsString('2 PCS', $csv);
        $headers = str_getcsv(strtok(ltrim($csv, "\xEF\xBB\xBF"), "\n"));
        $this->assertSame(['Principal Barang', 'Barcode', 'Kode Barang', 'Nama Barang', 'Qty Rusak'], array_slice($headers, -5));
        $this->assertStringContainsString('"=""899DAMAGE"""', $csv);

        $singleCheckCsv = $report->buildCsv($officer, [...$filters, 'check_id' => $check->id]);
        $this->assertStringContainsString($check->reference_number, $singleCheckCsv);
    }

    #[Test]
    public function multiple_checkers_can_scan_into_the_same_check(): void
    {
        [$owner, $item] = $this->makeOfficerAndItem();
        $secondChecker = User::factory()->create([
            'role' => UserRole::StockOfficer,
            'branch_id' => $owner->branch_id,
        ]);
        $service = app(DamageCheckService::class);
        $check = $service->create([
            'check_date' => today()->toDateString(),
            'branch_id' => $owner->branch_id,
            'principal_id' => null,
            'location' => 'Gudang Bersama',
            'notes' => null,
            'join_pin' => '5678',
        ], $owner);

        $this->assertSame([$owner->id], $check->checkers()->pluck('users.id')->all());

        $this->actingAs($secondChecker);
        \Livewire\Livewire::test(\App\Filament\Pages\DamageChecker::class)
            ->call('selectCheck', $check->id)
            ->assertSet('pendingJoinCheckId', $check->id)
            ->set('joinPin', '5678')
            ->call('submitJoin')
            ->assertSet('selectedCheckId', $check->id);

        $this->assertEqualsCanonicalizing(
            [$owner->id, $secondChecker->id],
            $check->checkers()->pluck('users.id')->all()
        );

        $row = $service->scan($check, $item, $secondChecker);
        $this->assertSame($secondChecker->id, $row->last_scanned_by);

        $service->leave($check, $secondChecker);
        $this->assertFalse($check->checkers()->whereKey($secondChecker->id)->exists());
        $this->assertDatabaseHas('damage_check_items', [
            'damage_check_id' => $check->id,
            'item_master_id' => $item->id,
            'qty_rusak_base' => 1,
        ]);

        $service->join($check, $secondChecker, '5678');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Hanya admin atau pembuat header');
        $service->complete($check, $secondChecker);
    }

    private function makeOfficerAndItem(): array
    {
        $branch = Branch::where('kode', 'PUSAT')->firstOrFail();
        $principal = Principal::create(['kode' => 'DAMAGE', 'nama' => 'Principal Damage', 'status' => true]);
        $officer = User::factory()->create(['role' => UserRole::StockOfficer, 'branch_id' => $branch->id]);
        $item = ItemMaster::create([
            'branch_id' => $branch->id,
            'principal_id' => $principal->id,
            'kode_barang' => 'DAMAGE-001',
            'barcode' => '899DAMAGE',
            'nama_barang' => 'Barang Rusak Test',
            'satuan' => 'PCS',
            'status' => true,
        ]);

        return [$officer, $item];
    }
}
