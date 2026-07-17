<?php

namespace App\Filament\Resources\Principals\Schemas;

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

                Toggle::make('status')
                    ->default(true),

            ]);
    }
}