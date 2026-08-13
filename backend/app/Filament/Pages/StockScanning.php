<?php

namespace App\Filament\Pages;

use App\Enums\StockSessionItemStatus;
use App\Enums\StockSessionStatus;
use App\Enums\UserRole;
use App\Models\StockFoundItem;
use App\Models\StockSession;
use App\Models\StockSessionItem;
use App\Services\ReportService;
use App\Services\StockFoundItemService;
use App\Services\StockScanningService;
use App\Services\StockSessionService;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;

class StockScanning extends Page
{
    private const SESSION_KEY = 'stock_scanning.selected_session_id';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedQrCode;

    protected static ?string $navigationLabel = 'Scan Barcode';

    protected static ?string $title = '';

    protected static string|\UnitEnum|null $navigationGroup = 'Operasional';

    protected static ?int $navigationSort = 1;

    protected string $view = 'filament.pages.stock-scanning';

    public ?int $selectedSessionId = null;

    public string $barcode = '';

    public ?StockSessionItem $scannedItem = null;

    public string $lastScannedBarcode = '';

    /** @var array<int, array{id: int, code: string, name: string, system_qty: string, status: string}> */
    public array $scanCandidates = [];

    /** @var array<int, int|string> */
    public array $qtyLevels = [];

    public string $separateCountMode = 'ctn';

    public string $editReason = '';

    public bool $isEditing = false;

    public string $pendingSearch = '';

    public string $checkedSearch = '';

    public string $comparisonSearch = '';

    public string $comparisonFilter = 'changed';

    public int $comparisonLimit = 10;

    public int $foundItemsLimit = 10;

    public int $mismatchedItemsLimit = 10;

    public bool $showComparisonPanel = false;

    public bool $showFoundItemsPanel = false;

    public bool $showCheckedItemsPanel = false;

    public bool $showMismatchedItemsPanel = false;

    public bool $showPendingItemsPanel = false;

    public ?string $notFoundBarcode = null;

    public string $foundItemName = '';

    public ?int $foundItemMasterId = null;

    public string $foundSystemQtyDisplay = '';

    /** @var array<int, string> */
    public array $foundQtyLabels = [];

    /** @var array<int, int> */
    public array $foundQtyFactors = [];

    /** @var array<int, int|string> */
    public array $foundQtyLevels = [];

    public string $foundQty = '';

    public ?int $suspectedItemMasterId = null;

    public string $foundNote = '';

    public ?int $editingFoundItemId = null;

    /** @var array<int, array{name: string, code: string, status: string, at: string}> */
    public array $recentScans = [];

    public function mount(): void
    {
        $this->selectedSessionId = session()->get(self::SESSION_KEY);

        if ($this->selectedSessionId && ! $this->ensureSelectedSessionAccess()) {
            session()->forget(self::SESSION_KEY);
            $this->selectedSessionId = null;
        }
    }

    public static function canAccess(): bool
    {
        $user = Auth::user();

        return $user && ($user->isAdmin() || $user->isStockOfficer());
    }

    public function getAvailableSessions()
    {
        $query = StockSession::query()
            ->with(['principal', 'branch', 'officers'])
            ->whereIn('status', [StockSessionStatus::Open, StockSessionStatus::InProgress]);

        $user = Auth::user();

        if ($user && ! $user->isCentralAdmin() && $user->branch_id) {
            $query
                ->where('branch_id', $user->branch_id)
                ->whereHas('items.itemMaster', fn ($query) => $query
                    ->where('branch_id', $user->branch_id)
                    ->where('status', true));
        }

        return $query
            ->orderBy('session_date')
            ->orderBy('principal_id')
            ->get();
    }

    public function selectSession(int $sessionId): void
    {
        $session = StockSession::with('principal')->findOrFail($sessionId);

        if (! Auth::user()?->managesBranch($session->branch_id)) {
            abort(403);
        }

        if (Auth::user()?->isStockOfficer()) {
            app(StockSessionService::class)->assignOfficer($session, Auth::user());
        }

        $this->selectedSessionId = $sessionId;
        session()->put(self::SESSION_KEY, $sessionId);
        $this->resetScanState();
    }

