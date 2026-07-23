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

    public ?int $principalId = null;

    public ?int $branchId = null;

    protected $queryString = ['reportDate', 'principalId', 'branchId'];

    public function mount(): void
    {
        $this->reportDate = $this->reportDate ?: today()->toDateString();

        if (Auth::user()?->isAdmin() && ! Auth::user()?->isCentralAdmin()) {
            $this->branchId = Auth::user()?->branch_id;
        }
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
                'branchId' => $this->branchId,
            ]),
        ];
    }

    protected function getHeaderActions(): array
    {
        $dateLabel = Carbon::parse($this->reportDate ?: today()->toDateString())->format('d M Y');

        return [
            Action::make('filterReport')
                ->label(($this->principalId || $this->branchId) ? "Filter: {$dateLabel}" : "Tanggal: {$dateLabel}")
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
                    Select::make('branchId')
                        ->label('Cabang')
                        ->options(fn () => Branch::query()->orderBy('nama')->pluck('nama', 'id'))
                        ->default($this->branchId)
                        ->disabled(fn () => Auth::user()?->isAdmin() && ! Auth::user()?->isCentralAdmin())
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
                    ->formatStateUsing(fn ($state, StockSessionItem $record) => app(ReportService::class)->formatBaseQty($state, $record))
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
}
