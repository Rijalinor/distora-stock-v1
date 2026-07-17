<?php

namespace App\DTOs;

class CsvRowData
{
    public function __construct(
        public string $principalKode,
        public string $principalNama,
        public string $itemKode,
        public string $itemNama,
        public ?string $satuan,
        public string $qtySistemDisplay,
        public int $qtySistemBase
    ) {}
}
