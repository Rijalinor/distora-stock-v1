<?php

namespace App\Filament\Resources\ItemMasters\Pages;

use App\Filament\Resources\ItemMasters\ItemMasterResource;
use App\Services\ItemMasterBackupService;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\FileUpload;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ListItemMasters extends ListRecords
{
    protected static string $resource = ItemMasterResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('backupItemMaster')
                ->label('Backup Item Master')
                ->icon('heroicon-m-arrow-down-tray')
                ->color('gray')
                ->action('downloadBackup'),
            Action::make('restoreItemMaster')
                ->label('Restore Item Master')
                ->icon('heroicon-m-arrow-up-tray')
                ->color('warning')
                ->requiresConfirmation()
                ->modalDescription('Upload file CSV backup Item Master. Data dengan kode barang yang sama akan diperbarui.')
                ->form([
                    FileUpload::make('backup_file')
                        ->label('File Backup CSV')
                        ->acceptedFileTypes(['text/csv', 'text/plain', 'application/vnd.ms-excel'])
                        ->disk('local')
                        ->directory('item-master-backups')
                        ->visibility('private')
                        ->required(),
                ])
                ->action(function (array $data): void {
                    $stats = app(ItemMasterBackupService::class)->restoreCsv($data['backup_file']);

                    Notification::make()
                        ->title('Restore Item Master selesai')
                        ->body("Baru: {$stats['created']} | Update: {$stats['updated']} | Skip: {$stats['skipped']}")
                        ->success()
                        ->send();
                }),
            CreateAction::make(),
        ];
    }

    public function downloadBackup(): StreamedResponse
    {
        $csv = app(ItemMasterBackupService::class)->buildCsv();
        $filename = 'backup-item-master-' . now()->format('Y-m-d-His') . '.csv';

        return response()->streamDownload(
            fn () => print($csv),
            $filename,
            ['Content-Type' => 'text/csv']
        );
    }
}
