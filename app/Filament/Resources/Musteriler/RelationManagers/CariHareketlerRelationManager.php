<?php

namespace App\Filament\Resources\Musteriler\RelationManagers;

use App\Models\CariHareketi;
use App\Support\Bicim;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/** Müşteri kartındaki salt okunur cari hareket listesi. */
class CariHareketlerRelationManager extends RelationManager
{
    protected static string $relationship = 'cariHareketler';

    protected static ?string $title = 'Cari hareketler';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('tarih')
                    ->label('Tarih')
                    ->dateTime('d.m.Y H:i')
                    ->sortable(),
                TextColumn::make('tip')
                    ->label('Tip')
                    ->badge(),
                TextColumn::make('kaynak')
                    ->label('Kaynak'),
                TextColumn::make('aciklama')
                    ->label('Açıklama')
                    ->placeholder('—')
                    ->wrap(),
                TextColumn::make('tutar')
                    ->label('Tutar')
                    ->formatStateUsing(fn ($state): string => Bicim::para($state))
                    ->alignEnd(),
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
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('id', 'desc')
            ->paginated([10, 25, 50])
            ->emptyStateHeading('Cari hareket yok');
    }
}
