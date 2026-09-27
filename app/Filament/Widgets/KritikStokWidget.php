<?php

namespace App\Filament\Widgets;

use App\Filament\Pages\CanliStok;
use App\Filament\Resources\Urunler\UrunResource;
use App\Models\Urun;
use App\Support\Bicim;
use Filament\Actions\Action;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

class KritikStokWidget extends TableWidget
{
    protected static ?int $sort = 5;

    protected int|string|array $columnSpan = ['default' => 12, 'xl' => 6];

    protected static ?string $heading = 'Kritik stoktaki ürünler';

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Urun::query()
                    ->secilebilir()
                    ->whereColumn('mevcut_stok', '<=', 'kritik_stok')
                    ->with('kategori'),
            )
            ->poll('30s')
            ->columns([
                TextColumn::make('ad')
                    ->label('Ürün'),
                TextColumn::make('kategori.ad')
                    ->label('Kategori'),
                TextColumn::make('mevcut_stok')
                    ->label('Mevcut')
                    ->formatStateUsing(fn ($state, Urun $record): string => Bicim::miktar($state, $record->birim))
                    ->color('danger')
                    ->weight('bold')
                    ->alignEnd(),
                TextColumn::make('kritik_stok')
                    ->label('Kritik seviye')
                    ->formatStateUsing(fn ($state, Urun $record): string => Bicim::miktar($state, $record->birim))
                    ->alignEnd(),
                TextColumn::make('eksik')
                    ->label('Eksik')
                    ->state(fn (Urun $record): string => bcsub((string) $record->kritik_stok, (string) $record->mevcut_stok, 2))
                    ->formatStateUsing(fn ($state, Urun $record): string => Bicim::miktar($state, $record->birim))
                    ->alignEnd(),
            ])
            ->recordUrl(fn (Urun $record): string => UrunResource::getUrl('edit', ['record' => $record]))
            ->headerActions([
                Action::make('canliStok')
                    ->label('Canlı Stok')
                    ->link()
                    ->url(CanliStok::getUrl()),
            ])
            ->defaultSort('mevcut_stok')
            ->paginated([6])
            ->emptyStateHeading('Kritik stokta ürün yok')
            ->emptyStateDescription('Tüm ürünler kritik seviyenin üzerinde.')
            ->emptyStateIcon(Heroicon::OutlinedCheckCircle);
    }
}
