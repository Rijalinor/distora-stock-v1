<?php

namespace App\Services;

use App\Enums\StockSessionItemStatus;
use App\Models\StockSession;
use App\Models\StockSessionItem;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ReportService
{
    public function formatBaseQty(?int $baseQty, StockSessionItem $item): string
    {
        if ($baseQty === null) {
            return '-';
        }

        $factors = $item->itemMaster?->getQtyFactorsArray() ?: \App\Services\StockScanningService::parseConversionFactors($item->nama_barang);
        $labels = $factors === [1]
            ? ['PCS']
            : ($item->itemMaster?->getQtyLabelsArray() ?: $this->labelsFromSatuan($item->satuan));
        $levels = \App\Services\StockScanningService::splitBaseQuantity(abs($baseQty), $factors);
        $labels = $this->normalizeLabels($labels, count($levels));
        $display = \App\Services\StockScanningService::buildQtyDisplayFromLabels($levels, $labels);

        return $baseQty < 0 ? '-' . $display : $display;
    }

    /**
     * Get detailed items list for a specific stock session.
     */
    public function getSessionReport(StockSession $session): Collection
    {
        return $session->items()
            ->with(['itemMaster', 'checkedBy'])
            ->get();
    }

    /**
     * Get items that have discrepancies (selisih != 0) in a specific session.
     */
    public function getSelisihReport(StockSession $session): Collection
    {
        return $session->items()
            ->whereNotNull('qty_aktual_base')
            ->where('selisih', '!=', 0)
            ->with(['itemMaster', 'checkedBy'])
            ->get();
    }

    /**
     * Get all mismatched items across all sessions, optionally filtered by date.
     */
    public function getAllSelisihItems(?string $date = null, ?int $principalId = null, ?int $branchId = null): Collection
    {
        $query = StockSessionItem::query()
            ->whereIn('status', [StockSessionItemStatus::Mismatched, StockSessionItemStatus::Missing])
            ->with(['stockSession.branch', 'stockSession.principal', 'stockSession.assignedOfficer', 'checkedBy']);

        if ($date) {
            $query->whereHas('stockSession', fn ($q) => $q->whereDate('session_date', $date));
        }

        if ($principalId) {
            $query->whereHas('stockSession', fn ($q) => $q->where('principal_id', $principalId));
        }

        if ($branchId) {
            $query->whereHas('stockSession', fn ($q) => $q->where('branch_id', $branchId));
        }

        return $query->orderBy('created_at', 'desc')->get();
    }

    /**
     * Get daily summary statistics for a specific date.
     *
     * @return array{sessions: int, total_items: int, checked_items: int, matched_items: int, mismatched_items: int}
     */
    public function getDailySummary(string $date, ?int $principalId = null, ?int $branchId = null): array
    {
        $sessions = StockSession::whereDate('session_date', $date);

        if ($principalId) {
            $sessions->where('principal_id', $principalId);
        }

        if ($branchId) {
            $sessions->where('branch_id', $branchId);
        }

        $summary = (clone $sessions)
            ->select([
                DB::raw('COUNT(*) as sessions'),
                DB::raw('COALESCE(SUM(total_items), 0) as total_items'),
                DB::raw('COALESCE(SUM(checked_items), 0) as checked_items'),
                DB::raw('COALESCE(SUM(matched_items), 0) as matched_items'),
                DB::raw('COALESCE(SUM(mismatched_items), 0) as mismatched_items'),
            ])
            ->first();

        return [
            'sessions' => (int) ($summary?->sessions ?? 0),
            'total_items' => (int) ($summary?->total_items ?? 0),
            'checked_items' => (int) ($summary?->checked_items ?? 0),
            'matched_items' => (int) ($summary?->matched_items ?? 0),
            'mismatched_items' => (int) ($summary?->mismatched_items ?? 0),
        ];
    }

    /**
     * Get detailed item rows across all stock sessions for a specific date.
     */
    public function getDailyDetailReport(string $date, ?int $principalId = null, ?int $branchId = null): Collection
    {
        return StockSessionItem::query()
            ->with([
                'stockSession.principal',
                'stockSession.branch',
                'stockSession.assignedOfficer',
                'itemMaster',
                'checkedBy',
            ])
            ->whereHas('stockSession', fn ($q) => $q->whereDate('session_date', $date))
            ->when($principalId, fn ($q) => $q->whereHas(
                'stockSession',
                fn ($q) => $q->where('principal_id', $principalId)
            ))
            ->when($branchId, fn ($q) => $q->whereHas(
                'stockSession',
                fn ($q) => $q->where('branch_id', $branchId)
            ))
            ->orderBy('created_at')
            ->get();
    }

    /**
     * Build CSV content for a stock session report.
     */
    public function buildSessionCsv(StockSession $session): string
    {
        $items = $this->getSessionReport($session);
        $session->load(['branch', 'principal', 'assignedOfficer']);

        $lines = [];
        $lines[] = implode(',', [
            'Principal',
            'Cabang',
            'Kode Barang',
            'Nama Barang',
            'Qty Sistem',
            'Qty Aktual',
            'Selisih',
            'Status',
            'Petugas',
            'Tanggal',
        ]);

        foreach ($items as $item) {
            $lines[] = implode(',', array_map(
                fn ($value) => '"' . str_replace('"', '""', (string) $value) . '"',
                [
                    $session->principal->nama,
                    $session->branch?->nama ?? '-',
                    $this->excelText($item->kode_barang),
                    $item->nama_barang,
                    $item->qty_sistem_display,
                    $item->qty_aktual_display ?? '-',
                    $this->formatBaseQty($item->selisih, $item),
                    $item->status->value,
                    $item->checkedBy?->name ?? '-',
                    $session->session_date->format('Y-m-d'),
                ]
            ));
        }

        return implode("\n", $lines);
    }

    /**
     * Build CSV content for all selisih items.
     */
    public function buildSelisihCsv(?string $date = null, ?int $principalId = null, ?int $branchId = null): string
    {
        $items = $this->getAllSelisihItems($date, $principalId, $branchId);

        $lines = [];
        $lines[] = implode(',', [
            'Tanggal',
            'Cabang',
            'Principal',
            'Kode Barang',
            'Nama Barang',
            'Qty Sistem',
            'Qty Aktual',
            'Selisih',
            'Petugas',
            'Waktu Check',
        ]);

        foreach ($items as $item) {
            $session = $item->stockSession;
            $lines[] = implode(',', array_map(
                fn ($value) => '"' . str_replace('"', '""', (string) $value) . '"',
                [
                    $session?->session_date?->format('Y-m-d') ?? '-',
                    $session?->branch?->nama ?? '-',
                    $session?->principal?->nama ?? '-',
                    $this->excelText($item->kode_barang),
                    $item->nama_barang,
                    $item->qty_sistem_display,
                    $item->qty_aktual_display ?? '-',
                    $this->formatBaseQty($item->selisih, $item),
                    $item->checkedBy?->name ?? '-',
                    $item->checked_at?->format('Y-m-d H:i') ?? '-',
                ]
            ));
        }

        return implode("\n", $lines);
    }

    /**
     * Build CSV content for a daily report (item details per principal).
     */
    public function buildDailyCsv(string $date, ?int $principalId = null, ?int $branchId = null): string
    {
        $items = $this->getDailyDetailReport($date, $principalId, $branchId);

        $lines = [];
        $lines[] = implode(',', [
            'Tanggal',
            'Cabang',
            'Principal Kode',
            'Principal',
            'Item Kode',
            'Barcode',
            'Item',
            'Size',
            'Status',
            'Qty Sistem',
            'Qty Aktual',
            'Selisih',
            'Petugas',
            'Jam Mulai',
            'Jam Check',
        ]);

        foreach ($items as $item) {
            $session = $item->stockSession;

            $lines[] = implode(',', array_map(
                fn ($value) => '"' . str_replace('"', '""', (string) $value) . '"',
                [
                    $session?->session_date?->format('Y-m-d') ?? '-',
                    $session?->branch?->nama ?? '-',
                    $session?->principal?->kode ?? '-',
                    $session?->principal?->nama ?? '-',
                    $this->excelText($item->kode_barang),
                    $this->excelText($item->itemMaster?->barcode ?? '-'),
                    $item->nama_barang,
                    $item->satuan ?? '-',
                    $item->status->value,
                    $this->formatBaseQty($item->qty_sistem_base ?? 0, $item),
                    $this->formatBaseQty($item->qty_aktual_base, $item),
                    $this->formatBaseQty($item->selisih, $item),
                    $item->checkedBy?->name ?? '-',
                    $session?->started_at?->format('H:i') ?? '-',
                    $item->checked_at?->format('H:i') ?? '-',
                ]
            ));
        }

        return implode("\n", $lines);
    }

    protected function excelText(?string $value): string
    {
        if (blank($value) || $value === '-') {
            return '-';
        }

        return '="' . str_replace('"', '""', (string) $value) . '"';
    }

    protected function labelsFromSatuan(?string $satuan): array
    {
        return $satuan
            ? array_values(array_filter(array_map('trim', explode('-', $satuan))))
            : [];
    }

    protected function normalizeLabels(array $labels, int $count): array
    {
        $labels = array_slice($labels ?: ['PCS'], 0, $count);

        while (count($labels) < $count) {
            $labels[] = count($labels) === $count - 1 ? 'PCS' : 'LEVEL ' . (count($labels) + 1);
        }

        return $labels;
    }
}
