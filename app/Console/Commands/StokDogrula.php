<?php

namespace App\Console\Commands;

use App\Models\Urun;
use App\Support\Bicim;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class StokDogrula extends Command
{
    protected $signature = 'stok:dogrula
        {--duzelt : Farklı bulunan ürünlerin mevcut stoğunu hareketlerden hesaplanan değere eşitler}';

    protected $description = 'Stok hareketlerinden stoğu yeniden hesaplar ve ürün kartındaki mevcut stokla karşılaştırır.';

    public function handle(): int
    {
        $farklar = [];

        Urun::withTrashed()->orderBy('ad')->each(function (Urun $urun) use (&$farklar): void {
            $kayitli = bcadd((string) $urun->mevcut_stok, '0', 2);
            $hesaplanan = $this->hareketlerdenHesapla($urun->id);

            if (bccomp($kayitli, $hesaplanan, 2) !== 0) {
                $farklar[] = [$urun, $kayitli, $hesaplanan];
            }
        });

        if ($farklar === []) {
            $this->info('Stok tutarlı: tüm ürünlerde kayıtlı stok, hareketlerle örtüşüyor.');

            return self::SUCCESS;
        }

        $this->table(
            ['Ürün', 'Kayıtlı', 'Hesaplanan', 'Fark'],
            array_map(fn (array $f): array => [
                $f[0]->ad,
                Bicim::miktar($f[1], $f[0]->birim),
                Bicim::miktar($f[2], $f[0]->birim),
                Bicim::miktar(bcsub($f[2], $f[1], 2), $f[0]->birim),
            ], $farklar),
        );
        $this->warn(count($farklar) . ' üründe tutarsızlık bulundu.');

        if (! $this->option('duzelt')) {
            $this->line('Düzeltmek için: php artisan stok:dogrula --duzelt');

            return self::FAILURE;
        }

        foreach ($farklar as [$urun]) {
            $this->duzelt($urun);
        }

        $this->info(count($farklar) . ' ürün düzeltildi. Hareketler kaynak alındı, ürün kartındaki stok onlara eşitlendi.');

        return self::SUCCESS;
    }

    /** Ürünün stoğunu tüm hareketlerinden yeniden hesaplar: Σ giriş − Σ çıkış. */
    protected function hareketlerdenHesapla(int $urunId): string
    {
        $toplam = DB::table('stok_hareketleri')
            ->where('urun_id', $urunId)
            ->selectRaw("COALESCE(SUM(CASE WHEN yon = 'giris' THEN miktar ELSE -miktar END), 0) AS toplam")
            ->value('toplam');

        return bcadd((string) $toplam, '0', 2);
    }

    /**
     * Hareketler doğruluğun kaynağıdır; ürün kartı onlara eşitlenir.
     * Bu, mevcut_stok'un StokServisi dışında yazıldığı tek yerdir.
     */
    protected function duzelt(Urun $urun): void
    {
        DB::transaction(function () use ($urun): void {
            /** @var Urun $kilitli */
            $kilitli = Urun::withTrashed()->whereKey($urun->id)->lockForUpdate()->firstOrFail();
            $eski = bcadd((string) $kilitli->mevcut_stok, '0', 2);
            $yeni = $this->hareketlerdenHesapla($kilitli->id);

            $kilitli->forceFill(['mevcut_stok' => $yeni])->save();

            activity('stok')
                ->performedOn($kilitli)
                ->withProperties(['eski' => $eski, 'yeni' => $yeni])
                ->log(sprintf(
                    'stok:dogrula düzeltmesi: %s mevcut stok %s → %s',
                    $kilitli->ad,
                    Bicim::miktar($eski, $kilitli->birim),
                    Bicim::miktar($yeni, $kilitli->birim),
                ));

            $this->line("  {$kilitli->ad}: " . Bicim::miktar($eski, $kilitli->birim) . ' → ' . Bicim::miktar($yeni, $kilitli->birim));
        });
    }
}
