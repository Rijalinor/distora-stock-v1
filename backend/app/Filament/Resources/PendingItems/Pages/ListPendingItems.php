<?php

namespace App\Filament\Resources\PendingItems\Pages;

use App\Filament\Resources\PendingItems\PendingItemResource;
use Filament\Resources\Pages\ListRecords;

class ListPendingItems extends ListRecords
{
    protected static string $resource = PendingItemResource::class;
}
