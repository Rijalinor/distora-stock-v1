<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ItemMaster extends Model
{
    protected $fillable = [
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

    public function stockSessionItems()
    {
        return $this->hasMany(StockSessionItem::class);
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
}
