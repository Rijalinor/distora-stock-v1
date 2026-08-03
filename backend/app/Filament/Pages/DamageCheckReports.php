<?php

namespace App\Filament\Pages;

use App\Models\Branch;
use App\Models\Principal;
use App\Services\DamageCheckReportService;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DamageCheckReports extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentChartBar;
    protected static ?string $navigationLabel = 'Laporan Barang Rusak';
    protected static ?string $title = 'Laporan Barang Rusak';
    protected static string|\UnitEnum|null $navigationGroup = 'Stock Opname';
    protected static ?int $navigationSort = 4;
    protected string $view = 'filament.pages.damage-check-reports';

    public string $dateFrom = '';
    public string $dateTo = '';
    public ?int $branchId = null;
    public ?int $principalId = null;
    public string $status = '';
    public int $limit = 25;

    public function mount(): void
    {
        $this->dateFrom = today()->startOfMonth()->toDateString();
        $this->dateTo = today()->toDateString();
        $this->branchId = Auth::user()?->isCentralAdmin() ? null : Auth::user()?->branch_id;
    }

    public static function canAccess(): bool
    {
        $user = Auth::user();

        return $user && ($user->isAdmin() || $user->isStockOfficer());
    }

    public function applyFilters(): void
    {
        $this->validate([
            'dateFrom' => ['required', 'date'],
            'dateTo' => ['required', 'date', 'after_or_equal:dateFrom'],
            'branchId' => ['nullable', 'exists:branches,id'],
            'principalId' => ['nullable', 'exists:principals,id'],
            'status' => ['nullable', 'in:open,completed'],
        ]);
        $this->limit = 25;
    }

    public function loadMore(): void
    {
        $this->limit += 25;
    }

    public function getCheckRows()
    {
        return app(DamageCheckReportService::class)
            ->checksQuery(Auth::user(), $this->filters())
            ->latest('id')
            ->limit($this->limit)
            ->get();
    }

    public function getTotalCheckRows(): int
    {
        return app(DamageCheckReportService::class)->checksQuery(Auth::user(), $this->filters())->count();
    }

    public function getSummary(): array
    {
        return app(DamageCheckReportService::class)->summary(Auth::user(), $this->filters());
    }

    public function exportCsv(): StreamedResponse
    {
        $this->applyFilters();
        $csv = app(DamageCheckReportService::class)->buildCsv(Auth::user(), $this->filters());

        return response()->streamDownload(
            fn () => print($csv),
            "laporan-barang-rusak-{$this->dateFrom}-{$this->dateTo}.csv",
            ['Content-Type' => 'text/csv; charset=UTF-8']
        );
    }

    public function exportCheckCsv(int $checkId): StreamedResponse
    {
        $check = app(DamageCheckReportService::class)
            ->checksQuery(Auth::user(), $this->filters())
            ->findOrFail($checkId);
        $filters = $this->filters();
        $filters['check_id'] = $check->id;
        $csv = app(DamageCheckReportService::class)->buildCsv(Auth::user(), $filters);

        return response()->streamDownload(
            fn () => print($csv),
            'barang-rusak-' . $check->reference_number . '.csv',
            ['Content-Type' => 'text/csv; charset=UTF-8']
        );
    }

    public function getBranches()
    {
        return Branch::query()->where('status', true)->orderBy('nama')->get();
    }

    public function getPrincipals()
    {
        return Principal::query()
            ->where('status', true)
            ->when(! Auth::user()?->isCentralAdmin(), fn ($query) => $query->forBranch(Auth::user()?->branch_id))
            ->orderBy('nama')->get();
    }

    private function filters(): array
    {
        return [
            'date_from' => $this->dateFrom ?: today()->startOfMonth()->toDateString(),
            'date_to' => $this->dateTo ?: today()->toDateString(),
            'branch_id' => Auth::user()?->isCentralAdmin() ? $this->branchId : Auth::user()?->branch_id,
            'principal_id' => $this->principalId,
            'status' => $this->status ?: null,
        ];
    }
}
