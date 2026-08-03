<?php

namespace App\Filament\Resources\PendingItems\Pages;

use App\Filament\Resources\PendingItems\PendingItemResource;
use App\Models\ItemBarcode;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditPendingItem extends EditRecord
{
    protected static string $resource = PendingItemResource::class;

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        $record = parent::handleRecordUpdate($record, $data);
        if ($record->matched_item_master_id) {
            ItemBarcode::firstOrCreate(
                ['branch_id' => $record->branch_id, 'barcode' => $record->barcode],
                ['item_master_id' => $record->matched_item_master_id, 'unit_label' => $record->unit_label, 'qty_base' => $record->qty_per_scan, 'is_primary' => false]
            );
            $record->update(['status' => 'matched']);
        }
        return $record;
    }
}
