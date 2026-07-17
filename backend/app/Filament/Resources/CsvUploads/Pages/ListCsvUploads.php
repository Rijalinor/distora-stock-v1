<?php

namespace App\Filament\Resources\CsvUploads\Pages;

use App\Filament\Resources\CsvUploads\CsvUploadResource;
use Filament\Resources\Pages\ListRecords;
use Filament\Actions\CreateAction;

class ListCsvUploads extends ListRecords
{
    protected static string $resource = CsvUploadResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Upload CSV Baru'),
        ];
    }
}
