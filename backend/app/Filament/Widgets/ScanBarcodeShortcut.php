<?php

namespace App\Filament\Widgets;

use App\Enums\UserRole;
use Filament\Widgets\Widget;
use Illuminate\Support\Facades\Auth;

class ScanBarcodeShortcut extends Widget
{
    protected static ?int $sort = 1;

    protected string $view = 'filament.widgets.scan-barcode-shortcut';

    public static function canView(): bool
    {
        $user = Auth::user();

        return $user && in_array($user->role, [UserRole::Admin, UserRole::StockOfficer], true);
    }

    protected function getViewData(): array
    {
        return [
            'url' => url('/admin/stock-scanning'),
        ];
    }
}
