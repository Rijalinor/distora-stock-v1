<?php

namespace App\Filament\Resources\ItemMasters\Schemas;

use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ViewField;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\Select;
use Filament\Support\Icons\Heroicon;
use Filament\Schemas\Schema;

class ItemMasterForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('kode_barang')
                    ->required(),
                TextInput::make('barcode')
                    ->default(null)
                    ->suffixAction(
                        Action::make('scanBarcode')
                            ->label('Scan Kamera')
                            ->icon(Heroicon::OutlinedQrCode)
                            ->extraAttributes([
                                'x-on:click.prevent' => "window.dispatchEvent(new CustomEvent('item-master-open-barcode-scanner'))",
                            ]),
                    ),
                ViewField::make('barcode_scanner')
                    ->view('filament.forms.components.barcode-scanner'),
                TextInput::make('nama_barang')
                    ->required(),
                Select::make('principal_id')
                    ->relationship('principal', 'nama')
                    ->searchable()
                    ->preload()
                    ->required()
                    ->label('Principal'),
                TextInput::make('satuan')
                    ->required(),
                Toggle::make('status')
                    ->required(),
            ]);
    }
}
