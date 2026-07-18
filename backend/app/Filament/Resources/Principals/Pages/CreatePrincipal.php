<?php

namespace App\Filament\Resources\Principals\Pages;

use App\Filament\Resources\Principals\PrincipalResource;
use App\Services\AuditLogService;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreatePrincipal extends CreateRecord
{
    protected static string $resource = PrincipalResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        $record = parent::handleRecordCreation($data);

        app(AuditLogService::class)->log('created', $record, [], $record->only([
            'kode',
            'nama',
            'group_principal_id',
            'status',
        ]));

        return $record;
    }
}
