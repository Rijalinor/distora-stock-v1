<?php

namespace App\Services;

use App\DTOs\CsvPreviewResult;
use App\DTOs\CsvRowData;
use App\Models\Branch;
use App\Models\ItemMaster;
use App\Models\Principal;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CsvImportService
{
    /**
     * Sanitize a string for safe database storage.
     * Converts non-UTF8 characters and replaces non-breaking spaces.
     */
    public static function sanitizeString(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        // Replace non-breaking space (\xC2\xA0 in UTF-8, \xA0 in Latin-1) with regular space
        $value = str_replace(["\xC2\xA0", "\xA0"], ' ', $value);

        // Remove other non-printable characters except newline/tab
        $value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $value);

        // Ensure valid UTF-8 — strip any remaining invalid sequences
        $value = mb_convert_encoding($value, 'UTF-8', 'UTF-8');

        return trim($value);
    }

    /**
     * Ensure a CSV file is UTF-8 encoded. Converts from common encodings if needed.
     * Returns the path to the (possibly converted) file.
     */
    private function ensureUtf8(string $filePath): string
    {
        $content = file_get_contents($filePath);

        // Remove BOM if present
        $content = str_replace("\xEF\xBB\xBF", '', $content);

        // Detect encoding
        $encoding = mb_detect_encoding($content, ['UTF-8', 'Windows-1252', 'ISO-8859-1', 'ASCII'], true);

        if ($encoding && $encoding !== 'UTF-8') {
            $content = mb_convert_encoding($content, 'UTF-8', $encoding);
            file_put_contents($filePath, $content);
            Log::info("CSV file converted from {$encoding} to UTF-8: {$filePath}");
        }

        return $filePath;
    }

    public function parseAndPreview(string $filePath): CsvPreviewResult
    {
        if (!file_exists($filePath)) {
            throw new \Exception("File not found at: {$filePath}");
        }

        // Ensure file is UTF-8 encoded
        $this->ensureUtf8($filePath);

        $handle = fopen($filePath, 'r');
        if (!$handle) {
            throw new \Exception("Failed to open file: {$filePath}");
        }

        // Read header
        $headers = fgetcsv($handle, 0, ',');
        if (!$headers) {
            fclose($handle);
            throw new \Exception("CSV file is empty or invalid.");
        }

        // Trim and sanitize headers
        $headers = array_map(fn($h) => trim(self::sanitizeString($h) ?? ''), $headers);

        // Required columns validation
        $requiredColumns = [
            'Principle#',
            'Principle Description',
            'Item#',
            'Item Description',
            'Size',
            'OnHand',
            'OnHand Base'
        ];

        foreach ($requiredColumns as $col) {
            if (!in_array($col, $headers)) {
                fclose($handle);
                throw new \Exception("Missing required CSV column: {$col}");
            }
        }

        $rows = [];
        $principalGroups = [];
        $totalRows = 0;

        while (($row = fgetcsv($handle, 0, ',')) !== false) {
            // Skip empty rows or rows that don't match header length
            if (empty($row) || count($row) < count($headers)) {
                continue;
            }

            $data = array_combine($headers, array_slice($row, 0, count($headers)));

            $principalKode = self::sanitizeString(trim($data['Principle#']));
            $principalNama = self::sanitizeString(trim($data['Principle Description']));
            $itemKodeRaw = self::sanitizeString(trim($data['Item#']));
            // Trim trailing dot from item code
            $itemKode = rtrim($itemKodeRaw, '.');
            $itemNama = self::sanitizeString(trim($data['Item Description']));
            $satuan = self::sanitizeString(trim($data['Size'])) ?: null;
            $onHandRaw = $data['OnHand'];
            $onHandBaseRaw = $data['OnHand Base'];

            // Skip if key fields are empty
            if ($principalKode === '' || $itemKode === '') {
                continue;
            }

            // Parse Display Qty
            $qtySistemDisplay = self::parseOnHandDisplay($onHandRaw, $satuan, $itemNama);

            // Parse Base Qty (remove commas, cast to int)
            $qtySistemBase = (int) str_replace(',', '', trim($onHandBaseRaw));

            $rowData = new CsvRowData(
                principalKode: $principalKode,
                principalNama: $principalNama,
                itemKode: $itemKode,
                itemNama: $itemNama,
                satuan: $satuan,
                qtySistemDisplay: $qtySistemDisplay,
                qtySistemBase: $qtySistemBase
            );

            $rows[] = $rowData;
            $totalRows++;

            // Grouping preview statistics
            if (!isset($principalGroups[$principalKode])) {
                $principalGroups[$principalKode] = [
                    'kode' => $principalKode,
                    'nama' => $principalNama,
                    'item_count' => 0
                ];
            }
            $principalGroups[$principalKode]['item_count']++;
        }

        fclose($handle);

        // Sort principal groups by item count descending
        uasort($principalGroups, fn($a, $b) => $b['item_count'] <=> $a['item_count']);

        return new CsvPreviewResult(
            totalRows: $totalRows,
            principalGroups: array_values($principalGroups),
            rows: $rows
        );
    }

    /**
     * Parse multi-level quantity from OnHand string and Size.
     * Example: "0.   2.  0. 0" with "CTN-PCS" -> "2 PCS"
     *
     * @param string $onHand
     * @param string|null $size
     * @return string
     */
    public static function parseOnHandDisplay(string $onHand, ?string $size, ?string $itemName = null): string
    {
        $parts = array_map(fn($p) => (int)trim($p), explode('.', $onHand));
        
        $labels = [];
        if ($size) {
            $labels = array_map(fn($s) => trim($s), explode('-', $size));
        }
        if (empty($labels)) {
            $factors = $itemName ? StockScanningService::parseConversionFactors($itemName) : [];
            $labels = $factors === [1] ? ['PCS'] : ['CTN', 'PCS'];
        }
        
        $displayParts = [];
        foreach ($labels as $index => $label) {
            if (isset($parts[$index]) && $parts[$index] > 0) {
                $displayParts[] = "{$parts[$index]} {$label}";
            }
        }
        
        if (empty($displayParts)) {
            $lastLabel = end($labels) ?: 'PCS';
            return "0 {$lastLabel}";
        }
        
        return implode(' ', $displayParts);
    }

    /**
     * Process an uploaded CSV file: parse, sync master data, return preview.
     *
     * @param string $filePath Absolute path to the CSV file
     * @return CsvPreviewResult
     */
    public function processUpload(string $filePath, ?int $branchId = null): CsvPreviewResult
    {
        $preview = $this->parseAndPreview($filePath);
        $this->syncDatabase($preview->rows, $branchId);

        return $preview;
    }

    /**
     * Resolve the absolute filesystem path for a stored upload.
     */
    public function resolveStoredPath(string $filename): string
    {
        return storage_path('app/private/' . ltrim($filename, '/'));
    }

    /**
     * Synchronize principals and item masters into the database from parsed rows.
     *
     * @param CsvRowData[] $rows
     * @return void
     */
    public function syncDatabase(array $rows, ?int $branchId = null): void
    {
        $branchId ??= Branch::where('kode', 'PUSAT')->value('id');

        DB::transaction(function () use ($rows, $branchId) {
            // 1. Sync Principals
            $principalsMap = []; // Cache to avoid multiple DB lookups
            
            // Extract unique principals
            $uniquePrincipals = [];
            foreach ($rows as $row) {
                $uniquePrincipals[$row->principalKode] = $row->principalNama;
            }

            foreach ($uniquePrincipals as $kode => $nama) {
                $principal = Principal::firstOrNew(['kode' => $kode]);
                $principal->nama = $nama;

                if (! $principal->exists) {
                    $principal->status = true;
                }

                $principal->save();
                $principalsMap[$kode] = $principal->id;
            }

            // 2. Sync Item Masters
            foreach ($rows as $row) {
                $principalId = $principalsMap[$row->principalKode] ?? null;
                if (!$principalId) {
                    continue;
                }

                // We update the name/satuan/principal but keep the barcode unchanged
                $itemMaster = ItemMaster::firstOrNew([
                    'branch_id' => $branchId,
                    'kode_barang' => $row->itemKode,
                ]);

                $itemMaster->nama_barang = $row->itemNama;
                $itemMaster->principal_id = $principalId;
                $itemMaster->satuan = $row->satuan;
                $itemMaster->status = true;

                // Auto-generate qty_structure if not yet set
                if (empty($itemMaster->qty_structure)) {
                    $itemMaster->qty_structure = ItemMaster::generateDefaultQtyStructure($row->itemNama, $row->satuan);
                }

                $itemMaster->save();
            }
        });
    }
}
