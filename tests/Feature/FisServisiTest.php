<?php

use App\Enums\FisDurumu;
use App\Enums\StokSebebi;
use App\Enums\OdemeTipi;
use App\Enums\OdemeYontemi;
use App\Models\CariHareketi;
use App\Models\Tahsilat;
use App\Services\CariServisi;
use App\Services\TahsilatServisi;
use App\Support\Bicim;
use App\Exceptions\FisStokYetersizException;
use App\Models\Fis;
use App\Models\FisKalemi;
use App\Models\Musteri;
use App\Models\StokHareketi;
use App\Models\Urun;
use App\Models\User;
use App\Services\FisServisi;
use App\Services\StokServisi;
use Spatie\Activitylog\Models\Activity;

beforeEach(function () {
    $this->kullanici = User::factory()->create();
    $this->actingAs($this->kullanici);

    $this->servis = app(FisServisi::class);
    $this->stok = app(StokServisi::class);
    $this->musteri = Musteri::factory()->create(['firma_adi' => 'Deneme Tekstil']);

    $this->keten = Urun::factory()->create(['ad' => 'Keten Beyaz', 'satis_fiyati' => '100']);
    $this->fon = Urun::factory()->create(['ad' => 'Fon Krem', 'satis_fiyati' => '75.5']);

    $this->stok->girisYap($this->keten, '50', StokSebebi::Alis);
    $this->stok->girisYap($this->fon, '20', StokSebebi::Alis);

    $this->yil = now()->year;
});

it('fiş kaydı stoğu düşürür, toplamları doğru hesaplar ve sıralı numara verir', function () {
    $fis = $this->servis->olustur($this->musteri, [
        ['urun' => $this->keten, 'miktar' => '12.5', 'birim_fiyat' => '100'],
        ['urun' => $this->fon, 'miktar' => '2', 'birim_fiyat' => '75.5'],
    ], iskonto: '10', aciklama: 'Peşin');

    expect($fis->fis_no)->toBe("{$this->yil}-000001")
        ->and($fis->durum)->toBe(FisDurumu::Aktif)
        ->and($fis->ara_toplam)->toBe('1401.00')
        ->and($fis->iskonto)->toBe('10.00')
        ->and($fis->genel_toplam)->toBe('1391.00')
        ->and($fis->user_id)->toBe($this->kullanici->id)
        ->and($fis->kalemler)->toHaveCount(2)
        ->and($fis->kalemler[0]->tutar)->toBe('1250.00')
        ->and($fis->kalemler[1]->tutar)->toBe('151.00');

    expect($this->keten->fresh()->mevcut_stok)->toBe('37.50')
        ->and($this->fon->fresh()->mevcut_stok)->toBe('18.00');

    $satisHareketleri = StokHareketi::where('fis_id', $fis->id)->get();
    expect($satisHareketleri)->toHaveCount(2)
        ->and($satisHareketleri->every(fn ($h) => $h->sebep === StokSebebi::Satis))->toBeTrue();

    $ikinci = $this->servis->olustur($this->musteri, [
        ['urun' => $this->keten, 'miktar' => '1', 'birim_fiyat' => '100'],
    ]);

    expect($ikinci->fis_no)->toBe("{$this->yil}-000002");
});

it('tutar hesabında yarım yukarı yuvarlar ve ondalık hatası oluşmaz', function () {
    $fis = $this->servis->olustur($this->musteri, [
        ['urun' => $this->keten, 'miktar' => '1.25', 'birim_fiyat' => '0.15'],   // 0.1875 → 0.19
        ['urun' => $this->fon, 'miktar' => '3', 'birim_fiyat' => '0.1'],         // 0.30
    ]);

    expect($fis->kalemler[0]->tutar)->toBe('0.19')
        ->and($fis->kalemler[1]->tutar)->toBe('0.30')
        ->and($fis->ara_toplam)->toBe('0.49')
        ->and($fis->genel_toplam)->toBe('0.49');
});

