<?php

namespace App\Filament\Pages;

use App\Enums\StockSessionItemStatus;
use App\Enums\StockSessionStatus;
use App\Enums\UserRole;
use App\Models\StockSession;
use App\Models\StockSessionItem;
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

    protected static ?string $title = 'Stock Opname';

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

    public string $editReason = '';

    public bool $isEditing = false;

    public string $pendingSearch = '';

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
            ->with('principal')
            ->whereDate('session_date', today())
            ->whereIn('status', [StockSessionStatus::Open, StockSessionStatus::InProgress]);

        if (Auth::user()?->isStockOfficer()) {
            $query->where(function ($q) {
                $q->whereNull('assigned_to')
                    ->orWhere('assigned_to', Auth::id());
            });
        }

        return $query->orderBy('principal_id')->get();
    }

    public function selectSession(int $sessionId): void
    {
        $session = StockSession::with('principal')->findOrFail($sessionId);

        if (Auth::user()?->isStockOfficer()) {
            if ($session->assigned_to && $session->assigned_to !== Auth::id()) {
                Notification::make()
                    ->title('Sesi sudah dikerjakan petugas lain')
                    ->danger()
                    ->send();

                return;
            }

            if (! $session->assigned_to) {
                app(StockSessionService::class)->assignOfficer($session, Auth::user());
            }
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

    public function scanBarcode(?string $barcode = null): void
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
        $items = app(StockScanningService::class)->findItemsByBarcode($session, $this->barcode);

        if ($items->isEmpty()) {
            $this->dispatch('stock-scan-failed');

            Notification::make()
                ->title('Barang tidak ditemukan')
                ->body('Barcode/kode tidak ada di sesi principal ini.')
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
                    'system_qty' => $item->qty_sistem_display,
                    'status' => $item->status->value,
                ])
                ->values()
                ->all();
            $this->barcode = '';
            $this->dispatch('stock-item-scanned');

            Notification::make()
                ->title('Barcode dipakai beberapa item')
                ->body('Pilih kode barang yang sedang dihitung.')
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
        $this->scannedItem = $item;
        $this->scanCandidates = [];
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
            ->title('Sesuai')
            ->body($this->scannedItem->nama_barang . ' — qty sesuai sistem.')
            ->success()
            ->send();

        $this->resetScanState();
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
            ->title($item->status === StockSessionItemStatus::Matched ? 'Sesuai' : 'Selisih tercatat')
            ->body("{$item->nama_barang} — aktual: {$item->qty_aktual_display}")
            ->color($item->status === StockSessionItemStatus::Matched ? 'success' : 'warning')
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

        $this->scannedItem = $item;
        $this->isEditing = $item->status !== StockSessionItemStatus::Pending;
        $this->prepareQtyLevels($item);
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

    public function getSelectedSession(): ?StockSession
    {
        if (! $this->selectedSessionId) {
            return null;
        }

        $session = StockSession::with([
            'principal',
            'items' => fn ($q) => $q->orderBy('status')->orderBy('nama_barang'),
            'items.itemMaster',
        ])
            ->find($this->selectedSessionId);

        if (! $session) {
            session()->forget(self::SESSION_KEY);
            $this->selectedSessionId = null;
        }

        return $session;
    }

    public function getQtyLabels(): array
    {
        if (! $this->scannedItem) {
            return ['CTN', 'PCS'];
        }

        return $this->getQtyLabelsForItem($this->scannedItem);
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
            ->whereDate('session_date', today())
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

        if (Auth::user()?->isStockOfficer() && $session->assigned_to && $session->assigned_to !== Auth::id()) {
            Notification::make()
                ->title('Akses ditolak')
                ->body('Sesi ini dipakai petugas lain.')
                ->danger()
                ->send();

            $this->selectedSessionId = null;
            session()->forget(self::SESSION_KEY);
            $this->resetScanState();

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

    public function resetScanState(): void
    {
        $this->barcode = '';
        $this->scannedItem = null;
        $this->lastScannedBarcode = '';
        $this->scanCandidates = [];
        $this->qtyLevels = [];
        $this->editReason = '';
        $this->isEditing = false;
        $this->pendingSearch = '';
        $this->dispatch('stock-scan-ready');
    }
}
