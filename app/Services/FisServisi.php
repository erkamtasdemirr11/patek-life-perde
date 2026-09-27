<?php

namespace App\Services;

use App\Enums\CariKaynagi;
use App\Enums\FisDurumu;
use App\Enums\OdemeTipi;
use App\Enums\OdemeYontemi;
use App\Enums\StokSebebi;
use App\Exceptions\FisStokYetersizException;
use App\Exceptions\YetersizStokException;
use App\Models\Fis;
use App\Models\Musteri;
use App\Models\Urun;
use App\Models\User;
use App\Support\Sayi;
use App\Support\YillikNumara;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use LogicException;

/**
 * Fiş kesme ve iptal etme buradan geçer. Fiş, kalemleri, stok düşümleri ve cari kaydı
 * tek transaction içinde yazılır; bir kalemde stok yetmezse hiçbiri kaydedilmez.
 *
 * Ödeme tipi:
 * - Açık hesap: fiş tutarı müşterinin borcuna yazılır, sonra tahsilatla kapatılır.
 * - Peşin: fişle aynı anda, fişe bağlı bir tahsilat makbuzu kesilir; bakiye değişmez.
 */
class FisServisi
{
    public function __construct(
        protected StokServisi $stokServisi,
        protected CariServisi $cariServisi,
        protected TahsilatServisi $tahsilatServisi,
    ) {}

    /**
     * @param  list<array{urun: Urun|int, miktar: string, birim_fiyat: string}>  $kalemler
     */
    public function olustur(
        Musteri $musteri,
        array $kalemler,
        string $iskonto = '0',
        ?string $aciklama = null,
        ?CarbonInterface $tarih = null,
        ?User $kullanici = null,
        OdemeTipi $odemeTipi = OdemeTipi::AcikHesap,
        ?OdemeYontemi $odemeYontemi = null,
    ): Fis {
        $kullanici ??= auth()->user();

        if ($kullanici === null) {
            throw new LogicException('Fiş için işlemi yapan kullanıcı belirtilmelidir.');
        }

        if ($odemeTipi === OdemeTipi::Pesin && $odemeYontemi === null) {
            throw new InvalidArgumentException('Peşin fişte ödeme yöntemi seçilmelidir.');
        }

        if ($odemeTipi === OdemeTipi::AcikHesap) {
            $odemeYontemi = null;
        }

        $tarih ??= now();
        $satirlar = $this->kalemleriHazirla($kalemler);
        $araToplam = Sayi::topla(array_column($satirlar, 'tutar'));
        $iskonto = Sayi::dogrula($iskonto, 'İskonto', sifirOlabilir: true);

        if (bccomp($iskonto, $araToplam, 2) > 0) {
            throw new InvalidArgumentException('İskonto ara toplamdan büyük olamaz.');
        }

        $genelToplam = bcsub($araToplam, $iskonto, 2);

        return DB::transaction(function () use ($musteri, $satirlar, $araToplam, $iskonto, $genelToplam, $aciklama, $tarih, $kullanici, $odemeTipi, $odemeYontemi): Fis {
            $this->stokYeterliliginiKontrolEt($satirlar);

            $fis = Fis::create([
                'fis_no' => YillikNumara::al('fis_numaralari', (int) $tarih->year),
                'musteri_id' => $musteri->id,
                'tarih' => $tarih,
                'ara_toplam' => $araToplam,
                'iskonto' => $iskonto,
                'genel_toplam' => $genelToplam,
                'odeme_tipi' => $odemeTipi,
                'durum' => FisDurumu::Aktif,
                'aciklama' => $aciklama,
                'user_id' => $kullanici->id,
            ]);

            foreach ($satirlar as $satir) {
                $fis->kalemler()->create([
                    'urun_id' => $satir['urun']->id,
                    'miktar' => $satir['miktar'],
                    'birim_fiyat' => $satir['birim_fiyat'],
                    'tutar' => $satir['tutar'],
                ]);

                $this->stokServisi->cikisYap(
                    $satir['urun'],
                    $satir['miktar'],
                    StokSebebi::Satis,
                    "Fiş {$fis->fis_no}",
                    $fis->id,
                    $kullanici,
                );
            }

            // Fiş tutarı müşterinin borcuna yazılır (tutarı sıfır olan fiş cariye dokunmaz).
            if (bccomp($genelToplam, '0', 2) > 0) {
                $this->cariServisi->borclandir(
                    $musteri,
                    $genelToplam,
                    CariKaynagi::Fis,
                    "Fiş {$fis->fis_no}",
                    $tarih,
                    fisId: $fis->id,
                    kullanici: $kullanici,
                );

                // Peşin: aynı transaction içinde fişe bağlı tahsilat kesilir, borç anında kapanır.
                if ($odemeTipi === OdemeTipi::Pesin) {
                    $this->tahsilatServisi->kaydet(
                        $musteri,
                        $genelToplam,
                        $odemeYontemi,
                        "Fiş {$fis->fis_no} peşin tahsilatı",
                        $tarih,
                        $kullanici,
                        fisId: $fis->id,
                    );
                }
            }

            return $fis->load(['kalemler.urun', 'musteri', 'pesinTahsilat']);
        });
    }

