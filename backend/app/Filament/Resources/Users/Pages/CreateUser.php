<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\UserResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use App\Enums\UserRole;
use Illuminate\Validation\ValidationException;

class CreateUser extends CreateRecord
{
    protected static string $resource = UserResource::class;

    protected static ?string $title = 'Tambah Pengguna';

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        if (! Auth::user()?->isCentralAdmin()) {
            $data['role'] = UserRole::StockOfficer->value;
            $data['branch_id'] = Auth::user()?->branch_id;
        }
        if (($data['role'] ?? null) === UserRole::StockOfficer->value && blank($data['branch_id'] ?? null)) {
            throw ValidationException::withMessages(['branch_id' => 'Cabang wajib dipilih untuk stock officer.']);
        }
        $data['password'] = Hash::make($data['password']);
        $data['email_verified_at'] = now();

        return $data;
    }
}
