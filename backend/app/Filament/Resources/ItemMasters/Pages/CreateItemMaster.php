<?php

namespace App\Filament\Resources\ItemMasters\Pages;

use App\Filament\Resources\ItemMasters\ItemMasterResource;
use App\Services\AuditLogService;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class CreateItemMaster extends CreateRecord
{
    protected static string $resource = ItemMasterResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $user = Auth::user();

        if ($user?->isAdmin() && ! $user->isCentralAdmin()) {
            $data['branch_id'] = $user->branch_id;
        }

        return $data;
    }

    protected function handleRecordCreation(array $data): Model
    {
        $record = parent::handleRecordCreation($data);

        app(AuditLogService::class)->log('created', $record, [], $record->only([
            'kode_barang',
            'branch_id',
            'barcode',
            'nama_barang',
            'principal_id',
            'satuan',
            'qty_structure',
            'status',
        ]));

        return $record;
    }
}
