<?php

namespace Database\Seeders;

use App\Models\User;
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
        User::updateOrCreate(
            ['email' => 'admin@distora.com'],
            [
                'name' => 'Admin Gudang',
                'password' => bcrypt('password'),
                'role' => \App\Enums\UserRole::Admin,
            ]
        );

        User::updateOrCreate(
            ['email' => 'officer1@distora.com'],
            [
                'name' => 'Petugas Stock 1',
                'password' => bcrypt('password'),
                'role' => \App\Enums\UserRole::StockOfficer,
            ]
        );

        User::updateOrCreate(
            ['email' => 'officer2@distora.com'],
            [
                'name' => 'Petugas Stock 2',
                'password' => bcrypt('password'),
                'role' => \App\Enums\UserRole::StockOfficer,
            ]
        );
    }
}
