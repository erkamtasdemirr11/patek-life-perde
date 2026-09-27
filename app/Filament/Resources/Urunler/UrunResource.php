<?php

namespace App\Filament\Resources\Urunler;

use App\Filament\Resources\Urunler\Pages\CreateUrun;
use App\Filament\Resources\Urunler\Pages\EditUrun;
use App\Filament\Resources\Urunler\Pages\ListUrunler;
use App\Filament\Resources\Urunler\Schemas\UrunForm;
use App\Filament\Resources\Urunler\Tables\UrunlerTable;
use App\Models\Urun;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use UnitEnum;

class UrunResource extends Resource
{
    protected static ?string $model = Urun::class;

    protected static ?string $slug = 'urunler';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSwatch;

    protected static string|UnitEnum|null $navigationGroup = 'Ürünler';

    protected static ?string $navigationLabel = 'Ürün Kartları';

    protected static ?string $modelLabel = 'Ürün';

    protected static ?string $pluralModelLabel = 'Ürünler';

    protected static ?int $navigationSort = 10;

    protected static ?string $recordTitleAttribute = 'ad';

    public static function form(Schema $schema): Schema
    {
        return UrunForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return UrunlerTable::configure($table);
    }

    /** Arşivlenmiş ürünler de filtreyle görülebilsin. */
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->withoutGlobalScopes([
            SoftDeletingScope::class,
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListUrunler::route('/'),
            'create' => CreateUrun::route('/create'),
            'edit' => EditUrun::route('/{record}/edit'),
        ];
    }
}
