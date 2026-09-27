<?php

namespace App\Filament\Pages;

use App\Enums\StokSebebi;
use App\Enums\StokYonu;
use App\Filament\Resources\Fisler\FisResource;
use App\Filament\Tables\CsvIndir;
use App\Filament\Tables\TarihAraligiFiltresi;
use App\Models\StokHareketi;
use App\Models\Urun;
use App\Support\Bicim;
use BackedEnum;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class StokGecmisi extends GecmisSayfasi
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClock;

    protected static string|UnitEnum|null $navigationGroup = 'Geçmiş';

    protected static ?string $navigationLabel = 'Stok Geçmişi';

    protected static ?string $title = 'Stok Geçmişi';

    protected static ?string $slug = 'stok-gecmisi';

    protected static ?int $navigationSort = 10;

    public function table(Table $table): Table
    {
        return $table
            ->query(StokHareketi::query()->with(['urun.kategori', 'user', 'fis']))
            ->columns([
                TextColumn::make('created_at')
                    ->label('Tarih')
                    ->dateTime('d.m.Y H:i')
                    ->sortable(),
                TextColumn::make('urun.ad')
                    ->label('Ürün')
                    ->searchable()
                    ->description(fn (StokHareketi $record): ?string => $record->urun->kategori?->ad),
                TextColumn::make('yon')
                    ->label('Yön')
                    ->badge(),
                TextColumn::make('sebep')
                    ->label('Sebep'),
                TextColumn::make('miktar')
                    ->label('Miktar')
                    ->formatStateUsing(fn ($state, StokHareketi $record): string => ($record->yon === StokYonu::Giris ? '+' : '−') . ' ' . Bicim::miktar($state, $record->urun->birim))
                    ->color(fn (StokHareketi $record): string => $record->yon === StokYonu::Giris ? 'success' : 'danger')
                    ->alignEnd()
                    ->sortable(),
                TextColumn::make('fis.fis_no')
                    ->label('Fiş')
                    ->url(fn (StokHareketi $record): ?string => $record->fis ? FisResource::getUrl('view', ['record' => $record->fis]) : null)
                    ->color('primary')
                    ->placeholder('—'),
                TextColumn::make('aciklama')
                    ->label('Açıklama')
                    ->limit(40)
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('user.name')
                    ->label('İşleyen')
                    ->toggleable(),
            ])
            ->filters([
                TarihAraligiFiltresi::make('created_at'),
                SelectFilter::make('urun_id')
                    ->label('Ürün')
                    ->relationship('urun', 'ad', fn (Builder $query): Builder => $query->withTrashed())
                    ->searchable()
                    ->preload(),
                SelectFilter::make('yon')
                    ->label('Yön')
                    ->options(StokYonu::class),
                SelectFilter::make('sebep')
                    ->label('Sebep')
                    ->options(StokSebebi::class),
                SelectFilter::make('user_id')
                    ->label('İşleyen')
                    ->relationship('user', 'name')
                    ->preload(),
            ])
            ->headerActions([
                CsvIndir::aksiyon(
                    'stok-gecmisi',
                    ['Tarih', 'Ürün', 'Kategori', 'Yön', 'Sebep', 'Miktar', 'Birim', 'Fiş', 'Açıklama', 'İşleyen'],
                    fn (StokHareketi $h): array => [
                        $h->created_at?->format('d.m.Y H:i'),
                        $h->urun->ad,
                        $h->urun->kategori?->ad,
                        $h->yon->getLabel(),
                        $h->sebep->getLabel(),
                        Bicim::sayi($h->miktar),
                        $h->urun->birim->kisa(),
                        $h->fis?->fis_no,
                        $h->aciklama,
                        $h->user?->name,
                    ],
                    ['urun.kategori', 'user', 'fis'],
                ),
            ])
            ->defaultSort('created_at', 'desc')
            ->paginated([25, 50, 100])
            ->emptyStateHeading('Henüz stok hareketi yok');
    }
}
