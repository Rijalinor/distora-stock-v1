<?php

namespace App\Filament\Resources\CsvUploads\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class CsvUploadForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                FileUpload::make('csv_file')
                    ->label('File CSV dari ERP')
                    ->acceptedFileTypes(['text/csv', 'text/plain', 'application/vnd.ms-excel'])
                    ->required()
                    ->disk('local')
                    ->directory('csv-uploads')
                    ->visibility('private')
                    ->maxSize(10240)
                    ->helperText('Upload file export OnHand dari ERP. Format: Principle#, Item#, OnHand, OnHand Base, dll.'),

                DatePicker::make('upload_date')
                    ->label('Tanggal Stock Opname')
                    ->required()
                    ->default(now())
                    ->native(false),

                TextInput::make('notes')
                    ->label('Catatan')
                    ->maxLength(500),
            ]);
    }
}
