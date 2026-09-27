<?php

namespace App\Filament\Pages;

use App\Enums\FisDurumu;
use App\Filament\Resources\Fisler\FisResource;
use App\Filament\Tables\CsvIndir;
use App\Filament\Tables\TarihAraligiFiltresi;
use App\Models\Fis;
use App\Support\Bicim;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class FisGecmisi extends GecmisSayfasi
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentText;

    protected static string|UnitEnum|null $navigationGroup = 'Geçmiş';

    protected static ?string $navigationLabel = 'Fiş Geçmişi';

    protected static ?string $title = 'Fiş Geçmişi';

    protected static ?string $slug = 'fis-gecmisi';

    protected static ?int $navigationSort = 20;

    public function table(Table $table): Table
    {
        return $table
            ->query(Fis::query()->with(['musteri', 'user', 'iptalEden'])->withCount('kalemler'))
            ->columns([
                TextColumn::make('fis_no')
                    ->label('Fiş no')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),
                TextColumn::make('tarih')
                    ->label('Tarih')
                    ->dateTime('d.m.Y H:i')
                    ->sortable(),
                TextColumn::make('musteri.firma_adi')
                    ->label('Müşteri')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('kalemler_count')
                    ->label('Kalem')
                    ->alignEnd(),
                TextColumn::make('ara_toplam')
                    ->label('Ara toplam')
                    ->formatStateUsing(fn ($state): string => Bicim::para($state))
                    ->alignEnd()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('iskonto')
                    ->label('İskonto')
                    ->formatStateUsing(fn ($state): string => Bicim::para($state))
                    ->alignEnd()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('genel_toplam')
                    ->label('Genel toplam')
                    ->formatStateUsing(fn ($state): string => Bicim::para($state))
                    ->alignEnd()
                    ->sortable(),
                TextColumn::make('durum')
                    ->label('Durum')
                    ->badge(),
                TextColumn::make('user.name')
                    ->label('Kesen')
                    ->toggleable(),
                TextColumn::make('iptal_tarihi')
                    ->label('İptal tarihi')
                    ->dateTime('d.m.Y H:i')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('iptalEden.name')
                    ->label('İptal eden')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('iptal_nedeni')
                    ->label('İptal nedeni')
                    ->limit(40)
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                TarihAraligiFiltresi::make('tarih'),
                SelectFilter::make('musteri_id')
                    ->label('Müşteri')
                    ->relationship('musteri', 'firma_adi', fn (Builder $query): Builder => $query->withTrashed())
                    ->searchable()
                    ->preload(),
                SelectFilter::make('durum')
                    ->label('Durum')
                    ->options(FisDurumu::class),
            ])
            ->recordActions([
                Action::make('goruntule')
                    ->label('Görüntüle')
                    ->icon(Heroicon::OutlinedEye)
                    ->url(fn (Fis $record): string => FisResource::getUrl('view', ['record' => $record])),
                FisResource::pdfAction(),
            ])
            ->headerActions([
                CsvIndir::aksiyon(
                    'fis-gecmisi',
                    ['Fiş no', 'Tarih', 'Müşteri', 'Kalem', 'Ara toplam', 'İskonto', 'Genel toplam', 'Durum', 'Kesen', 'Açıklama', 'İptal tarihi', 'İptal eden', 'İptal nedeni'],
                    fn (Fis $f): array => [
                        $f->fis_no,
                        $f->tarih->format('d.m.Y H:i'),
                        $f->musteri->firma_adi,
                        $f->kalemler_count ?? $f->kalemler()->count(),
                        Bicim::sayi($f->ara_toplam),
                        Bicim::sayi($f->iskonto),
                        Bicim::sayi($f->genel_toplam),
                        $f->durum->getLabel(),
                        $f->user?->name,
                        $f->aciklama,
                        $f->iptal_tarihi?->format('d.m.Y H:i'),
                        $f->iptalEden?->name,
                        $f->iptal_nedeni,
                    ],
                    ['musteri', 'user', 'iptalEden'],
                ),
            ])
            ->defaultSort('tarih', 'desc')
            ->paginated([25, 50, 100])
            ->emptyStateHeading('Henüz fiş kesilmemiş');
    }
}
