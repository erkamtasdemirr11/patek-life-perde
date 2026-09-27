<?php

namespace App\Filament\Pages;

use App\Enums\CariKaynagi;
use App\Enums\CariTipi;
use App\Filament\Resources\Fisler\FisResource;
use App\Filament\Tables\CsvIndir;
use App\Filament\Tables\TarihAraligiFiltresi;
use App\Models\CariHareketi;
use App\Support\Bicim;
use BackedEnum;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class CariHareketler extends GecmisSayfasi
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedScale;

    protected static string|UnitEnum|null $navigationGroup = 'Geçmiş';

    protected static ?string $navigationLabel = 'Cari Hareketler';

    protected static ?string $title = 'Cari Hareketler';

    protected static ?string $slug = 'cari-hareketler';

    protected static ?int $navigationSort = 30;

    public function table(Table $table): Table
    {
        return $table
            ->query(CariHareketi::query()->with(['musteri', 'user', 'fis', 'tahsilat']))
            ->columns([
                TextColumn::make('tarih')
                    ->label('Tarih')
                    ->dateTime('d.m.Y H:i')
                    ->sortable(),
                TextColumn::make('musteri.firma_adi')
                    ->label('Müşteri')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),
                TextColumn::make('tip')
                    ->label('Tip')
                    ->badge(),
                TextColumn::make('kaynak')
                    ->label('Kaynak'),
                TextColumn::make('belge')
                    ->label('Belge')
                    ->state(fn (CariHareketi $record): ?string => $record->fis?->fis_no ?? $record->tahsilat?->makbuz_no)
                    ->url(fn (CariHareketi $record): ?string => $record->fis ? FisResource::getUrl('view', ['record' => $record->fis]) : null)
                    ->color(fn (CariHareketi $record): ?string => $record->fis ? 'primary' : null)
                    ->placeholder('—'),
                TextColumn::make('aciklama')
                    ->label('Açıklama')
                    ->limit(40)
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('tutar')
                    ->label('Tutar')
                    ->formatStateUsing(fn ($state, CariHareketi $record): string => ($record->tip === CariTipi::Borc ? '+' : '−') . ' ' . Bicim::para($state))
                    ->color(fn (CariHareketi $record): string => $record->tip === CariTipi::Borc ? 'danger' : 'success')
                    ->alignEnd()
                    ->sortable(),
                TextColumn::make('bakiye_sonrasi')
                    ->label('Bakiye')
                    ->formatStateUsing(fn ($state): string => Bicim::para($state))
                    ->color(fn (CariHareketi $record): ?string => match (bccomp((string) $record->bakiye_sonrasi, '0', 2)) {
                        1 => 'danger',
                        -1 => 'success',
                        default => null,
                    })
                    ->alignEnd(),
                TextColumn::make('user.name')
                    ->label('İşleyen')
                    ->toggleable(),
            ])
            ->filters([
                TarihAraligiFiltresi::make('tarih'),
                SelectFilter::make('musteri_id')
                    ->label('Müşteri')
                    ->relationship('musteri', 'firma_adi', fn (Builder $query): Builder => $query->withTrashed())
                    ->searchable()
                    ->preload(),
                SelectFilter::make('tip')
                    ->label('Tip')
                    ->options(CariTipi::class),
                SelectFilter::make('kaynak')
                    ->label('Kaynak')
                    ->options(CariKaynagi::class),
            ])
            ->headerActions([
                CsvIndir::aksiyon(
                    'cari-hareketler',
                    ['Tarih', 'Müşteri', 'Tip', 'Kaynak', 'Belge', 'Açıklama', 'Borç', 'Alacak', 'Bakiye', 'İşleyen'],
                    fn (CariHareketi $h): array => [
                        $h->tarih->format('d.m.Y H:i'),
                        $h->musteri->firma_adi,
                        $h->tip->getLabel(),
                        $h->kaynak->getLabel(),
                        $h->fis?->fis_no ?? $h->tahsilat?->makbuz_no,
                        $h->aciklama,
                        $h->tip === CariTipi::Borc ? Bicim::sayi($h->tutar) : null,
                        $h->tip === CariTipi::Alacak ? Bicim::sayi($h->tutar) : null,
                        Bicim::sayi($h->bakiye_sonrasi),
                        $h->user?->name,
                    ],
                    ['musteri', 'user', 'fis', 'tahsilat'],
                ),
            ])
            ->defaultSort('tarih', 'desc')
            ->paginated([25, 50, 100])
            ->emptyStateHeading('Henüz cari hareket yok');
    }
}
