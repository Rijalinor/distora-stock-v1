<?php

namespace App\Filament\Resources\StockSessions\RelationManagers;

use App\Enums\StockSessionItemStatus;
use App\Models\StockSessionItem;
use App\Services\ReportService;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class ItemsRelationManager extends RelationManager
{
    protected static string $relationship = 'items';

    protected static ?string $title = 'Daftar Item';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('kode_barang')
                    ->label('Kode')
                    ->searchable(),

                TextColumn::make('nama_barang')
                    ->label('Nama Barang')
                    ->searchable()
                    ->limit(40),

                TextColumn::make('qty_sistem_display')
                    ->label('Qty Sistem'),

                TextColumn::make('qty_aktual_display')
                    ->label('Qty Aktual')
                    ->placeholder('-'),

                TextColumn::make('selisih')
                    ->label('Selisih')
                    ->formatStateUsing(fn ($state, StockSessionItem $record) => app(ReportService::class)->formatBaseQty($state, $record))
                    ->placeholder('-')
                    ->color(fn ($state) => match (true) {
                        $state === null => 'gray',
                        $state == 0 => 'success',
                        default => 'danger',
                    }),

                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (StockSessionItemStatus $state): string => match ($state) {
                        StockSessionItemStatus::Pending => 'Belum',
                        StockSessionItemStatus::Matched => 'Sesuai',
                        StockSessionItemStatus::Mismatched => 'Selisih',
                    })
                    ->color(fn (StockSessionItemStatus $state): string => match ($state) {
                        StockSessionItemStatus::Pending => 'gray',
                        StockSessionItemStatus::Matched => 'success',
                        StockSessionItemStatus::Mismatched => 'danger',
                    }),

                TextColumn::make('checkedBy.name')
                    ->label('Petugas')
                    ->placeholder('-'),

                TextColumn::make('checked_at')
                    ->label('Waktu')
                    ->dateTime('H:i')
                    ->placeholder('-'),
            ])
            ->defaultSort('kode_barang')
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        StockSessionItemStatus::Pending->value => 'Belum',
                        StockSessionItemStatus::Matched->value => 'Sesuai',
                        StockSessionItemStatus::Mismatched->value => 'Selisih',
                    ]),
            ]);
    }
}
