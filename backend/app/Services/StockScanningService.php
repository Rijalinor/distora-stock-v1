<?php

namespace App\Services;

use App\Enums\StockSessionItemStatus;
use App\Models\ItemMaster;
use App\Models\StockAdjustmentLog;
use App\Models\StockSession;
use App\Models\StockSessionItem;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class StockScanningService
{
    protected StockSessionService $sessionService;

    public function __construct(StockSessionService $sessionService)
    {
        $this->sessionService = $sessionService;
    }

    /**
     * Find a stock session item by barcode or product code.
     *
     * @param StockSession $session
     * @param string $barcode
     * @return StockSessionItem|null
     */
    public function findByBarcode(StockSession $session, string $barcode): ?StockSessionItem
    {
        return $this->findItemsByBarcode($session, $barcode)->first();
    }

    /**
     * @return Collection<int, StockSessionItem>
     */
    public function findItemsByBarcode(StockSession $session, string $barcode): Collection
    {
        $barcode = trim($barcode);
        if ($barcode === '') {
            return collect();
        }

        $itemMasterIds = ItemMaster::query()
            ->where('barcode', $barcode)
            ->orWhere('kode_barang', $barcode)
            ->pluck('id');

        if ($itemMasterIds->isNotEmpty()) {
            $items = StockSessionItem::query()
                ->where('stock_session_id', $session->id)
                ->whereIn('item_master_id', $itemMasterIds)
                ->with('itemMaster')
                ->orderBy('kode_barang')
                ->get();

            if ($items->isNotEmpty()) {
                return $items;
            }
        }

        return StockSessionItem::query()
            ->where('stock_session_id', $session->id)
            ->where('kode_barang', $barcode)
            ->with('itemMaster')
            ->get();
    }

    /**
     * Record actual quantity for an item.
     *
     * @param StockSessionItem $item
     * @param array $qtyLevels
     * @param User $officer
     * @return void
     */
    public function recordStock(StockSessionItem $item, array $qtyLevels, User $officer): void
    {
        DB::transaction(function () use ($item, $qtyLevels, $officer) {
            $factors = $this->resolveQtyFactors($item);
            $labels = $this->resolveQtyLabels($item);
            $qtyBase = self::calculateBaseQuantity($qtyLevels, $factors);
            $qtyDisplay = self::buildQtyDisplay($qtyLevels, $labels);
            
            $selisih = $qtyBase - $item->qty_sistem_base;
            $status = $selisih === 0 ? StockSessionItemStatus::Matched : StockSessionItemStatus::Mismatched;

            $item->update([
                'qty_aktual_base' => $qtyBase,
                'qty_aktual_display' => $qtyDisplay,
                'selisih' => $selisih,
                'status' => $status,
                'checked_by' => $officer->id,
                'checked_at' => now(),
            ]);

            app(AuditLogService::class)->log('stock_recorded', $item, [], [
                'qty_aktual_base' => $qtyBase,
                'qty_aktual_display' => $qtyDisplay,
                'selisih' => $selisih,
                'status' => $status->value,
            ]);

            $this->sessionService->recalculateProgress($item->stockSession);
        });
    }

    /**
     * Mark an item as matched (copy system quantity).
     *
     * @param StockSessionItem $item
     * @param User $officer
     * @return void
     */
    public function markAsMatched(StockSessionItem $item, User $officer): void
    {
        DB::transaction(function () use ($item, $officer) {
            $item->update([
                'qty_aktual_base' => $item->qty_sistem_base,
                'qty_aktual_display' => $item->qty_sistem_display,
                'selisih' => 0,
                'status' => StockSessionItemStatus::Matched,
                'checked_by' => $officer->id,
                'checked_at' => now(),
            ]);

            app(AuditLogService::class)->log('stock_matched', $item, [], [
                'qty_aktual_base' => $item->qty_sistem_base,
                'qty_aktual_display' => $item->qty_sistem_display,
                'selisih' => 0,
                'status' => StockSessionItemStatus::Matched->value,
            ]);

            $this->sessionService->recalculateProgress($item->stockSession);
        });
    }

    public function markAsMissing(StockSessionItem $item, User $officer): void
    {
        DB::transaction(function () use ($item, $officer) {
            $labels = $this->resolveQtyLabels($item);
            $selisih = 0 - $item->qty_sistem_base;

            $item->update([
                'qty_aktual_base' => 0,
                'qty_aktual_display' => self::buildQtyDisplayFromLabels([], $labels),
                'selisih' => $selisih,
                'status' => StockSessionItemStatus::Missing,
                'checked_by' => $officer->id,
                'checked_at' => now(),
            ]);

            app(AuditLogService::class)->log('stock_missing', $item, [], [
                'qty_aktual_base' => 0,
                'qty_aktual_display' => self::buildQtyDisplayFromLabels([], $labels),
                'selisih' => $selisih,
                'status' => StockSessionItemStatus::Missing->value,
            ]);

            $this->sessionService->recalculateProgress($item->stockSession);
        });
    }

    /**
     * Update an item's quantity with adjustment log.
     *
     * @param StockSessionItem $item
     * @param array $newQtyLevels
     * @param User $officer
     * @param string|null $reason
     * @return void
     */
    public function updateStock(StockSessionItem $item, array $newQtyLevels, User $officer, ?string $reason = null): void
    {
        DB::transaction(function () use ($item, $newQtyLevels, $officer, $reason) {
            $factors = $this->resolveQtyFactors($item);
            $labels = $this->resolveQtyLabels($item);
            $qtyAfterBase = self::calculateBaseQuantity($newQtyLevels, $factors);
            $qtyAfterDisplay = self::buildQtyDisplay($newQtyLevels, $labels);
            $before = [
                'qty_aktual_base' => $item->qty_aktual_base,
                'qty_aktual_display' => $item->qty_aktual_display,
                'selisih' => $item->selisih,
                'status' => $item->status instanceof StockSessionItemStatus
                    ? $item->status->value
                    : $item->status,
            ];

            // Log adjustment
            StockAdjustmentLog::create([
                'stock_session_item_id' => $item->id,
                'adjusted_by' => $officer->id,
                'qty_before_base' => $item->qty_aktual_base ?? 0,
                'qty_after_base' => $qtyAfterBase,
                'qty_before_display' => $item->qty_aktual_display ?? '0',
                'qty_after_display' => $qtyAfterDisplay,
                'reason' => $reason,
            ]);

            // Update item
            $selisih = $qtyAfterBase - $item->qty_sistem_base;
            $status = $selisih === 0 ? StockSessionItemStatus::Matched : StockSessionItemStatus::Mismatched;

            $item->update([
                'qty_aktual_base' => $qtyAfterBase,
                'qty_aktual_display' => $qtyAfterDisplay,
                'selisih' => $selisih,
                'status' => $status,
                'checked_by' => $officer->id,
                'checked_at' => now(),
            ]);

            app(AuditLogService::class)->log('stock_corrected', $item, $before, [
                'qty_aktual_base' => $qtyAfterBase,
                'qty_aktual_display' => $qtyAfterDisplay,
                'selisih' => $selisih,
                'status' => $status->value,
                'reason' => $reason,
            ]);

            $this->sessionService->recalculateProgress($item->stockSession);
        });
    }

    /**
     * Parse conversion factors from description.
     * Example: "(1X12)" -> [12], "(1X12X12)" -> [12, 12]
     *
     * @param string $description
     * @return int[]
     */
    public static function parseConversionFactors(string $description): array
    {
        if (preg_match('/\(1X(\d+)(?:X(\d+))?(?:X(\d+))?\)/i', $description, $matches)) {
            $factors = [];
            if (isset($matches[1])) {
                $factors[] = (int) $matches[1];
            }
            if (isset($matches[2]) && $matches[2] !== '') {
                $factors[] = (int) $matches[2];
            }
            if (isset($matches[3]) && $matches[3] !== '') {
                $factors[] = (int) $matches[3];
            }
            return $factors;
        }
        return [];
    }

    /**
     * Calculate base quantity from levels and factors.
     *
     * @param int[] $qtyLevels
     * @param int[] $factors
     * @return int
     */
    public static function calculateBaseQuantity(array $qtyLevels, array $factors): int
    {
        $levelsCount = count($factors) + 1;
        $qtyLevels = array_pad(array_slice($qtyLevels, 0, $levelsCount), $levelsCount, 0);
        
        $totalBase = 0;
        for ($i = 0; $i < $levelsCount; $i++) {
            $multiplier = 1;
            for ($j = $i; $j < count($factors); $j++) {
                $multiplier *= $factors[$j];
            }
            $totalBase += (int)$qtyLevels[$i] * $multiplier;
        }
        return $totalBase;
    }

    /**
     * Build user friendly qty display from levels.
     *
     * @param int[] $qtyLevels
     * @param string|null $size
     * @return string
     */
    public static function buildQtyDisplay(array $qtyLevels, array|string|null $labelsOrSize): string
    {
        if (is_array($labelsOrSize)) {
            return self::buildQtyDisplayFromLabels($qtyLevels, $labelsOrSize);
        }

        $labels = [];
        if ($labelsOrSize) {
            $labels = array_map(fn($s) => trim($s), explode('-', $labelsOrSize));
        }
        if (empty($labels)) {
            $labels = ['CTN', 'PCS'];
        }
        
        $displayParts = [];
        foreach ($labels as $index => $label) {
            if (isset($qtyLevels[$index]) && (int)$qtyLevels[$index] > 0) {
                $displayParts[] = "{$qtyLevels[$index]} {$label}";
            }
        }
        
        if (empty($displayParts)) {
            $lastLabel = end($labels) ?: 'PCS';
            return "0 {$lastLabel}";
        }
        
        return implode(' ', $displayParts);
    }

    /**
     * Build user friendly qty display from explicit labels.
     *
     * @param int[] $qtyLevels
     * @param array<int, string> $labels
     */
    public static function buildQtyDisplayFromLabels(array $qtyLevels, array $labels): string
    {
        if (empty($labels)) {
            return self::buildQtyDisplay($qtyLevels, null);
        }

        $displayParts = [];

        foreach ($labels as $index => $label) {
            if (isset($qtyLevels[$index]) && (int) $qtyLevels[$index] > 0) {
                $displayParts[] = "{$qtyLevels[$index]} {$label}";
            }
        }

        if (empty($displayParts)) {
            return '0 ' . ($labels[array_key_last($labels)] ?? 'PCS');
        }

        return implode(' ', $displayParts);
    }

    /**
     * Split base quantity back into its individual levels.
     *
     * @param int $baseQty
     * @param int[] $factors
     * @return int[]
     */
    public static function splitBaseQuantity(int $baseQty, array $factors): array
    {
        $levelsCount = count($factors) + 1;
        $qtyLevels = array_fill(0, $levelsCount, 0);
        $remainder = $baseQty;
        
        for ($i = 0; $i < $levelsCount; $i++) {
            $multiplier = 1;
            for ($j = $i; $j < count($factors); $j++) {
                $multiplier *= $factors[$j];
            }
            $qtyLevels[$i] = intdiv($remainder, $multiplier);
            $remainder = $remainder % $multiplier;
        }
        
        return $qtyLevels;
    }

    /**
     * @return array<int, string>
     */
    protected function resolveQtyLabels(StockSessionItem $item): array
    {
        $labels = $item->itemMaster?->getQtyLabelsArray() ?? [];

        return ! empty($labels) ? $labels : ['CTN', 'PCS'];
    }

    /**
     * @return array<int, int>
     */
    protected function resolveQtyFactors(StockSessionItem $item): array
    {
        $factors = $item->itemMaster?->getQtyFactorsArray() ?? [];

        if (! empty($factors)) {
            return $factors;
        }

        return self::parseConversionFactors($item->nama_barang);
    }
}
