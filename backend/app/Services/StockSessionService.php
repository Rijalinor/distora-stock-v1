<?php

namespace App\Services;

use App\DTOs\CsvPreviewResult;
use App\Enums\StockSessionStatus;
use App\Enums\StockSessionItemStatus;
use App\Models\CsvUpload;
use App\Models\ItemMaster;
use App\Models\Principal;
use App\Models\StockSession;
use App\Models\StockSessionItem;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class StockSessionService
{
    /**
     * Generate stock opname sessions and their items from a CSV upload.
     *
     * @param CsvUpload $upload
     * @param CsvPreviewResult $previewResult
     * @return Collection<StockSession>
     */
    public function generateSessions(CsvUpload $upload, CsvPreviewResult $previewResult): Collection
    {
        return DB::transaction(function () use ($upload, $previewResult) {
            $sessions = collect();

            // 1. Get database map for Principals (kode -> effective principal id)
            $principalsMap = Principal::query()
                ->get(['id', 'kode', 'group_principal_id'])
                ->mapWithKeys(fn (Principal $principal) => [
                    $principal->kode => $principal->effectivePrincipalId(),
                ]);

            // 2. Get database map for Item Masters (kode_barang -> id)
            $itemsMap = ItemMaster::pluck('id', 'kode_barang');

            // 3. Group rows by effective principal id, so merged principals share one session.
            $rowsByPrincipal = collect($previewResult->rows)
                ->groupBy(fn ($row) => $principalsMap->get($row->principalKode));

            foreach ($rowsByPrincipal as $principalId => $rows) {
                if (!$principalId) {
                    continue; // Skip if principal is missing (should not happen after sync)
                }

                // Create Stock Session
                $session = StockSession::create([
                    'csv_upload_id' => $upload->id,
                    'principal_id' => $principalId,
                    'session_date' => $upload->upload_date,
                    'status' => StockSessionStatus::Open,
                    'total_items' => $rows->count(),
                    'checked_items' => 0,
                    'matched_items' => 0,
                    'mismatched_items' => 0,
                ]);

                app(AuditLogService::class)->log('session_created', $session, [], [
                    'csv_upload_id' => $upload->id,
                    'principal_id' => $principalId,
                    'session_date' => $upload->upload_date?->toDateString(),
                    'total_items' => $rows->count(),
                ]);

                // Create Stock Session Items
                foreach ($rows as $row) {
                    $itemMasterId = $itemsMap->get($row->itemKode);

                    StockSessionItem::create([
                        'stock_session_id' => $session->id,
                        'item_master_id' => $itemMasterId,
                        'kode_barang' => $row->itemKode,
                        'nama_barang' => $row->itemNama,
                        'satuan' => $row->satuan,
                        'qty_sistem_display' => $row->qtySistemDisplay,
                        'qty_sistem_base' => $row->qtySistemBase,
                        'status' => StockSessionItemStatus::Pending,
                    ]);
                }

                $sessions->push($session);
            }

            return $sessions;
        });
    }

    /**
     * Assign stock session to a specific officer and start it.
     *
     * @param StockSession $session
     * @param User $officer
     * @return void
     */
    public function assignOfficer(StockSession $session, User $officer): void
    {
        $before = [
            'assigned_to' => $session->assigned_to,
            'status' => $session->status?->value,
            'started_at' => $session->started_at?->toDateTimeString(),
        ];

        $data = [
            'assigned_to' => $officer->id,
        ];

        if ($session->status === StockSessionStatus::Open) {
            $data['status'] = StockSessionStatus::InProgress;
            $data['started_at'] = now();
        }

        $session->update($data);

        app(AuditLogService::class)->log('session_assigned', $session, $before, [
            'assigned_to' => $session->assigned_to,
            'status' => $session->status?->value,
            'started_at' => $session->started_at?->toDateTimeString(),
        ]);
    }

    /**
     * Complete a stock session.
     *
     * @param StockSession $session
     * @return void
     * @throws \Exception
     */
    public function completeSession(StockSession $session): void
    {
        $before = [
            'status' => $session->status?->value,
            'completed_at' => $session->completed_at?->toDateTimeString(),
        ];

        $session->update([
            'status' => StockSessionStatus::Completed,
            'completed_at' => now(),
        ]);

        app(AuditLogService::class)->log('session_completed', $session, $before, [
            'status' => $session->status?->value,
            'completed_at' => $session->completed_at?->toDateTimeString(),
        ]);
    }

    /**
     * @return array{
     *     date: string,
     *     total_sessions: int,
     *     active_sessions: int,
     *     completed_sessions: int,
     *     pending_principals: \Illuminate\Support\Collection<int, array<string, mixed>>
     * }
     */
    public function summarizeTodaySessions(): array
    {
        $date = today()->toDateString();

        $sessions = StockSession::query()
            ->with('principal')
            ->whereDate('session_date', $date)
            ->get();

        $pendingPrincipals = $sessions
            ->filter(fn (StockSession $session) => $session->status !== StockSessionStatus::Completed)
            ->map(fn (StockSession $session) => [
                'principal' => $session->principal?->nama ?? '-',
                'status' => $session->status->value,
                'checked_items' => $session->checked_items,
                'total_items' => $session->total_items,
                'mismatched_items' => $session->mismatched_items,
            ])
            ->values();

        return [
            'date' => $date,
            'total_sessions' => $sessions->count(),
            'active_sessions' => $sessions->whereIn('status', [StockSessionStatus::Open, StockSessionStatus::InProgress])->count(),
            'completed_sessions' => $sessions->where('status', StockSessionStatus::Completed)->count(),
            'pending_principals' => $pendingPrincipals,
        ];
    }

    /**
     * Close all non-completed sessions for today.
     *
     * @return int
     */
    public function closeTodaySessions(): int
    {
        return DB::transaction(function (): int {
            $sessions = StockSession::query()
                ->whereDate('session_date', today())
                ->whereIn('status', [StockSessionStatus::Open, StockSessionStatus::InProgress])
                ->get();

            foreach ($sessions as $session) {
                $before = [
                    'status' => $session->status?->value,
                    'completed_at' => $session->completed_at?->toDateTimeString(),
                ];

                $session->update([
                    'status' => StockSessionStatus::Completed,
                    'completed_at' => now(),
                ]);

                app(AuditLogService::class)->log('session_closed_by_day', $session, $before, [
                    'status' => $session->status?->value,
                    'completed_at' => $session->completed_at?->toDateTimeString(),
                    'closed_date' => today()->toDateString(),
                ]);
            }

            return $sessions->count();
        });
    }

    /**
     * Recalculate progress counters for a session.
     *
     * @param StockSession $session
     * @return void
     */
    public function recalculateProgress(StockSession $session): void
    {
        $counts = DB::table('stock_session_items')
            ->where('stock_session_id', $session->id)
            ->selectRaw('
                COUNT(*) as total,
                SUM(CASE WHEN status != ? THEN 1 ELSE 0 END) as checked,
                SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as matched,
                SUM(CASE WHEN status IN (?, ?) THEN 1 ELSE 0 END) as mismatched
            ', [
                StockSessionItemStatus::Pending->value,
                StockSessionItemStatus::Matched->value,
                StockSessionItemStatus::Mismatched->value,
                StockSessionItemStatus::Missing->value,
            ])
            ->first();

        $session->update([
            'total_items' => $counts->total,
            'checked_items' => $counts->checked ?? 0,
            'matched_items' => $counts->matched ?? 0,
            'mismatched_items' => $counts->mismatched ?? 0,
        ]);
    }
}
