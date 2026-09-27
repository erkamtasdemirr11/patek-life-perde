<?php

namespace App\Filament\Tables;

use Filament\Forms\Components\DatePicker;
use Filament\Tables\Filters\Filter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

/** Geçmiş ve log sayfalarındaki ortak tarih aralığı filtresi. */
final class TarihAraligiFiltresi
{
    public static function make(string $sutun = 'created_at', string $ad = 'tarih'): Filter
    {
        return Filter::make($ad)
            ->label('Tarih aralığı')
            ->schema([
                DatePicker::make('baslangic')
                    ->label('Başlangıç'),
                DatePicker::make('bitis')
                    ->label('Bitiş'),
            ])
            ->query(fn (Builder $query, array $data): Builder => $query
                ->when($data['baslangic'] ?? null, fn (Builder $q, string $tarih): Builder => $q->whereDate($sutun, '>=', $tarih))
                ->when($data['bitis'] ?? null, fn (Builder $q, string $tarih): Builder => $q->whereDate($sutun, '<=', $tarih)))
            ->indicateUsing(function (array $data): array {
                $gostergeler = [];

                if ($data['baslangic'] ?? null) {
                    $gostergeler[] = 'Başlangıç: ' . Carbon::parse($data['baslangic'])->format('d.m.Y');
                }

                if ($data['bitis'] ?? null) {
                    $gostergeler[] = 'Bitiş: ' . Carbon::parse($data['bitis'])->format('d.m.Y');
                }

                return $gostergeler;
            });
    }
}