it('bir kalemde stok yetersizse fiş hiç oluşmaz ve diğer kalemler geri alınır', function () {
    $hareketOnce = StokHareketi::count();

    try {
        $this->servis->olustur($this->musteri, [
            ['urun' => $this->keten, 'miktar' => '10', 'birim_fiyat' => '100'],   // yeterli
            ['urun' => $this->fon, 'miktar' => '23', 'birim_fiyat' => '75.5'],   // 20 var, 3 eksik
        ]);
        $this->fail('FisStokYetersizException bekleniyordu.');
    } catch (FisStokYetersizException $e) {
        expect($e->eksikler)->toHaveCount(1)
            ->and($e->getMessage())->toContain('Fon Krem')
            ->and($e->getMessage())->toContain('eksik 3,00 m')
            ->and($e->getMessage())->not->toContain('Keten Beyaz');
    }

    expect(Fis::count())->toBe(0)
        ->and(FisKalemi::count())->toBe(0)
        ->and(StokHareketi::count())->toBe($hareketOnce)
        ->and($this->keten->fresh()->mevcut_stok)->toBe('50.00')
        ->and($this->fon->fresh()->mevcut_stok)->toBe('20.00');

    // Başarısız deneme numara tüketmez.
    $fis = $this->servis->olustur($this->musteri, [
        ['urun' => $this->keten, 'miktar' => '1', 'birim_fiyat' => '100'],
    ]);
    expect($fis->fis_no)->toBe("{$this->yil}-000001");
});

it('geçersiz kalem ve iskontoları reddeder', function () {
    $kalem = [['urun' => $this->keten, 'miktar' => '1', 'birim_fiyat' => '100']];

    expect(fn () => $this->servis->olustur($this->musteri, []))
        ->toThrow(InvalidArgumentException::class, 'en az bir kalem');
    expect(fn () => $this->servis->olustur($this->musteri, $kalem, iskonto: '150'))
        ->toThrow(InvalidArgumentException::class, 'İskonto ara toplamdan büyük olamaz.');
    expect(fn () => $this->servis->olustur($this->musteri, $kalem, iskonto: '-5'))
        ->toThrow(InvalidArgumentException::class);
    expect(fn () => $this->servis->olustur($this->musteri, [['urun' => $this->keten, 'miktar' => '0', 'birim_fiyat' => '100']]))
        ->toThrow(InvalidArgumentException::class);
    expect(fn () => $this->servis->olustur($this->musteri, [$kalem[0], $kalem[0]]))
        ->toThrow(InvalidArgumentException::class, 'birden fazla satırda');

    $this->keten->update(['aktif' => false]);
    expect(fn () => $this->servis->olustur($this->musteri, $kalem))
        ->toThrow(InvalidArgumentException::class, 'satışa kapalı');

    expect(Fis::count())->toBe(0);
});

it('fişteki birim fiyat, ürün fiyatı sonradan değişse de değişmez', function () {
    $fis = $this->servis->olustur($this->musteri, [
        ['urun' => $this->keten, 'miktar' => '1', 'birim_fiyat' => '100'],
    ]);

    $this->keten->update(['satis_fiyati' => '999']);

    expect($fis->fresh()->kalemler[0]->birim_fiyat)->toBe('100.00');
});

it('fiş iptali stoğu geri ekler, iptal bilgilerini yazar ve açıklamalı log üretir', function () {
    $fis = $this->servis->olustur($this->musteri, [
        ['urun' => $this->keten, 'miktar' => '12.5', 'birim_fiyat' => '100'],
        ['urun' => $this->fon, 'miktar' => '2', 'birim_fiyat' => '75.5'],
    ]);
    expect($this->keten->fresh()->mevcut_stok)->toBe('37.50');

    $iptal = $this->servis->iptalEt($fis, 'Müşteri vazgeçti');

    expect($iptal->durum)->toBe(FisDurumu::Iptal)
        ->and($iptal->iptal_nedeni)->toBe('Müşteri vazgeçti')
        ->and($iptal->iptal_eden_id)->toBe($this->kullanici->id)
        ->and($iptal->iptal_tarihi)->not->toBeNull()
        ->and($fis->fresh()->iptalMi())->toBeTrue();

    expect($this->keten->fresh()->mevcut_stok)->toBe('50.00')
        ->and($this->fon->fresh()->mevcut_stok)->toBe('20.00');

    $geriAlanlar = StokHareketi::where('fis_id', $fis->id)->where('sebep', StokSebebi::FisIptal->value)->get();
    expect($geriAlanlar)->toHaveCount(2);

    $log = Activity::where('log_name', 'fis')->latest('id')->first();
    expect($log)->not->toBeNull()
        ->and($log->description)->toContain("Fiş {$fis->fis_no} iptal edildi: Müşteri vazgeçti")
        ->and($log->causer_id)->toBe($this->kullanici->id);
});

it('iptal edilmiş fiş tekrar iptal edilemez ve neden zorunludur', function () {
    $fis = $this->servis->olustur($this->musteri, [
        ['urun' => $this->keten, 'miktar' => '5', 'birim_fiyat' => '100'],
    ]);

    expect(fn () => $this->servis->iptalEt($fis, '   '))
        ->toThrow(InvalidArgumentException::class, 'İptal nedeni zorunludur.');

    $this->servis->iptalEt($fis, 'Yanlış kesildi');
    $hareketSayisi = StokHareketi::count();

    expect(fn () => $this->servis->iptalEt($fis, 'Tekrar'))
        ->toThrow(LogicException::class, 'zaten iptal edilmiş');

    expect(StokHareketi::count())->toBe($hareketSayisi)
        ->and($this->keten->fresh()->mevcut_stok)->toBe('50.00');
});

