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

    /** @var array<int, int|string> */
    public array $calculatorPcs = [];

    /** @var array<int, string> */
    public array $calculatorResults = [];

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
        $this->calculatorPcs = [];
        $this->calculatorResults = [];

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
                ->orWhere('kode_barang', 'like', '%' . $this->barcode . '%')
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
            'qty_labels' => $item->getQtyLabelsArray(),
            'qty_factors' => $item->getQtyFactorsArray() ?: StockScanningService::parseConversionFactors($item->nama_barang),
            'structure' => collect($item->qty_structure ?? [])
                ->map(fn (array $level): string => strtoupper($level['label']) . ' = ' . $level['factor'])
                ->implode(', '),
        ])->all();

        $this->dispatch($this->items ? 'item-lookup-found' : 'item-lookup-missing');
    }

    public function updatedCalculatorPcs($value, string $key): void
    {
        $item = collect($this->items)->firstWhere('id', (int) $key);

        if (! $item) {
            return;
        }

        $pcs = max(0, (int) $value);
        $factor = max(1, (int) ($item['qty_factors'][0] ?? 1));
        $ctnLabel = $item['qty_labels'][0] ?? 'CTN';
        $pcsLabel = $item['qty_labels'][array_key_last($item['qty_labels'])] ?? 'PCS';

        if ($pcs === 0) {
            $this->calculatorResults[$key] = "0 {$pcsLabel}";

            return;
        }

        if ($factor <= 1) {
            $this->calculatorResults[$key] = "{$pcs} {$pcsLabel}";

            return;
        }

        $ctn = intdiv($pcs, $factor);
        $remainder = $pcs % $factor;
        $parts = [];

        if ($ctn > 0) {
            $parts[] = "{$ctn} {$ctnLabel}";
        }

        if ($remainder > 0) {
            $parts[] = "{$remainder} {$pcsLabel}";
        }

        $this->calculatorResults[$key] = implode(' ', $parts);
    }

    public function resetLookup(): void
    {
        $this->reset(['barcode', 'items', 'searched', 'calculatorPcs', 'calculatorResults']);
        $this->dispatch('item-lookup-ready');
    }
}
