<?php

namespace App\Filament\Widgets;

use App\Enums\FisDurumu;
use App\Filament\Pages\CanliStok;
use App\Filament\Resources\Musteriler\MusteriResource;
use App\Models\Fis;
use App\Models\Musteri;
use App\Models\Urun;
use App\Support\Bicim;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\DB;

/** Panonun üst şeridi: satış, alacak ve stok özetleri (küçük 7 günlük eğilim çizgileriyle). */
class OzetWidget extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    protected ?string $pollingInterval = '60s';

    protected function getColumns(): int
    {
        return 4;
    }

    protected function getStats(): array
    {
        $bugunkuFisler = Fis::query()->where('durum', FisDurumu::Aktif->value)->whereDate('tarih', today());
        $bugunkuSatis = (string) (clone $bugunkuFisler)->sum('genel_toplam');
        $bugunkuFisAdedi = (clone $bugunkuFisler)->count();

        $aylikSatis = (string) Fis::query()
            ->where('durum', FisDurumu::Aktif->value)
            ->whereBetween('tarih', [now()->startOfMonth(), now()])
            ->sum('genel_toplam');

        $borclular = Musteri::query()->where('bakiye', '>', 0);
        $acikAlacak = (string) (clone $borclular)->sum('bakiye');
        $borcluSayisi = (clone $borclular)->count();

        $kritikUrun = Urun::query()->secilebilir()->whereColumn('mevcut_stok', '<=', 'kritik_stok')->count();

        $son7Gun = $this->gunlukSatis(7);

        return [
            Stat::make('Bugünkü satış', Bicim::para($bugunkuSatis))
                ->description($bugunkuFisAdedi . ' fiş kesildi')
                ->descriptionIcon(Heroicon::OutlinedReceiptPercent)
                ->chart($son7Gun)
                ->color('primary'),

            Stat::make('Bu ay satış', Bicim::para($aylikSatis))
                ->description('Ayın başından bugüne')
                ->descriptionIcon(Heroicon::OutlinedChartBar)
                ->chart($son7Gun)
                ->color('primary'),

            Stat::make('Açık alacak', Bicim::para($acikAlacak))
                ->description($borcluSayisi > 0 ? $borcluSayisi . ' borçlu müşteri' : 'Tüm hesaplar kapalı')
                ->descriptionIcon(Heroicon::OutlinedBanknotes)
                ->color($borcluSayisi > 0 ? 'warning' : 'success')
                ->url(MusteriResource::getUrl()),

            Stat::make('Kritik stok', $kritikUrun)
                ->description($kritikUrun > 0 ? 'Yenilenmesi gereken ürün' : 'Tüm stoklar yeterli')
                ->descriptionIcon($kritikUrun > 0 ? Heroicon::OutlinedExclamationTriangle : Heroicon::OutlinedCheckCircle)
                ->color($kritikUrun > 0 ? 'danger' : 'success')
                ->url(CanliStok::getUrl(['tableFilters' => ['kritik' => ['isActive' => true]]])),
        ];
    }

    /**
     * Son N günün günlük satış toplamları (eğilim çizgisi için, gösterim amaçlı).
     *
     * @return list<float>
     */
    protected function gunlukSatis(int $gun): array
    {
        $baslangic = today()->subDays($gun - 1);

        $toplamlar = Fis::query()
            ->where('durum', FisDurumu::Aktif->value)
            ->where('tarih', '>=', $baslangic)
            ->groupBy('gun')
            ->pluck(DB::raw('SUM(genel_toplam) as toplam'), DB::raw('DATE(tarih) as gun'))
            ->all();

        $seri = [];

        for ($i = 0; $i < $gun; $i++) {
            $seri[] = (float) ($toplamlar[$baslangic->copy()->addDays($i)->toDateString()] ?? 0);
        }

        return $seri;
    }
}
