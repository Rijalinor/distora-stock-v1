<?php

namespace App\Filament\Resources\StockSessions;

use App\Filament\Resources\StockSessions\Pages\ListStockSessions;
use App\Filament\Resources\StockSessions\Pages\ViewStockSession;
use App\Filament\Resources\StockSessions\RelationManagers\ItemsRelationManager;
use App\Filament\Resources\StockSessions\Tables\StockSessionsTable;
use App\Models\StockSession;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class StockSessionResource extends Resource
{
    protected static ?string $model = StockSession::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    protected static ?string $navigationLabel = 'Sesi Stock';

    protected static ?string $modelLabel = 'Sesi Stock';

    protected static ?string $pluralModelLabel = 'Sesi Stock Opname';

    protected static string|\UnitEnum|null $navigationGroup = 'Stock Opname';

    protected static ?int $navigationSort = 2;

    public static function form(Schema $schema): Schema
    {
        return $schema;
    }

    public static function table(Table $table): Table
    {
        return StockSessionsTable::configure($table);
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();
        $user = Auth::user();

        if ($user && ! $user->isCentralAdmin() && $user->branch_id) {
            $query->where('branch_id', $user->branch_id);
        }

        return $query;
    }

    public static function getRelations(): array
    {
        return [
            ItemsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListStockSessions::route('/'),
            'view' => ViewStockSession::route('/{record}'),
        ];
    }
}
