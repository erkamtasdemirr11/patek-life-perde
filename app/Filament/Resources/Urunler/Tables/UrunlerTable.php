<?php

namespace App\Filament\Resources\Urunler\Tables;

use App\Models\Urun;
use App\Support\Bicim;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\RestoreAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class UrunlerTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('gorsel')
                    ->label('')
                    ->disk('public')
                    ->circular()
                    ->toggleable(),
                TextColumn::make('ad')
                    ->label('Ürün')
                    ->searchable()
                    ->sortable()
                    ->description(fn (Urun $record): ?string => $record->renk ?: null),
                TextColumn::make('kategori.ad')
                    ->label('Kategori')
                    ->sortable(),
                TextColumn::make('birim')
                    ->label('Birim')
                    ->badge(),
                TextColumn::make('satis_fiyati')
                    ->label('Satış fiyatı')
                    ->formatStateUsing(fn ($state): string => Bicim::para($state))
                    ->placeholder('—')
                    ->alignEnd()
                    ->sortable(),
                TextColumn::make('mevcut_stok')
                    ->label('Stok')
                    ->formatStateUsing(fn ($state, Urun $record): string => Bicim::miktar($state, $record->birim))
                    ->color(fn (Urun $record): ?string => $record->kritikStokta() ? 'danger' : null)
                    ->weight(fn (Urun $record): ?string => $record->kritikStokta() ? 'bold' : null)
                    ->alignEnd()
                    ->sortable(),
                IconColumn::make('aktif')
                    ->label('Aktif')
                    ->boolean(),
                TextColumn::make('deleted_at')
                    ->label('Silinme')
                    ->dateTime('d.m.Y H:i')
                    ->placeholder('—')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('kategori_id')
                    ->label('Kategori')
                    ->relationship('kategori', 'ad')
                    ->preload()
                    ->searchable(),
                TernaryFilter::make('aktif')
                    ->label('Durum')
                    ->trueLabel('Aktif')
                    ->falseLabel('Pasif'),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make()
                    ->label('Sil')
                    ->modalHeading('Ürünü sil')
                    ->modalDescription('Ürün listeden kaldırılır; geçmiş kayıtlarda adıyla görünmeye devam eder. "Son silinenler" sekmesinden geri alabilirsiniz.')
                    ->successNotificationTitle('Ürün silindi'),
                RestoreAction::make()
                    ->label('Geri al')
                    ->successNotificationTitle('Ürün geri alındı'),
            ])
            ->defaultSort('ad');
    }
}
