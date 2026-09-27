<?php

namespace App\Filament\Widgets;

use App\Enums\FisDurumu;
use App\Models\Fis;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\DB;

/** Son 30 günün günlük satış toplamı (aktif fişler). */
class SatisGrafigiWidget extends ChartWidget
{
    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = ['default' => 12, 'xl' => 8];

    protected ?string $heading = 'Son 30 gün satış';

    protected ?string $description = 'Günlük fiş toplamları (₺), iptal edilenler hariç.';

    protected ?string $maxHeight = '280px';

    protected ?string $pollingInterval = '120s';

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getData(): array
    {
        $gun = 30;
        $baslangic = today()->subDays($gun - 1);

        $toplamlar = Fis::query()
            ->where('durum', FisDurumu::Aktif->value)
            ->where('tarih', '>=', $baslangic)
            ->groupBy('gun')
            ->pluck(DB::raw('SUM(genel_toplam) as toplam'), DB::raw('DATE(tarih) as gun'))
            ->all();

        $etiketler = [];
        $veriler = [];

        for ($i = 0; $i < $gun; $i++) {
            $tarih = $baslangic->copy()->addDays($i);
            $etiketler[] = $tarih->format('d.m');
            $veriler[] = (float) ($toplamlar[$tarih->toDateString()] ?? 0);
        }

        return [
            'labels' => $etiketler,
            'datasets' => [[
                'label' => 'Satış (₺)',
                'data' => $veriler,
                'backgroundColor' => '#0f3a29',
                'hoverBackgroundColor' => '#c49a44',
                'borderRadius' => 4,
            ]],
        ];
    }

    protected function getOptions(): array
    {
        return [
            'plugins' => ['legend' => ['display' => false]],
            'scales' => [
                'y' => ['beginAtZero' => true, 'ticks' => ['precision' => 0]],
                'x' => ['grid' => ['display' => false]],
            ],
        ];
    }
}
