<?php

namespace App\Filament\Widgets;

use App\Enums\StokYonu;
use App\Models\StokHareketi;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\DB;

/** Son 30 günde günlük stok hareketi sayısı: giriş ve çıkış ayrı çizgiler. */
class StokHareketGrafigiWidget extends ChartWidget
{
    protected static ?int $sort = 3;

    protected int|string|array $columnSpan = ['default' => 12, 'xl' => 4];

    protected ?string $heading = 'Stok hareketleri';

    protected ?string $description = 'Son 30 gün, günlük işlem sayısı.';

    protected ?string $maxHeight = '280px';

    protected ?string $pollingInterval = '120s';

    protected function getType(): string
    {
        return 'line';
    }

    protected function getData(): array
    {
        $gun = 30;
        $baslangic = today()->subDays($gun - 1);

        $sayilar = StokHareketi::query()
            ->where('created_at', '>=', $baslangic)
            ->groupBy('gun', 'yon')
            ->get([DB::raw('DATE(created_at) as gun'), 'yon', DB::raw('COUNT(*) as adet')])
            ->groupBy(fn ($satir) => $satir->yon->value)
            ->map(fn ($grup) => $grup->pluck('adet', 'gun')->all());

        $etiketler = [];
        $giris = [];
        $cikis = [];

        for ($i = 0; $i < $gun; $i++) {
            $tarih = $baslangic->copy()->addDays($i);
            $anahtar = $tarih->toDateString();
            $etiketler[] = $tarih->format('d.m');
            $giris[] = (int) ($sayilar[StokYonu::Giris->value][$anahtar] ?? 0);
            $cikis[] = (int) ($sayilar[StokYonu::Cikis->value][$anahtar] ?? 0);
        }

        return [
            'labels' => $etiketler,
            'datasets' => [
                [
                    'label' => 'Giriş',
                    'data' => $giris,
                    'borderColor' => '#1f6347',
                    'backgroundColor' => 'rgba(31, 99, 71, .15)',
                    'fill' => true,
                    'tension' => .35,
                    'pointRadius' => 0,
                ],
                [
                    'label' => 'Çıkış',
                    'data' => $cikis,
                    'borderColor' => '#c49a44',
                    'backgroundColor' => 'rgba(196, 154, 68, .15)',
                    'fill' => true,
                    'tension' => .35,
                    'pointRadius' => 0,
                ],
            ],
        ];
    }

    protected function getOptions(): array
    {
        return [
            'plugins' => ['legend' => ['position' => 'bottom']],
            'scales' => [
                'y' => ['beginAtZero' => true, 'ticks' => ['precision' => 0]],
                'x' => ['grid' => ['display' => false], 'ticks' => ['maxTicksLimit' => 6]],
            ],
        ];
    }
}
