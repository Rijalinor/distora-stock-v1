<?php

namespace App\Filament\Resources\PendingItems\Tables;

use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class PendingItemsTable
{
    public static function configure(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('barcode')->searchable(),
            TextColumn::make('item_name')->label('Nama Barang')->searchable(),
            TextColumn::make('branch.nama')->label('Cabang'),
            TextColumn::make('principal.nama')->label('Principal')->placeholder('-'),
            TextColumn::make('status')->badge()->color(fn ($state) => $state === 'matched' ? 'success' : 'warning'),
            TextColumn::make('creator.name')->label('Ditambahkan Oleh'),
        ])->recordActions([EditAction::make()]);
    }
}
