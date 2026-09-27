<?php

namespace App\Services;

use App\Enums\CariKaynagi;
use App\Enums\CariTipi;
use App\Models\CariHareketi;
use App\Models\Musteri;
use App\Models\User;
use App\Support\Bicim;
use App\Support\Sayi;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use LogicException;

/**
 * Müşteri bakiyesini değiştiren tek kapı. musteriler.bakiye başka hiçbir yerden yazılmaz.
 *
 * Stok servisiyle aynı disiplin: transaction içinde müşteri satırı kilitlenir,
 * cari_hareketler'e kayıt eklenir (bakiye_sonrasi ile), bakiye güncellenir.
 * Pozitif bakiye = müşteri bize borçlu. Borç artırır, alacak azaltır; bakiye eksiye düşebilir.
 */
class CariServisi
{
    public function borclandir(
        Musteri $musteri,
        string $tutar,
        CariKaynagi $kaynak,
        ?string $aciklama = null,
        ?CarbonInterface $tarih = null,
        ?int $fisId = null,
        ?int $tahsilatId = null,
        ?User $kullanici = null,
    ): CariHareketi {
        return $this->hareketEkle($musteri, CariTipi::Borc, $tutar, $kaynak, $aciklama, $tarih, $fisId, $tahsilatId, $kullanici);
    }

    public function alacaklandir(
        Musteri $musteri,
        string $tutar,
        CariKaynagi $kaynak,
        ?string $aciklama = null,
        ?CarbonInterface $tarih = null,
        ?int $fisId = null,
        ?int $tahsilatId = null,
        ?User $kullanici = null,
    ): CariHareketi {
        return $this->hareketEkle($musteri, CariTipi::Alacak, $tutar, $kaynak, $aciklama, $tarih, $fisId, $tahsilatId, $kullanici);
    }

    /**
     * Müşteri oluşturulurken girilen açılış bakiyesini tek bir cari harekete çevirir.
     * Pozitif → borç, negatif → alacak. Sıfırsa hiçbir şey yapmaz. Bir kez çalışır.
     */
    public function acilisBakiyesiIsle(Musteri $musteri, ?User $kullanici = null): ?CariHareketi
    {
        $deger = (string) ($musteri->acilis_bakiyesi ?? '0');

        if (bccomp($deger, '0', 2) === 0) {
            return null;
        }

        if ($musteri->cariHareketler()->where('kaynak', CariKaynagi::Acilis->value)->exists()) {
            throw new LogicException("{$musteri->firma_adi} için açılış bakiyesi zaten işlenmiş.");
        }

        $mutlak = ltrim($deger, '-');
        $tarih = $musteri->created_at ?? now();

        return bccomp($deger, '0', 2) > 0
            ? $this->borclandir($musteri, $mutlak, CariKaynagi::Acilis, 'Açılış bakiyesi', $tarih, kullanici: $kullanici)
            : $this->alacaklandir($musteri, $mutlak, CariKaynagi::Acilis, 'Açılış bakiyesi', $tarih, kullanici: $kullanici);
    }

    /**
     * Yanlış girilen açılış bakiyesini düzeltir. Açılış hareketine dokunulmaz:
     * doğru tutarla kayıtlı tutar arasındaki fark tek bir düzeltme hareketi olarak yazılır
     * ve müşteri kartındaki açılış bakiyesi doğru değere çekilir.
     */
    public function acilisBakiyesiDuzelt(Musteri $musteri, string $dogruTutar, string $aciklama, ?User $kullanici = null): CariHareketi
    {
        $yeni = $this->isaretliTutar($dogruTutar, 'Açılış bakiyesi');
        $aciklama = trim($aciklama);

        if ($aciklama === '') {
            throw new InvalidArgumentException('Açılış bakiyesi düzeltmesinde açıklama zorunludur.');
        }

        // Hareket açıklamasına eski → yeni tutar da yazılır; sütun 255 karakterdir.
        if (mb_strlen($aciklama) > 150) {
            throw new InvalidArgumentException('Açıklama en fazla 150 karakter olabilir.');
        }

        $kullanici ??= auth()->user();

        $hareket = DB::transaction(function () use ($musteri, $yeni, $aciklama, $kullanici): CariHareketi {
            /** @var Musteri $kilitli */
            $kilitli = Musteri::withTrashed()->whereKey($musteri->getKey())->lockForUpdate()->firstOrFail();
            $eski = bcadd((string) $kilitli->acilis_bakiyesi, '0', 2);
            $fark = bcsub($yeni, $eski, 2);

            if (bccomp($fark, '0', 2) === 0) {
                throw new InvalidArgumentException('Girilen tutar kayıtlı açılış bakiyesiyle aynı; düzeltilecek fark yok.');
            }

            $metin = sprintf('Açılış bakiyesi düzeltmesi (%s → %s): %s', Bicim::para($eski), Bicim::para($yeni), $aciklama);

            $hareket = bccomp($fark, '0', 2) > 0
                ? $this->borclandir($kilitli, $fark, CariKaynagi::Duzeltme, $metin, kullanici: $kullanici)
                : $this->alacaklandir($kilitli, ltrim($fark, '-'), CariKaynagi::Duzeltme, $metin, kullanici: $kullanici);

            // Model olayları atlanır: Musteri'deki updating koruması bu alanı başka her yoldan kilitli tutar.
            $kilitli->forceFill(['acilis_bakiyesi' => $yeni])->saveQuietly();

            activity('cari')
                ->performedOn($kilitli)
                ->causedBy($kullanici)
                ->withProperties([
                    'eski' => $eski,
                    'yeni' => $yeni,
                    'fark' => $fark,
                    'neden' => $aciklama,
                ])
                ->log(sprintf(
                    'Açılış bakiyesi düzeltildi: %s %s → %s: %s',
                    $kilitli->firma_adi,
                    Bicim::para($eski),
                    Bicim::para($yeni),
                    $aciklama,
                ));

            return $hareket;
        });

        $musteri->refresh();

        return $hareket;
    }

