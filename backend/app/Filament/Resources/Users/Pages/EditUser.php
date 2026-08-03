<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\UserResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use App\Enums\UserRole;
use Illuminate\Validation\ValidationException;

class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

    protected static ?string $title = 'Edit Pengguna';

    protected function mutateFormDataBeforeSave(array $data): array
    {
        if (! Auth::user()?->isCentralAdmin()) {
            $data['role'] = UserRole::StockOfficer->value;
            $data['branch_id'] = Auth::user()?->branch_id;
        }
        if (($data['role'] ?? null) === UserRole::StockOfficer->value && blank($data['branch_id'] ?? null)) {
            throw ValidationException::withMessages(['branch_id' => 'Cabang wajib dipilih untuk stock officer.']);
        }
        if (filled($data['password'])) {
            $data['password'] = Hash::make($data['password']);
        } else {
            unset($data['password']);
        }

        return $data;
    }

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
