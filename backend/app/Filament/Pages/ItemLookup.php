<?php

namespace App\Filament\Pages;

use App\Models\ItemMaster;
use App\Services\StockScanningService;
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
        $baseQuery = ItemMaster::query()
            ->with(['principal', 'branch', 'barcodes'])
            ->where('status', true);

        if (! $user->isCentralAdmin()) {
            $baseQuery->where('branch_id', $user->branch_id);
        }

        $items = (clone $baseQuery)
            ->where(fn ($query) => $query
                ->where('barcode', $this->barcode)
                ->orWhere('kode_barang', $this->barcode)
                ->orWhereHas('barcodes', fn ($query) => $query->where('barcode', $this->barcode)))
            ->orderBy('nama_barang')
            ->get();

        if ($items->isEmpty()) {
            $items = (clone $baseQuery)
                ->where('kode_barang', 'like', '%' . $this->barcode . '%')
                ->orderBy('nama_barang')
                ->get();
        }

        $this->items = $items->map(function (ItemMaster $item): array {
            $labels = $item->getQtyLabelsArray();
            $factors = $item->getQtyFactorsArray() ?: StockScanningService::parseConversionFactors($item->nama_barang);

            return [
                'id' => $item->id,
                'code' => $item->kode_barang,
                'barcode' => $item->barcodes->map(fn ($barcode) => "{$barcode->barcode} ({$barcode->unit_label}: {$barcode->qty_base} PCS)")->implode(', ') ?: $item->barcode,
                'name' => $item->nama_barang,
                'principal' => $item->principal?->nama ?? '-',
                'branch' => $item->branch?->nama ?? '-',
                'unit' => $item->satuan ?: implode('-', $labels),
                'calculator' => [
                    'ctn_label' => $labels[0] ?? 'CTN',
                    'pcs_label' => $labels[array_key_last($labels)] ?? 'PCS',
                    'ctn_size' => max(1, (int) ($factors[0] ?? 1)),
                ],
                'structure' => collect($item->qty_structure ?? [])
                    ->map(fn (array $level): string => strtoupper($level['label']) . ' = ' . $level['factor'])
                    ->implode(', '),
            ];
        })->all();

        $this->dispatch($this->items ? 'item-lookup-found' : 'item-lookup-missing');
    }

    public function resetLookup(): void
    {
        $this->reset(['barcode', 'items', 'searched']);
        $this->dispatch('item-lookup-ready');
    }
}
