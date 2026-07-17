<?php

namespace App\DTOs;

class CsvPreviewResult
{
    /**
     * @param int $totalRows
     * @param array $principalGroups Array of array with structure ['kode' => string, 'nama' => string, 'item_count' => int]
     * @param CsvRowData[] $rows
     */
    public function __construct(
        public int $totalRows,
        public array $principalGroups,
        public array $rows
    ) {}
}
