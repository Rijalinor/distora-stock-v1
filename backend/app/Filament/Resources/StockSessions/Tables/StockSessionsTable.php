<?php

namespace App\Filament\Resources\StockSessions\Tables;

use App\Enums\StockSessionStatus;
use App\Enums\UserRole;
use App\Models\User;
use App\Services\StockSessionService;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class StockSessionsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('session_date')
                    ->label('Tanggal')
                    ->date('d M Y')
                    ->sortable(),

                TextColumn::make('principal.nama')
                    ->label('Principal')
                    ->searchable()
                    ->sortable()
                    ->limit(35),

                TextColumn::make('branch.nama')
                    ->label('Cabang')
                    ->placeholder('-')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (StockSessionStatus $state): string => match ($state) {
                        StockSessionStatus::Open => 'Belum Dikerjakan',
                        StockSessionStatus::InProgress => 'Sedang Dikerjakan',
                        StockSessionStatus::Completed => 'Selesai',
                    })
                    ->color(fn (StockSessionStatus $state): string => match ($state) {
                        StockSessionStatus::Open => 'gray',
                        StockSessionStatus::InProgress => 'warning',
                        StockSessionStatus::Completed => 'success',
                    }),

                TextColumn::make('progress')
                    ->label('Progress')
                    ->state(fn ($record) => "{$record->checked_items}/{$record->total_items}")
                    ->description(fn ($record) => $record->total_items > 0
                        ? round(($record->checked_items / $record->total_items) * 100) . '%'
                        : '0%'),

                TextColumn::make('matched_items')
                    ->label('Sesuai')
                    ->numeric()
                    ->color('success'),

                TextColumn::make('mismatched_items')
                    ->label('Selisih')
                    ->numeric()
                    ->color('danger'),

                TextColumn::make('assignedOfficer.name')
                    ->label('Petugas Utama')
                    ->placeholder('Belum ditugaskan'),

                TextColumn::make('started_at')
                    ->label('Mulai')
                    ->dateTime('H:i')
                    ->placeholder('-'),

                TextColumn::make('completed_at')
                    ->label('Selesai')
                    ->dateTime('H:i')
                    ->placeholder('-'),
            ])
            ->defaultSort('session_date', 'desc')
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        StockSessionStatus::Open->value => 'Belum Dikerjakan',
                        StockSessionStatus::InProgress->value => 'Sedang Dikerjakan',
                        StockSessionStatus::Completed->value => 'Selesai',
                    ]),

                SelectFilter::make('principal_id')
                    ->label('Principal')
                    ->relationship('principal', 'nama')
                    ->searchable()
                    ->preload(),

                SelectFilter::make('branch_id')
                    ->label('Cabang')
                    ->relationship('branch', 'nama')
                    ->searchable()
                    ->preload(),

                Filter::make('csv_upload_id')
                    ->query(fn (Builder $query, array $data): Builder => isset($data['value'])
                        ? $query->where('csv_upload_id', $data['value'])
                        : $query),

                SelectFilter::make('assigned_to')
                    ->label('Petugas Utama')
                    ->options(fn () => User::where('role', UserRole::StockOfficer)->pluck('name', 'id')),
            ])
            ->recordActions([
                ViewAction::make(),
                Action::make('assignOfficer')
                    ->label('Tugaskan')
                    ->icon('heroicon-o-user-plus')
                    ->color('warning')
                    ->visible(fn ($record) => $record->assigned_to === null)
                    ->form([
                        Select::make('officer_id')
                            ->label('Pilih Petugas')
                            ->options(fn () => User::where('role', UserRole::StockOfficer)->pluck('name', 'id'))
                            ->required()
                            ->searchable(),
                    ])
                    ->action(function ($record, array $data): void {
                        $officer = User::findOrFail($data['officer_id']);
                        app(StockSessionService::class)->assignOfficer($record, $officer);

                        Notification::make()
                            ->title('Petugas berhasil ditugaskan')
                            ->body("{$officer->name} ditugaskan ke sesi {$record->principal->nama}")
                            ->success()
                            ->send();
                    }),
            ]);
    }
}
