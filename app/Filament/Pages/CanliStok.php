<?php

namespace App\Filament\Pages;

use App\Filament\Resources\Urunler\UrunResource;
use App\Models\Urun;
use App\Support\Bicim;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/**
 * Anlık stok görünümü. 30 saniyede bir kendini yeniler
 * (paylaşımlı hostingi yormamak için 10 sn yerine 30 sn).
 */
class CanliStok extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSignal;

    protected static string|UnitEnum|null $navigationGroup = 'Stok';

    protected static ?string $navigationLabel = 'Canlı Stok';

    protected static ?string $title = 'Canlı Stok';

    protected static ?string $slug = 'canli-stok';

    protected static ?int $navigationSort = 20;

    public function table(Table $table): Table
    {
        return $table
            ->query(Urun::query()->secilebilir()->with('kategori'))
            ->poll('30s')
            ->columns([
                TextColumn::make('ad')
                    ->label('Ürün')
                    ->searchable()
                    ->sortable()
                    ->description(fn (Urun $record): ?string => $record->renk ?: null),
                TextColumn::make('kategori.ad')
                    ->label('Kategori')
                    ->sortable(),
                TextColumn::make('mevcut_stok')
                    ->label('Mevcut stok')
                    ->formatStateUsing(fn ($state, Urun $record): string => Bicim::miktar($state, $record->birim))
                    ->color(fn (Urun $record): ?string => $record->kritikStokta() ? 'danger' : null)
                    ->weight(fn (Urun $record): ?string => $record->kritikStokta() ? 'bold' : null)
                    ->icon(fn (Urun $record) => $record->kritikStokta() ? Heroicon::OutlinedExclamationTriangle : null)
                    ->alignEnd()
                    ->sortable(),
                TextColumn::make('kritik_stok')
                    ->label('Kritik seviye')
                    ->formatStateUsing(fn ($state, Urun $record): string => Bicim::miktar($state, $record->birim))
                    ->alignEnd()
                    ->sortable()
                    ->toggleable(),
            ])
            ->filters([
                SelectFilter::make('kategori_id')
                    ->label('Kategori')
                    ->relationship('kategori', 'ad')
                    ->preload(),
                Filter::make('kritik')
                    ->label('Sadece kritik stoktakiler')
                    ->toggle()
                    ->query(fn (Builder $query): Builder => $query->whereColumn('mevcut_stok', '<=', 'kritik_stok')),
            ])
            ->recordUrl(fn (Urun $record): string => UrunResource::getUrl('edit', ['record' => $record]))
            ->defaultSort('ad')
            ->paginated([25, 50, 100])
            ->emptyStateHeading('Gösterilecek ürün yok')
            ->emptyStateDescription('Ürün Kartları sayfasından aktif ürün ekleyin.');
    }

    public function content(Schema $schema): Schema
    {
        return $schema
            ->components([
                EmbeddedTable::make(),
            ]);
    }
}
