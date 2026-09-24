<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ItemMaster extends Model
{
    protected $fillable = [
        'branch_id',
        'kode_barang',
        'barcode',
        'nama_barang',
        'principal_id',
        'satuan',
        'qty_structure',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'qty_structure' => 'array',
            'status' => 'boolean',
        ];
    }

    public function principal()
    {
        return $this->belongsTo(Principal::class);
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function setQtyStructureAttribute($value): void
    {
        if (! is_array($value)) {
            $this->attributes['qty_structure'] = null;

            return;
        }

        $structure = collect($value)
            ->filter(fn ($level) => is_array($level) && filled($level['label'] ?? null))
            ->map(fn ($level) => [
                'label' => strtoupper(trim((string) $level['label'])),
                'factor' => max(1, (int) ($level['factor'] ?? 1)),
            ])
            ->values()
            ->all();

        $this->attributes['qty_structure'] = empty($structure) ? null : json_encode($structure);
    }

    public function stockSessionItems()
    {
        return $this->hasMany(StockSessionItem::class);
    }

    public function barcodes()
    {
        return $this->hasMany(ItemBarcode::class);
    }

    public function itemsWithSameBarcode()
    {
        return $this->hasMany(self::class, 'barcode', 'barcode');
    }

    /**
     * @return array<int, string>
     */
    public function getQtyLabelsArray(): array
    {
        $structure = $this->normalizedQtyStructure();

        if (! empty($structure)) {
            return array_values(array_map(
                fn (array $level, int $index): string => trim((string) ($level['label'] ?? 'LEVEL ' . ($index + 1))),
                $structure,
                array_keys($structure)
            ));
        }

        $labels = $this->satuan
            ? array_values(array_filter(array_map('trim', explode('-', $this->satuan))))
            : [];

        return ! empty($labels) ? $labels : ['CTN', 'PCS'];
    }

    /**
     * @return array<int, int>
     */
    public function getQtyFactorsArray(): array
    {
        $structure = $this->normalizedQtyStructure();

        if (! empty($structure)) {
            $factors = [];

            foreach ($structure as $index => $level) {
                if ($index === array_key_last($structure)) {
                    continue;
                }

                $factor = (int) ($level['factor'] ?? 1);
                $factors[] = max(1, $factor);
            }

            return $factors;
        }

        return [];
    }

    /**
     * @return array<int, array{label:string,factor:int|null}>
     */
    protected function normalizedQtyStructure(): array
    {
        $structure = $this->qty_structure ?? [];

        if (! is_array($structure)) {
            return [];
        }

        return array_values(array_filter($structure, static function ($level): bool {
            return is_array($level) && filled(trim((string) ($level['label'] ?? '')));
        }));
    }

    /**
     * Parse and build default qty_structure from itemName and/or satuan (Size).
     * Example: itemName="BAYGON COIL STANDART MAX (1X60)", satuan="CTN-PCS"
     * -> [ ['label' => 'CTN', 'factor' => 60], ['label' => 'PCS', 'factor' => 1] ]
     *
     * @return array<int, array{label: string, factor: int}>
     */
    public static function generateDefaultQtyStructure(?string $itemName, ?string $satuan = null): array
    {
        $itemName = trim((string) $itemName);
        $satuan = trim((string) $satuan);

        // 1. Determine unit labels
        $labels = [];
        if ($satuan !== '') {
            $labels = array_values(array_filter(array_map('trim', explode('-', $satuan))));
            $labels = array_map('strtoupper', $labels);
        }

        // If no labels from satuan, deduce from itemName factor
        $parsedFactors = \App\Services\StockScanningService::parseConversionFactors($itemName);
        if (empty($labels)) {
            if (count($parsedFactors) === 1 && $parsedFactors[0] === 1) {
                $labels = ['PCS'];
            } elseif (count($parsedFactors) === 1) {
                $labels = ['CTN', 'PCS'];
            } elseif (count($parsedFactors) === 2) {
                $labels = ['CTN', 'PCK', 'PCS'];
            } else {
                $labels = ['CTN', 'PCS'];
            }
        }

        // If single unit (e.g. PCS)
        if (count($labels) <= 1) {
            $onlyLabel = $labels[0] ?? 'PCS';
            return [
                ['label' => $onlyLabel, 'factor' => 1],
            ];
        }

        // For multiple units, calculate factor to smallest unit
        // parsedFactors from (1X60) is [60], or (1X12X10) is [12, 10]
        $structure = [];
        $totalLevels = count($labels);

        // Map factors to each level
        for ($i = 0; $i < $totalLevels; $i++) {
            $label = $labels[$i];
            if ($i === $totalLevels - 1) {
                // Smallest unit is always factor 1
                $factor = 1;
            } else {
                // If parsed factors match level count
                if (!empty($parsedFactors)) {
                    $multiplier = 1;
                    for ($j = $i; $j < count($parsedFactors); $j++) {
                        $multiplier *= $parsedFactors[$j];
                    }
                    $factor = max(1, $multiplier);
                } else {
                    $factor = 1;
                }
            }

            $structure[] = [
                'label' => $label,
                'factor' => $factor,
            ];
        }

        return $structure;
    }
}