    /** İptal: neden zorunlu, her kalem stoğa geri eklenir, bağlı peşin tahsilat da iptal edilir, fiş tekrar iptal edilemez. */
    public function iptalEt(Fis $fis, string $neden, ?User $kullanici = null): Fis
    {
        $neden = trim($neden);

        if ($neden === '') {
            throw new InvalidArgumentException('İptal nedeni zorunludur.');
        }

        $kullanici ??= auth()->user();

        if ($kullanici === null) {
            throw new LogicException('Fiş iptali için işlemi yapan kullanıcı belirtilmelidir.');
        }

        return DB::transaction(function () use ($fis, $neden, $kullanici): Fis {
            /** @var Fis $kilitli */
            $kilitli = Fis::whereKey($fis->getKey())->lockForUpdate()->firstOrFail();

            if ($kilitli->iptalMi()) {
                throw new LogicException("Fiş {$kilitli->fis_no} zaten iptal edilmiş.");
            }

            foreach ($kilitli->kalemler as $kalem) {
                $this->stokServisi->girisYap(
                    $kalem->urun,
                    (string) $kalem->miktar,
                    StokSebebi::FisIptal,
                    "Fiş {$kilitli->fis_no} iptali",
                    $kilitli->id,
                    $kullanici,
                );
            }

            // Fişe bağlı peşin tahsilat varsa önce o iptal edilir (tutar borca geri yazılır),
            // ardından fiş tutarı borçtan düşülür; bakiye fiş öncesine döner.
            $pesin = $kilitli->pesinTahsilat;

            if ($pesin !== null && $pesin->aktifMi()) {
                $this->tahsilatServisi->iptalEt($pesin, "Fiş {$kilitli->fis_no} iptali: {$neden}", $kullanici);
            }

            if (bccomp((string) $kilitli->genel_toplam, '0', 2) > 0) {
                $this->cariServisi->alacaklandir(
                    $kilitli->musteri,
                    (string) $kilitli->genel_toplam,
                    CariKaynagi::FisIptal,
                    "Fiş {$kilitli->fis_no} iptali",
                    now(),
                    fisId: $kilitli->id,
                    kullanici: $kullanici,
                );
            }

            // saveQuietly: modelin genel "güncellendi" logu yerine aşağıdaki açıklamalı iptal logu düşer.
            $kilitli->forceFill([
                'durum' => FisDurumu::Iptal,
                'iptal_nedeni' => $neden,
                'iptal_eden_id' => $kullanici->id,
                'iptal_tarihi' => now(),
            ])->saveQuietly();

            activity('fis')
                ->performedOn($kilitli)
                ->causedBy($kullanici)
                ->withProperties([
                    'fis_no' => $kilitli->fis_no,
                    'genel_toplam' => (string) $kilitli->genel_toplam,
                    'neden' => $neden,
                ])
                ->log("Fiş {$kilitli->fis_no} iptal edildi: {$neden}");

            $fis->refresh();

            return $kilitli;
        });
    }

    /**
     * Kalemleri doğrular, ürünleri çözer, tutarları hesaplar.
     *
     * @return list<array{urun: Urun, miktar: string, birim_fiyat: string, tutar: string}>
     */
    protected function kalemleriHazirla(array $kalemler): array
    {
        if ($kalemler === []) {
            throw new InvalidArgumentException('Fişte en az bir kalem olmalıdır.');
        }

        $satirlar = [];
        $gorulen = [];

        foreach (array_values($kalemler) as $i => $kalem) {
            $sira = $i + 1;
            $urun = $kalem['urun'] instanceof Urun
                ? $kalem['urun']
                : Urun::withTrashed()->find($kalem['urun']);

            if ($urun === null) {
                throw new InvalidArgumentException("{$sira}. kalemdeki ürün bulunamadı.");
            }

            if ($urun->trashed() || ! $urun->aktif) {
                throw new InvalidArgumentException("{$urun->ad} satışa kapalı (pasif veya arşivde).");
            }

            if (isset($gorulen[$urun->id])) {
                throw new InvalidArgumentException("{$urun->ad} fişte birden fazla satırda olamaz; miktarları tek satırda toplayın.");
            }

            $gorulen[$urun->id] = true;

            $miktar = Sayi::dogrula((string) ($kalem['miktar'] ?? ''), "{$sira}. kalemin miktarı");
            $birimFiyat = Sayi::dogrula((string) ($kalem['birim_fiyat'] ?? ''), "{$sira}. kalemin birim fiyatı", sifirOlabilir: true);

            $satirlar[] = [
                'urun' => $urun,
                'miktar' => $miktar,
                'birim_fiyat' => $birimFiyat,
                'tutar' => Sayi::carp($miktar, $birimFiyat),
            ];
        }

        return $satirlar;
    }

    /**
     * Tüm ürünleri id sırasıyla kilitler ve stok yeterliliğini topluca kontrol eder.
     * Böylece kullanıcı eksik olan her ürünü tek seferde görür.
     */
    protected function stokYeterliliginiKontrolEt(array $satirlar): void
    {
        $idler = array_map(fn (array $s): int => $s['urun']->id, $satirlar);
        sort($idler);

        $kilitliUrunler = Urun::withTrashed()->whereIn('id', $idler)->orderBy('id')->lockForUpdate()->get()->keyBy('id');
        $eksikler = [];

        foreach ($satirlar as $satir) {
            $urun = $kilitliUrunler[$satir['urun']->id];

            if (bccomp((string) $urun->mevcut_stok, $satir['miktar'], 2) < 0) {
                $eksikler[] = new YetersizStokException($urun, (string) $urun->mevcut_stok, $satir['miktar']);
            }
        }

        if ($eksikler !== []) {
            throw new FisStokYetersizException($eksikler);
        }
    }
}
