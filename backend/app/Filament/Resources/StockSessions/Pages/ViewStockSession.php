<?php

namespace App\Filament\Resources\StockSessions\Pages;

use App\Enums\StockSessionStatus;
use App\Enums\UserRole;
use App\Filament\Resources\StockSessions\StockSessionResource;
use App\Models\User;
use App\Services\ReportService;
use App\Services\StockSessionService;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ViewStockSession extends ViewRecord
{
    protected static string $resource = StockSessionResource::class;

    protected static ?string $title = 'Detail Sesi Stock';

    public function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Ringkasan Sesi')
                    ->schema([
                        TextEntry::make('principal.nama')
                            ->label('Principal'),

                        TextEntry::make('branch.nama')
                            ->label('Cabang')
                            ->placeholder('-'),

                        TextEntry::make('session_date')
                            ->label('Tanggal')
                            ->date('d M Y'),

                        TextEntry::make('status')
                            ->label('Status')
                            ->badge()
                            ->formatStateUsing(fn (StockSessionStatus $state): string => match ($state) {
                                StockSessionStatus::Open => 'Belum Dikerjakan',
                                StockSessionStatus::InProgress => 'Sedang Dikerjakan',
                                StockSessionStatus::Completed => 'Selesai',
                            }),

                        TextEntry::make('progress_summary')
                            ->label('Progress')
                            ->state(fn ($record) => "{$record->checked_items} / {$record->total_items} item"),

                        TextEntry::make('matched_items')
                            ->label('Sesuai')
                            ->numeric(),

                        TextEntry::make('mismatched_items')
                            ->label('Selisih')
                            ->numeric(),

                        TextEntry::make('assignedOfficer.name')
                            ->label('Petugas Utama')
                            ->placeholder('Belum ditugaskan'),

                        TextEntry::make('started_at')
                            ->label('Jam Mulai')
                            ->dateTime('d M Y H:i')
                            ->placeholder('-'),

                        TextEntry::make('completed_at')
                            ->label('Jam Selesai')
                            ->dateTime('d M Y H:i')
                            ->placeholder('-'),
                    ])
                    ->columns(4),
            ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('assignOfficer')
                ->label('Tugaskan Petugas')
                ->icon('heroicon-o-user-plus')
                ->color('warning')
                ->visible(fn () => $this->record->assigned_to === null)
                ->form([
                    Select::make('officer_id')
                        ->label('Pilih Petugas')
                        ->options(fn () => User::where('role', UserRole::StockOfficer)->pluck('name', 'id'))
                        ->required()
                        ->searchable(),
                ])
                ->action(function (array $data): void {
                    $officer = User::findOrFail($data['officer_id']);
                    app(StockSessionService::class)->assignOfficer($this->record, $officer);

                    Notification::make()
                        ->title('Petugas berhasil ditugaskan')
                        ->body("{$officer->name} ditugaskan ke sesi ini.")
                        ->success()
                        ->send();
                }),

            Action::make('exportCsv')
                ->label('Export CSV')
                ->icon('heroicon-o-arrow-down-tray')
                ->action(function (): StreamedResponse {
                    $reportService = app(ReportService::class);
                    $csv = $reportService->buildSessionCsv($this->record);
                    $filename = 'stock-opname-' . $this->record->principal->kode . '-' . $this->record->session_date->format('Y-m-d') . '.csv';

                    return response()->streamDownload(
                        fn () => print($csv),
                        $filename,
                        ['Content-Type' => 'text/csv']
                    );
                }),
        ];
    }
}
