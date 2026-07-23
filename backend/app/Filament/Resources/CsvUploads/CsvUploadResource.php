<?php

namespace App\Filament\Resources\CsvUploads;

use App\Filament\Resources\CsvUploads\Pages\CreateCsvUpload;
use App\Filament\Resources\CsvUploads\Pages\ListCsvUploads;
use App\Filament\Resources\CsvUploads\Pages\ViewCsvUpload;
use App\Filament\Resources\CsvUploads\Schemas\CsvUploadForm;
use App\Filament\Resources\CsvUploads\Tables\CsvUploadsTable;
use App\Models\CsvUpload;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class CsvUploadResource extends Resource
{
    protected static ?string $model = CsvUpload::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowUpTray;

    protected static ?string $navigationLabel = 'Upload Stok Harian';

    protected static ?string $modelLabel = 'Upload Stok';

    protected static ?string $pluralModelLabel = 'Upload Stok Harian';

    protected static string|\UnitEnum|null $navigationGroup = 'Stock Opname';

    protected static ?int $navigationSort = 1;

    public static function canViewAny(): bool
    {
        return Auth::user()?->isAdmin() ?? false;
    }

    public static function form(Schema $schema): Schema
    {
        return CsvUploadForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return CsvUploadsTable::configure($table);
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
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCsvUploads::route('/'),
            'create' => CreateCsvUpload::route('/create'),
            'view' => ViewCsvUpload::route('/{record}'),
        ];
    }
}
