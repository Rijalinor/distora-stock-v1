<?php

namespace App\Filament\Resources\ItemMasters\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
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
                SelectFilter::make('principal_id')
                    ->label('Principal')
                    ->relationship('principal', 'nama')
                    ->searchable()
                    ->preload(),
                Filter::make('missing_barcode')
                    ->label('Belum Ada Barcode')
                    ->query(fn (Builder $query): Builder => $query
                        ->where(fn (Builder $query): Builder => $query
                            ->whereNull('barcode')
                            ->orWhere('barcode', ''))),
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
                \Filament\Actions\Action::make('quickBarcodes')
                    ->label('Barcode')
                    ->icon('heroicon-m-qr-code')
                    ->color('info')
                    ->modalHeading(fn (ItemMaster $record) => "Kelola Barcode: {$record->nama_barang} ({$record->kode_barang})")
                    ->modalDescription('Tambah atau perbarui barcode kemasan barang ini secara cepat.')
                    ->modalWidth('2xl')
                    ->fillForm(fn (ItemMaster $record): array => [
                        'barcodes' => $record->barcodes()->get()->map(fn ($b) => [
                            'id' => $b->id,
                            'barcode' => $b->barcode,
                            'unit_label' => $b->unit_label,
                            'qty_base' => $b->qty_base,
                            'is_primary' => (bool) $b->is_primary,
                        ])->all(),
                    ])
                    ->form([
                        \Filament\Forms\Components\Repeater::make('barcodes')
                            ->label('Daftar Barcode Kemasan')
                            ->addActionLabel('Tambah Barcode Kemasan')
                            ->columns(['default' => 1, 'md' => 12])
                            ->schema([
                                \Filament\Forms\Components\TextInput::make('barcode')
                                    ->label('Barcode')
                                    ->required()
                                    ->distinct()
                                    ->columnSpan(['default' => 1, 'md' => 5])
                                    ->suffixAction(
                                        \Filament\Actions\Action::make('scanBarcode')
                                            ->icon('heroicon-m-camera')
                                            ->tooltip('Scan Barcode dengan Kamera')
                                            ->alpineClickHandler('$dispatch(\'item-master-open-barcode-scanner\', { input: $el.closest(\'.fi-input-wrp\')?.querySelector(\'input\') })')
                                    ),
                                \Filament\Forms\Components\TextInput::make('unit_label')
                                    ->label('Kemasan')
                                    ->placeholder('PCS/CTN')
                                    ->default('PCS')
                                    ->columnSpan(['default' => 1, 'md' => 3])
                                    ->required(),
                                \Filament\Forms\Components\TextInput::make('qty_base')
                                    ->label('Qty PCS')
                                    ->numeric()
                                    ->minValue(1)
                                    ->default(1)
                                    ->columnSpan(['default' => 1, 'md' => 2])
                                    ->required(),
                                \Filament\Forms\Components\Toggle::make('is_primary')
                                    ->label('Utama')
                                    ->columnSpan(['default' => 1, 'md' => 2]),
                            ]),
                        \Filament\Schemas\Components\View::make('filament.forms.components.barcode-scanner')
                            ->columnSpanFull(),
                    ])
                    ->action(function (ItemMaster $record, array $data): void {
                        $submitted = $data['barcodes'] ?? [];
                        
                        // Sync barcodes
                        $existingIds = [];
                        foreach ($submitted as $row) {
                            if (blank($row['barcode'] ?? null)) {
                                continue;
                            }
                            $barcodeModel = !empty($row['id']) 
                                ? $record->barcodes()->find($row['id']) 
                                : null;
                                
                            if (!$barcodeModel) {
                                $barcodeModel = $record->barcodes()->make([
                                    'branch_id' => $record->branch_id,
                                ]);
                            }

                            $barcodeModel->barcode = trim($row['barcode']);
                            $barcodeModel->unit_label = strtoupper(trim($row['unit_label'] ?: 'PCS'));
                            $barcodeModel->qty_base = max(1, (int) ($row['qty_base'] ?: 1));
                            $barcodeModel->is_primary = !empty($row['is_primary']);
                            $barcodeModel->save();

                            $existingIds[] = $barcodeModel->id;
                        }

                        // Delete removed barcodes
                        $record->barcodes()->whereNotIn('id', $existingIds)->delete();
                        
                        \Filament\Notifications\Notification::make()
                            ->title('Barcode berhasil diperbarui')
                            ->success()
                            ->send();
                    }),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
