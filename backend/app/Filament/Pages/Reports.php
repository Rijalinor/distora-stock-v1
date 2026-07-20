<?php

namespace App\Filament\Pages;

use App\Enums\StockSessionItemStatus;
use App\Filament\Widgets\ReportStatsOverview;
use App\Models\Principal;
use App\Models\StockSessionItem;
use App\Services\ReportService;
use BackedEnum;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\StreamedResponse;

class Reports extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChartBar;

    protected static ?string $navigationLabel = 'Laporan';

    protected static ?string $title = 'Laporan Stock Opname';

    protected static string|\UnitEnum|null $navigationGroup = 'Stock Opname';

    protected static ?int $navigationSort = 3;

    protected string $view = 'filament.pages.reports';

    public string $reportDate = '';

    public ?int $principalId = null;

    protected $queryString = ['reportDate', 'principalId'];

    public function mount(): void
    {
        $this->reportDate = $this->reportDate ?: today()->toDateString();
    }

    public static function canAccess(): bool
    {
        return Auth::user()?->isAdmin() ?? false;
    }

    protected function getHeaderWidgets(): array
    {
        return [
            ReportStatsOverview::make([
                'date' => $this->reportDate ?: null,
                'principalId' => $this->principalId,
            ]),
        ];
    }

    protected function getHeaderActions(): array
    {
        $dateLabel = Carbon::parse($this->reportDate ?: today()->toDateString())->format('d M Y');

        return [
            Action::make('filterReport')
                ->label($this->principalId ? "Filter: {$dateLabel}" : "Tanggal: {$dateLabel}")
                ->icon('heroicon-m-funnel')
                ->color('gray')
                ->form([
                    DatePicker::make('reportDate')
                        ->label('Tanggal Laporan')
                        ->default($this->reportDate ?: today()->toDateString())
                        ->native(false),
                    Select::make('principalId')
                        ->label('Principal')
                        ->options(fn () => Principal::query()->orderBy('nama')->pluck('nama', 'id'))
                        ->default($this->principalId)
                        ->searchable()
                        ->preload()
                        ->placeholder('Semua principal'),
                ])
                ->action(function (array $data): void {
                    $this->reportDate = $data['reportDate'] ?: today()->toDateString();
                    $this->principalId = filled($data['principalId'] ?? null) ? (int) $data['principalId'] : null;
                }),

            ActionGroup::make([
                Action::make('exportDaily')
                    ->label('Download Laporan Harian')
                    ->icon('heroicon-m-arrow-down-tray')
                    ->action('exportDailyCsv'),

                Action::make('exportSelisih')
                    ->label('Download Data Selisih')
                    ->icon('heroicon-m-arrow-down-tray')
                    ->action('exportSelisihCsv'),
            ])
                ->label('Export CSV')
                ->icon('heroicon-m-arrow-down-tray')
                ->color('warning'),
        ];
    }

    public function exportDailyCsv(): StreamedResponse
    {
        $csv = app(ReportService::class)->buildDailyCsv($this->reportDate, $this->principalId);
        $filename = 'laporan-harian-' . $this->reportDate . $this->principalFilenameSuffix() . '.csv';

        return response()->streamDownload(
            fn () => print($csv),
            $filename,
            ['Content-Type' => 'text/csv']
        );
    }

    public function exportSelisihCsv(): StreamedResponse
    {
        $csv = app(ReportService::class)->buildSelisihCsv($this->reportDate, $this->principalId);
        $filename = 'selisih-' . $this->reportDate . $this->principalFilenameSuffix() . '.csv';

        return response()->streamDownload(
            fn () => print($csv),
            $filename,
            ['Content-Type' => 'text/csv']
        );
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                StockSessionItem::query()
                    ->with(['stockSession.principal', 'checkedBy'])
                    ->whereIn('status', [StockSessionItemStatus::Mismatched, StockSessionItemStatus::Missing])
                    ->when($this->reportDate, fn (Builder $q) => $q->whereHas(
                        'stockSession',
                        fn (Builder $q) => $q->whereDate('session_date', $this->reportDate)
                    ))
                    ->when($this->principalId, fn (Builder $q) => $q->whereHas(
                        'stockSession',
                        fn (Builder $q) => $q->where('principal_id', $this->principalId)
                    ))
            )
            ->columns([
                TextColumn::make('stockSession.principal.nama')
                    ->label('Principal')
                    ->sortable(),

                TextColumn::make('kode_barang')
                    ->label('Kode')
                    ->searchable()
                    ->copyable(),

                TextColumn::make('nama_barang')
                    ->label('Nama Barang')
                    ->searchable()
                    ->limit(40),

                TextColumn::make('qty_sistem_display')
                    ->label('Qty Sistem'),

                TextColumn::make('qty_aktual_display')
                    ->label('Qty Aktual')
                    ->placeholder('-'),

                TextColumn::make('selisih')
                    ->label('Selisih')
                    ->numeric()
                    ->color(fn ($state) => $state < 0 ? 'danger' : 'warning'),

                TextColumn::make('checkedBy.name')
                    ->label('Petugas')
                    ->placeholder('-'),

                TextColumn::make('checked_at')
                    ->label('Waktu')
                    ->dateTime('H:i')
                    ->placeholder('-'),
            ])
            ->defaultSort('checked_at', 'desc')
            ->filters([
                SelectFilter::make('principal')
                    ->label('Principal')
                    ->relationship('stockSession.principal', 'nama')
                    ->searchable()
                    ->preload(),
            ]);
    }

    protected function principalFilenameSuffix(): string
    {
        if (! $this->principalId) {
            return '';
        }

        $principal = Principal::find($this->principalId);

        return $principal ? '-' . str($principal->kode ?: $principal->nama)->slug() : '';
    }
}