    public function backToSessionList(): void
    {
        $this->selectedSessionId = null;
        session()->forget(self::SESSION_KEY);
        $this->resetScanState();
    }

    public function scanBarcode(?string $barcode = null, bool $exactOnly = false): void
    {
        if (! $this->ensureSelectedSessionAccess()) {
            return;
        }

        if ($barcode !== null) {
            $this->barcode = $barcode;
        }

        if (! $this->selectedSessionId || blank($this->barcode)) {
            return;
        }

        $session = StockSession::findOrFail($this->selectedSessionId);
        $items = app(StockScanningService::class)->findItemsByBarcode($session, $this->barcode, $exactOnly);

        if ($items->isEmpty()) {
            $existingFoundItem = app(StockFoundItemService::class)->findExisting($session, $this->barcode);

            if ($existingFoundItem) {
                Notification::make()
                    ->title('Barang temuan sudah tercatat')
                    ->body("Barcode/kode {$this->barcode} sebelumnya sudah dicatat dengan qty {$existingFoundItem->qty_aktual_display}.")
                    ->warning()
                    ->send();

                $this->barcode = '';
                $this->dispatch('stock-scan-ready');

                return;
            }

            $foundItemService = app(StockFoundItemService::class);
            $itemMaster = $foundItemService->findItemMaster($session, $this->barcode);

            $this->notFoundBarcode = trim($this->barcode);
            $this->foundItemMasterId = $itemMaster?->id;
            $this->foundItemName = $itemMaster?->nama_barang ?? '';
            $this->foundQtyFactors = $itemMaster
                ? ($itemMaster->getQtyFactorsArray() ?: StockScanningService::parseConversionFactors($itemMaster->nama_barang))
                : [];
            $foundLevelsCount = count($this->foundQtyFactors) + 1;
            $this->foundQtyLabels = $itemMaster
                ? array_pad(array_slice($itemMaster->getQtyLabelsArray(), 0, $foundLevelsCount), $foundLevelsCount, 'PCS')
                : [];
            $this->foundQtyLevels = $itemMaster ? array_fill(0, $foundLevelsCount, 0) : [];
            $this->foundSystemQtyDisplay = $itemMaster
                ? collect($this->foundQtyLabels)->map(fn (string $label) => "0 {$label}")->implode(' ')
                : '';
            $this->foundQty = $itemMaster ? '' : '1 PCS';
            $this->dispatch('stock-found-item-ready');

            Notification::make()
                ->title($itemMaster ? 'Barang tidak ada di sesi' : 'Barang tidak ditemukan')
                ->body($itemMaster
                    ? 'Barang ditemukan di Item Master. Stok sistem pada sesi ini dianggap 0 dan dapat dicatat sebagai barang temuan.'
                    : 'Barcode, kode, atau nama barang tidak ada di sesi principal ini. Bisa dicatat sebagai barang temuan.')
                ->warning()
                ->send();

            $this->barcode = '';

            return;
        }

        if ($items->count() > 1) {
            $this->lastScannedBarcode = $this->barcode;
            $this->scanCandidates = $items
                ->map(fn (StockSessionItem $item): array => [
                    'id' => $item->id,
                    'code' => $item->kode_barang,
                    'name' => $item->nama_barang,
                    'system_qty' => $this->formatSystemQty($item),
                    'status' => $item->status->value,
                ])
                ->values()
                ->all();
            $this->barcode = '';
            $this->dispatch('stock-scan-candidates');

            Notification::make()
                ->title('Ditemukan beberapa item')
                ->body('Pilih barang yang sedang dihitung.')
                ->warning()
                ->send();

            return;
        }

        $this->openScannedItem($items->first());
    }

    public function chooseScanCandidate(int $itemId): void
    {
        if (! $this->ensureSelectedSessionAccess()) {
            return;
        }

        $session = StockSession::findOrFail($this->selectedSessionId);
        $item = $session->items()->findOrFail($itemId);

        $this->openScannedItem($item);
    }

