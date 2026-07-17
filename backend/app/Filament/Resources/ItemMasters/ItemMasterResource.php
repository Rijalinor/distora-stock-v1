<?php

namespace App\Filament\Resources\ItemMasters;

use App\Filament\Resources\ItemMasters\Pages\CreateItemMaster;
use App\Filament\Resources\ItemMasters\Pages\EditItemMaster;
use App\Filament\Resources\ItemMasters\Pages\ListItemMasters;
use App\Filament\Resources\ItemMasters\Schemas\ItemMasterForm;
use App\Filament\Resources\ItemMasters\Tables\ItemMastersTable;
use App\Models\ItemMaster;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;


class ItemMasterResource extends Resource
{
    protected static ?string $model = ItemMaster::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArchiveBox;

    protected static ?string $recordTitleAttribute = 'nama_barang';

    protected static ?string $navigationLabel = 'Item Master';

    protected static ?string $pluralModelLabel = 'Item Master';

    protected static ?string $modelLabel = 'Item Master';

    protected static string|\UnitEnum|null $navigationGroup = 'Master Data';

    public static function form(Schema $schema): Schema
    {
        return ItemMasterForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ItemMastersTable::configure($table);
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
            'index' => ListItemMasters::route('/'),
            'create' => CreateItemMaster::route('/create'),
            'edit' => EditItemMaster::route('/{record}/edit'),
        ];
    }
}
