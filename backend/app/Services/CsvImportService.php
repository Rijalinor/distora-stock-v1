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
     * Parse the CSV file and return a preview result.
     *
     * @param string $filePath
     * @return CsvPreviewResult
     * @throws \Exception
     */
    public function parseAndPreview(string $filePath): CsvPreviewResult
    {
        if (!file_exists($filePath)) {
            throw new \Exception("File not found at: {$filePath}");
        }

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

        // Trim headers
        $headers = array_map(fn($h) => trim($h), $headers);

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

            $principalKode = trim($data['Principle#']);
            $principalNama = trim($data['Principle Description']);
            $itemKodeRaw = trim($data['Item#']);
            // Trim trailing dot from item code
            $itemKode = rtrim($itemKodeRaw, '.');
            $itemNama = trim($data['Item Description']);
            $satuan = trim($data['Size']) ?: null;
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
                $principal = Principal::updateOrCreate(
                    ['kode' => $kode],
                    ['nama' => $nama, 'status' => true]
                );
                $principalsMap[$kode] = $principal->id;
            }

            // 2. Sync Item Masters
            foreach ($rows as $row) {
                $principalId = $principalsMap[$row->principalKode] ?? null;
                if (!$principalId) {
                    continue;
                }

                // We update the name/satuan/principal but keep the barcode unchanged
                ItemMaster::updateOrCreate(
                    ['branch_id' => $branchId, 'kode_barang' => $row->itemKode],
                    [
                        'nama_barang' => $row->itemNama,
                        'principal_id' => $principalId,
                        'satuan' => $row->satuan,
                        'status' => true
                    ]
                );
            }
        });
    }
}
