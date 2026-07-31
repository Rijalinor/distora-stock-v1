<?php

namespace App\Filament\Resources\StockSessions\Pages;

use App\Filament\Resources\StockSessions\StockSessionResource;
use App\Filament\Resources\CsvUploads\CsvUploadResource;
use App\Services\StockSessionService;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Placeholder;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Utilities\Get;

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
                    'summary' => app(StockSessionService::class)->summarizeTodaySessions($this->managedBranchId()),
                ]))
                ->visible(fn (): bool => auth()->user()?->isAdmin() ?? false),

            Action::make('openNewDay')
                ->label('Buka Hari Baru')
                ->icon('heroicon-o-arrow-up-tray')
                ->color('success')
                ->url(CsvUploadResource::getUrl('create'))
                ->visible(fn (): bool => auth()->user()?->isAdmin() ?? false),

            Action::make('closeSessions')
                ->label('Tutup Sesi')
                ->icon('heroicon-o-lock-closed')
                ->color('danger')
                ->modalHeading('Tutup sesi stock opname')
                ->modalDescription('Pilih tanggal dan periksa sesi aktif sebelum menutupnya.')
                ->modalSubmitActionLabel('Tutup Sesi')
                ->form([
                    DatePicker::make('session_date')
                        ->label('Tanggal sesi')
                        ->default(today())
                        ->maxDate(today())
                        ->live()
                        ->required(),
                    Placeholder::make('session_summary')
                        ->label('Ringkasan')
                        ->content(fn (Get $get) => view('filament.pages.stock-sessions-close-day', [
                            'summary' => app(StockSessionService::class)->summarizeSessions(
                                $get('session_date') ?: today()->toDateString(),
                                $this->managedBranchId(),
                            ),
                        ])),
                ])
                ->requiresConfirmation()
                ->action(function (array $data): void {
                    $closed = app(StockSessionService::class)->closeSessions(
                        $data['session_date'],
                        $this->managedBranchId(),
                    );

                    Notification::make()
                        ->title('Sesi ditutup')
                        ->body("{$closed} sesi berhasil ditutup.")
                        ->success()
                        ->send();

                    $this->redirect(StockSessionResource::getUrl('index'));
                })
                ->visible(fn (): bool => auth()->user()?->isAdmin() ?? false),
        ];
    }

    private function managedBranchId(): ?int
    {
        $user = auth()->user();

        return $user?->isCentralAdmin() ? null : $user?->branch_id;
    }
}
