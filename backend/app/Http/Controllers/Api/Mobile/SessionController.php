<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Enums\StockSessionStatus;
use App\Http\Controllers\Controller;
use App\Models\StockSession;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SessionController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $query = StockSession::query()
            ->with(['principal', 'branch', 'assignedOfficer'])
            ->whereDate('session_date', today())
            ->whereIn('status', [StockSessionStatus::Open, StockSessionStatus::InProgress]);

        if ($user && ! $user->isCentralAdmin() && $user->branch_id) {
            $query->where('branch_id', $user->branch_id);
        }

        $sessions = $query->orderBy('principal_id')->get()->map(fn (StockSession $session) => [
            'id' => $session->id,
            'principal' => [
                'id' => $session->principal->id,
                'nama' => $session->principal->nama,
            ],
            'branch' => [
                'id' => $session->branch?->id,
                'nama' => $session->branch?->nama,
            ],
            'status' => $session->status->value,
            'total_items' => $session->total_items,
            'checked_items' => $session->checked_items,
            'matched_items' => $session->matched_items,
            'mismatched_items' => $session->mismatched_items,
            'assigned_to' => $session->assigned_to,
            'assigned_officer' => $session->assignedOfficer?->name,
        ]);

        return response()->json(['data' => $sessions]);
    }

    public function show(StockSession $session): JsonResponse
    {
        $user = request()->user();

        if ($user && ! $user->isCentralAdmin() && $user->branch_id && $session->branch_id !== $user->branch_id) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $session->load(['principal', 'branch', 'assignedOfficer', 'items.checkedBy']);

        return response()->json([
            'id' => $session->id,
            'principal' => [
                'id' => $session->principal->id,
                'nama' => $session->principal->nama,
            ],
            'branch' => [
                'id' => $session->branch?->id,
                'nama' => $session->branch?->nama,
            ],
            'status' => $session->status->value,
            'session_date' => $session->session_date?->format('Y-m-d'),
            'total_items' => $session->total_items,
            'checked_items' => $session->checked_items,
            'matched_items' => $session->matched_items,
            'mismatched_items' => $session->mismatched_items,
            'assigned_officer' => $session->assignedOfficer?->name,
            'items' => $session->items->map(fn ($item) => [
                'id' => $item->id,
                'kode_barang' => $item->kode_barang,
                'nama_barang' => $item->nama_barang,
                'qty_sistem_display' => $item->qty_sistem_display,
                'qty_aktual_display' => $item->qty_aktual_display,
                'selisih' => $item->selisih,
                'status' => $item->status->value,
                'checked_by' => $item->checkedBy?->name,
                'checked_at' => $item->checked_at?->toDateTimeString(),
            ]),
        ]);
    }
}
