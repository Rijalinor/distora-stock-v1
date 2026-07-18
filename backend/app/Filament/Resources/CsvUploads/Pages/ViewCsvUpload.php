<?php

namespace App\Filament\Resources\CsvUploads\Pages;

use App\Enums\CsvUploadStatus;
use App\Filament\Resources\CsvUploads\CsvUploadResource;
use App\Filament\Resources\StockSessions\StockSessionResource;
use App\Services\CsvImportService;
use App\Services\StockSessionService;
use Filament\Actions\Action;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Storage;

class ViewCsvUpload extends ViewRecord
{
    protected static string $resource = CsvUploadResource::class;

    protected static ?string $title = 'Preview Upload Stok';

    public function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Informasi Upload')
                    ->schema([
                        TextEntry::make('upload_date')
                            ->label('Tanggal Stock Opname')
                            ->date('d M Y'),

                        TextEntry::make('original_filename')
                            ->label('Nama File'),

                        TextEntry::make('total_rows')
                            ->label('Total Item')
                            ->numeric(),

                        TextEntry::make('status')
                            ->label('Status')
                            ->badge()
                            ->formatStateUsing(fn (CsvUploadStatus $state): string => match ($state) {
                                CsvUploadStatus::Pending => 'Menunggu',
                                CsvUploadStatus::Previewed => 'Siap Generate',
                                CsvUploadStatus::Processing => 'Memproses',
                                CsvUploadStatus::Completed => 'Selesai',
                                CsvUploadStatus::Failed => 'Gagal',
                            }),

                        TextEntry::make('uploadedBy.name')
                            ->label('Diupload oleh'),

                        TextEntry::make('notes')
                            ->label('Catatan')
                            ->placeholder('-'),
                    ])
                    ->columns(3),

                Section::make('Grouping per Principal')
                    ->description('Sistem akan membuka sesi stock opname dari data ini. Principal yang digabung akan masuk ke sesi principal induknya.')
                    ->schema([
                        TextEntry::make('summary')
                            ->label('')
                            ->formatStateUsing(function ($state, $record) {
                                $groups = $record->summary['principal_groups'] ?? [];

                                if (empty($groups)) {
                                    return 'Belum ada data grouping.';
                                }

                                $rows = collect($groups)->map(function ($group) {
                                    $nama = e($group['nama']);
                                    $kode = e($group['kode']);
                                    $count = $group['item_count'];

                                    return "<tr class='border-b border-gray-200 dark:border-gray-700'>
                                        <td class='py-2 pr-4 font-mono text-sm'>{$kode}</td>
                                        <td class='py-2 pr-4'>{$nama}</td>
                                        <td class='py-2 text-right font-semibold'>{$count} item</td>
                                    </tr>";
                                })->implode('');

                                return "<div class='overflow-x-auto'>
                                    <table class='w-full text-sm'>
                                        <thead>
                                            <tr class='text-left text-gray-500 dark:text-gray-400'>
                                                <th class='pb-2 pr-4'>Kode</th>
                                                <th class='pb-2 pr-4'>Principal</th>
                                                <th class='pb-2 text-right'>Jumlah Item</th>
                                            </tr>
                                        </thead>
                                        <tbody>{$rows}</tbody>
                                    </table>
                                </div>";
                            })
                            ->html(),
                    ]),
            ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('generateSessions')
                ->label('Buka Sesi Stock Opname')
                ->icon('heroicon-o-play')
                ->color('success')
                ->requiresConfirmation()
                ->modalHeading('Buka sesi stock opname?')
                ->modalDescription('Sesi akan dibuat dari upload ini. Principal yang sudah digabung akan disatukan ke principal induknya.')
                ->visible(fn () => $this->record->status === CsvUploadStatus::Previewed)
                ->action(function () {
                    $importService = app(CsvImportService::class);
                    $sessionService = app(StockSessionService::class);

                    $this->record->update(['status' => CsvUploadStatus::Processing]);

                    try {
                        $fullPath = Storage::disk('local')->path($this->record->filename);
                        $preview = $importService->parseAndPreview($fullPath);
                        $sessions = $sessionService->generateSessions($this->record, $preview);

                        $this->record->update(['status' => CsvUploadStatus::Completed]);

                        Notification::make()
                            ->title('Session berhasil dibuat')
                            ->body("Dibuat {$sessions->count()} sesi stock opname.")
                            ->success()
                            ->send();

                        $this->redirect(StockSessionResource::getUrl('index'));
                    } catch (\Throwable $e) {
                        $this->record->update([
                            'status' => CsvUploadStatus::Failed,
                            'notes' => $e->getMessage(),
                        ]);

                        Notification::make()
                            ->title('Gagal generate session')
                            ->body($e->getMessage())
                            ->danger()
                            ->send();
                    }
                }),

            Action::make('viewSessions')
                ->label('Lihat Sesi')
                ->icon('heroicon-o-clipboard-document-list')
                ->visible(fn () => $this->record->status === CsvUploadStatus::Completed)
                ->url(fn () => StockSessionResource::getUrl('index', [
                    'tableFilters' => ['csv_upload_id' => ['value' => $this->record->id]],
                ])),
        ];
    }
}
