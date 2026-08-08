<?php

namespace App\Filament\Resources\Principals;

use App\Filament\Resources\Principals\Pages\CreatePrincipal;
use App\Filament\Resources\Principals\Pages\EditPrincipal;
use App\Filament\Resources\Principals\Pages\ListPrincipals;
use App\Filament\Resources\Principals\Schemas\PrincipalForm;
use App\Filament\Resources\Principals\Tables\PrincipalsTable;
use App\Models\Principal;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class PrincipalResource extends Resource
{
    protected static ?string $model = Principal::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $recordTitleAttribute = 'nama';

    protected static string|\UnitEnum|null $navigationGroup = 'Master Data';

    public static function canViewAny(): bool
    {
        return Auth::user()?->isAdmin() ?? false;
    }

    public static function canEdit(Model $record): bool
    {
        $user = Auth::user();

        return $user?->isAdmin()
            && ($user->isCentralAdmin() || $record->itemMasters()->where('branch_id', $user->branch_id)->exists());
    }

    public static function canCreate(): bool { return Auth::user()?->isCentralAdmin() ?? false; }
    public static function canDelete(Model $record): bool { return Auth::user()?->isCentralAdmin() ?? false; }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->when(
            ! Auth::user()?->isCentralAdmin(),
            fn (Builder $query) => $query->forBranch(Auth::user()?->branch_id)
        );
    }

    public static function form(Schema $schema): Schema
    {
        return PrincipalForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PrincipalsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPrincipals::route('/'),
            'create' => CreatePrincipal::route('/create'),
            'edit' => EditPrincipal::route('/{record}/edit'),
        ];
    }
}
