<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Enums\StockSessionItemStatus;
use App\Http\Controllers\Controller;
use App\Models\StockSession;
use App\Services\StockScanningService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ScanController extends Controller
{
    public function store(Request $request, StockSession $session, StockScanningService $scanningService): JsonResponse
    {
        $user = $request->user();

        if ($user?->isStockOfficer() && $session->assigned_to && $session->assigned_to !== $user->id) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $data = $request->validate([
            'barcode' => ['required', 'string'],
            'qty_levels' => ['nullable', 'array'],
            'qty_levels.*' => ['integer', 'min:0'],
            'mode' => ['nullable', 'in:record,match'],
            'reason' => ['nullable', 'string', 'max:255'],
        ]);

        $item = $scanningService->findByBarcode($session, $data['barcode']);

        if (! $item) {
            return response()->json([
                'message' => 'Barang tidak ditemukan di sesi ini.',
            ], 404);
        }

        if (($data['mode'] ?? 'record') === 'match') {
            $scanningService->markAsMatched($item, $user);
        } else {
            $levels = $data['qty_levels'] ?? [];

            if ($item->status === StockSessionItemStatus::Matched && empty($levels)) {
                $scanningService->markAsMatched($item, $user);
            } else {
                $scanningService->recordStock($item, $levels, $user);
            }
        }

        $item->refresh();
        $session->refresh();

        return response()->json([
            'message' => 'Scan tersimpan.',
            'data' => [
                'item' => [
                    'id' => $item->id,
                    'kode_barang' => $item->kode_barang,
                    'nama_barang' => $item->nama_barang,
                    'qty_sistem_display' => $item->qty_sistem_display,
                    'qty_aktual_display' => $item->qty_aktual_display,
                    'qty_aktual_base' => $item->qty_aktual_base,
                    'selisih' => $item->selisih,
                    'status' => $item->status->value,
                ],
                'session' => [
                    'id' => $session->id,
                    'checked_items' => $session->checked_items,
                    'matched_items' => $session->matched_items,
                    'mismatched_items' => $session->mismatched_items,
                ],
            ],
        ]);
    }
}
