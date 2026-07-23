<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Branch;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $branch = Branch::updateOrCreate(
            ['kode' => 'PUSAT'],
            [
                'nama' => 'Pusat',
                'status' => true,
            ]
        );

        User::updateOrCreate(
            ['email' => 'admin@distora.com'],
            [
                'name' => 'Admin Gudang',
                'password' => bcrypt('password'),
                'role' => \App\Enums\UserRole::Admin,
                'branch_id' => $branch->id,
            ]
        );

        User::updateOrCreate(
            ['email' => 'officer1@distora.com'],
            [
                'name' => 'Petugas Stock 1',
                'password' => bcrypt('password'),
                'role' => \App\Enums\UserRole::StockOfficer,
                'branch_id' => $branch->id,
            ]
        );

        User::updateOrCreate(
            ['email' => 'officer2@distora.com'],
            [
                'name' => 'Petugas Stock 2',
                'password' => bcrypt('password'),
                'role' => \App\Enums\UserRole::StockOfficer,
                'branch_id' => $branch->id,
            ]
        );
    }
}
