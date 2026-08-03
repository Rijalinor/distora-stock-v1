<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ItemBarcode extends Model
{
    protected $fillable = [
        'item_master_id',
        'branch_id',
        'barcode',
        'unit_label',
        'qty_base',
        'is_primary',
    ];

    protected function casts(): array
    {
        return ['is_primary' => 'boolean'];
    }

    protected static function booted(): void
    {
        static::saving(function (self $barcode): void {
            $barcode->barcode = trim($barcode->barcode);
            $barcode->unit_label = strtoupper(trim($barcode->unit_label ?: 'PCS'));
            $barcode->qty_base = max(1, (int) $barcode->qty_base);
            $barcode->branch_id ??= $barcode->itemMaster()->value('branch_id');

            if (! $barcode->exists && ! $barcode->itemMaster()->whereHas('barcodes')->exists()) {
                $barcode->is_primary = true;
            }
        });

        static::saved(function (self $barcode): void {
            if ($barcode->is_primary) {
                self::query()
                    ->where('item_master_id', $barcode->item_master_id)
                    ->whereKeyNot($barcode->id)
                    ->update(['is_primary' => false]);
                $barcode->itemMaster()->update(['barcode' => $barcode->barcode]);
            } elseif ($barcode->wasChanged('is_primary')) {
                self::syncPrimaryBarcode($barcode->item_master_id);
            }
        });

        static::deleted(fn (self $barcode) => self::syncPrimaryBarcode($barcode->item_master_id));
    }

    public function itemMaster()
    {
        return $this->belongsTo(ItemMaster::class);
    }

    private static function syncPrimaryBarcode(int $itemMasterId): void
    {
        $primary = self::query()
            ->where('item_master_id', $itemMasterId)
            ->orderByDesc('is_primary')
            ->orderBy('id')
            ->first();

        if ($primary && ! $primary->is_primary) {
            self::query()->whereKey($primary->id)->update(['is_primary' => true]);
        }

        ItemMaster::query()->whereKey($itemMasterId)->update(['barcode' => $primary?->barcode]);
    }
}