    /**
     * Tarih aralıklı ekstre: devreden bakiye, aralıktaki hareketler (yürüyen bakiyeyle) ve kapanış.
     *
     * @return array{
     *     baslangic: ?CarbonInterface, bitis: ?CarbonInterface, devreden: string,
     *     satirlar: list<array{hareket: CariHareketi, bakiye: string}>,
     *     borc_toplam: string, alacak_toplam: string, kapanis: string
     * }
     */
    public function ekstre(Musteri $musteri, ?CarbonInterface $baslangic = null, ?CarbonInterface $bitis = null): array
    {
        $sorgu = CariHareketi::query()
            ->where('musteri_id', $musteri->id)
            ->with('user')
            ->orderBy('tarih')
            ->orderBy('id');

        $devreden = '0.00';

        if ($baslangic !== null) {
            $devreden = $this->hareketlerdenHesapla($musteri->id, oncesi: $baslangic);
            $sorgu->where('tarih', '>=', $baslangic);
        }

        if ($bitis !== null) {
            $sorgu->where('tarih', '<=', $bitis);
        }

        $bakiye = $devreden;
        $borcToplam = '0.00';
        $alacakToplam = '0.00';
        $satirlar = [];

        foreach ($sorgu->get() as $hareket) {
            $tutar = (string) $hareket->tutar;

            if ($hareket->tip === CariTipi::Borc) {
                $bakiye = bcadd($bakiye, $tutar, 2);
                $borcToplam = bcadd($borcToplam, $tutar, 2);
            } else {
                $bakiye = bcsub($bakiye, $tutar, 2);
                $alacakToplam = bcadd($alacakToplam, $tutar, 2);
            }

            $satirlar[] = ['hareket' => $hareket, 'bakiye' => $bakiye];
        }

        return [
            'baslangic' => $baslangic,
            'bitis' => $bitis,
            'devreden' => $devreden,
            'satirlar' => $satirlar,
            'borc_toplam' => $borcToplam,
            'alacak_toplam' => $alacakToplam,
            'kapanis' => $bakiye,
        ];
    }

    /** Bakiyeyi hareketlerden yeniden hesaplar: Σ borç − Σ alacak (isteğe bağlı: bir tarihten öncesi). */
    public function hareketlerdenHesapla(int $musteriId, ?CarbonInterface $oncesi = null): string
    {
        $toplam = DB::table('cari_hareketler')
            ->where('musteri_id', $musteriId)
            ->when($oncesi !== null, fn ($q) => $q->where('tarih', '<', $oncesi))
            ->selectRaw("COALESCE(SUM(CASE WHEN tip = 'borc' THEN tutar ELSE -tutar END), 0) AS toplam")
            ->value('toplam');

        return bcadd((string) $toplam, '0', 2);
    }

    /** "-40.5" → "-40.50". İşaretli tutarı doğrular; sıfır olabilir. */
    protected function isaretliTutar(string $deger, string $alan): string
    {
        $deger = trim($deger);
        $negatif = str_starts_with($deger, '-');
        $mutlak = Sayi::dogrula($negatif ? substr($deger, 1) : $deger, $alan, sifirOlabilir: true);

        return $negatif && bccomp($mutlak, '0', 2) !== 0 ? '-' . $mutlak : $mutlak;
    }

    protected function hareketEkle(
        Musteri $musteri,
        CariTipi $tip,
        string $tutar,
        CariKaynagi $kaynak,
        ?string $aciklama,
        ?CarbonInterface $tarih,
        ?int $fisId,
        ?int $tahsilatId,
        ?User $kullanici,
    ): CariHareketi {
        $tutar = Sayi::dogrula($tutar, 'Tutar');

        if ($kaynak === CariKaynagi::Duzeltme && blank($aciklama)) {
            throw new InvalidArgumentException('Düzeltme kaydında açıklama zorunludur.');
        }

        $kullanici ??= auth()->user();

        if ($kullanici === null) {
            throw new LogicException('Cari hareket için işlemi yapan kullanıcı belirtilmelidir.');
        }

        $tarih ??= now();

        $hareket = DB::transaction(function () use ($musteri, $tip, $tutar, $kaynak, $aciklama, $tarih, $fisId, $tahsilatId, $kullanici): CariHareketi {
            /** @var Musteri $kilitli */
            $kilitli = Musteri::withTrashed()->whereKey($musteri->getKey())->lockForUpdate()->firstOrFail();
            $mevcut = (string) $kilitli->bakiye;

            $yeniBakiye = $tip === CariTipi::Borc
                ? bcadd($mevcut, $tutar, 2)
                : bcsub($mevcut, $tutar, 2);

            $hareket = CariHareketi::create([
                'musteri_id' => $kilitli->id,
                'tarih' => $tarih,
                'tip' => $tip,
                'kaynak' => $kaynak,
                'tutar' => $tutar,
                'bakiye_sonrasi' => $yeniBakiye,
                'fis_id' => $fisId,
                'tahsilat_id' => $tahsilatId,
                'aciklama' => $aciklama,
                'user_id' => $kullanici->id,
            ]);

            $kilitli->forceFill(['bakiye' => $yeniBakiye])->save();

            return $hareket;
        });

        // Çağıranın elindeki model de güncel bakiyeyi görsün.
        $musteri->refresh();

        return $hareket;
    }
}