    protected function openScannedItem(StockSessionItem $item): void
    {
        try {
            $item = app(StockScanningService::class)->acquireLock($item, Auth::user());
        } catch (\RuntimeException $e) {
            $this->dispatch('stock-scan-failed');

            Notification::make()
                ->title('Barang sedang dikerjakan')
                ->body($e->getMessage())
                ->warning()
                ->send();

            $this->resetScanState(false);

            return;
        }

        $this->scannedItem = $item;
        $this->scanCandidates = [];
        $this->notFoundBarcode = null;
        $this->lastScannedBarcode = '';
        $this->isEditing = $item->status !== StockSessionItemStatus::Pending;
        $this->prepareQtyLevels($item);
        $this->barcode = '';
        $this->rememberScan($item);
        $this->dispatch('stock-item-scanned');

        if ($this->isEditing) {
            Notification::make()
                ->title('Barang sudah pernah dicek')
                ->body("Status terakhir: {$item->status->value}. Anda bisa koreksi qty bila perlu.")
                ->info()
                ->send();
        }
    }

    public function markComplete(): void
    {
        if (! $this->ensureScannedItemAccess()) {
            return;
        }

        if (! $this->scannedItem) {
            return;
        }

        app(StockScanningService::class)->markAsMatched($this->scannedItem, Auth::user());

        Notification::make()
            ->title('Berhasil disimpan')
            ->body("{$this->scannedItem->nama_barang} — aktual: {$this->scannedItem->qty_sistem_display}")
            ->success()
            ->duration(2500)
            ->send();

        $this->resetScanState();
    }

    public function markCurrentModeMatched(): void
    {
        if (! $this->ensureScannedItemAccess() || ! $this->scannedItem) {
            return;
        }

        $systemLevels = StockScanningService::splitBaseQuantity(
            $this->scannedItem->qty_sistem_base,
            $this->getQtyFactorsForItem($this->scannedItem)
        );

        if ($this->usesSeparateCtnPcsCount()) {
            $activeIndexes = $this->getSeparateCountModeIndexes();
            $this->qtyLevels = $this->scannedItem->qty_aktual_base !== null
                ? StockScanningService::splitBaseQuantity(
                    $this->scannedItem->qty_aktual_base,
                    $this->getQtyFactorsForItem($this->scannedItem)
                )
                : array_fill(0, count($systemLevels), 0);
        } else {
            $activeIndexes = array_keys($systemLevels);
        }

        foreach ($activeIndexes as $index) {
            $this->qtyLevels[$index] = $systemLevels[$index] ?? 0;
        }

        $this->submitActualQty();
    }

    public function markMissing(): void
    {
        if (! $this->ensureScannedItemAccess()) {
            return;
        }

        if (! $this->scannedItem) {
            return;
        }

        app(StockScanningService::class)->markAsMissing($this->scannedItem, Auth::user());

        Notification::make()
            ->title('Barang tidak ada')
            ->body($this->scannedItem->nama_barang . ' ditandai tidak ada fisik.')
            ->warning()
            ->send();

        $this->resetScanState();
    }

    public function markItemMissing(int $itemId): void
    {
        if (! $this->ensureSelectedSessionAccess()) {
            return;
        }

        $session = StockSession::findOrFail($this->selectedSessionId);
        $item = $session->items()->findOrFail($itemId);

        try {
            $item = app(StockScanningService::class)->acquireLock($item, Auth::user());
        } catch (\RuntimeException $e) {
            Notification::make()
                ->title('Barang sedang dikerjakan')
                ->body($e->getMessage())
                ->warning()
                ->send();

            return;
        }

        app(StockScanningService::class)->markAsMissing($item, Auth::user());

        Notification::make()
            ->title('Barang tidak ada')
            ->body($item->nama_barang . ' ditandai tidak ada fisik.')
            ->warning()
            ->send();

        $this->resetScanState();
    }