it('fişler ve kalemleri düzenlenemez, silinemez', function () {
    $fis = $this->servis->olustur($this->musteri, [
        ['urun' => $this->keten, 'miktar' => '5', 'birim_fiyat' => '100'],
    ]);

    expect(fn () => $fis->update(['aciklama' => 'değişti']))->toThrow(LogicException::class);
    expect(fn () => $fis->update(['genel_toplam' => '1']))->toThrow(LogicException::class);
    expect(fn () => $fis->delete())->toThrow(LogicException::class);
    expect(fn () => $fis->kalemler[0]->update(['miktar' => '99']))->toThrow(LogicException::class);
    expect(fn () => $fis->kalemler[0]->delete())->toThrow(LogicException::class);

    expect(Fis::count())->toBe(1)
        ->and($fis->fresh()->genel_toplam)->toBe('500.00');
});

it('fiş PDF çıktısı üretilir', function () {
    $fis = $this->servis->olustur($this->musteri, [
        ['urun' => $this->keten, 'miktar' => '5', 'birim_fiyat' => '100'],
    ]);

    $this->get(route('fis.pdf', $fis))
        ->assertOk()
        ->assertHeader('content-type', 'application/pdf');

    $this->servis->iptalEt($fis, 'Deneme');

    $this->get(route('fis.pdf', $fis))->assertOk();
});

it('fiş varsayılan olarak termal rulonun baskı alanına (72 mm) göre tek sayfa üretilir, A4 ayrıca alınabilir', function () {
    // Uzun fiş: sayfa boyu içeriğe göre uzamalı, ikinci sayfaya taşmamalı.
    $kalemler = [];

    foreach (range(1, 40) as $sira) {
        $urun = Urun::factory()->create(['ad' => "Uzun Adlı Deneme Kumaşı {$sira}", 'renk' => 'Antrasit', 'satis_fiyati' => '1250.5']);
        $this->stok->girisYap($urun, '10', StokSebebi::Alis);
        $kalemler[] = ['urun' => $urun, 'miktar' => '2.5', 'birim_fiyat' => '1250.5'];
    }

    $fis = $this->servis->olustur($this->musteri, $kalemler);

    // 72 mm = 204,09 punto. Sayfa 80 mm olursa yazıcıda içerik sağa kayar ve kesilir.
    $termal = $this->get(route('fis.pdf', $fis))->assertOk()->getContent();

    expect(preg_match('/\/MediaBox \[0(?:\.0+)? 0(?:\.0+)? (\d+(?:\.\d+)?) (\d+(?:\.\d+)?)\]/', $termal, $boyut))->toBe(1)
        ->and(round((float) $boyut[1]))->toBe(204.0)
        ->and((float) $boyut[2])->toBeGreaterThan(842.0)
        ->and($termal)->toContain('/Count 1');

    // A4 = 595,28 punto.
    $a4 = $this->get(route('fis.pdf', ['fis' => $fis, 'boyut' => 'a4']))->assertOk()->getContent();

    expect(preg_match('/\/MediaBox \[0(?:\.0+)? 0(?:\.0+)? (\d+(?:\.\d+)?) /', $a4, $boyut))->toBe(1)
        ->and(round((float) $boyut[1]))->toBe(595.0);
});

it('peşin fiş, fişle aynı anda fişe bağlı tahsilat makbuzu keser ve bakiyeyi değiştirmez', function () {
    $fis = $this->servis->olustur($this->musteri, [
        ['urun' => $this->keten, 'miktar' => '5', 'birim_fiyat' => '100'],
    ], odemeTipi: OdemeTipi::Pesin, odemeYontemi: OdemeYontemi::Nakit);

    $tahsilat = $fis->pesinTahsilat;

    expect($fis->odeme_tipi)->toBe(OdemeTipi::Pesin)
        ->and($fis->pesinMi())->toBeTrue()
        ->and($tahsilat)->not->toBeNull()
        ->and($tahsilat->fis_id)->toBe($fis->id)
        ->and($tahsilat->tutar)->toBe('500.00')
        ->and($tahsilat->odeme_yontemi)->toBe(OdemeYontemi::Nakit)
        ->and($tahsilat->makbuz_no)->toBe("{$this->yil}-000001")
        ->and($this->musteri->fresh()->bakiye)->toBe('0.00')
        ->and(CariHareketi::where('musteri_id', $this->musteri->id)->count())->toBe(2)
        ->and($this->keten->fresh()->mevcut_stok)->toBe('45.00');

    $ozet = $fis->fresh()->bakiyeOzeti();

    expect($ozet['onceki'])->toBe('0.00')
        ->and($ozet['fis'])->toBe('500.00')
        ->and($ozet['sonrasi'])->toBe('0.00');
});

