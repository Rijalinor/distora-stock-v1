<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\ScanBarcodeShortcut;
use Filament\Pages\Dashboard as BaseDashboard;
use Illuminate\Support\Facades\Auth;

class Dashboard extends BaseDashboard
{
    public static function canAccess(): bool
    {
        $user = Auth::user();

        return $user && ($user->isAdmin() || $user->isStockOfficer());
    }

    public function getWidgets(): array
    {
        return [
            ScanBarcodeShortcut::class,
        ];
    }
}
