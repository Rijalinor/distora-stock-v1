<?php

namespace App\Services;

use App\Models\ItemMaster;
use App\Models\Branch;
use App\Models\Principal;
use App\Models\ItemBarcode;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class ItemMasterBackupService
{
    public function buildCsv(?int $branchId = null): string
    {
        $lines = [];
        $lines[] = $this->row([
            'branch_kode',
            'branch_nama',
            'principal_kode',
            'principal_nama',
            'kode_barang',
            'barcode',
            'barcodes_json',
            'nama_barang',
            'satuan',
            'qty_labels',
            'qty_factors',
            'qty_structure_json',
            'status',
            'updated_at',
        ]);

        ItemMaster::query()
            ->with(['branch', 'principal', 'barcodes'])
            ->when($branchId, fn ($query) => $query->where('branch_id', $branchId))
            ->orderBy('branch_id')
            ->orderBy('kode_barang')
            ->chunk(500, function ($items) use (&$lines): void {
                foreach ($items as $item) {
                    $lines[] = $this->row([
                        $this->excelText($item->branch?->kode ?? ''),
                        $item->branch?->nama ?? '',
                        $this->excelText($item->principal?->kode ?? ''),
                        $item->principal?->nama ?? '',
                        $this->excelText($item->kode_barang),
                        $this->excelText($item->barcode),
                        json_encode($item->barcodes->map->only(['barcode', 'unit_label', 'qty_base', 'is_primary'])->values(), JSON_UNESCAPED_SLASHES),
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
     * @return array{created: int, updated: int, skipped: int, examples: array<int, array<string, string>>}
     */
    public function previewCsv(string|UploadedFile $file, ?int $branchId = null): array
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

        $forcedBranch = $branchId ? Branch::findOrFail($branchId) : null;
        $branchIds = Branch::query()->pluck('id', 'kode');
        $existingItems = ItemMaster::query()
            ->get(['branch_id', 'kode_barang'])
            ->mapWithKeys(fn (ItemMaster $item) => ["{$item->branch_id}|{$item->kode_barang}" => true]);
        $preview = ['created' => 0, 'updated' => 0, 'skipped' => 0, 'examples' => []];

        while (($values = fgetcsv($handle)) !== false) {
            $row = array_combine($headers, array_slice(array_pad($values, count($headers), ''), 0, count($headers)));
            $row = array_map(fn ($value) => $this->restoreCellValue((string) $value), $row ?: []);

            if (! $row || blank($row['kode_barang'] ?? null) || blank($row['principal_kode'] ?? null)) {
                $preview['skipped']++;
                continue;
            }

            $branchCode = $forcedBranch?->kode ?? (trim((string) ($row['branch_kode'] ?? '')) ?: 'PUSAT');
            $targetBranchId = $forcedBranch?->id ?? $branchIds->get($branchCode);
            $code = trim($row['kode_barang']);
            $action = $targetBranchId && $existingItems->has("{$targetBranchId}|{$code}") ? 'updated' : 'created';
            $preview[$action]++;

            if (count($preview['examples']) < 5) {
                $preview['examples'][] = [
                    'action' => $action === 'created' ? 'Baru' : 'Diperbarui',
                    'branch' => $branchCode,
                    'code' => $code,
                    'name' => trim($row['nama_barang'] ?: $code),
                ];
            }
        }

        fclose($handle);

        return $preview;
    }
    /**
     * @return array{created: int, updated: int, skipped: int}
     */
    public function restoreCsv(string|UploadedFile $file, ?int $branchId = null): array
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

        DB::transaction(function () use ($handle, $headers, $branchId, &$stats): void {
            while (($values = fgetcsv($handle)) !== false) {
                $row = array_combine($headers, array_slice(array_pad($values, count($headers), ''), 0, count($headers)));

                $row = array_map(fn ($value) => $this->restoreCellValue((string) $value), $row ?: []);

                if (! $row || blank($row['kode_barang'] ?? null) || blank($row['principal_kode'] ?? null)) {
                    $stats['skipped']++;
                    continue;
                }

                $branch = $this->resolveBranch($row, $branchId);

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

                $item = ItemMaster::firstOrNew([
                    'branch_id' => $branch->id,
                    'kode_barang' => trim($row['kode_barang']),
                ]);
                $exists = $item->exists;

                $item->fill([
                    'principal_id' => $principal->id,
                    'barcode' => blank($row['barcode'] ?? null) ? null : trim($row['barcode']),
                    'nama_barang' => trim($row['nama_barang'] ?: $row['kode_barang']),
                    'satuan' => blank($row['satuan'] ?? null) ? null : trim($row['satuan']),
                    'qty_structure' => $this->restoreQtyStructure($row),
                    'status' => ! in_array(strtolower(trim((string) ($row['status'] ?? 'active'))), ['0', 'false', 'inactive', 'nonaktif'], true),
                ])->save();

                $this->restoreBarcodes($item, $row);

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

    protected function excelText(?string $value): string
    {
        if (blank($value)) {
            return '';
        }

        return '="' . str_replace('"', '""', (string) $value) . '"';
    }

    protected function restoreCellValue(string $value): string
    {
        $value = trim($value);

        if (preg_match('/^="(.*)"$/s', $value, $matches)) {
            return str_replace('""', '"', $matches[1]);
        }

        return $value;
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

    protected function restoreBarcodes(ItemMaster $item, array $row): void
    {
        if (array_key_exists('barcodes_json', $row)) {
            $barcodes = json_decode((string) $row['barcodes_json'], true);

            if (is_array($barcodes)) {
                $item->barcodes()->delete();

                foreach ($barcodes as $barcode) {
                    if (blank($barcode['barcode'] ?? null)) {
                        continue;
                    }

                    ItemBarcode::create([
                        'item_master_id' => $item->id,
                        'branch_id' => $item->branch_id,
                        'barcode' => trim((string) $barcode['barcode']),
                        'unit_label' => strtoupper(trim((string) ($barcode['unit_label'] ?? 'PCS'))),
                        'qty_base' => max(1, (int) ($barcode['qty_base'] ?? 1)),
                        'is_primary' => (bool) ($barcode['is_primary'] ?? false),
                    ]);
                }

                return;
            }
        }

        if (filled($item->barcode) && ! $item->barcodes()->where('barcode', $item->barcode)->exists()) {
            ItemBarcode::create([
                'item_master_id' => $item->id,
                'branch_id' => $item->branch_id,
                'barcode' => $item->barcode,
                'unit_label' => 'PCS',
                'qty_base' => 1,
                'is_primary' => true,
            ]);
        }
    }

    protected function resolveBranch(array $row, ?int $branchId = null): Branch
    {
        if ($branchId) {
            return Branch::findOrFail($branchId);
        }

        $branchKode = trim((string) ($row['branch_kode'] ?? ''));

        if ($branchKode !== '') {
            return Branch::firstOrCreate(
                ['kode' => $branchKode],
                [
                    'nama' => trim((string) ($row['branch_nama'] ?? '')) ?: $branchKode,
                    'status' => true,
                ]
            );
        }

        return Branch::firstOrCreate(
            ['kode' => 'PUSAT'],
            ['nama' => 'Pusat', 'status' => true]
        );
    }
}
