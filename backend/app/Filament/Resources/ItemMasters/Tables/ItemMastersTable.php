<?php

namespace App\Filament\Resources\ItemMasters\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use App\Models\ItemMaster;

class ItemMastersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('kode_barang')
                    ->searchable(),
                TextColumn::make('barcode')
                    ->searchable(),
                TextColumn::make('items_with_same_barcode_count')
                    ->label('Duplikat')
                    ->badge()
                    ->state(fn ($record) => blank($record->barcode) ? '-' : ItemMaster::query()
                        ->where('branch_id', $record->branch_id)
                        ->where('barcode', $record->barcode)
                        ->count())
                    ->color(fn ($state) => is_numeric($state) && $state > 1 ? 'warning' : 'gray')
                    ->formatStateUsing(fn ($state) => is_numeric($state) && $state > 1 ? "{$state} item" : '-'),
                TextColumn::make('nama_barang')
                    ->searchable(),
                TextColumn::make('branch.nama')
                    ->label('Cabang')
                    ->placeholder('-')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('principal.nama')
                    ->label('Principal')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('satuan')
                    ->searchable(),
                IconColumn::make('status')
                    ->boolean(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Filter::make('duplicate_barcode')
                    ->label('Barcode Duplikat')
                    ->query(fn (Builder $query): Builder => $query
                        ->whereNotNull('barcode')
                        ->where('barcode', '!=', '')
                        ->whereExists(function ($subquery): void {
                            $subquery
                                ->select(DB::raw(1))
                                ->from('item_masters as duplicates')
                                ->whereColumn('duplicates.barcode', 'item_masters.barcode')
                                ->whereColumn('duplicates.branch_id', 'item_masters.branch_id')
                                ->whereColumn('duplicates.id', '!=', 'item_masters.id');
                        })),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
