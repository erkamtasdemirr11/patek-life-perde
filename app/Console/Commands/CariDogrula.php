<?php

namespace App\Console\Commands;

use App\Models\Musteri;
use App\Services\CariServisi;
use App\Support\Bicim;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CariDogrula extends Command
{
    protected $signature = 'cari:dogrula
        {--duzelt : Farklı bulunan müşterilerin bakiyesini hareketlerden hesaplanan değere eşitler}';

    protected $description = 'Cari hareketlerden bakiyeyi yeniden hesaplar ve müşteri kartındaki bakiyeyle karşılaştırır.';

    public function __construct(protected CariServisi $cariServisi)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $farklar = [];

        Musteri::withTrashed()->orderBy('firma_adi')->each(function (Musteri $musteri) use (&$farklar): void {
            $kayitli = bcadd((string) $musteri->bakiye, '0', 2);
            $hesaplanan = $this->cariServisi->hareketlerdenHesapla($musteri->id);

            if (bccomp($kayitli, $hesaplanan, 2) !== 0) {
                $farklar[] = [$musteri, $kayitli, $hesaplanan];
            }
        });

        if ($farklar === []) {
            $this->info('Cari tutarlı: tüm müşterilerde kayıtlı bakiye, hareketlerle örtüşüyor.');

            return self::SUCCESS;
        }

        $this->table(
            ['Müşteri', 'Kayıtlı', 'Hesaplanan', 'Fark'],
            array_map(fn (array $f): array => [
                $f[0]->firma_adi,
                Bicim::para($f[1]),
                Bicim::para($f[2]),
                Bicim::para(bcsub($f[2], $f[1], 2)),
            ], $farklar),
        );
        $this->warn(count($farklar) . ' müşteride tutarsızlık bulundu.');

        if (! $this->option('duzelt')) {
            $this->line('Düzeltmek için: php artisan cari:dogrula --duzelt');

            return self::FAILURE;
        }

        foreach ($farklar as [$musteri]) {
            $this->duzelt($musteri);
        }

        $this->info(count($farklar) . ' müşteri düzeltildi. Hareketler kaynak alındı, müşteri kartındaki bakiye onlara eşitlendi.');

        return self::SUCCESS;
    }

    /**
     * Hareketler doğruluğun kaynağıdır; müşteri kartı onlara eşitlenir.
     * Bu, bakiye'nin CariServisi dışında yazıldığı tek yerdir.
     */
    protected function duzelt(Musteri $musteri): void
    {
        DB::transaction(function () use ($musteri): void {
            /** @var Musteri $kilitli */
            $kilitli = Musteri::withTrashed()->whereKey($musteri->id)->lockForUpdate()->firstOrFail();
            $eski = bcadd((string) $kilitli->bakiye, '0', 2);
            $yeni = $this->cariServisi->hareketlerdenHesapla($kilitli->id);

            $kilitli->forceFill(['bakiye' => $yeni])->save();

            activity('cari')
                ->performedOn($kilitli)
                ->withProperties(['eski' => $eski, 'yeni' => $yeni])
                ->log(sprintf(
                    'cari:dogrula düzeltmesi: %s bakiye %s → %s',
                    $kilitli->firma_adi,
                    Bicim::para($eski),
                    Bicim::para($yeni),
                ));

            $this->line("  {$kilitli->firma_adi}: " . Bicim::para($eski) . ' → ' . Bicim::para($yeni));
        });
    }
}
