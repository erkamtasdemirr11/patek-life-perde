<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\Fisler\FisResource;
use App\Models\Fis;
use App\Support\Bicim;
use Filament\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

/** Panodaki son 6 fiş; satıra tıklayınca fiş açılır. */
class SonFislerWidget extends TableWidget
{
    protected static ?int $sort = 4;

    protected int|string|array $columnSpan = ['default' => 12, 'xl' => 6];

    protected static ?string $heading = 'Son fişler';

    public function table(Table $table): Table
    {
        return $table
            ->query(Fis::query()->with('musteri')->latest('tarih')->latest('id')->limit(6))
            ->paginated(false)
            ->columns([
                TextColumn::make('fis_no')
                    ->label('Fiş no')
                    ->weight('bold'),
                TextColumn::make('tarih')
                    ->label('Tarih')
                    ->dateTime('d.m.Y H:i'),
                TextColumn::make('musteri.firma_adi')
                    ->label('Müşteri')
                    ->limit(28),
                TextColumn::make('genel_toplam')
                    ->label('Tutar')
                    ->formatStateUsing(fn ($state): string => Bicim::para($state))
                    ->alignEnd(),
                TextColumn::make('durum')
                    ->label('Durum')
                    ->badge(),
            ])
            ->recordUrl(fn (Fis $record): string => FisResource::getUrl('view', ['record' => $record]))
            ->headerActions([
                Action::make('tumu')
                    ->label('Tümünü gör')
                    ->link()
                    ->url(FisResource::getUrl()),
            ])
            ->emptyStateHeading('Henüz fiş kesilmemiş')
            ->emptyStateDescription('İlk fişi "Yeni fiş" butonuyla kesebilirsiniz.');
    }
}
