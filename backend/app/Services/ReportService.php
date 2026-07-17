<?php

namespace App\Services;

use App\Enums\StockSessionItemStatus;
use App\Models\StockSession;
use App\Models\StockSessionItem;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ReportService
{
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
    public function getAllSelisihItems(?string $date = null): Collection
    {
        $query = StockSessionItem::query()
            ->where('status', StockSessionItemStatus::Mismatched)
            ->with(['stockSession.principal', 'stockSession.assignedOfficer', 'checkedBy']);

        if ($date) {
            $query->whereHas('stockSession', fn ($q) => $q->whereDate('session_date', $date));
        }

        return $query->orderBy('created_at', 'desc')->get();
    }

    /**
     * Get daily summary statistics for a specific date.
     *
     * @return array{sessions: int, total_items: int, checked_items: int, matched_items: int, mismatched_items: int}
     */
    public function getDailySummary(string $date): array
    {
        $sessions = StockSession::whereDate('session_date', $date);

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
     * Get summary of all stock sessions for a specific date.
     */
    public function getDailyReport(string $date): Collection
    {
        return StockSession::whereDate('session_date', $date)
            ->with(['principal', 'assignedOfficer'])
            ->get();
    }

    /**
     * Build CSV content for a stock session report.
     */
    public function buildSessionCsv(StockSession $session): string
    {
        $items = $this->getSessionReport($session);
        $session->load(['principal', 'assignedOfficer']);

        $lines = [];
        $lines[] = implode(',', [
            'Principal',
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
                    $item->kode_barang,
                    $item->nama_barang,
                    $item->qty_sistem_display,
                    $item->qty_aktual_display ?? '-',
                    $item->selisih ?? '-',
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
    public function buildSelisihCsv(?string $date = null): string
    {
        $items = $this->getAllSelisihItems($date);

        $lines = [];
        $lines[] = implode(',', [
            'Tanggal',
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
                    $session?->principal?->nama ?? '-',
                    $item->kode_barang,
                    $item->nama_barang,
                    $item->qty_sistem_display,
                    $item->qty_aktual_display ?? '-',
                    $item->selisih ?? '-',
                    $item->checkedBy?->name ?? '-',
                    $item->checked_at?->format('Y-m-d H:i') ?? '-',
                ]
            ));
        }

        return implode("\n", $lines);
    }

    /**
     * Build CSV content for a daily report (sessions summary).
     */
    public function buildDailyCsv(string $date): string
    {
        $sessions = $this->getDailyReport($date);

        $lines = [];
        $lines[] = implode(',', [
            'Tanggal',
            'Principal',
            'Status',
            'Total Item',
            'Tercek',
            'Sesuai',
            'Selisih',
            'Petugas',
            'Jam Mulai',
            'Jam Selesai',
        ]);

        foreach ($sessions as $session) {
            $lines[] = implode(',', array_map(
                fn ($value) => '"' . str_replace('"', '""', (string) $value) . '"',
                [
                    $session->session_date->format('Y-m-d'),
                    $session->principal->nama,
                    $session->status->value,
                    $session->total_items,
                    $session->checked_items,
                    $session->matched_items,
                    $session->mismatched_items,
                    $session->assignedOfficer?->name ?? '-',
                    $session->started_at?->format('H:i') ?? '-',
                    $session->completed_at?->format('H:i') ?? '-',
                ]
            ));
        }

        return implode("\n", $lines);
    }
}
