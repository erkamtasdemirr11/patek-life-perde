<?php

namespace App\Filament\Resources\Kategoriler;

use App\Filament\Resources\Kategoriler\Pages\ManageKategoriler;
use App\Models\Kategori;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

class KategoriResource extends Resource
{
    protected static ?string $model = Kategori::class;

    protected static ?string $slug = 'kategoriler';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTag;

    protected static string|UnitEnum|null $navigationGroup = 'Ürünler';

    protected static ?string $navigationLabel = 'Kategoriler';

    protected static ?string $modelLabel = 'Kategori';

    protected static ?string $pluralModelLabel = 'Kategoriler';

    protected static ?int $navigationSort = 20;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('ad')
                    ->label('Kategori adı')
                    ->required()
                    ->unique(ignoreRecord: true)
                    ->maxLength(100),
                TextInput::make('sira')
                    ->label('Sıra')
                    ->integer()
                    ->minValue(0)
                    ->default(0)
                    ->helperText('Listede de sürükleyerek sıralayabilirsiniz.'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('ad')
                    ->label('Kategori')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('urunler_count')
                    ->label('Ürün sayısı')
                    ->counts('urunler')
                    ->sortable(),
            ])
            ->defaultSort('sira')
            ->reorderable('sira')
            ->recordActions([
                EditAction::make(),
                DeleteAction::make()
                    // Ürünü olan kategori silinemez; geçmiş kayıtlar korunur.
                    ->hidden(fn (Kategori $record): bool => $record->urunler_count > 0),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageKategoriler::route('/'),
        ];
    }
}
