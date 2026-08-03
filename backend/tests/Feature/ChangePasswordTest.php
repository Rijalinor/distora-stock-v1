<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Filament\Pages\ChangePassword;
use App\Filament\Resources\Users\Pages\EditUser;
use App\Models\User;
use App\Models\Branch;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

class ChangePasswordTest extends TestCase
{
    use RefreshDatabase;

    public function test_stock_officer_can_access_the_filament_panel(): void
    {
        $officer = User::factory()->create([
            'role' => UserRole::StockOfficer,
            'branch_id' => Branch::where('kode', 'PUSAT')->value('id'),
        ]);

        $this->assertTrue($officer->canAccessPanel(Filament::getPanel('admin')));
        $branchlessOfficer = User::factory()->create(['role' => UserRole::StockOfficer, 'branch_id' => null]);
        $this->assertFalse($branchlessOfficer->canAccessPanel(Filament::getPanel('admin')));
    }

    public function test_user_can_change_their_own_password(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('old-password'),
            'role' => UserRole::StockOfficer,
            'branch_id' => Branch::where('kode', 'PUSAT')->value('id'),
        ]);

        $this->actingAs($user);

        Livewire::test(ChangePassword::class)
            ->fillForm([
                'current_password' => 'old-password',
                'password' => 'new-password',
                'password_confirmation' => 'new-password',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertTrue(Hash::check('new-password', $user->fresh()->password));
    }

    public function test_current_password_is_required_to_change_own_password(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('old-password'),
            'role' => UserRole::StockOfficer,
            'branch_id' => Branch::where('kode', 'PUSAT')->value('id'),
        ]);

        $this->actingAs($user);

        Livewire::test(ChangePassword::class)
            ->fillForm([
                'current_password' => 'wrong-password',
                'password' => 'new-password',
                'password_confirmation' => 'new-password',
            ])
            ->call('save')
            ->assertHasFormErrors(['current_password']);

        $this->assertTrue(Hash::check('old-password', $user->fresh()->password));
    }

    public function test_admin_can_reset_user_password_from_user_resource_form(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin, 'branch_id' => null]);
        $user = User::factory()->create([
            'password' => Hash::make('old-password'),
            'role' => UserRole::StockOfficer,
            'branch_id' => Branch::where('kode', 'PUSAT')->value('id'),
        ]);

        $this->actingAs($admin);

        Livewire::test(EditUser::class, ['record' => $user->getRouteKey()])
            ->fillForm([
                'name' => $user->name,
                'email' => $user->email,
                'password' => 'reset-password',
                'role' => UserRole::StockOfficer->value,
                'branch_id' => $user->branch_id,
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertTrue(Hash::check('reset-password', $user->fresh()->password));
    }
}
