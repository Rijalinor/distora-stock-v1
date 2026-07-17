<?php

namespace App\Filament\Resources\StockSessions\Pages;

use App\Filament\Resources\StockSessions\StockSessionResource;
use Filament\Resources\Pages\ListRecords;

class ListStockSessions extends ListRecords
{
    protected static string $resource = StockSessionResource::class;

    protected static ?string $title = 'Sesi Stock Opname';
}
