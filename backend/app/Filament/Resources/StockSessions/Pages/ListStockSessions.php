<?php

namespace App\Filament\Resources\StockSessions\Pages;

use App\Filament\Resources\StockSessions\StockSessionResource;
use App\Filament\Resources\CsvUploads\CsvUploadResource;
use App\Services\StockSessionService;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;

class ListStockSessions extends ListRecords
{
    protected static string $resource = StockSessionResource::class;

    protected static ?string $title = 'Sesi Stock Opname';

    protected function getHeaderActions(): array
    {
        return [
            Action::make('todayStatus')
                ->label('Status Hari Ini')
                ->icon('heroicon-o-calendar-days')
                ->color('gray')
                ->modalHeading('Status stock opname hari ini')
                ->modalSubmitAction(false)
                ->modalCancelActionLabel('Tutup')
                ->modalContent(fn () => view('filament.pages.stock-sessions-close-day', [
                    'summary' => app(StockSessionService::class)->summarizeTodaySessions(),
                ]))
                ->visible(fn (): bool => auth()->user()?->isAdmin() ?? false),

            Action::make('openNewDay')
                ->label('Buka Hari Baru')
                ->icon('heroicon-o-arrow-up-tray')
                ->color('success')
                ->url(CsvUploadResource::getUrl('create'))
                ->visible(fn (): bool => auth()->user()?->isAdmin() ?? false),

            Action::make('closeTodaySessions')
                ->label('Tutup Semua Sesi Hari Ini')
                ->icon('heroicon-o-lock-closed')
                ->color('danger')
                ->modalHeading('Tutup semua sesi hari ini?')
                ->modalDescription('Lihat dulu principal yang belum selesai. Setelah ditutup, sesi aktif hari ini tidak bisa dipakai lagi.')
                ->modalContent(fn () => view('filament.pages.stock-sessions-close-day', [
                    'summary' => app(StockSessionService::class)->summarizeTodaySessions(),
                ]))
                ->requiresConfirmation()
                ->action(function (): void {
                    $closed = app(StockSessionService::class)->closeTodaySessions();

                    Notification::make()
                        ->title('Sesi hari ini ditutup')
                        ->body("{$closed} sesi berhasil ditutup.")
                        ->success()
                        ->send();

                    $this->redirect(StockSessionResource::getUrl('index'));
                })
                ->visible(fn (): bool => auth()->user()?->isAdmin() ?? false),
        ];
    }
}
