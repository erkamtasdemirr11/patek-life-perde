<?php

namespace App\Services;

use App\Enums\StokSebebi;
use App\Enums\StokYonu;
use App\Exceptions\YetersizStokException;
use App\Models\StokHareketi;
use App\Models\Urun;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use LogicException;

/**
 * Stoğu değiştiren tek kapı. urunler.mevcut_stok başka hiçbir yerden yazılmaz.
 *
 * Her işlem: transaction içinde ürün satırını kilitler, stok_hareketleri tablosuna
 * kayıt ekler, mevcut_stok alanını günceller. Miktarlar string olarak alınır ve
 * bcmath ile hesaplanır; float kullanılmaz.
 */
class StokServisi
{
    public function girisYap(
        Urun $urun,
        string $miktar,
        StokSebebi $sebep,
        ?string $aciklama = null,
        ?int $fisId = null,
        ?User $kullanici = null,
    ): StokHareketi {
        return $this->hareketEkle($urun, StokYonu::Giris, $miktar, $sebep, $aciklama, $fisId, $kullanici);
    }

    public function cikisYap(
        Urun $urun,
        string $miktar,
        StokSebebi $sebep,
        ?string $aciklama = null,
        ?int $fisId = null,
        ?User $kullanici = null,
    ): StokHareketi {
        return $this->hareketEkle($urun, StokYonu::Cikis, $miktar, $sebep, $aciklama, $fisId, $kullanici);
    }

    protected function hareketEkle(
        Urun $urun,
        StokYonu $yon,
        string $miktar,
        StokSebebi $sebep,
        ?string $aciklama,
        ?int $fisId,
        ?User $kullanici,
    ): StokHareketi {
        $miktar = $this->miktariDogrula($miktar, 'Miktar');

        if (! $sebep->uygunMu($yon)) {
            throw new InvalidArgumentException(sprintf(
                '"%s" sebebi stok %s işlemi için kullanılamaz.',
                $sebep->getLabel(),
                mb_strtolower($yon->getLabel()),
            ));
        }

        $kullanici ??= auth()->user();

        if ($kullanici === null) {
            throw new LogicException('Stok hareketi için işlemi yapan kullanıcı belirtilmelidir.');
        }

        $hareket = DB::transaction(function () use ($urun, $yon, $miktar, $sebep, $aciklama, $fisId, $kullanici): StokHareketi {
            /** @var Urun $kilitli */
            $kilitli = Urun::withTrashed()->whereKey($urun->getKey())->lockForUpdate()->firstOrFail();
            $mevcut = (string) $kilitli->mevcut_stok;

            if ($yon === StokYonu::Cikis && bccomp($mevcut, $miktar, 2) < 0) {
                throw new YetersizStokException($kilitli, $mevcut, $miktar);
            }

            $yeniStok = $yon === StokYonu::Giris
                ? bcadd($mevcut, $miktar, 2)
                : bcsub($mevcut, $miktar, 2);

            $hareket = StokHareketi::create([
                'urun_id' => $kilitli->id,
                'yon' => $yon,
                'sebep' => $sebep,
                'miktar' => $miktar,
                'fis_id' => $fisId,
                'aciklama' => $aciklama,
                'user_id' => $kullanici->id,
            ]);

            $kilitli->forceFill(['mevcut_stok' => $yeniStok])->save();

            return $hareket;
        });

        // Çağıranın elindeki model de güncel stoğu görsün.
        $urun->refresh();

        return $hareket;
    }

    /** "12.5" -> "12.50"; boş, negatif, sıfır veya ikiden fazla ondalık kabul edilmez. */
    protected function miktariDogrula(string $deger, string $alan): string
    {
        $deger = trim($deger);

        if (! preg_match('/^\d+(\.\d{1,2})?$/', $deger)) {
            throw new InvalidArgumentException("{$alan} sayısal olmalı ve en fazla iki ondalık basamak içermelidir (örn. 12.50).");
        }

        $deger = bcadd($deger, '0', 2);

        if (bccomp($deger, '0', 2) <= 0) {
            throw new InvalidArgumentException("{$alan} sıfırdan büyük olmalıdır.");
        }

        return $deger;
    }
}
