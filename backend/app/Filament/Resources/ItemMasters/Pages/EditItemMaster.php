<?php

namespace App\Filament\Resources\ItemMasters\Pages;

use App\Filament\Resources\ItemMasters\ItemMasterResource;
use App\Services\AuditLogService;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditItemMaster extends EditRecord
{
    protected static string $resource = ItemMasterResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->before(fn (Model $record) => app(AuditLogService::class)->log(
                    'deleted',
                    $record,
                    $record->only([
                        'kode_barang',
                        'barcode',
                        'nama_barang',
                        'principal_id',
                        'satuan',
                        'qty_structure',
                        'status',
                    ]),
                    [],
                )),
        ];
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        $before = $record->only([
            'kode_barang',
            'barcode',
            'nama_barang',
            'principal_id',
            'satuan',
            'qty_structure',
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
