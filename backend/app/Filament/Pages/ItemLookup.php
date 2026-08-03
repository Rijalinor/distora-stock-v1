<?php

namespace App\Filament\Pages;

use App\Models\ItemMaster;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;

class ItemLookup extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedMagnifyingGlass;

    protected static ?string $navigationLabel = 'Cek Barang';

    protected static ?string $title = 'Cek Barang';

    protected static string|\UnitEnum|null $navigationGroup = 'Operasional';

    protected static ?int $navigationSort = 2;

    protected string $view = 'filament.pages.item-lookup';

    public string $barcode = '';

    /** @var array<int, array<string, mixed>> */
    public array $items = [];

    public bool $searched = false;

    public static function canAccess(): bool
    {
        $user = Auth::user();

        return $user && ($user->isAdmin() || $user->isStockOfficer());
    }

    public function searchItem(?string $barcode = null): void
    {
        $this->barcode = trim($barcode ?? $this->barcode);
        $this->searched = true;
        $this->items = [];

        if ($this->barcode === '') {
            return;
        }

        $user = Auth::user();
        $query = ItemMaster::query()
            ->with(['principal', 'branch', 'barcodes'])
            ->where('status', true)
            ->where(fn ($query) => $query
                ->where('barcode', $this->barcode)
                ->orWhere('kode_barang', $this->barcode)
                ->orWhereHas('barcodes', fn ($query) => $query->where('barcode', $this->barcode)));

        if (! $user->isCentralAdmin()) {
            $query->where('branch_id', $user->branch_id);
        }

        $this->items = $query->orderBy('nama_barang')->get()->map(fn (ItemMaster $item): array => [
            'id' => $item->id,
            'code' => $item->kode_barang,
            'barcode' => $item->barcodes->map(fn ($barcode) => "{$barcode->barcode} ({$barcode->unit_label}: {$barcode->qty_base} PCS)")->implode(', ') ?: $item->barcode,
            'name' => $item->nama_barang,
            'principal' => $item->principal?->nama ?? '-',
            'branch' => $item->branch?->nama ?? '-',
            'unit' => $item->satuan ?: implode('-', $item->getQtyLabelsArray()),
            'structure' => collect($item->qty_structure ?? [])
                ->map(fn (array $level): string => strtoupper($level['label']) . ' = ' . $level['factor'])
                ->implode(', '),
        ])->all();

        $this->dispatch($this->items ? 'item-lookup-found' : 'item-lookup-missing');
    }

    public function resetLookup(): void
    {
        $this->reset(['barcode', 'items', 'searched']);
        $this->dispatch('item-lookup-ready');
    }
}
