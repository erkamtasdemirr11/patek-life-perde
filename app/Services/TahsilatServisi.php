<?php

namespace App\Services;

use App\Enums\CariKaynagi;
use App\Enums\OdemeYontemi;
use App\Enums\TahsilatDurumu;
use App\Models\Musteri;
use App\Models\Tahsilat;
use App\Models\User;
use App\Support\Sayi;
use App\Support\YillikNumara;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use LogicException;

/**
 * Tahsilat kaydı ve iptali. Tahsilat ile cari karşılığı tek transaction içinde yazılır.
 * $fisId verilirse tahsilat o fişin peşin ödemesidir (FisServisi çağırır).
 */
class TahsilatServisi
{
    public function __construct(protected CariServisi $cariServisi) {}

    public function kaydet(
        Musteri $musteri,
        string $tutar,
        OdemeYontemi $yontem,
        ?string $aciklama = null,
        ?CarbonInterface $tarih = null,
        ?User $kullanici = null,
        ?int $fisId = null,
    ): Tahsilat {
        $tutar = Sayi::dogrula($tutar, 'Tutar');
        $kullanici ??= auth()->user();

        if ($kullanici === null) {
            throw new LogicException('Tahsilat için işlemi yapan kullanıcı belirtilmelidir.');
        }

        if ($musteri->trashed()) {
            throw new InvalidArgumentException("{$musteri->firma_adi} silinmiş; silinmiş müşteriye tahsilat girilemez.");
        }

        $tarih ??= now();

        return DB::transaction(function () use ($musteri, $tutar, $yontem, $aciklama, $tarih, $kullanici, $fisId): Tahsilat {
            $tahsilat = Tahsilat::create([
                'makbuz_no' => YillikNumara::al('makbuz_numaralari', (int) $tarih->year),
                'musteri_id' => $musteri->id,
                'fis_id' => $fisId,
                'tarih' => $tarih,
                'tutar' => $tutar,
                'odeme_yontemi' => $yontem,
                'durum' => TahsilatDurumu::Aktif,
                'aciklama' => $aciklama,
                'user_id' => $kullanici->id,
            ]);

            $this->cariServisi->alacaklandir(
                $musteri,
                $tutar,
                CariKaynagi::Tahsilat,
                "Tahsilat {$tahsilat->makbuz_no} ({$yontem->getLabel()})",
                $tarih,
                tahsilatId: $tahsilat->id,
                kullanici: $kullanici,
            );

            return $tahsilat;
        });
    }

    /** İptal: neden zorunlu, tutar müşterinin borcuna geri yazılır, tekrar iptal edilemez. */
    public function iptalEt(Tahsilat $tahsilat, string $neden, ?User $kullanici = null): Tahsilat
    {
        $neden = trim($neden);

        if ($neden === '') {
            throw new InvalidArgumentException('İptal nedeni zorunludur.');
        }

        $kullanici ??= auth()->user();

        if ($kullanici === null) {
            throw new LogicException('Tahsilat iptali için işlemi yapan kullanıcı belirtilmelidir.');
        }

        return DB::transaction(function () use ($tahsilat, $neden, $kullanici): Tahsilat {
            /** @var Tahsilat $kilitli */
            $kilitli = Tahsilat::whereKey($tahsilat->getKey())->lockForUpdate()->firstOrFail();

            if ($kilitli->iptalMi()) {
                throw new LogicException("Tahsilat {$kilitli->makbuz_no} zaten iptal edilmiş.");
            }

            $this->cariServisi->borclandir(
                $kilitli->musteri,
                (string) $kilitli->tutar,
                CariKaynagi::TahsilatIptal,
                "Tahsilat {$kilitli->makbuz_no} iptali",
                now(),
                tahsilatId: $kilitli->id,
                kullanici: $kullanici,
            );

            // saveQuietly: modelin genel "güncellendi" logu yerine aşağıdaki açıklamalı iptal logu düşer.
            $kilitli->forceFill([
                'durum' => TahsilatDurumu::Iptal,
                'iptal_nedeni' => $neden,
                'iptal_eden_id' => $kullanici->id,
                'iptal_tarihi' => now(),
            ])->saveQuietly();

            activity('tahsilat')
                ->performedOn($kilitli)
                ->causedBy($kullanici)
                ->withProperties([
                    'makbuz_no' => $kilitli->makbuz_no,
                    'tutar' => (string) $kilitli->tutar,
                    'neden' => $neden,
                ])
                ->log("Tahsilat {$kilitli->makbuz_no} iptal edildi: {$neden}");

            $tahsilat->refresh();

            return $kilitli;
        });
    }
}