    public function submitActualQty(): void
    {
        if (! $this->ensureScannedItemAccess()) {
            return;
        }

        if (! $this->scannedItem) {
            return;
        }

        $levels = array_map(fn ($v) => (int) $v, $this->qtyLevels);
        $scanningService = app(StockScanningService::class);

        if ($this->isEditing) {
            $scanningService->updateStock(
                $this->scannedItem,
                $levels,
                Auth::user(),
                $this->editReason ?: 'Koreksi hasil stock'
            );
        } else {
            $scanningService->recordStock($this->scannedItem, $levels, Auth::user());
        }

        $item = $this->scannedItem->fresh();

        Notification::make()
            ->title('Berhasil disimpan')
            ->body("{$item->nama_barang} — aktual: {$item->qty_aktual_display}")
            ->color($item->status === StockSessionItemStatus::Matched ? 'success' : 'warning')
            ->duration(2500)
            ->send();

        $this->resetScanState();
    }

    public function startEditItem(int $itemId): void
    {
        if (! $this->ensureSelectedSessionAccess()) {
            return;
        }

        $session = StockSession::findOrFail($this->selectedSessionId);
        $item = $session->items()->findOrFail($itemId);

        $this->openScannedItem($item);
    }

    protected function rememberScan(StockSessionItem $item): void
    {
        array_unshift($this->recentScans, [
            'name' => $item->nama_barang,
            'code' => $item->kode_barang,
            'status' => $item->status->value,
            'at' => now()->format('H:i'),
        ]);

        $this->recentScans = array_slice($this->recentScans, 0, 5);
    }

    public function completeSession(): void
    {
        if (! $this->ensureSelectedSessionAccess()) {
            return;
        }

        if (! $this->selectedSessionId) {
            return;
        }

        $session = StockSession::findOrFail($this->selectedSessionId);

        try {
            app(StockSessionService::class)->completeSession($session);

            Notification::make()
                ->title('Sesi selesai')
                ->body('Semua item telah diperiksa.')
                ->success()
                ->send();

            $this->backToSessionList();
        } catch (\Exception $e) {
            Notification::make()
                ->title('Belum bisa diselesaikan')
                ->body($e->getMessage())
                ->warning()
                ->send();
        }
    }

    public function startManualFoundItem(): void
    {
        if (! $this->ensureSelectedSessionAccess()) {
            return;
        }

        $this->resetScanState(false);
        $this->notFoundBarcode = '';
        $this->foundQty = '1 PCS';
        $this->dispatch('stock-found-item-ready');
    }

    public function recordFoundItem(): void
    {
        if (! $this->ensureSelectedSessionAccess() || $this->notFoundBarcode === null) {
            return;
        }

        if (blank($this->notFoundBarcode) || blank($this->foundItemName) || (! $this->foundItemMasterId && blank($this->foundQty))) {
            Notification::make()
                ->title('Data belum lengkap')
                ->body('Kode/barcode, nama barang, dan qty fisik wajib diisi.')
                ->warning()
                ->send();

            return;
        }

        $session = StockSession::findOrFail($this->selectedSessionId);
        $qtyDisplay = $this->foundQty;
        $qtyBase = null;

        if ($this->foundItemMasterId) {
            $levels = array_map(fn ($value) => max(0, (int) $value), $this->foundQtyLevels);
            $qtyDisplay = StockScanningService::buildQtyDisplayFromLabels($levels, $this->foundQtyLabels);
            $qtyBase = StockScanningService::calculateBaseQuantity($levels, $this->foundQtyFactors);
        }

        try {
            $data = [
                'kode_barang' => trim($this->notFoundBarcode),
                'nama_barang' => $this->foundItemName ?: $this->notFoundBarcode,
                'qty_aktual_display' => $qtyDisplay,
                'qty_aktual_base' => $qtyBase,
                'suspected_item_master_id' => $this->suspectedItemMasterId,
                'note' => $this->foundNote,
            ];

            if ($this->editingFoundItemId) {
                $foundItem = $session->foundItems()->findOrFail($this->editingFoundItemId);
                app(StockFoundItemService::class)->updateFoundItem($foundItem, $data);
            } else {
                app(StockFoundItemService::class)->recordFoundItem($session, $data, Auth::user());
            }
        } catch (\RuntimeException $e) {
            Notification::make()
                ->title('Barang temuan sudah tercatat')
                ->body($e->getMessage())
                ->warning()
                ->send();

            $this->resetScanState(false);

            return;
        }

        Notification::make()
            ->title('Berhasil disimpan')
            ->body(($this->foundItemName ?: $this->notFoundBarcode) . " — aktual: {$qtyDisplay}")
            ->success()
            ->duration(2500)
            ->send();

        $this->resetScanState(false);
    }

