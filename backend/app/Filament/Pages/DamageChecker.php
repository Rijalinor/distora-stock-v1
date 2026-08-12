<?php

namespace App\Filament\Pages;

use App\Enums\DamageCheckStatus;
use App\Models\Branch;
use App\Models\DamageCheck;
use App\Models\DamageCheckItem;
use App\Models\ItemMaster;
use App\Models\Principal;
use App\Models\DamageCheckPendingItem;
use App\Services\DamageCheckService;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;

class DamageChecker extends Page
{
    private const SESSION_KEY = 'damage_checker.selected_check_id';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedExclamationTriangle;
    protected static ?string $navigationLabel = 'Checker Barang Rusak';
    protected static ?string $title = 'Checker Barang Rusak';
    protected static string|\UnitEnum|null $navigationGroup = 'Operasional';
    protected static ?int $navigationSort = 3;
    protected string $view = 'filament.pages.damage-checker';

    protected ?DamageCheck $selectedCheckCache = null;

    public ?int $selectedCheckId = null;
    public string $checkDate = '';
    public ?int $branchId = null;
    public ?int $principalId = null;
    public string $location = '';
    public string $notes = '';
    public string $createJoinPin = '';
    public string $joinPin = '';
    public ?int $pendingJoinCheckId = null;
    public string $legacyJoinPin = '';
    public string $barcode = '';
    public string $itemSearch = '';
    public string $manualSearch = '';
    public int $manualQty = 1;
    /** @var array<int, int|string> */
    public array $bulkQty = [];
    public int $itemsLimit = 5;
    public bool $showPendingForm = false;
    public string $pendingBarcode = '';
    public string $pendingItemName = '';
    public ?int $pendingPrincipalId = null;
    public string $pendingUnitLabel = 'PCS';
    public int $pendingQtyPerScan = 1;
    public string $pendingNotes = '';
    public string $scanFeedback = '';

    /** @var array<int, array{id:int, code:string, name:string, principal:string, qty_base:int, unit:string}> */
    public array $scanCandidates = [];

    public function mount(): void
    {
        $this->checkDate = today()->toDateString();
        $this->branchId = Auth::user()?->branch_id;
        $this->selectedCheckId = session()->get(self::SESSION_KEY);

        if ($this->selectedCheckId && ! $this->getSelectedCheck()) {
            $this->backToList();
        } elseif ($this->selectedCheckId && Auth::user()?->isStockOfficer()) {
            $check = $this->getSelectedCheck();

            if ($check?->status === DamageCheckStatus::Open && ! $check->checkers()->whereKey(Auth::id())->exists()) {
                $this->pendingJoinCheckId = $check->id;
                $this->selectedCheckId = null;
                session()->forget(self::SESSION_KEY);
            }
        }
    }

    public static function canAccess(): bool
    {
        $user = Auth::user();

        return $user && ($user->isAdmin() || $user->isStockOfficer());
    }

