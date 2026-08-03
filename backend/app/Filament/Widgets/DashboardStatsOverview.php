<?php

namespace App\Filament\Widgets;

use App\Enums\StockSessionStatus;
use App\Enums\UserRole;
use App\Models\StockSession;
use App\Models\StockSessionItem;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Auth;

class DashboardStatsOverview extends BaseWidget
{
    protected static ?int $sort = 2;

    public static function canView(): bool
    {
        return Auth::user()?->isAdmin() === true;
    }

    protected function getStats(): array
    {
        $user = Auth::user();
        $today = now()->toDateString();

        $sessions = StockSession::query()
            ->whereDate('session_date', $today)
            ->when(! $user?->isCentralAdmin(), fn ($query) => $query->where('branch_id', $user?->branch_id))
            ->when(
                $user?->role === UserRole::StockOfficer,
                fn ($query) => $query->where('assigned_to', $user->id)
            );

        $activeSessions = (clone $sessions)
            ->whereIn('status', [StockSessionStatus::Open, StockSessionStatus::InProgress])
            ->count();

        $totalItems = (clone $sessions)->sum('total_items');
        $checkedItems = (clone $sessions)->sum('checked_items');
        $matchedItems = (clone $sessions)->sum('matched_items');
        $mismatchedItems = (clone $sessions)->sum('mismatched_items');
        $progress = $totalItems > 0 ? round(($checkedItems / $totalItems) * 100) : 0;

        $checkedToday = StockSessionItem::query()
            ->whereDate('checked_at', $today)
            ->when(! $user?->isCentralAdmin(), fn ($query) => $query->whereHas('stockSession', fn ($query) => $query->where('branch_id', $user?->branch_id)))
            ->when(
                $user?->role === UserRole::StockOfficer,
                fn ($query) => $query->where('checked_by', $user->id)
            )
            ->count();

        return [
            Stat::make('Sesi Aktif', $activeSessions)
                ->description('Open dan sedang berjalan hari ini')
                ->descriptionIcon('heroicon-m-clipboard-document-list')
                ->color('primary'),

            Stat::make('Progress Hari Ini', "{$progress}%")
                ->description("{$checkedItems} dari {$totalItems} item tercek")
                ->descriptionIcon('heroicon-m-chart-bar')
                ->chart([0, max(5, $progress / 2), $progress])
                ->color($progress >= 100 ? 'success' : 'warning'),

            Stat::make('Item Sesuai', $matchedItems)
                ->description('Qty aktual sama dengan sistem')
                ->descriptionIcon('heroicon-m-check-circle')
                ->color('success'),

            Stat::make('Item Selisih', $mismatchedItems)
                ->description('Perlu dicek ulang atau adjustment')
                ->descriptionIcon('heroicon-m-exclamation-triangle')
                ->color($mismatchedItems > 0 ? 'danger' : 'gray'),

            Stat::make('Scan Hari Ini', $checkedToday)
                ->description('Input aktual yang tercatat hari ini')
                ->descriptionIcon('heroicon-m-qr-code')
                ->color('info'),
        ];
    }
}
