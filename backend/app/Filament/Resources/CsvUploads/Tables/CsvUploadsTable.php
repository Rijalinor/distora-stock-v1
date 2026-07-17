<?php

namespace App\Filament\Resources\CsvUploads\Tables;

use App\Enums\CsvUploadStatus;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class CsvUploadsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('upload_date')
                    ->label('Tanggal')
                    ->date()
                    ->sortable(),

                TextColumn::make('original_filename')
                    ->label('File')
                    ->searchable()
                    ->limit(40),

                TextColumn::make('total_rows')
                    ->label('Total Item')
                    ->numeric()
                    ->sortable(),

                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (CsvUploadStatus $state): string => match ($state) {
                        CsvUploadStatus::Pending => 'Menunggu',
                        CsvUploadStatus::Previewed => 'Siap Generate',
                        CsvUploadStatus::Processing => 'Memproses',
                        CsvUploadStatus::Completed => 'Selesai',
                        CsvUploadStatus::Failed => 'Gagal',
                    })
                    ->color(fn (CsvUploadStatus $state): string => match ($state) {
                        CsvUploadStatus::Pending => 'gray',
                        CsvUploadStatus::Previewed => 'warning',
                        CsvUploadStatus::Processing => 'info',
                        CsvUploadStatus::Completed => 'success',
                        CsvUploadStatus::Failed => 'danger',
                    }),

                TextColumn::make('uploadedBy.name')
                    ->label('Diupload oleh')
                    ->toggleable(),

                TextColumn::make('stockSessions_count')
                    ->label('Sesi')
                    ->counts('stockSessions')
                    ->sortable(),

                TextColumn::make('created_at')
                    ->label('Waktu Upload')
                    ->dateTime('d M Y H:i')
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        CsvUploadStatus::Previewed->value => 'Siap Generate',
                        CsvUploadStatus::Completed->value => 'Selesai',
                    ]),
            ])
            ->recordActions([
                ViewAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
