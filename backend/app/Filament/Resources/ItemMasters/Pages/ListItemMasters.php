<?php

namespace App\Filament\Resources\ItemMasters\Pages;

use App\Filament\Resources\ItemMasters\ItemMasterResource;
use App\Models\Branch;
use App\Models\ItemMaster;
use App\Models\Principal;
use App\Services\ItemMasterBackupService;
use App\Services\ItemMasterTransferService;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
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
                ->visible(fn () => auth()->user()?->isCentralAdmin() ?? false)
                ->action('downloadBackup'),
            Action::make('restoreItemMaster')
                ->label('Restore Item Master')
                ->icon('heroicon-m-arrow-up-tray')
                ->color('warning')
                ->visible(fn () => auth()->user()?->isCentralAdmin() ?? false)
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
            Action::make('copyItemMaster')
                ->label('Salin Antar Cabang')
                ->icon('heroicon-m-arrows-right-left')
                ->color('primary')
                ->visible(fn () => auth()->user()?->isCentralAdmin() ?? false)
                ->modalHeading('Salin Item Master Antar Cabang')
                ->modalDescription('Data cabang sumber tetap ada. Kode yang sudah ada di cabang tujuan akan diperbarui.')
                ->modalSubmitActionLabel('Salin Data')
                ->form([
                    Select::make('source_branch_id')
                        ->label('Cabang Sumber')
                        ->options(fn () => Branch::query()->where('status', true)->orderBy('nama')->pluck('nama', 'id'))
                        ->searchable()
                        ->preload()
                        ->live()
                        ->afterStateUpdated(function (Set $set): void {
                            $set('target_branch_id', null);
                            $set('principal_id', null);
                        })
                        ->required(),
                    Select::make('target_branch_id')
                        ->label('Cabang Tujuan')
                        ->options(fn (Get $get) => Branch::query()
                            ->where('status', true)
                            ->when($get('source_branch_id'), fn ($query, $sourceBranchId) => $query->where('id', '!=', $sourceBranchId))
                            ->orderBy('nama')
                            ->pluck('nama', 'id'))
                        ->searchable()
                        ->preload()
                        ->required(),
                    Select::make('principal_id')
                        ->label('Principal')
                        ->options(fn (Get $get) => Principal::query()
                            ->whereIn('id', ItemMaster::query()
                                ->where('branch_id', $get('source_branch_id'))
                                ->select('principal_id'))
                            ->orderBy('nama')
                            ->pluck('nama', 'id'))
                        ->searchable()
                        ->preload()
                        ->required(),
                ])
                ->action(function (array $data): void {
                    $stats = app(ItemMasterTransferService::class)->copyPrincipal(
                        (int) $data['source_branch_id'],
                        (int) $data['target_branch_id'],
                        (int) $data['principal_id']
                    );

                    Notification::make()
                        ->title('Salin Item Master selesai')
                        ->body("Total: {$stats['total']} | Baru: {$stats['created']} | Diperbarui: {$stats['updated']}")
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
