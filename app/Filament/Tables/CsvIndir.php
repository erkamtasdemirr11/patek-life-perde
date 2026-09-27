<?php

namespace App\Filament\Tables;

use Closure;
use Filament\Actions\Action;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Contracts\HasTable;
use Illuminate\Database\Eloquent\Model;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Tablonun o anki filtre ve sıralamasıyla CSV üretir; kuyruk gerektirmez.
 * Noktalı virgül ayraç + UTF-8 BOM: Türkçe Excel dosyayı doğrudan doğru açar.
 */
final class CsvIndir
{
    /**
     * @param  array<string>  $basliklar
     * @param  Closure(Model): array<int, mixed>  $satir  bir kaydı CSV satırına çevirir
     * @param  array<string>  $iliskiler  önceden yüklenecek ilişkiler
     */
    public static function aksiyon(string $dosyaOnEki, array $basliklar, Closure $satir, array $iliskiler = []): Action
    {
        return Action::make('csv')
            ->label('CSV indir')
            ->icon(Heroicon::OutlinedArrowDownTray)
            ->color('gray')
            ->action(function (HasTable $livewire) use ($dosyaOnEki, $basliklar, $satir, $iliskiler): StreamedResponse {
                $sorgu = $livewire->getFilteredSortedTableQuery()->with($iliskiler);
                $dosya = $dosyaOnEki . '-' . now()->format('Ymd-Hi') . '.csv';

                return response()->streamDownload(function () use ($sorgu, $basliklar, $satir): void {
                    $cikti = fopen('php://output', 'w');
                    fwrite($cikti, "\xEF\xBB\xBF");
                    fputcsv($cikti, $basliklar, ';', '"', '');

                    foreach ($sorgu->lazy(500) as $kayit) {
                        fputcsv($cikti, $satir($kayit), ';', '"', '');
                    }

                    fclose($cikti);
                }, $dosya, ['Content-Type' => 'text/csv; charset=UTF-8']);
            });
    }
}
