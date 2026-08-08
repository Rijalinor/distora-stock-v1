<?php

namespace App\Filament\Resources\Principals\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Table;
use App\Models\Principal;


class PrincipalsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('kode')
                    ->searchable(),

                TextColumn::make('nama')
                    ->searchable(),

                TextColumn::make('groupPrincipal.nama')
                    ->label('Gabung ke')
                    ->placeholder('-')
                    ->searchable(),

                IconColumn::make('status')
                    ->getStateUsing(fn (Principal $record): bool => auth()->user()?->isCentralAdmin()
                        ? (bool) $record->status
                        : $record->itemMasters()->where('branch_id', auth()->user()?->branch_id)->where('status', true)->exists())
                    ->boolean(),

                TextColumn::make('created_at')
                    ->dateTime(),
            ])
            ->filters([
                //
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
