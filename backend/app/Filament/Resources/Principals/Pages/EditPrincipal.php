<?php

namespace App\Filament\Resources\Principals\Pages;

use App\Filament\Resources\Principals\PrincipalResource;
use App\Services\AuditLogService;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditPrincipal extends EditRecord
{
    protected static string $resource = PrincipalResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->before(fn (Model $record) => app(AuditLogService::class)->log(
                    'deleted',
                    $record,
                    $record->only([
                        'kode',
                        'nama',
                        'group_principal_id',
                        'status',
                    ]),
                    [],
                )),
        ];
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        $before = $record->only([
            'kode',
            'nama',
            'group_principal_id',
            'status',
        ]);

        $record = parent::handleRecordUpdate($record, $data);

        $after = $record->only(array_keys($before));

        if ($before !== $after) {
            app(AuditLogService::class)->log('updated', $record, $before, $after);
        }

        return $record;
    }
}