    public function editFoundItem(int $id): void
    {
        if (! $this->ensureSelectedSessionAccess()) {
            return;
        }

        $item = StockFoundItem::query()
            ->where('stock_session_id', $this->selectedSessionId)
            ->findOrFail($id);

        $this->resetScanState(false);
        $this->editingFoundItemId = $item->id;
        $this->notFoundBarcode = $item->kode_barang;
        $this->foundItemName = $item->nama_barang;
        $this->foundQty = $item->qty_aktual_display;
        $this->foundNote = $item->note ?? '';
        $this->dispatch('stock-found-item-ready');
    }

    public function deleteFoundItem(int $id): void
    {
        if (! $this->ensureSelectedSessionAccess()) {
            return;
        }

        $item = StockFoundItem::query()
            ->where('stock_session_id', $this->selectedSessionId)
            ->findOrFail($id);

        app(StockFoundItemService::class)->deleteFoundItem($item);

        Notification::make()->title('Barang temuan dihapus')->success()->send();
    }

    public function getSelectedSession(): ?StockSession
    {
        if (! $this->selectedSessionId) {
            return null;
        }

        $session = StockSession::with('principal')
            ->withCount('foundItems')
            ->find($this->selectedSessionId);

        if (! $session) {
            session()->forget(self::SESSION_KEY);
            $this->selectedSessionId = null;
        }

        return $session;
    }

    public function loadMoreComparison(): void
    {
        $this->comparisonLimit += 10;
    }

    public function loadMoreFoundItems(): void
    {
        $this->foundItemsLimit += 10;
    }

    public function loadMoreMismatchedItems(): void
    {
        $this->mismatchedItemsLimit += 10;
    }

    public function togglePanel(string $panel): void
    {
        match ($panel) {
            'comparison' => $this->showComparisonPanel = ! $this->showComparisonPanel,
            'found' => $this->showFoundItemsPanel = ! $this->showFoundItemsPanel,
            'checked' => $this->showCheckedItemsPanel = ! $this->showCheckedItemsPanel,
            'mismatched' => $this->showMismatchedItemsPanel = ! $this->showMismatchedItemsPanel,
            'pending' => $this->showPendingItemsPanel = ! $this->showPendingItemsPanel,
            default => null,
        };
    }

    public function getFoundItemsData()
    {
        return StockFoundItem::query()
            ->with('foundBy')
            ->where('stock_session_id', $this->selectedSessionId)
            ->orderBy('kode_barang')
            ->limit($this->foundItemsLimit)
            ->get();
    }
    public function getPendingItemsData(): array
    {
        $query = StockSessionItem::query()
            ->with('itemMaster')
            ->where('stock_session_id', $this->selectedSessionId)
            ->where('status', StockSessionItemStatus::Pending->value)
            ->when(trim($this->pendingSearch) !== '', function ($query): void {
                $search = '%' . trim($this->pendingSearch) . '%';
                $query->where(fn ($query) => $query
                    ->where('kode_barang', 'like', $search)
                    ->orWhere('nama_barang', 'like', $search));
            });

        return [
            'items' => (clone $query)->orderBy('kode_barang')->limit(25)->get(),
            'total' => $query->count(),
        ];
    }

