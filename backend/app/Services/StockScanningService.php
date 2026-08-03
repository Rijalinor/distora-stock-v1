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
use Illuminate\Support\Str;

class StockScanningService
{
    public const LOCK_MINUTES = 5;

    protected StockSessionService $sessionService;

    public function __construct(StockSessionService $sessionService)
    {
        $this->sessionService = $sessionService;
    }

    /**
     * Find a stock session item by barcode, product code, or item text.
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
    public function findItemsByBarcode(StockSession $session, string $barcode, bool $exactOnly = false): Collection
    {
        $barcode = trim($barcode);
        if ($barcode === '') {
            return collect();
        }

        $exactItems = $this->findItemsByExactCode($session, $barcode);

        if ($exactItems->isNotEmpty()) {
            return $exactItems;
        }

        if ($exactOnly || $this->isLikelyBarcode($barcode)) {
            return collect();
        }

        return $this->searchSessionItems($session, $barcode);
    }

    protected function isLikelyBarcode(string $value): bool
    {
        return preg_match('/^\d{6,}$/', $value) === 1;
    }

    /**
     * @return Collection<int, StockSessionItem>
     */
    protected function findItemsByExactCode(StockSession $session, string $barcode): Collection
    {
        $itemMasterIds = ItemMaster::query()
            ->where('branch_id', $session->branch_id)
            ->where(fn ($query) => $query
                ->where('barcode', $barcode)
                ->orWhere('kode_barang', $barcode)
                ->orWhereHas('barcodes', fn ($query) => $query->where('barcode', $barcode)))
            ->pluck('id');

        return StockSessionItem::query()
            ->where('stock_session_id', $session->id)
            ->where(fn ($query) => $query
                ->where('kode_barang', $barcode)
                ->when($itemMasterIds->isNotEmpty(), fn ($query) => $query->orWhereIn('item_master_id', $itemMasterIds)))
            ->with('itemMaster.barcodes')
            ->orderBy('kode_barang')
            ->get();
    }

    /**
     * @return Collection<int, StockSessionItem>
     */
    protected function searchSessionItems(StockSession $session, string $search): Collection
    {
        $normalizedSearch = $this->normalizeSearchText($search);

        return StockSessionItem::query()
            ->where('stock_session_id', $session->id)
            ->with('itemMaster.barcodes')
            ->get()
            ->map(fn (StockSessionItem $item) => [
                'item' => $item,
                'score' => $this->searchScore($item, $normalizedSearch),
                'code' => $item->kode_barang,
            ])
            ->filter(fn (array $result) => $result['score'] > 0)
            ->sortBy([
                ['score', 'desc'],
                ['code', 'asc'],
            ])
            ->take(25)
            ->pluck('item')
            ->values();
    }

    protected function searchScore(StockSessionItem $item, string $search): float
    {
        $values = collect([
            $item->kode_barang,
            $item->nama_barang,
            $item->satuan,
            $item->itemMaster?->kode_barang,
            $item->itemMaster?->barcode,
            ...($item->itemMaster?->barcodes?->pluck('barcode')->all() ?? []),
            $item->itemMaster?->nama_barang,
        ])
            ->filter()
            ->map(fn ($value) => $this->normalizeSearchText((string) $value))
            ->unique();

        if ($values->contains($search)) {
            return 1000;
        }

        if ($values->contains(fn (string $value) => str_starts_with($value, $search))) {
            return 900;
        }

        if ($values->contains(fn (string $value) => str_contains($value, $search))) {
            return 800;
        }

        if (mb_strlen($search) < 4) {
            return 0;
        }

        $searchWords = array_values(array_filter(explode(' ', $search)));
        $bestWordScores = collect($searchWords)->map(function (string $searchWord) use ($values): float {
            return $values
                ->flatMap(fn (string $value) => explode(' ', $value))
                ->map(fn (string $word) => $this->similarity($searchWord, $word))
                ->max() ?? 0;
        });

        $minimumSimilarity = $bestWordScores->min() ?? 0;

        if ($minimumSimilarity < 70) {
            return 0;
        }

        return 500 + $bestWordScores->average();
    }

    protected function normalizeSearchText(string $value): string
    {
        return (string) Str::of($value)
            ->ascii()
            ->lower()
            ->replaceMatches('/[^a-z0-9]+/', ' ')
            ->squish();
    }

    protected function similarity(string $first, string $second): float
    {
        similar_text($first, $second, $percentage);

        return $percentage;
    }

    public function acquireLock(StockSessionItem $item, User $officer): StockSessionItem
    {
        $expiresAt = now()->subMinutes(self::LOCK_MINUTES);

        $updated = StockSessionItem::query()
            ->whereKey($item->id)
            ->where(function ($query) use ($officer, $expiresAt): void {
                $query->whereNull('locked_by')
                    ->orWhere('locked_by', $officer->id)
                    ->orWhere('locked_at', '<', $expiresAt);
            })
            ->update([
                'locked_by' => $officer->id,
                'locked_at' => now(),
            ]);

        if ($updated === 0) {
            $item->refresh()->load('lockedBy');
            $name = $item->lockedBy?->name ?? 'petugas lain';

            throw new \RuntimeException("Barang sedang dihitung oleh {$name}. Coba lagi setelah petugas itu selesai.");
        }

        return $item->refresh();
    }

    public function releaseLock(StockSessionItem $item, User $officer): void
    {
        StockSessionItem::query()
            ->whereKey($item->id)
            ->where('locked_by', $officer->id)
            ->update([
                'locked_by' => null,
                'locked_at' => null,
            ]);
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
                'locked_by' => null,
                'locked_at' => null,
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
                'locked_by' => null,
                'locked_at' => null,
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
                'locked_by' => null,
                'locked_at' => null,
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
                'locked_by' => null,
                'locked_at' => null,
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
