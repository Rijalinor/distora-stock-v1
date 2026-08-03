<?php

namespace App\Services;

use App\Models\ItemMaster;
use App\Models\StockFoundItem;
use App\Models\StockSession;
use App\Models\User;
use RuntimeException;

class StockFoundItemService
{
    public function findItemMaster(StockSession $session, string $code): ?ItemMaster
    {
        $code = trim($code);

        return ItemMaster::query()
            ->where('branch_id', $session->branch_id)
            ->where(fn ($query) => $query
                ->where('kode_barang', $code)
                ->orWhere('barcode', $code)
                ->orWhereHas('barcodes', fn ($query) => $query->where('barcode', $code)))
            ->first();
    }

    public function findExisting(StockSession $session, string $code, ?int $exceptId = null): ?StockFoundItem
    {
        $code = trim($code);

        return $session->foundItems()
            ->when($exceptId, fn ($query) => $query->whereKeyNot($exceptId))
            ->where(fn ($query) => $query
                ->where('kode_barang', $code)
                ->orWhere('barcode', $code))
            ->first();
    }

    public function updateFoundItem(StockFoundItem $foundItem, array $data): StockFoundItem
    {
        $code = trim((string) $data['kode_barang']);

        if ($this->findExisting($foundItem->stockSession, $code, $foundItem->id)) {
            throw new RuntimeException("Barcode/kode {$code} sudah tercatat sebagai barang temuan di sesi ini.");
        }

        $foundItem->update([
            'kode_barang' => $code,
            'barcode' => $code,
            'nama_barang' => trim((string) $data['nama_barang']),
            'qty_aktual_display' => trim((string) $data['qty_aktual_display']),
            'qty_aktual_base' => max(0, (int) ($data['qty_aktual_base'] ?? $this->baseFallbackFromDisplay($data['qty_aktual_display']))),
            'note' => $data['note'] ?? null,
        ]);

        return $foundItem->fresh();
    }

    public function deleteFoundItem(StockFoundItem $foundItem): void
    {
        $foundItem->delete();
    }

    public function recordFoundItem(StockSession $session, array $data, User $officer): StockFoundItem
    {
        $code = trim((string) $data['kode_barang']);

        if ($this->findExisting($session, $code)) {
            throw new RuntimeException("Barcode/kode {$code} sudah tercatat sebagai barang temuan di sesi ini.");
        }

        $itemMaster = $this->findItemMaster($session, $code);

        $satuan = $itemMaster?->satuan ?: ($data['satuan'] ?? 'PCS');
        $qtyDisplay = trim((string) ($data['qty_aktual_display'] ?? ''));
        $qtyBase = array_key_exists('qty_aktual_base', $data) && $data['qty_aktual_base'] !== null
            ? max(0, (int) $data['qty_aktual_base'])
            : $this->baseFallbackFromDisplay($qtyDisplay);

        if ($qtyDisplay === '') {
            $label = collect(explode('-', (string) $satuan))->map(fn ($value) => trim($value))->filter()->last() ?: 'PCS';
            $qtyDisplay = "{$qtyBase} {$label}";
        }

        return StockFoundItem::create([
            'stock_session_id' => $session->id,
            'item_master_id' => $itemMaster?->id,
            'suspected_item_master_id' => filled($data['suspected_item_master_id'] ?? null) ? (int) $data['suspected_item_master_id'] : null,
            'kode_barang' => $itemMaster?->kode_barang ?? $code,
            'barcode' => $itemMaster?->barcode ?: $code,
            'nama_barang' => $itemMaster?->nama_barang ?: trim((string) ($data['nama_barang'] ?? $code)),
            'satuan' => $satuan,
            'qty_aktual_display' => $qtyDisplay,
            'qty_aktual_base' => $qtyBase,
            'status' => filled($data['suspected_item_master_id'] ?? null) ? 'suspected_swap' : 'unresolved',
            'note' => $data['note'] ?? null,
            'found_by' => $officer->id,
            'found_at' => now(),
        ]);
    }

    protected function baseFallbackFromDisplay(string $qtyDisplay): int
    {
        if (preg_match('/-?\d+/', $qtyDisplay, $matches) !== 1) {
            return 0;
        }

        return max(0, (int) $matches[0]);
    }
}
