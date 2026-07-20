<?php

namespace App\Services;

use App\Models\ItemMaster;
use App\Models\Principal;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class ItemMasterBackupService
{
    public function buildCsv(): string
    {
        $lines = [];
        $lines[] = $this->row([
            'principal_kode',
            'principal_nama',
            'kode_barang',
            'barcode',
            'nama_barang',
            'satuan',
            'qty_labels',
            'qty_factors',
            'qty_structure_json',
            'status',
            'updated_at',
        ]);

        ItemMaster::query()
            ->with('principal')
            ->orderBy('kode_barang')
            ->chunk(500, function ($items) use (&$lines): void {
                foreach ($items as $item) {
                    $lines[] = $this->row([
                        $item->principal?->kode ?? '',
                        $item->principal?->nama ?? '',
                        $item->kode_barang,
                        $item->barcode,
                        $item->nama_barang,
                        $item->satuan,
                        implode('-', $item->getQtyLabelsArray()),
                        implode('-', $item->getQtyFactorsArray()),
                        json_encode($item->qty_structure ?? [], JSON_UNESCAPED_SLASHES),
                        $item->status ? 'active' : 'inactive',
                        $item->updated_at?->format('Y-m-d H:i:s'),
                    ]);
                }
            });

        return implode("\n", $lines) . "\n";
    }

    /**
     * @return array{created: int, updated: int, skipped: int}
     */
    public function restoreCsv(string|UploadedFile $file): array
    {
        $path = $file instanceof UploadedFile
            ? $file->getRealPath()
            : Storage::disk('local')->path($file);

        $handle = fopen($path, 'rb');

        if (! $handle) {
            throw ValidationException::withMessages(['backup_file' => 'File backup tidak bisa dibaca.']);
        }

        $headers = array_map('trim', fgetcsv($handle) ?: []);
        $required = ['principal_kode', 'principal_nama', 'kode_barang', 'barcode', 'nama_barang', 'satuan'];
        $missing = array_diff($required, $headers);

        if ($missing) {
            fclose($handle);

            throw ValidationException::withMessages([
                'backup_file' => 'Format backup tidak sesuai. Kolom hilang: ' . implode(', ', $missing),
            ]);
        }

        $stats = ['created' => 0, 'updated' => 0, 'skipped' => 0];

        DB::transaction(function () use ($handle, $headers, &$stats): void {
            while (($values = fgetcsv($handle)) !== false) {
                $row = array_combine($headers, array_slice(array_pad($values, count($headers), ''), 0, count($headers)));

                if (! $row || blank($row['kode_barang'] ?? null) || blank($row['principal_kode'] ?? null)) {
                    $stats['skipped']++;
                    continue;
                }

                $principal = Principal::firstOrCreate(
                    ['kode' => trim($row['principal_kode'])],
                    [
                        'nama' => trim($row['principal_nama'] ?: $row['principal_kode']),
                        'status' => true,
                    ]
                );

                if (filled($row['principal_nama'] ?? null) && $principal->nama !== trim($row['principal_nama'])) {
                    $principal->update(['nama' => trim($row['principal_nama'])]);
                }

                $item = ItemMaster::firstOrNew(['kode_barang' => trim($row['kode_barang'])]);
                $exists = $item->exists;

                $item->fill([
                    'principal_id' => $principal->id,
                    'barcode' => blank($row['barcode'] ?? null) ? null : trim($row['barcode']),
                    'nama_barang' => trim($row['nama_barang'] ?: $row['kode_barang']),
                    'satuan' => blank($row['satuan'] ?? null) ? null : trim($row['satuan']),
                    'qty_structure' => $this->restoreQtyStructure($row),
                    'status' => ! in_array(strtolower(trim((string) ($row['status'] ?? 'active'))), ['0', 'false', 'inactive', 'nonaktif'], true),
                ])->save();

                $stats[$exists ? 'updated' : 'created']++;
            }
        });

        fclose($handle);

        return $stats;
    }

    protected function row(array $values): string
    {
        return implode(',', array_map(
            fn ($value) => '"' . str_replace('"', '""', (string) $value) . '"',
            $values
        ));
    }

    protected function restoreQtyStructure(array $row): ?array
    {
        if (filled($row['qty_structure_json'] ?? null)) {
            $decoded = json_decode($row['qty_structure_json'], true);

            if (is_array($decoded)) {
                return $decoded;
            }
        }

        $labels = array_values(array_filter(array_map('trim', explode('-', (string) ($row['qty_labels'] ?? '')))));
        $factors = array_map('intval', array_filter(array_map('trim', explode('-', (string) ($row['qty_factors'] ?? '')))));

        if (empty($labels)) {
            return null;
        }

        return array_map(
            fn (string $label, int $index): array => [
                'label' => $label,
                'factor' => $factors[$index] ?? 1,
            ],
            $labels,
            array_keys($labels)
        );
    }
}
