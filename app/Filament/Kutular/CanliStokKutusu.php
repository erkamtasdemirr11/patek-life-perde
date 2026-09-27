<?php

namespace App\Filament\Kutular;

use App\Models\Urun;
use App\Support\Bicim;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

/**
 * Formların yanında duran canlı stok kutusu: aranabilir, 30 saniyede bir yenilenir.
 * Ürün kaydı, stok giriş/çıkış ve fiş kayıt sayfalarında ortak kullanılır.
 * (app/Filament/Widgets dışında olduğu için panoda otomatik görünmez.)
 */
class CanliStokKutusu extends TableWidget
{
    public string $baslik = 'Canlı stok';

    public bool $fiyatGoster = false;

    public ?int $haricId = null;

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->heading($this->baslik)
            ->description('30 saniyede bir yenilenir.')
            ->query(
                Urun::query()
                    ->secilebilir()
                    ->with('kategori')
                    ->when($this->haricId, fn (Builder $query, int $id): Builder => $query->whereKeyNot($id)),
            )
            ->poll('30s')
            ->columns([
                TextColumn::make('ad')
                    ->label('Ürün')
                    ->searchable()
                    ->sortable()
                    ->description(fn (Urun $record): ?string => $record->kategori?->ad)
                    ->weight('medium'),
                TextColumn::make('mevcut_stok')
                    ->label('Stok')
                    ->formatStateUsing(fn ($state, Urun $record): string => Bicim::miktar($state, $record->birim))
                    ->color(fn (Urun $record): ?string => $record->kritikStokta() ? 'danger' : null)
                    ->weight(fn (Urun $record): ?string => $record->kritikStokta() ? 'bold' : null)
                    ->alignEnd()
                    ->sortable(),
                TextColumn::make('satis_fiyati')
                    ->label('Fiyat')
                    ->formatStateUsing(fn ($state): string => Bicim::para($state))
                    ->placeholder('—')
                    ->alignEnd()
                    ->visible($this->fiyatGoster),
            ])
            ->searchPlaceholder('Kod veya ad ara')
            ->defaultSort('ad')
            ->paginated([10, 25])
            ->emptyStateHeading('Aktif ürün yok');
    }
}
