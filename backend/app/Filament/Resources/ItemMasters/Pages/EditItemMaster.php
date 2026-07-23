<?php

namespace App\Filament\Resources\ItemMasters\Pages;

use App\Filament\Resources\ItemMasters\ItemMasterResource;
use App\Services\AuditLogService;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

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
                        'branch_id',
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
        $user = Auth::user();

        if ($user?->isAdmin() && ! $user->isCentralAdmin()) {
            $data['branch_id'] = $user->branch_id;
        }

        $before = $record->only([
            'kode_barang',
            'branch_id',
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
