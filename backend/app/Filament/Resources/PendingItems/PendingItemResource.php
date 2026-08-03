<?php

namespace App\Filament\Resources\PendingItems;

use App\Filament\Resources\PendingItems\Pages\EditPendingItem;
use App\Filament\Resources\PendingItems\Pages\ListPendingItems;
use App\Filament\Resources\PendingItems\Schemas\PendingItemForm;
use App\Filament\Resources\PendingItems\Tables\PendingItemsTable;
use App\Models\PendingItem;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class PendingItemResource extends Resource
{
    protected static ?string $model = PendingItem::class;
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedQuestionMarkCircle;
    protected static ?string $navigationLabel = 'Barang Pending';
    protected static ?string $modelLabel = 'Barang Pending';
    protected static ?string $pluralModelLabel = 'Barang Pending';
    protected static string|\UnitEnum|null $navigationGroup = 'Master Data';

    public static function canViewAny(): bool { return Auth::user()?->isAdmin() ?? false; }
    public static function canCreate(): bool { return false; }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->when(! Auth::user()?->isCentralAdmin(), fn (Builder $query) => $query->where('branch_id', Auth::user()?->branch_id));
    }

    public static function form(Schema $schema): Schema { return PendingItemForm::configure($schema); }
    public static function table(Table $table): Table { return PendingItemsTable::configure($table); }
    public static function getPages(): array { return ['index' => ListPendingItems::route('/'), 'edit' => EditPendingItem::route('/{record}/edit')]; }
}
