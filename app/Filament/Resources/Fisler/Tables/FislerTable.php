<?php

namespace App\Filament\Resources\Fisler\Tables;

use App\Enums\FisDurumu;
use App\Enums\OdemeTipi;
use App\Filament\Resources\Fisler\FisResource;
use App\Support\Bicim;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class FislerTable
{
    public static function configure(Table $table): Table
    {
        return $table
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
                    ->counts('kalemler')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('genel_toplam')
                    ->label('Genel toplam')
                    ->formatStateUsing(fn ($state): string => Bicim::para($state))
                    ->alignEnd()
                    ->sortable(),
                TextColumn::make('odeme_tipi')
                    ->label('Ödeme')
                    ->badge(),
                TextColumn::make('durum')
                    ->label('Durum')
                    ->badge(),
                TextColumn::make('user.name')
                    ->label('Kesen')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('durum')
                    ->label('Durum')
                    ->options(FisDurumu::class),
                SelectFilter::make('odeme_tipi')
                    ->label('Ödeme')
                    ->options(OdemeTipi::class),
            ])
            ->recordActions([
                ViewAction::make(),
                FisResource::pdfAction(),
                FisResource::iptalAction(),
            ])
            ->defaultSort('tarih', 'desc');
    }
}