    public function createCheck(): void
    {
        $data = $this->validate([
            'checkDate' => ['required', 'date'],
            'branchId' => [Auth::user()?->isCentralAdmin() ? 'required' : 'nullable', 'nullable', 'exists:branches,id'],
            'principalId' => ['nullable', 'exists:principals,id'],
            'location' => ['required', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'createJoinPin' => ['required', 'regex:/^\d{4,6}$/'],
        ]);

        $check = app(DamageCheckService::class)->create([
            'check_date' => $data['checkDate'],
            'branch_id' => $data['branchId'],
            'principal_id' => $data['principalId'],
            'location' => $data['location'],
            'notes' => $data['notes'],
            'join_pin' => $data['createJoinPin'],
        ], Auth::user());

        $this->selectCheck($check->id);
        Notification::make()->title('Header pemeriksaan dibuat')->success()->send();
    }

    public function selectCheck(int $id): void
    {
        $check = $this->accessibleChecks()->whereKey($id)->firstOrFail();

        if (Auth::user()?->isStockOfficer() && $check->status === DamageCheckStatus::Open) {
            if (! $check->checkers()->whereKey(Auth::id())->exists()) {
                $this->pendingJoinCheckId = $check->id;
                $this->joinPin = '';

                return;
            }

            $this->openCheck($check);

            return;
        }

        $this->openCheck($check);
    }

    public function submitJoin(): void
    {
        $this->validate(['joinPin' => ['required', 'regex:/^\d{4,6}$/']]);
        $check = $this->accessibleChecks()->findOrFail($this->pendingJoinCheckId);

        try {
            $check = app(DamageCheckService::class)->join($check, Auth::user(), $this->joinPin);
        } catch (\RuntimeException $e) {
            Notification::make()->title('Tidak dapat bergabung')->body($e->getMessage())->warning()->send();

            return;
        }

        $this->pendingJoinCheckId = null;
        $this->joinPin = '';
        $this->openCheck($check);
        Notification::make()->title('Berhasil bergabung')->success()->send();
    }

    public function cancelJoin(): void
    {
        $this->pendingJoinCheckId = null;
        $this->joinPin = '';
    }

    public function setLegacyJoinPin(): void
    {
        $this->validate(['legacyJoinPin' => ['required', 'regex:/^\d{4,6}$/']]);
        $check = $this->getSelectedCheck();

        if (! $check) {
            return;
        }

        app(DamageCheckService::class)->setJoinPin($check, Auth::user(), $this->legacyJoinPin);
        $this->legacyJoinPin = '';
        Notification::make()->title('PIN bergabung disimpan')->success()->send();
    }

    private function openCheck(DamageCheck $check): void
    {
        $this->forgetSelectedCheckCache();
        $this->selectedCheckId = $check->id;
        session()->put(self::SESSION_KEY, $check->id);
        $this->reset(['barcode', 'scanCandidates']);
        $this->reset(['itemSearch', 'bulkQty']);
        $this->itemsLimit = 5;
        $this->dispatch('damage-scan-ready');
    }

    public function backToList(): void
    {
        $this->forgetSelectedCheckCache();
        $this->selectedCheckId = null;
        session()->forget(self::SESSION_KEY);
        $this->reset(['barcode', 'scanCandidates']);
        $this->reset(['itemSearch', 'bulkQty']);
        $this->scanFeedback = '';
        $this->itemsLimit = 5;
    }

    public function leaveCheck(): void
    {
        $check = $this->getSelectedCheck();

        if ($check && Auth::user()?->isStockOfficer() && $check->status === DamageCheckStatus::Open) {
            app(DamageCheckService::class)->leave($check, Auth::user());
            Notification::make()->title('Anda telah keluar dari sesi')->success()->send();
        }

        $this->backToList();
    }

    public function scanBarcode(?string $barcode = null): void
    {
        $check = $this->getSelectedCheck();

        if ($barcode !== null) {
            $this->barcode = trim($barcode);
        }

        if (! $check || blank($this->barcode)) {
            return;
        }

        $items = app(DamageCheckService::class)->findItems($check, $this->barcode);

        if ($items->isEmpty()) {
            $pending = app(DamageCheckService::class)->findPendingItem($check, $this->barcode);
            if ($pending) {
                $row = app(DamageCheckService::class)->scanPending($check, $pending, Auth::user());
                $this->barcode = '';
                $this->scanFeedback = "{$pending->item_name} - total {$row->qty_rusak_display}";
                $this->forgetSelectedCheckCache();
                $this->dispatch('damage-scan-success');
                return;
            }

            $this->pendingBarcode = $this->barcode;
            $this->barcode = '';
            $this->showPendingForm = true;
            $this->dispatch('damage-scan-failed');

            return;
        }

        if ($items->count() > 1) {
            $this->scanCandidates = $items->map(fn (ItemMaster $item): array => [
                'id' => $item->id,
                'code' => $item->kode_barang,
                'name' => $item->nama_barang,
                'principal' => $item->principal?->nama ?? '-',
                'qty_base' => (int) ($item->scan_qty_base ?? 1),
                'unit' => (string) ($item->scan_unit_label ?? 'PCS'),
            ])->all();
            $this->barcode = '';

            return;
        }

        $this->recordItem($items->first());
    }

    public function createPendingItem(): void
    {
        $data = $this->validate([
            'pendingBarcode' => ['required', 'string', 'max:255'],
            'pendingItemName' => ['required', 'string', 'max:255'],
            'pendingPrincipalId' => ['nullable', 'exists:principals,id'],
            'pendingUnitLabel' => ['required', 'string', 'max:20'],
            'pendingQtyPerScan' => ['required', 'integer', 'min:1', 'max:100000'],
            'pendingNotes' => ['nullable', 'string', 'max:1000'],
        ]);
        $row = app(DamageCheckService::class)->createAndScanPending($this->getSelectedCheck(), [
            'barcode' => $data['pendingBarcode'], 'item_name' => $data['pendingItemName'],
            'principal_id' => $data['pendingPrincipalId'], 'unit_label' => $data['pendingUnitLabel'],
            'qty_per_scan' => $data['pendingQtyPerScan'], 'notes' => $data['pendingNotes'],
        ], Auth::user());
        $this->cancelPendingItem();
        $this->forgetSelectedCheckCache();
        Notification::make()->title('Barang pending ditambahkan')->body("Langsung tercatat {$row->qty_rusak_display} dan dapat discan checker lain.")->success()->send();
    }

    public function cancelPendingItem(): void
    {
        $this->reset(['showPendingForm', 'pendingBarcode', 'pendingItemName', 'pendingPrincipalId', 'pendingNotes']);
        $this->pendingUnitLabel = 'PCS';
        $this->pendingQtyPerScan = 1;
    }

    public function changePendingQuantity(int $itemId, int $change): void
    {
        $item = $this->selectedPendingItem($itemId);
        app(DamageCheckService::class)->updatePendingQuantity($item, $item->qty_rusak_base + $change);
        $this->forgetSelectedCheckCache();
    }

    public function addBulkPendingQuantity(int $itemId): void
    {
        $quantity = max(1, (int) ($this->bulkQty['pending-' . $itemId] ?? 1));
        $item = $this->selectedPendingItem($itemId);

        app(DamageCheckService::class)->updatePendingQuantity($item, $item->qty_rusak_base + $quantity);
        $this->bulkQty['pending-' . $itemId] = 1;
        $this->forgetSelectedCheckCache();
    }

    public function deletePendingItem(int $itemId): void
    {
        app(DamageCheckService::class)->deletePendingItem($this->selectedPendingItem($itemId));
        $this->forgetSelectedCheckCache();
    }

    public function chooseCandidate(int $itemId): void
    {
        $check = $this->getSelectedCheck();
        $item = ItemMaster::findOrFail($itemId);
        $candidate = collect($this->scanCandidates)->firstWhere('id', $itemId);

        if ($check) {
            $this->recordItem($item, (int) ($candidate['qty_base'] ?? 1));
        }
    }

    public function changeQuantity(int $itemId, int $change): void
    {
        $item = $this->selectedItem($itemId);
        app(DamageCheckService::class)->updateQuantity($item, $item->qty_rusak_base + $change);
        $this->forgetSelectedCheckCache();
        $this->dispatch('damage-scan-ready');
    }

    public function addBulkQuantity(int $itemId): void
    {
        $quantity = max(1, (int) ($this->bulkQty[$itemId] ?? 1));
        $item = $this->selectedItem($itemId);

        app(DamageCheckService::class)->updateQuantity($item, $item->qty_rusak_base + $quantity);
        $this->bulkQty[$itemId] = 1;
        $this->forgetSelectedCheckCache();
        $this->dispatch('damage-scan-ready');
    }

    public function deleteItem(int $itemId): void
    {
        app(DamageCheckService::class)->deleteItem($this->selectedItem($itemId));
        $this->forgetSelectedCheckCache();
        Notification::make()->title('Salah scan dihapus')->success()->send();
        $this->dispatch('damage-scan-ready');
    }

    public function addManualItem(int $itemId): void
    {
        $this->addBulkQuantity($itemId);
    }

    public function completeCheck(): void
    {
        $check = $this->getSelectedCheck();

        if (! $check) {
            return;
        }

        try {
            app(DamageCheckService::class)->complete($check, Auth::user());
            $this->forgetSelectedCheckCache();
            Notification::make()->title('Pemeriksaan selesai')->success()->send();
            $this->backToList();
        } catch (\RuntimeException $e) {
            Notification::make()->title('Belum bisa diselesaikan')->body($e->getMessage())->warning()->send();
        }
    }

    public function getSelectedCheck(): ?DamageCheck
    {
        if (! $this->selectedCheckId) {
            return null;
        }

        if ($this->selectedCheckCache?->id === $this->selectedCheckId) {
            return $this->selectedCheckCache;
        }

        return $this->selectedCheckCache = $this->accessibleChecks()
            ->with(['branch', 'principal', 'officer', 'checkers'])
            ->withCount(['items', 'checkers'])
            ->withSum('items', 'qty_rusak_base')
            ->withCount('pendingItems')
            ->withSum('pendingItems', 'qty_rusak_base')
            ->find($this->selectedCheckId);
    }

    public function getItemsData(): array
    {
        if (! $this->selectedCheckId) {
            return ['items' => collect(), 'latestItem' => null, 'total' => 0];
        }

        $query = DamageCheckItem::query()
            ->with(['itemMaster.principal', 'lastScanner'])
            ->where('damage_check_id', $this->selectedCheckId)
            ->when(trim($this->itemSearch) !== '', function ($query): void {
                $search = '%' . trim($this->itemSearch) . '%';
                $query->whereHas('itemMaster', fn ($query) => $query
                    ->where('nama_barang', 'like', $search)
                    ->orWhere('kode_barang', 'like', $search)
                    ->orWhere('barcode', 'like', $search)
                    ->orWhereHas('barcodes', fn ($query) => $query->where('barcode', 'like', $search)));
            });

        return [
            'items' => (clone $query)
                ->select('damage_check_items.*')
                ->join('item_masters', 'item_masters.id', '=', 'damage_check_items.item_master_id')
                ->leftJoin('principals', 'principals.id', '=', 'item_masters.principal_id')
                ->orderBy('principals.nama')
                ->orderBy('item_masters.kode_barang')
                ->orderBy('damage_check_items.id')
                ->limit($this->itemsLimit)
                ->get(),
            'latestItem' => (clone $query)->orderByDesc('last_scanned_at')->orderByDesc('id')->first(),
            'total' => $query->count(),
        ];
    }

    public function getPendingItemsData()
    {
        return DamageCheckPendingItem::query()
            ->with(['pendingItem.principal', 'lastScanner'])
            ->where('damage_check_id', $this->selectedCheckId)
            ->orderByDesc('last_scanned_at')->get();
    }

    public function getManualItemCandidates()
    {
        return collect();
    }

    public function loadMoreItems(): void
    {
        $this->itemsLimit += 5;
    }

    public function updatedItemSearch(): void
    {
        $this->itemsLimit = 5;
    }

    public function getRecentChecks()
    {
        return $this->accessibleChecks()
            ->with(['branch', 'principal', 'officer'])
            ->withCount('items')
            ->latest('id')
            ->limit(15)
            ->get();
    }

    public function getBranches()
    {
        return Branch::query()->where('status', true)->orderBy('nama')->get();
    }

    public function getPrincipals()
    {
        return Principal::query()
            ->where('status', true)
            ->when(! Auth::user()?->isCentralAdmin(), fn ($query) => $query->forBranch(Auth::user()?->branch_id))
            ->orderBy('nama')->get();
    }

    public function canCompleteSelectedCheck(): bool
    {
        $check = $this->getSelectedCheck();
        $user = Auth::user();

        return $check && $user && ($user->isAdmin() || (int) $check->officer_id === (int) $user->id);
    }

    public function canViewSelectedPin(): bool
    {
        $check = $this->getSelectedCheck();

        return $check && (int) $check->officer_id === (int) Auth::id();
    }

    private function recordItem(ItemMaster $item, ?int $scanQtyBase = null): void
    {
        $scanQtyBase ??= (int) ($item->scan_qty_base ?? 1);
        $row = app(DamageCheckService::class)->scan($this->getSelectedCheck(), $item, Auth::user(), $scanQtyBase);
        $this->reset(['barcode', 'scanCandidates']);
        $this->scanFeedback = "{$row->itemMaster->nama_barang} - total {$row->qty_rusak_display}";
        $this->forgetSelectedCheckCache();
        $this->dispatch('damage-scan-success');
    }

    protected function forgetSelectedCheckCache(): void
    {
        $this->selectedCheckCache = null;
    }

    private function selectedItem(int $itemId): DamageCheckItem
    {
        return DamageCheckItem::query()
            ->where('damage_check_id', $this->getSelectedCheck()?->id)
            ->findOrFail($itemId);
    }

    private function selectedPendingItem(int $itemId): DamageCheckPendingItem
    {
        return DamageCheckPendingItem::where('damage_check_id', $this->getSelectedCheck()?->id)->findOrFail($itemId);
    }

    private function accessibleChecks()
    {
        $user = Auth::user();

        return DamageCheck::query()
            ->when(! $user->isCentralAdmin(), fn ($query) => $query->where('branch_id', $user->branch_id))
            ->when($user->isStockOfficer(), fn ($query) => $query->where(fn ($query) => $query
                ->where('status', DamageCheckStatus::Open)
                ->orWhere('officer_id', $user->id)
                ->orWhereHas('checkers', fn ($query) => $query->whereKey($user->id))));
    }
}


