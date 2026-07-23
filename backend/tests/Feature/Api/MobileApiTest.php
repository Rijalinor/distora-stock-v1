<?php

namespace Tests\Feature\Api;

use App\Enums\StockSessionItemStatus;
use App\Enums\StockSessionStatus;
use App\Enums\UserRole;
use App\Models\Branch;
use App\Models\ItemMaster;
use App\Models\Principal;
use App\Models\StockSession;
use App\Models\StockSessionItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class MobileApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_mobile_login_sessions_and_scan_flow(): void
    {
        $branch = Branch::where('kode', 'PUSAT')->firstOrFail();

        $otherBranch = Branch::create([
            'kode' => 'CBG2',
            'nama' => 'Cabang 2',
            'status' => true,
        ]);

        $user = User::factory()->create([
            'name' => 'Officer',
            'email' => 'officer1@distora.com',
            'password' => Hash::make('password'),
            'role' => UserRole::StockOfficer,
            'branch_id' => $branch->id,
        ]);

        $secondOfficer = User::factory()->create([
            'name' => 'Officer 2',
            'email' => 'officer2@distora.com',
            'password' => Hash::make('password'),
            'role' => UserRole::StockOfficer,
            'branch_id' => $branch->id,
        ]);

        $otherBranchOfficer = User::factory()->create([
            'name' => 'Officer 3',
            'email' => 'officer3@distora.com',
            'password' => Hash::make('password'),
            'role' => UserRole::StockOfficer,
            'branch_id' => $otherBranch->id,
        ]);

        $principal = Principal::create([
            'kode' => '101',
            'nama' => 'PT. JOHNSON',
            'status' => 'active',
        ]);

        $itemMaster = ItemMaster::create([
            'branch_id' => $branch->id,
            'kode_barang' => '696743',
            'barcode' => '888724',
            'nama_barang' => 'BAYGON COIL (1X36)',
            'principal_id' => $principal->id,
            'satuan' => 'CTN-PCS',
            'status' => 'active',
        ]);

        $session = StockSession::create([
            'principal_id' => $principal->id,
            'branch_id' => $branch->id,
            'session_date' => today(),
            'assigned_to' => $user->id,
            'status' => StockSessionStatus::Open,
            'total_items' => 1,
            'checked_items' => 0,
            'matched_items' => 0,
            'mismatched_items' => 0,
        ]);

        StockSessionItem::create([
            'stock_session_id' => $session->id,
            'item_master_id' => $itemMaster->id,
            'kode_barang' => $itemMaster->kode_barang,
            'nama_barang' => $itemMaster->nama_barang,
            'satuan' => $itemMaster->satuan,
            'qty_sistem_display' => '1 CTN',
            'qty_sistem_base' => 36,
            'status' => StockSessionItemStatus::Pending,
        ]);

        $login = $this->postJson('/api/mobile/login', [
            'email' => 'officer1@distora.com',
            'password' => 'password',
        ]);

        $login->assertOk()
            ->assertJsonPath('user.email', 'officer1@distora.com')
            ->assertJsonStructure(['token_type', 'token', 'user']);

        $token = $login->json('token');

        $this->getJson('/api/mobile/me', [
            'Authorization' => "Bearer {$token}",
        ])->assertOk()->assertJsonPath('email', 'officer1@distora.com');

        $sessions = $this->getJson('/api/mobile/sessions', [
            'Authorization' => "Bearer {$token}",
        ]);

        $sessions->assertOk()
            ->assertJsonPath('data.0.id', $session->id)
            ->assertJsonPath('data.0.principal.nama', 'PT. JOHNSON');

        $scan = $this->postJson('/api/mobile/sessions/' . $session->id . '/scan', [
            'barcode' => '888724',
            'qty_levels' => [1, 0],
        ], [
            'Authorization' => "Bearer {$token}",
        ]);

        $scan->assertOk()
            ->assertJsonPath('data.item.status', StockSessionItemStatus::Matched->value)
            ->assertJsonPath('data.item.qty_aktual_base', 36);

        $this->assertDatabaseHas('stock_session_items', [
            'stock_session_id' => $session->id,
            'kode_barang' => '696743',
            'qty_aktual_base' => 36,
            'status' => StockSessionItemStatus::Matched->value,
        ]);

        $secondLogin = $this->postJson('/api/mobile/login', [
            'email' => 'officer2@distora.com',
            'password' => 'password',
        ]);

        $secondToken = $secondLogin->json('token');

        $this->getJson('/api/mobile/sessions', [
            'Authorization' => "Bearer {$secondToken}",
        ])->assertOk()
            ->assertJsonPath('data.0.id', $session->id);

        $this->postJson('/api/mobile/sessions/' . $session->id . '/scan', [
            'barcode' => '888724',
            'qty_levels' => [1, 0],
        ], [
            'Authorization' => "Bearer {$secondToken}",
        ])->assertOk();

        $otherLogin = $this->postJson('/api/mobile/login', [
            'email' => 'officer3@distora.com',
            'password' => 'password',
        ]);

        $otherToken = $otherLogin->json('token');

        $this->getJson('/api/mobile/sessions', [
            'Authorization' => "Bearer {$otherToken}",
        ])->assertOk()
            ->assertJsonCount(0, 'data');

        $this->postJson('/api/mobile/sessions/' . $session->id . '/scan', [
            'barcode' => '888724',
            'qty_levels' => [1, 0],
        ], [
            'Authorization' => "Bearer {$otherToken}",
        ])->assertForbidden();
    }
}
