<?php

namespace App\Filament\Resources\PendingItems\Schemas;

use App\Models\ItemMaster;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Schema;

class PendingItemForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('barcode')->disabled(),
            TextInput::make('item_name')->required()->maxLength(255),
            TextInput::make('unit_label')->required()->maxLength(20),
            TextInput::make('qty_per_scan')->numeric()->minValue(1)->required(),
            Select::make('principal_id')->relationship('principal', 'nama')->searchable()->preload(),
            Select::make('matched_item_master_id')->label('Cocokkan ke Item Master')->options(fn ($record) => ItemMaster::query()->where('branch_id', $record?->branch_id)->orderBy('nama_barang')->pluck('nama_barang', 'id'))->searchable(),
            Textarea::make('notes')->columnSpanFull(),
        ]);
    }
}
