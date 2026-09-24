<?php

namespace App\Filament\Resources\ItemMasters\Schemas;

use App\Models\Branch;
use App\Models\ItemMaster;
use Filament\Actions\Action;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
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
                Section::make('Informasi Barang')
                    ->description('Data identitas barang, cabang, dan principal')
                    ->columns(['default' => 1, 'md' => 2])
                    ->schema([
                        TextInput::make('kode_barang')
                            ->label('Kode Barang')
                            ->placeholder('Contoh: BAY001')
                            ->required(),
                        TextInput::make('nama_barang')
                            ->label('Nama Barang')
                            ->placeholder('Contoh: BAYGON COIL STANDART MAX (1X60)')
                            ->helperText('Jika mengandung pola konversi seperti (1X60) atau (1X12X10), struktur qty otomatis terisi.')
                            ->live(onBlur: true)
                            ->afterStateUpdated(function (?string $state, Get $get, Set $set): void {
                                self::autoPopulateStructureAndBarcodes($state, $get('satuan'), $get, $set);
                            })
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
                            ->label('Satuan Kemasan (Size)')
                            ->placeholder('Contoh: CTN-PCS atau CTN-SAC-PCS')
                            ->helperText('Gunakan tanda minus (-) untuk memisahkan satuan kemasan.')
                            ->live(onBlur: true)
                            ->afterStateUpdated(function (?string $state, Get $get, Set $set): void {
                                self::autoPopulateStructureAndBarcodes($get('nama_barang'), $state, $get, $set);
                            })
                            ->suffixAction(
                                Action::make('autoDetectStructure')
                                    ->icon('heroicon-m-sparkles')
                                    ->tooltip('Generate ulang struktur qty dari nama & satuan')
                                    ->action(function (Get $get, Set $set): void {
                                        self::autoPopulateStructureAndBarcodes($get('nama_barang'), $get('satuan'), $get, $set, true);
                                    })
                            )
                            ->required(),
                        Toggle::make('status')
                            ->label('Status Aktif')
                            ->default(true)
                            ->required(),
                    ]),

                Section::make('Struktur Satuan & Konversi Kemasan')
                    ->description('Tentukan isi konversi kemasan dari tingkat terbesar ke terkecil (PCS)')
                    ->collapsible()
                    ->schema([
                        Repeater::make('qty_structure')
                            ->label('Tingkatan Satuan')
                            ->helperText('Contoh: CTN faktor 60, lalu PCS faktor 1.')
                            ->default([
                                ['label' => 'CTN', 'factor' => 1],
                                ['label' => 'PCS', 'factor' => 1],
                            ])
                            ->minItems(1)
                            ->addActionLabel('Tambah Satuan')
                            ->reorderable()
                            ->columnSpanFull()
                            ->columns(2)
                            ->schema([
                                TextInput::make('label')
                                    ->label('Satuan')
                                    ->placeholder('Contoh: CTN, SAC, PCK, PCS')
                                    ->required(),
                                TextInput::make('factor')
                                    ->label('Isi ke satuan terkecil (PCS)')
                                    ->numeric()
                                    ->minValue(1)
                                    ->default(1)
                                    ->required()
                                    ->helperText('Contoh: 1 CTN isi 60 PCS, maka isi 60. Untuk PCS selalu 1.'),
                            ]),
                    ]),

                Section::make('Barcode Kemasan (Multi-Barcode)')
                    ->description('Daftar barcode per kemasan untuk scan cepat saat stock opname')
                    ->collapsible()
                    ->schema([
                        Repeater::make('barcodes')
                            ->relationship()
                            ->label('Barcode')
                            ->helperText('Scan atau ketik barcode. Satu barang bisa memiliki barcode CTN, SAC, dan PCS.')
                            ->addActionLabel('Tambah Barcode Kemasan')
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
                                        ->alpineClickHandler('$dispatch(\'item-master-open-barcode-scanner\', { input: $el.closest(\'.fi-input-wrp\')?.querySelector(\'input\') })')),
                                TextInput::make('unit_label')
                                    ->label('Kemasan')
                                    ->placeholder('PCS/CTN')
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
                                    ->label('Utama')
                                    ->columnSpan(['default' => 1, 'md' => 2]),
                            ]),
                    ]),

                View::make('filament.forms.components.barcode-scanner')
                    ->columnSpanFull(),
            ]);
    }

    /**
     * Auto populate qty_structure and barcodes draft when empty or explicitly requested.
     */
    protected static function autoPopulateStructureAndBarcodes(?string $namaBarang, ?string $satuan, Get $get, Set $set, bool $force = false): void
    {
        if (blank($namaBarang) && blank($satuan)) {
            return;
        }

        $currentStructure = $get('qty_structure');
        $isStructureEmptyOrDefault = empty($currentStructure) || count($currentStructure) <= 1 || (
            count($currentStructure) === 2 &&
            ($currentStructure[0]['label'] ?? '') === 'CTN' &&
            (int) ($currentStructure[0]['factor'] ?? 1) === 1
        );

        if ($force || $isStructureEmptyOrDefault) {
            $generated = ItemMaster::generateDefaultQtyStructure($namaBarang, $satuan);
            if (! empty($generated)) {
                $set('qty_structure', $generated);
            }
        }
    }
}

