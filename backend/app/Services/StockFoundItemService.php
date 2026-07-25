<?php

namespace App\Services;

use App\Models\ItemMaster;
use App\Models\StockFoundItem;
use App\Models\StockSession;
use App\Models\User;

class StockFoundItemService
{
    public function recordFoundItem(StockSession $session, array $data, User $officer): StockFoundItem
    {
        $code = trim((string) $data['kode_barang']);
        $itemMaster = ItemMaster::query()
            ->where('branch_id', $session->branch_id)
            ->where(fn ($query) => $query
                ->where('kode_barang', $code)
                ->orWhere('barcode', $code))
            ->first();

        $qtyBase = max(0, (int) ($data['qty_aktual_base'] ?? 0));
        $satuan = $itemMaster?->satuan ?: ($data['satuan'] ?? 'PCS');
        $label = collect(explode('-', (string) $satuan))->map(fn ($value) => trim($value))->filter()->last() ?: 'PCS';

        return StockFoundItem::create([
            'stock_session_id' => $session->id,
            'item_master_id' => $itemMaster?->id,
            'suspected_item_master_id' => filled($data['suspected_item_master_id'] ?? null) ? (int) $data['suspected_item_master_id'] : null,
            'kode_barang' => $itemMaster?->kode_barang ?? $code,
            'barcode' => $itemMaster?->barcode ?: $code,
            'nama_barang' => $itemMaster?->nama_barang ?: trim((string) ($data['nama_barang'] ?? $code)),
            'satuan' => $satuan,
            'qty_aktual_display' => "{$qtyBase} {$label}",
            'qty_aktual_base' => $qtyBase,
            'status' => filled($data['suspected_item_master_id'] ?? null) ? 'suspected_swap' : 'unresolved',
            'note' => $data['note'] ?? null,
            'found_by' => $officer->id,
            'found_at' => now(),
        ]);
    }
}