    public function getCheckedItemsData(): array
    {
        $query = StockSessionItem::query()
            ->with(['checkedBy', 'itemMaster'])
            ->where('stock_session_id', $this->selectedSessionId)
            ->where('status', '!=', StockSessionItemStatus::Pending->value)
            ->when(trim($this->checkedSearch) !== '', function ($query): void {
                $search = '%' . trim($this->checkedSearch) . '%';
                $query->where(function ($query) use ($search): void {
                    $query->where('kode_barang', 'like', $search)
                        ->orWhere('nama_barang', 'like', $search)
                        ->orWhereHas('checkedBy', fn ($query) => $query->where('name', 'like', $search));
                });
            });

        return [
            'items' => (clone $query)->orderBy('kode_barang')->limit(25)->get(),
            'total' => $query->count(),
        ];
    }

    public function getMismatchedItemsData(): array
    {
        $query = StockSessionItem::query()
            ->with('itemMaster')
            ->where('stock_session_id', $this->selectedSessionId)
            ->where('status', StockSessionItemStatus::Mismatched->value);

        return [
            'items' => (clone $query)->orderBy('kode_barang')->limit($this->mismatchedItemsLimit)->get(),
            'total' => $query->count(),
        ];
    }
    public function getQtyLabels(): array
    {
        if (! $this->scannedItem) {
            return ['CTN', 'PCS'];
        }

        return $this->getQtyLabelsForItem($this->scannedItem);
    }

    public function formatSystemQty(StockSessionItem $item): string
    {
        return app(ReportService::class)->formatBaseQty($item->qty_sistem_base, $item);
    }

    public function usesSeparateCtnPcsCount(): bool
    {
        return (bool) $this->scannedItem?->stockSession?->principal?->separate_ctn_pcs_count;
    }

    public function setSeparateCountMode(string $mode): void
    {
        if (in_array($mode, ['ctn', 'pcs'], true)) {
            $this->separateCountMode = $mode;
        }
    }

    public function getSeparateCountModeIndex(): int
    {
        return $this->getSeparateCountModeIndexes()[0] ?? 0;
    }

    /**
     * @return int[]
     */
    public function getSeparateCountModeIndexes(): array
    {
        if (! $this->scannedItem || $this->separateCountMode === 'ctn') {
            return [0];
        }

        $lastIndex = max(0, count($this->getQtyLabelsForItem($this->scannedItem)) - 1);

        return range(1, $lastIndex);
    }

    public function getActiveModeSystemQty(): string
    {
        if (! $this->scannedItem) {
            return '0';
        }

        $levels = StockScanningService::splitBaseQuantity(
            $this->scannedItem->qty_sistem_base,
            $this->getQtyFactorsForItem($this->scannedItem)
        );
        $labels = $this->getQtyLabelsForItem($this->scannedItem);

        if ($this->separateCountMode === 'ctn') {
            return (int) ($levels[0] ?? 0) . ' ' . ($labels[0] ?? 'CTN');
        }

        return StockScanningService::buildQtyDisplayFromLabels(
            array_intersect_key($levels, array_flip($this->getSeparateCountModeIndexes())),
            $labels
        );
    }

    public function getComparisonDateForSession(StockSession $session): ?string
    {
        return app(ReportService::class)->findPreviousStockDate(
            $session->session_date->toDateString(),
            $session->principal_id,
            $session->branch_id
        );
    }

    public function getComparisonRowsForSession(StockSession $session)
    {
        $comparisonDate = $this->getComparisonDateForSession($session);

        if (! $comparisonDate) {
            return collect();
        }

        return app(ReportService::class)->getSelisihComparison(
            $comparisonDate,
            $session->session_date->toDateString(),
            $session->principal_id,
            $session->branch_id
        );
    }

    protected function prepareQtyLevels(StockSessionItem $item): void
    {
        $factors = $this->getQtyFactorsForItem($item);
        $levelsCount = count($factors) + 1;

        if ($item->qty_aktual_base !== null) {
            $this->qtyLevels = StockScanningService::splitBaseQuantity($item->qty_aktual_base, $factors);
        } else {
            $this->qtyLevels = StockScanningService::splitBaseQuantity($item->qty_sistem_base, $factors);
        }

        $this->qtyLevels = array_pad(array_slice($this->qtyLevels, 0, $levelsCount), $levelsCount, 0);
        $this->editReason = '';
    }

