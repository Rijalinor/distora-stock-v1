<?php

namespace App\Filament\Resources\Principals\Schemas;

use App\Models\Principal;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class PrincipalForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([

                TextInput::make('kode')
                    ->required()
                    ->maxLength(20),

                TextInput::make('nama')
                    ->required()
                    ->maxLength(100),

                Select::make('group_principal_id')
                    ->label('Gabung ke Principal')
                    ->helperText('Kosongkan jika principal ini berdiri sendiri.')
                    ->options(fn (?Principal $record): array => Principal::query()
                        ->when($record, fn ($query) => $query->whereKeyNot($record->id))
                        ->whereNull('group_principal_id')
                        ->orderBy('nama')
                        ->pluck('nama', 'id')
                        ->all())
                    ->searchable()
                    ->preload()
                    ->nullable(),

                Toggle::make('status')
                    ->default(true),

            ]);
    }
}
