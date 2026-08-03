<?php

namespace App\Services;

use App\Enums\DamageCheckStatus;
use App\Models\DamageCheck;
use App\Models\DamageCheckItem;
use App\Models\ItemMaster;
use App\Models\PendingItem;
use App\Models\DamageCheckPendingItem;
use App\Models\User;
use App\Enums\UserRole;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class DamageCheckService
{
    public function create(array $data, User $officer): DamageCheck
    {
        $joinPin = (string) ($data['join_pin'] ?? '');

        if (! preg_match('/^\d{4,6}$/', $joinPin)) {
            throw new RuntimeException('PIN bergabung harus terdiri dari 4 sampai 6 angka.');
        }

        return DB::transaction(function () use ($data, $officer): DamageCheck {
            $branchId = $officer->isCentralAdmin() ? (int) $data['branch_id'] : (int) $officer->branch_id;

            if (! $branchId) {
                throw new RuntimeException('Cabang wajib dipilih.');
            }

            $check = DamageCheck::create([
                'check_date' => $data['check_date'],
                'branch_id' => $branchId,
                'principal_id' => $data['principal_id'] ?: null,
                'officer_id' => $officer->id,
                'location' => trim($data['location']),
                'notes' => trim($data['notes'] ?? '') ?: null,
                'join_pin' => (string) $data['join_pin'],
                'status' => DamageCheckStatus::Open,
            ]);

            $check->update(['reference_number' => 'RUSAK-' . $check->check_date->format('Ymd') . '-' . str_pad((string) $check->id, 5, '0', STR_PAD_LEFT)]);

            $checkerIds = collect($data['officer_ids'] ?? [])
                ->map(fn ($id): int => (int) $id)
                ->unique()
                ->values();

            if ($officer->isStockOfficer()) {
                $checkerIds = $checkerIds->push($officer->id)->unique()->values();
            }

            if ($checkerIds->count() > 5) {
                throw new RuntimeException('Maksimal 5 checker dalam satu pemeriksaan.');
            }

            $validIds = $checkerIds->isEmpty()
                ? collect()
                : User::query()
                    ->whereIn('id', $checkerIds)
                    ->where('branch_id', $branchId)
                    ->where('role', UserRole::StockOfficer)
                    ->pluck('id');

            if ($validIds->count() !== $checkerIds->count()) {
                throw new RuntimeException('Semua checker harus berasal dari cabang yang sama.');
            }

            $check->checkers()->sync($validIds);

            return $check->fresh();
        });
    }

    public function join(DamageCheck $check, User $user, string $pin): DamageCheck
    {
        return DB::transaction(function () use ($check, $user, $pin): DamageCheck {
            $check = DamageCheck::query()->lockForUpdate()->findOrFail($check->id);
            $this->ensureOpen($check);

            if (! $user->isStockOfficer() || (int) $user->branch_id !== (int) $check->branch_id) {
                throw new RuntimeException('Checker harus berasal dari cabang yang sama.');
            }

            if ($check->checkers()->whereKey($user->id)->exists()) {
                return $check;
            }

            if (! $check->join_pin || ! hash_equals((string) $check->join_pin, $pin)) {
                throw new RuntimeException('PIN pemeriksaan salah.');
            }

            if ($check->checkers()->count() >= 5) {
                throw new RuntimeException('Pemeriksaan sudah memiliki maksimal 5 checker.');
            }

            $check->checkers()->attach($user->id);

            return $check->fresh();
        });
    }

    public function setJoinPin(DamageCheck $check, User $user, string $pin): void
    {
        $this->ensureOpen($check);

        if (! $user->isAdmin() && (int) $check->officer_id !== (int) $user->id) {
            throw new RuntimeException('Hanya admin atau pembuat header yang dapat mengatur PIN.');
        }

        if (! preg_match('/^\d{4,6}$/', $pin)) {
            throw new RuntimeException('PIN bergabung harus terdiri dari 4 sampai 6 angka.');
        }

        $check->update(['join_pin' => $pin]);
    }

    public function leave(DamageCheck $check, User $user): void
    {
        $this->ensureOpen($check);
        $check->checkers()->detach($user->id);
    }

    public function findItems(DamageCheck $check, string $barcode): Collection
    {
        $barcode = trim($barcode);

        return ItemMaster::query()
            ->with([
                'principal',
                'barcodes' => fn ($query) => $query->where('barcode', $barcode),
            ])
            ->where('status', true)
            ->where('branch_id', $check->branch_id)
            ->when($check->principal_id, fn ($query) => $query->where('principal_id', $check->principal_id))
            ->where(fn ($query) => $query
                ->where('barcode', $barcode)
                ->orWhere('kode_barang', $barcode)
                ->orWhereHas('barcodes', fn ($query) => $query->where('barcode', $barcode)))
            ->orderBy('nama_barang')
            ->get()
            ->each(function (ItemMaster $item) use ($barcode): void {
                $mapping = $item->barcodes->firstWhere('barcode', $barcode);
                $item->setAttribute('scan_qty_base', $mapping?->qty_base ?? 1);
                $item->setAttribute('scan_unit_label', $mapping?->unit_label ?? 'PCS');
            });
    }

    public function scan(DamageCheck $check, ItemMaster $item, ?User $scanner = null, int $scanQtyBase = 1): DamageCheckItem
    {
        return DB::transaction(function () use ($check, $item, $scanner, $scanQtyBase): DamageCheckItem {
            $check = DamageCheck::query()->lockForUpdate()->findOrFail($check->id);
            $this->ensureOpen($check);

            if ($scanner && ! $scanner->isAdmin() && ! $check->checkers()->whereKey($scanner->id)->exists()) {
                throw new RuntimeException('Anda tidak ditugaskan sebagai checker pada pemeriksaan ini.');
            }

            if ((int) $item->branch_id !== (int) $check->branch_id || ($check->principal_id && (int) $item->principal_id !== (int) $check->principal_id)) {
                throw new RuntimeException('Barang tidak sesuai dengan cabang atau principal header.');
            }

            $scanQtyBase = max(1, $scanQtyBase);

            $row = DamageCheckItem::query()
                ->where('damage_check_id', $check->id)
                ->where('item_master_id', $item->id)
                ->lockForUpdate()
                ->first();

            if ($row) {
                $row->increment('qty_rusak_base', $scanQtyBase);
                $row->update([
                    'qty_rusak_display' => $row->qty_rusak_base . ' PCS',
                    'last_scanned_at' => now(),
                    'last_scanned_by' => $scanner?->id,
                ]);

                return $row->fresh('itemMaster');
            }

            return DamageCheckItem::create([
                'damage_check_id' => $check->id,
                'item_master_id' => $item->id,
                'qty_rusak_base' => $scanQtyBase,
                'qty_rusak_display' => $scanQtyBase . ' PCS',
                'last_scanned_at' => now(),
                'last_scanned_by' => $scanner?->id,
            ])->load('itemMaster');
        });
    }

    public function findPendingItem(DamageCheck $check, string $barcode): ?PendingItem
    {
        return PendingItem::query()
            ->where('branch_id', $check->branch_id)
            ->where('barcode', trim($barcode))
            ->where('status', 'pending')
            ->first();
    }

    public function createAndScanPending(DamageCheck $check, array $data, User $scanner): DamageCheckPendingItem
    {
        return DB::transaction(function () use ($check, $data, $scanner): DamageCheckPendingItem {
            $pending = PendingItem::firstOrCreate(
                ['branch_id' => $check->branch_id, 'barcode' => trim($data['barcode'])],
                [
                    'temporary_code' => 'PENDING-' . now()->format('YmdHis'),
                    'item_name' => trim($data['item_name']),
                    'principal_id' => $data['principal_id'] ?: null,
                    'unit_label' => strtoupper(trim($data['unit_label'] ?: 'PCS')),
                    'qty_per_scan' => max(1, (int) $data['qty_per_scan']),
                    'status' => 'pending',
                    'created_by' => $scanner->id,
                    'notes' => trim($data['notes'] ?? '') ?: null,
                ]
            );

            return $this->scanPending($check, $pending, $scanner);
        });
    }

    public function scanPending(DamageCheck $check, PendingItem $pending, User $scanner): DamageCheckPendingItem
    {
        return DB::transaction(function () use ($check, $pending, $scanner): DamageCheckPendingItem {
            $check = DamageCheck::query()->lockForUpdate()->findOrFail($check->id);
            $this->ensureOpen($check);

            if (! $scanner->isAdmin() && ! $check->checkers()->whereKey($scanner->id)->exists()) {
                throw new RuntimeException('Anda tidak ditugaskan sebagai checker pada pemeriksaan ini.');
            }

            if ((int) $pending->branch_id !== (int) $check->branch_id || $pending->status !== 'pending') {
                throw new RuntimeException('Barang pending tidak tersedia untuk sesi ini.');
            }

            $quantity = max(1, (int) $pending->qty_per_scan);
            $row = DamageCheckPendingItem::query()
                ->where('damage_check_id', $check->id)
                ->where('pending_item_id', $pending->id)
                ->lockForUpdate()
                ->first();

            if ($row) {
                $row->increment('qty_rusak_base', $quantity);
                $row->update([
                    'qty_rusak_display' => $row->qty_rusak_base . ' PCS',
                    'last_scanned_at' => now(),
                    'last_scanned_by' => $scanner->id,
                ]);

                return $row->fresh('pendingItem');
            }

            return DamageCheckPendingItem::create([
                'damage_check_id' => $check->id,
                'pending_item_id' => $pending->id,
                'qty_rusak_base' => $quantity,
                'qty_rusak_display' => $quantity . ' PCS',
                'last_scanned_at' => now(),
                'last_scanned_by' => $scanner->id,
            ])->load('pendingItem');
        });
    }

    public function updatePendingQuantity(DamageCheckPendingItem $item, int $quantity): void
    {
        $this->ensureOpen($item->damageCheck);
        if ($quantity < 1) {
            throw new RuntimeException('Qty minimal 1 PCS. Hapus baris jika salah scan.');
        }
        $item->update(['qty_rusak_base' => $quantity, 'qty_rusak_display' => $quantity . ' PCS']);
    }

    public function deletePendingItem(DamageCheckPendingItem $item): void
    {
        $this->ensureOpen($item->damageCheck);
        $item->delete();
    }

    public function updateQuantity(DamageCheckItem $item, int $quantity): DamageCheckItem
    {
        $this->ensureOpen($item->damageCheck);

        if ($quantity < 1) {
            throw new RuntimeException('Qty minimal 1 PCS. Hapus baris jika salah scan.');
        }

        $item->update([
            'qty_rusak_base' => $quantity,
            'qty_rusak_display' => $quantity . ' PCS',
        ]);

        return $item->fresh();
    }

    public function deleteItem(DamageCheckItem $item): void
    {
        $this->ensureOpen($item->damageCheck);
        $item->delete();
    }

    public function complete(DamageCheck $check, User $user): void
    {
        $this->ensureOpen($check);

        if (! $user->isAdmin() && (int) $check->officer_id !== (int) $user->id) {
            throw new RuntimeException('Hanya admin atau pembuat header yang dapat menyelesaikan pemeriksaan.');
        }

        if (! $check->items()->exists() && ! $check->pendingItems()->exists()) {
            throw new RuntimeException('Belum ada barang rusak yang tercatat.');
        }

        $check->update(['status' => DamageCheckStatus::Completed, 'completed_at' => now()]);
    }

    private function ensureOpen(DamageCheck $check): void
    {
        if ($check->status !== DamageCheckStatus::Open) {
            throw new RuntimeException('Pemeriksaan sudah selesai dan tidak dapat diubah.');
        }
    }
}
