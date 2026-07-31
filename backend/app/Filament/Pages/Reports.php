<?php

namespace App\Filament\Pages;

use App\Enums\StockSessionItemStatus;
use App\Filament\Widgets\ReportStatsOverview;
use App\Models\Branch;
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

    public string $comparisonDate = '';

    public ?int $principalId = null;

    public ?int $branchId = null;

    public string $reportSearch = '';

    protected $queryString = ['reportDate', 'principalId', 'branchId'];

    public function mount(): void
    {
        $this->reportDate = $this->reportDate ?: today()->toDateString();

        if (Auth::user() && ! Auth::user()?->isCentralAdmin()) {
            $this->branchId = Auth::user()?->branch_id;
        }

        $this->syncComparisonDate();
    }

    public static function canAccess(): bool
    {
        $user = Auth::user();

        return $user && ($user->isAdmin() || $user->isStockOfficer());
    }

    protected function getHeaderWidgets(): array
    {
        if (Auth::user()?->isStockOfficer()) {
            return [];
        }

        return [
            ReportStatsOverview::make([
                'date' => $this->reportDate ?: null,
                'principalId' => $this->principalId,
                'branchId' => $this->branchId,
            ]),
        ];
    }

    protected function getHeaderActions(): array
    {
        $dateLabel = Carbon::parse($this->reportDate ?: today()->toDateString())->format('d M Y');

        $filterAction = Action::make('filterReport')
            ->label(Auth::user()?->isStockOfficer() ? $dateLabel : (($this->principalId || $this->branchId) ? "Filter: {$dateLabel}" : "Tanggal: {$dateLabel}"))
            ->icon('heroicon-m-calendar-days')
            ->color(Auth::user()?->isStockOfficer() ? 'primary' : 'gray')
            ->form([
                DatePicker::make('reportDate')
                    ->label('Tanggal Stock Opname')
                    ->default($this->reportDate ?: today()->toDateString())
                    ->native(false),
                Select::make('principalId')
                    ->label('Principal')
                    ->options(fn () => Principal::query()->orderBy('nama')->pluck('nama', 'id'))
                    ->default($this->principalId)
                    ->searchable()
                    ->preload()
                    ->placeholder('Semua principal'),
                Select::make('branchId')
                    ->label('Cabang')
                    ->options(fn () => Branch::query()->orderBy('nama')->pluck('nama', 'id'))
                    ->default($this->branchId)
                    ->disabled(fn () => Auth::user() && ! Auth::user()?->isCentralAdmin())
                    ->dehydrated()
                    ->searchable()
                    ->preload()
                    ->placeholder('Semua cabang'),
            ])
            ->action(function (array $data): void {
                $this->reportDate = $data['reportDate'] ?: today()->toDateString();
                $this->principalId = filled($data['principalId'] ?? null) ? (int) $data['principalId'] : null;
                $this->branchId = Auth::user()?->isCentralAdmin()
                    ? (filled($data['branchId'] ?? null) ? (int) $data['branchId'] : null)
                    : Auth::user()?->branch_id;
                $this->reportSearch = '';
                $this->syncComparisonDate();
            });

        if (Auth::user()?->isStockOfficer()) {
            return [$filterAction];
        }

        return [
            $filterAction,
            Action::make('printSelisih')
                ->label('Print Selisih')
                ->icon('heroicon-m-printer')
                ->color('gray')
                ->action(fn () => $this->js("document.body.dataset.printReport = 'selisih'; window.print()")),
            Action::make('printComparison')
                ->label('Print Perbandingan')
                ->icon('heroicon-m-printer')
                ->color('gray')
                ->action(fn () => $this->js("document.body.dataset.printReport = 'comparison'; window.print()")),
            ActionGroup::make([
                Action::make('exportDaily')
                    ->label('Download Laporan Harian')
                    ->icon('heroicon-m-arrow-down-tray')
                    ->action('exportDailyCsv'),

                Action::make('exportSelisih')
                    ->label('Download Data Selisih')
                    ->icon('heroicon-m-arrow-down-tray')
                    ->action('exportSelisihCsv'),

                Action::make('exportSelisihComparison')
                    ->label('Download Perbandingan Selisih')
                    ->icon('heroicon-m-arrow-down-tray')
                    ->action('exportSelisihComparisonCsv'),
            ])
                ->label('Export CSV')
                ->icon('heroicon-m-arrow-down-tray')
                ->color('warning'),
        ];
    }

    public function exportDailyCsv(): StreamedResponse
    {
        $csv = app(ReportService::class)->buildDailyCsv($this->reportDate, $this->principalId, $this->branchId);
        $filename = 'laporan-harian-' . $this->reportDate . $this->principalFilenameSuffix() . $this->branchFilenameSuffix() . '.csv';

        return response()->streamDownload(
            fn () => print($csv),
            $filename,
            ['Content-Type' => 'text/csv']
        );
    }

    public function exportSelisihCsv(): StreamedResponse
    {
        $csv = app(ReportService::class)->buildSelisihCsv($this->reportDate, $this->principalId, $this->branchId);
        $filename = 'selisih-' . $this->reportDate . $this->principalFilenameSuffix() . $this->branchFilenameSuffix() . '.csv';

        return response()->streamDownload(
            fn () => print($csv),
            $filename,
            ['Content-Type' => 'text/csv']
        );
    }

    public function exportSelisihComparisonCsv(): StreamedResponse
    {
        $this->syncComparisonDate();

        $csv = app(ReportService::class)->buildSelisihComparisonCsv(
            $this->comparisonDate,
            $this->reportDate,
            $this->principalId,
            $this->branchId
        );
        $filename = 'perbandingan-selisih-' . $this->comparisonDate . '-vs-' . $this->reportDate . $this->principalFilenameSuffix() . $this->branchFilenameSuffix() . '.csv';

        return response()->streamDownload(
            fn () => print($csv),
            $filename,
            ['Content-Type' => 'text/csv']
        );
    }

    public function getSelisihComparisonRows()
    {
        $this->syncComparisonDate();

        if (! $this->comparisonDate) {
            return collect();
        }

        return app(ReportService::class)->getSelisihComparison(
            $this->comparisonDate,
            $this->reportDate,
            $this->principalId,
            $this->branchId
        );
    }

    public function getFoundItems()
    {
        return app(ReportService::class)->getFoundItems($this->reportDate, $this->principalId, $this->branchId);
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
                    ->when($this->branchId, fn (Builder $q) => $q->whereHas(
                        'stockSession',
                        fn (Builder $q) => $q->where('branch_id', $this->branchId)
                    ))
            )
            ->columns([
                TextColumn::make('stockSession.branch.nama')
                    ->label('Cabang')
                    ->placeholder('-')
                    ->sortable(),

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
                    ->formatStateUsing(fn ($state, StockSessionItem $record) => app(ReportService::class)->formatSignedBaseQty($state, $record))
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

                SelectFilter::make('branch')
                    ->label('Cabang')
                    ->relationship('stockSession.branch', 'nama')
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

    protected function branchFilenameSuffix(): string
    {
        if (! $this->branchId) {
            return '';
        }

        $branch = Branch::find($this->branchId);

        return $branch ? '-' . str($branch->kode ?: $branch->nama)->slug() : '';
    }

    protected function syncComparisonDate(): void
    {
        $this->comparisonDate = app(ReportService::class)->findPreviousStockDate(
            $this->reportDate ?: today()->toDateString(),
            $this->principalId,
            $this->branchId
        ) ?? '';
    }
}
