<?php

namespace App\Services;

use App\Models\DamageCheckItem;
use App\Models\DamageCheck;
use App\Models\DamageCheckPendingItem;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class DamageCheckReportService
{
    public function query(User $user, array $filters): Builder
    {
        return DamageCheckItem::query()
            ->with(['damageCheck.branch', 'damageCheck.principal', 'damageCheck.officer', 'itemMaster.principal', 'lastScanner'])
            ->when($filters['principal_id'] ?? null, fn (Builder $query, $principalId) => $query->whereHas(
                'itemMaster',
                fn (Builder $query) => $query->where('principal_id', $principalId)
            ))
            ->whereHas('damageCheck', function (Builder $query) use ($user, $filters): void {
                $query
                    ->whereDate('check_date', '>=', $filters['date_from'])
                    ->whereDate('check_date', '<=', $filters['date_to'])
                    ->when($filters['check_id'] ?? null, fn (Builder $query, $checkId) => $query->whereKey($checkId))
                    ->when(! $user->isCentralAdmin(), fn (Builder $query) => $query->where('branch_id', $user->branch_id))
                    ->when($filters['branch_id'] ?? null, fn (Builder $query, $branchId) => $query->where('branch_id', $branchId))
                    ->when($filters['status'] ?? null, fn (Builder $query, $status) => $query->where('status', $status));
            });
    }

    public function checksQuery(User $user, array $filters): Builder
    {
        return DamageCheck::query()
            ->with(['branch', 'principal', 'officer', 'checkers'])
            ->withCount('items')
            ->withSum('items', 'qty_rusak_base')
            ->withCount('pendingItems')
            ->withSum('pendingItems', 'qty_rusak_base')
            ->whereDate('check_date', '>=', $filters['date_from'])
            ->whereDate('check_date', '<=', $filters['date_to'])
            ->when(! $user->isCentralAdmin(), fn (Builder $query) => $query->where('branch_id', $user->branch_id))
            ->when($filters['branch_id'] ?? null, fn (Builder $query, $branchId) => $query->where('branch_id', $branchId))
            ->when($filters['principal_id'] ?? null, fn (Builder $query, $principalId) => $query->where(fn (Builder $query) => $query
                ->whereHas('items.itemMaster', fn (Builder $query) => $query->where('principal_id', $principalId))
                ->orWhereHas('pendingItems.pendingItem', fn (Builder $query) => $query->where('principal_id', $principalId))))
            ->when($filters['status'] ?? null, fn (Builder $query, $status) => $query->where('status', $status));
    }

    public function summary(User $user, array $filters): array
    {
        $query = $this->query($user, $filters);
        $pending = $this->pendingQuery($user, $filters);

        return [
            'checks' => $this->checksQuery($user, $filters)->count(),
            'items' => (clone $query)->count() + (clone $pending)->count(),
            'pieces' => (int) (clone $query)->sum('qty_rusak_base') + (int) (clone $pending)->sum('qty_rusak_base'),
        ];
    }

    public function buildCsv(User $user, array $filters): string
    {
        $stream = fopen('php://temp', 'r+');
        fwrite($stream, "\xEF\xBB\xBF");
        fputcsv($stream, ['Referensi', 'Tanggal', 'Cabang', 'Lokasi', 'Principal Header', 'Pembuat Header', 'Checker Terakhir', 'Status', 'Principal Barang', 'Barcode', 'Kode Barang', 'Nama Barang', 'Qty Rusak']);

        $this->query($user, $filters)
            ->orderByDesc('last_scanned_at')
            ->each(function (DamageCheckItem $row) use ($stream): void {
                $check = $row->damageCheck;
                fputcsv($stream, [
                    $check->reference_number,
                    $check->check_date->format('Y-m-d'),
                    $check->branch->nama,
                    $check->location,
                    $check->principal?->nama ?? 'Semua principal',
                    $check->officer->name,
                    $row->lastScanner?->name ?? '-',
                    $check->status->value,
                    $row->itemMaster->principal?->nama ?? '-',
                    $row->itemMaster->barcode ? '="' . str_replace('"', '""', $row->itemMaster->barcode) . '"' : '',
                    $row->itemMaster->kode_barang,
                    $row->itemMaster->nama_barang,
                    $row->qty_rusak_display,
                ]);
            });

        $this->pendingQuery($user, $filters)->orderByDesc('last_scanned_at')
            ->each(function (DamageCheckPendingItem $row) use ($stream): void {
                $check = $row->damageCheck;
                fputcsv($stream, [
                    $check->reference_number, $check->check_date->format('Y-m-d'), $check->branch->nama,
                    $check->location, $check->principal?->nama ?? 'Semua principal', $check->officer->name,
                    $row->lastScanner?->name ?? '-', $check->status->value,
                    $row->pendingItem->principal?->nama ?? 'Belum diketahui',
                    '="' . str_replace('"', '""', $row->pendingItem->barcode) . '"',
                    $row->pendingItem->temporary_code, $row->pendingItem->item_name . ' [PENDING]', $row->qty_rusak_display,
                ]);
            });

        rewind($stream);
        $csv = stream_get_contents($stream);
        fclose($stream);

        return $csv;
    }

    private function pendingQuery(User $user, array $filters): Builder
    {
        return DamageCheckPendingItem::query()
            ->with(['damageCheck.branch', 'damageCheck.principal', 'damageCheck.officer', 'pendingItem.principal', 'lastScanner'])
            ->when($filters['principal_id'] ?? null, fn (Builder $query, $id) => $query->whereHas('pendingItem', fn (Builder $query) => $query->where('principal_id', $id)))
            ->whereHas('damageCheck', function (Builder $query) use ($user, $filters): void {
                $query->whereDate('check_date', '>=', $filters['date_from'])->whereDate('check_date', '<=', $filters['date_to'])
                    ->when($filters['check_id'] ?? null, fn (Builder $query, $id) => $query->whereKey($id))
                    ->when(! $user->isCentralAdmin(), fn (Builder $query) => $query->where('branch_id', $user->branch_id))
                    ->when($filters['branch_id'] ?? null, fn (Builder $query, $id) => $query->where('branch_id', $id))
                    ->when($filters['status'] ?? null, fn (Builder $query, $status) => $query->where('status', $status));
            });
    }
}