    /**
     * @return array<int, string>
     */
    protected function getQtyLabelsForItem(StockSessionItem $item): array
    {
        $factors = $this->getQtyFactorsForItem($item);
        $levelsCount = count($factors) + 1;

        $labels = $item->itemMaster?->getQtyLabelsArray() ?? [];

        if (empty($labels)) {
            $labels = $item->satuan
                ? array_values(array_filter(array_map('trim', explode('-', $item->satuan))))
                : [];
        }

        if (empty($labels)) {
            $labels = ['CTN', 'PCS'];
        }

        $labels = array_slice($labels, 0, $levelsCount);

        while (count($labels) < $levelsCount) {
            $labels[] = count($labels) === $levelsCount - 1 ? 'PCS' : 'LEVEL ' . (count($labels) + 1);
        }

        if (count($labels) > 1) {
            $labels[array_key_last($labels)] = $labels[array_key_last($labels)] ?: 'PCS';
        }

        return $labels;
    }

    /**
     * @return array<int, int>
     */
    protected function getQtyFactorsForItem(StockSessionItem $item): array
    {
        $factors = $item->itemMaster?->getQtyFactorsArray() ?? [];

        if (! empty($factors)) {
            return $factors;
        }

        return StockScanningService::parseConversionFactors($item->nama_barang);
    }

    protected function ensureSelectedSessionAccess(): bool
    {
        if (! $this->selectedSessionId) {
            return false;
        }

        $session = StockSession::query()
            ->whereKey($this->selectedSessionId)
            ->whereIn('status', [StockSessionStatus::Open, StockSessionStatus::InProgress])
            ->first();

        if (! $session) {
            $this->selectedSessionId = null;
            session()->forget(self::SESSION_KEY);
            $this->resetScanState();

            Notification::make()
                ->title('Sesi tidak valid')
                ->body('Sesi sudah tidak bisa dipakai atau aksesnya tidak sesuai.')
                ->warning()
                ->send();

            return false;
        }

        $user = Auth::user();

        if ($user && ! $user->isCentralAdmin() && $user->branch_id && $session->branch_id !== $user->branch_id) {
            $this->selectedSessionId = null;
            session()->forget(self::SESSION_KEY);
            $this->resetScanState();

            Notification::make()
                ->title('Akses cabang ditolak')
                ->body('Sesi ini bukan cabang Anda.')
                ->danger()
                ->send();

            return false;
        }

        return true;
    }

    protected function ensureScannedItemAccess(): bool
    {
        if (! $this->scannedItem || ! $this->selectedSessionId) {
            return false;
        }

        return $this->ensureSelectedSessionAccess();
    }

    public function resetScanState(bool $releaseLock = true): void
    {
        if ($releaseLock && $this->scannedItem) {
            app(StockScanningService::class)->releaseLock($this->scannedItem, Auth::user());
        }

        $this->barcode = '';
        $this->scannedItem = null;
        $this->lastScannedBarcode = '';
        $this->scanCandidates = [];
        $this->notFoundBarcode = null;
        $this->qtyLevels = [];
        $this->editReason = '';
        $this->isEditing = false;
        $this->pendingSearch = '';
        $this->checkedSearch = '';
        $this->comparisonSearch = '';
        $this->comparisonFilter = 'changed';
        $this->comparisonLimit = 10;
        $this->foundItemsLimit = 10;
        $this->mismatchedItemsLimit = 10;
        $this->showComparisonPanel = false;
        $this->showFoundItemsPanel = false;
        $this->showCheckedItemsPanel = false;
        $this->showMismatchedItemsPanel = false;
        $this->showPendingItemsPanel = false;
        $this->foundItemName = '';
        $this->foundItemMasterId = null;
        $this->foundSystemQtyDisplay = '';
        $this->foundQtyLabels = [];
        $this->foundQtyFactors = [];
        $this->foundQtyLevels = [];
        $this->foundQty = '';
        $this->suspectedItemMasterId = null;
        $this->foundNote = '';
        $this->editingFoundItemId = null;
        $this->dispatch('stock-scan-ready');
    }
}
