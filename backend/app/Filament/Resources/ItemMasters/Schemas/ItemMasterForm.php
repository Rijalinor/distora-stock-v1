<?php

namespace App\Filament\Resources\ItemMasters\Schemas;

use App\Models\Branch;
use Filament\Actions\Action;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Schemas\Components\View;

class ItemMasterForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('kode_barang')
                    ->required(),
                TextInput::make('nama_barang')
                    ->required(),
                Select::make('branch_id')
                    ->label('Cabang')
                    ->options(fn () => Branch::query()->where('status', true)->orderBy('nama')->pluck('nama', 'id'))
                    ->default(fn () => auth()->user()?->branch_id)
                    ->disabled(fn () => auth()->user()?->isAdmin() && ! auth()->user()?->isCentralAdmin())
                    ->dehydrated()
                    ->searchable()
                    ->preload()
                    ->required(),
                Select::make('principal_id')
                    ->relationship('principal', 'nama')
                    ->searchable()
                    ->preload()
                    ->required()
                    ->label('Principal'),
                TextInput::make('satuan')
                    ->required(),
                Repeater::make('qty_structure')
                    ->label('Struktur Qty')
                    ->helperText('Pilih satuan dari terbesar ke terkecil. Contoh: CTN faktor 12, lalu PCS faktor 1.')
                    ->default([])
                    ->minItems(1)
                    ->addActionLabel('Tambah satuan')
                    ->reorderable()
                    ->columnSpanFull()
                    ->columns(2)
                    ->schema([
                        Select::make('label')
                            ->label('Satuan')
                            ->options([
                                'CTN' => 'CTN',
                                'PCK' => 'PCK',
                                'DOZ' => 'DOZ',
                                'PCS' => 'PCS',
                            ])
                            ->native(false)
                            ->live()
                            ->afterStateUpdated(function (?string $state, Set $set): void {
                                if ($state === 'PCS') {
                                    $set('factor', 1);
                                }
                            })
                            ->required(),
                        TextInput::make('factor')
                            ->label('Isi ke satuan terkecil')
                            ->numeric()
                            ->minValue(1)
                            ->default(fn (Get $get): int => $get('label') === 'PCS' ? 1 : 1)
                            ->formatStateUsing(fn ($state, Get $get): int => $get('label') === 'PCS' ? 1 : max(1, (int) ($state ?: 1)))
                            ->required()
                            ->helperText('Contoh: CTN isi 12 PCS, maka isi 12. Untuk PCS isi 1.'),
                    ]),
                Repeater::make('barcodes')
                    ->relationship()
                    ->label('Barcode Kemasan')
                    ->helperText('Satu barang dapat memiliki barcode PCS, PCK, dan CTN. Qty PCS per scan menentukan jumlah yang ditambahkan saat barcode dipindai.')
                    ->addActionLabel('Tambah barcode kemasan')
                    ->defaultItems(0)
                    ->columnSpanFull()
                    ->columns(['default' => 1, 'md' => 12])
                    ->schema([
                        TextInput::make('barcode')
                            ->label('Barcode')
                            ->required()
                            ->distinct()
                            ->columnSpan(['default' => 1, 'md' => 5])
                            ->suffixAction(Action::make('scan')
                                ->icon('heroicon-m-camera')
                                ->tooltip('Scan dengan kamera')
                                ->extraAttributes(['x-on:click' => '$dispatch(\'item-master-open-barcode-scanner\', { input: $el.closest(\'.fi-input-wrp\')?.querySelector(\'input\') })'])),
                        Select::make('unit_label')
                            ->label('Kemasan')
                            ->options(['PCS' => 'PCS', 'PCK' => 'PCK', 'CTN' => 'CTN', 'DOZ' => 'DOZ'])
                            ->default('PCS')
                            ->columnSpan(['default' => 1, 'md' => 3])
                            ->required(),
                        TextInput::make('qty_base')
                            ->label('Qty PCS per scan')
                            ->numeric()
                            ->minValue(1)
                            ->default(1)
                            ->columnSpan(['default' => 1, 'md' => 2])
                            ->required(),
                        Toggle::make('is_primary')
                            ->label('Barcode Utama')
                            ->columnSpan(['default' => 1, 'md' => 2]),
                    ]),
                View::make('filament.forms.components.barcode-scanner')
                    ->columnSpanFull(),
                Toggle::make('status')
                    ->required(),
            ]);
    }
}