it('peşin fişte ödeme yöntemi zorunludur ve hiçbir şey kaydedilmez', function () {
    expect(fn () => $this->servis->olustur($this->musteri, [
        ['urun' => $this->keten, 'miktar' => '5', 'birim_fiyat' => '100'],
    ], odemeTipi: OdemeTipi::Pesin))->toThrow(InvalidArgumentException::class, 'ödeme yöntemi');

    expect(Fis::count())->toBe(0)
        ->and(Tahsilat::count())->toBe(0)
        ->and($this->keten->fresh()->mevcut_stok)->toBe('50.00');
});

it('peşin fişin iptali bağlı tahsilatı da iptal eder; bakiye ve stok eski haline döner', function () {
    $fis = $this->servis->olustur($this->musteri, [
        ['urun' => $this->keten, 'miktar' => '5', 'birim_fiyat' => '100'],
    ], odemeTipi: OdemeTipi::Pesin, odemeYontemi: OdemeYontemi::Havale);

    $this->servis->iptalEt($fis, 'Müşteri vazgeçti');

    $tahsilat = $fis->pesinTahsilat->fresh();

    expect($fis->fresh()->iptalMi())->toBeTrue()
        ->and($tahsilat->iptalMi())->toBeTrue()
        ->and($tahsilat->iptal_nedeni)->toContain("Fiş {$fis->fis_no} iptali")
        ->and($this->musteri->fresh()->bakiye)->toBe('0.00')
        ->and($this->keten->fresh()->mevcut_stok)->toBe('50.00')
        ->and(CariHareketi::where('musteri_id', $this->musteri->id)->count())->toBe(4);
});

it('bakiye özeti fiş anındaki önceki ve sonraki bakiyeyi verir; alacaklı müşteride alacaktan düşer', function () {
    // Müşteri 300 lira alacaklı (fazla ödemiş) olarak başlasın.
    $alacakli = Musteri::factory()->create(['firma_adi' => 'Alacaklı Ltd', 'acilis_bakiyesi' => '-300']);
    app(CariServisi::class)->acilisBakiyesiIsle($alacakli);
    expect($alacakli->fresh()->bakiye)->toBe('-300.00');

    $fis = $this->servis->olustur($alacakli, [['urun' => $this->keten, 'miktar' => '5', 'birim_fiyat' => '100']]);
    $ozet = $fis->bakiyeOzeti();

    expect($ozet['onceki'])->toBe('-300.00')
        ->and($ozet['fis'])->toBe('500.00')
        ->and($ozet['sonrasi'])->toBe('200.00')
        ->and(Bicim::bakiye($ozet['onceki']))->toBe("300,00 \u{20BA} alacak")
        ->and(Bicim::bakiye($ozet['sonrasi']))->toBe("200,00 \u{20BA} borç");

    // Sonraki bir tahsilat fişin özetini değiştirmez: özet fiş anının fotoğrafıdır.
    app(TahsilatServisi::class)->kaydet($alacakli, '200', OdemeYontemi::Nakit);

    expect($alacakli->fresh()->bakiye)->toBe('0.00')
        ->and($fis->fresh()->bakiyeOzeti()['sonrasi'])->toBe('200.00');
});

it('fiş PDF şablonu cari durumu ve ödeme tipini gösterir', function () {
    $fis = $this->servis->olustur($this->musteri, [
        ['urun' => $this->keten, 'miktar' => '5', 'birim_fiyat' => '100'],
    ], odemeTipi: OdemeTipi::Pesin, odemeYontemi: OdemeYontemi::Nakit);

    $html = view('pdf.fis', [
        'fis' => $fis->load(['kalemler.urun', 'musteri', 'user', 'iptalEden', 'cariHareketi', 'pesinTahsilat']),
        'firma' => config('firma'),
    ])->render();

    expect($html)->toContain('Cari hesap durumu')
        ->toContain('Önceki bakiye')
        ->toContain('Fiş sonrası bakiye')
        ->toContain('Peşin')
        ->toContain($fis->pesinTahsilat->makbuz_no)
        ->toContain('peşin ödenmiştir');
});
