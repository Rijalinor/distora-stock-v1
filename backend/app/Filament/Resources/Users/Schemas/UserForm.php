<?php

namespace App\Filament\Resources\Users\Schemas;

use App\Enums\UserRole;
use App\Models\Branch;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Illuminate\Validation\Rules\Password;

class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required()
                    ->maxLength(100),

                TextInput::make('email')
                    ->required()
                    ->email()
                    ->unique(ignoreRecord: true)
                    ->maxLength(255),

                TextInput::make('password')
                    ->password()
                    ->required(fn (string $operation): bool => $operation === 'create')
                    ->dehydrated(fn ($state): bool => filled($state))
                    ->rule(Password::default()),

                Select::make('role')
                    ->required()
                    ->options(fn () => auth()->user()?->isCentralAdmin()
                        ? [
                            UserRole::Admin->value => 'Admin',
                            UserRole::StockOfficer->value => 'Stock Officer',
                        ]
                        : [
                            UserRole::StockOfficer->value => 'Stock Officer',
                        ])
                    ->default(UserRole::StockOfficer->value),

                Select::make('branch_id')
                    ->label('Cabang')
                    ->options(fn () => Branch::query()->where('status', true)->orderBy('nama')->pluck('nama', 'id'))
                    ->default(fn () => auth()->user()?->branch_id)
                    ->disabled(fn () => auth()->user()?->isAdmin() && ! auth()->user()?->isCentralAdmin())
                    ->dehydrated()
                    ->searchable()
                    ->preload()
                    ->nullable()
                    ->helperText('Admin boleh dikosongkan. Petugas sebaiknya diisi cabangnya.'),
            ]);
    }
}
