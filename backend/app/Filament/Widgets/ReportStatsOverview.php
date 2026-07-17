<?php

namespace App\Filament\Widgets;

use App\Services\ReportService;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class ReportStatsOverview extends BaseWidget
{
    public ?string $date = null;

    protected static ?int $sort = 1;

    protected function getStats(): array
    {
        $summary = app(ReportService::class)->getDailySummary(
            $this->date ?? now()->format('Y-m-d')
        );

        $pct = $summary['total_items'] > 0
            ? round(($summary['checked_items'] / $summary['total_items']) * 100)
            : 0;

        return [
            Stat::make('Sesi', $summary['sessions'])
                ->description('Total sesi hari ini')
                ->color('gray'),

            Stat::make('Total Item', $summary['total_items'])
                ->description('Seluruh item dari semua sesi')
                ->color('gray'),

            Stat::make('Tercek', $summary['checked_items'])
                ->description("{$pct}% dari {$summary['total_items']} item")
                ->color('warning')
                ->chart([0, $pct]),

            Stat::make('Sesuai', $summary['matched_items'])
                ->description('Item dengan qty sesuai sistem')
                ->color('success'),

            Stat::make('Selisih', $summary['mismatched_items'])
                ->description('Item dengan qty berbeda')
                ->color('danger'),
        ];
    }
}
