<?php

namespace App\Filament\Pages;

use App\Enums\DamageCheckStatus;
use App\Models\Branch;
use App\Models\DamageCheck;
use App\Models\DamageCheckItem;
use App\Models\ItemMaster;
use App\Models\Principal;
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
    public int $itemsLimit = 10;

    /** @var array<int, array{id:int, code:string, name:string, principal:string}> */
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
        $this->selectedCheckId = $check->id;
        session()->put(self::SESSION_KEY, $check->id);
        $this->reset(['barcode', 'scanCandidates']);
        $this->reset(['itemSearch']);
        $this->itemsLimit = 10;
        $this->dispatch('damage-scan-ready');
    }

    public function backToList(): void
    {
        $this->selectedCheckId = null;
        session()->forget(self::SESSION_KEY);
        $this->reset(['barcode', 'scanCandidates']);
        $this->reset(['itemSearch']);
        $this->itemsLimit = 10;
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
            Notification::make()->title('Barcode tidak ditemukan')->body('Periksa barcode, cabang, dan principal pada header.')->danger()->send();
            $this->barcode = '';
            $this->dispatch('damage-scan-failed');

            return;
        }

        if ($items->count() > 1) {
            $this->scanCandidates = $items->map(fn (ItemMaster $item): array => [
                'id' => $item->id,
                'code' => $item->kode_barang,
                'name' => $item->nama_barang,
                'principal' => $item->principal?->nama ?? '-',
            ])->all();
            $this->barcode = '';

            return;
        }

        $this->recordItem($items->first());
    }

    public function chooseCandidate(int $itemId): void
    {
        $check = $this->getSelectedCheck();
        $item = ItemMaster::findOrFail($itemId);

        if ($check) {
            $this->recordItem($item);
        }
    }

    public function changeQuantity(int $itemId, int $change): void
    {
        $item = $this->selectedItem($itemId);
        app(DamageCheckService::class)->updateQuantity($item, $item->qty_rusak_base + $change);
        $this->dispatch('damage-scan-ready');
    }

    public function deleteItem(int $itemId): void
    {
        app(DamageCheckService::class)->deleteItem($this->selectedItem($itemId));
        Notification::make()->title('Salah scan dihapus')->success()->send();
        $this->dispatch('damage-scan-ready');
    }

    public function completeCheck(): void
    {
        $check = $this->getSelectedCheck();

        if (! $check) {
            return;
        }

        try {
            app(DamageCheckService::class)->complete($check, Auth::user());
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

        return $this->accessibleChecks()
            ->with(['branch', 'principal', 'officer', 'checkers'])
            ->withCount(['items', 'checkers'])
            ->withSum('items', 'qty_rusak_base')
            ->find($this->selectedCheckId);
    }

    public function getItemsData(): array
    {
        if (! $this->getSelectedCheck()) {
            return ['items' => collect(), 'total' => 0];
        }

        $query = DamageCheckItem::query()
            ->with(['itemMaster', 'lastScanner'])
            ->where('damage_check_id', $this->selectedCheckId)
            ->when(trim($this->itemSearch) !== '', function ($query): void {
                $search = '%' . trim($this->itemSearch) . '%';
                $query->whereHas('itemMaster', fn ($query) => $query
                    ->where('nama_barang', 'like', $search)
                    ->orWhere('kode_barang', 'like', $search)
                    ->orWhere('barcode', 'like', $search));
            });

        return [
            'items' => (clone $query)->orderByDesc('last_scanned_at')->orderByDesc('id')->limit($this->itemsLimit)->get(),
            'total' => $query->count(),
        ];
    }

    public function loadMoreItems(): void
    {
        $this->itemsLimit += 10;
    }

    public function updatedItemSearch(): void
    {
        $this->itemsLimit = 10;
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
        return Principal::query()->where('status', true)->orderBy('nama')->get();
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

    private function recordItem(ItemMaster $item): void
    {
        $row = app(DamageCheckService::class)->scan($this->getSelectedCheck(), $item, Auth::user());
        $this->reset(['barcode', 'scanCandidates']);
        Notification::make()
            ->title('Scan berhasil')
            ->body("{$row->itemMaster->nama_barang} — total {$row->qty_rusak_display}")
            ->success()
            ->duration(1800)
            ->send();
        $this->dispatch('damage-scan-success');
    }

    private function selectedItem(int $itemId): DamageCheckItem
    {
        return DamageCheckItem::query()
            ->where('damage_check_id', $this->getSelectedCheck()?->id)
            ->findOrFail($itemId);
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
