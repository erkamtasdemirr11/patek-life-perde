<?php

use App\Enums\CariKaynagi;
use App\Enums\CariTipi;
use App\Enums\OdemeYontemi;
use App\Enums\StokSebebi;
use App\Enums\TahsilatDurumu;
use App\Exceptions\FisStokYetersizException;
use App\Models\CariHareketi;
use App\Models\Musteri;
use App\Models\Tahsilat;
use App\Models\Urun;
use App\Models\User;
use App\Services\CariServisi;
use App\Services\FisServisi;
use App\Services\StokServisi;
use App\Services\TahsilatServisi;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Spatie\Activitylog\Models\Activity;

beforeEach(function () {
    $this->kullanici = User::factory()->create();
    $this->actingAs($this->kullanici);

    $this->cari = app(CariServisi::class);
    $this->tahsilat = app(TahsilatServisi::class);
    $this->fis = app(FisServisi::class);

    $this->musteri = Musteri::factory()->create(['firma_adi' => 'Deneme Tekstil']);
    $this->urun = Urun::factory()->create(['ad' => 'Keten Beyaz', 'satis_fiyati' => '100']);
    app(StokServisi::class)->girisYap($this->urun, '50', StokSebebi::Alis);

    $this->yil = now()->year;
});

it('borç ve alacak kayıtları bakiyeyi ve bakiye_sonrasi alanını doğru günceller', function () {
    $borc = $this->cari->borclandir($this->musteri, '100', CariKaynagi::Duzeltme, 'Eski defterden devir');

    expect($this->musteri->bakiye)->toBe('100.00')
        ->and($borc->tip)->toBe(CariTipi::Borc)
        ->and($borc->kaynak)->toBe(CariKaynagi::Duzeltme)
        ->and($borc->tutar)->toBe('100.00')
        ->and($borc->bakiye_sonrasi)->toBe('100.00')
        ->and($borc->user_id)->toBe($this->kullanici->id);

    // Bakiye eksiye düşebilir (müşteri fazla ödemiş).
    $alacak = $this->cari->alacaklandir($this->musteri, '150', CariKaynagi::Duzeltme, 'Fazla ödeme');

    expect($this->musteri->fresh()->bakiye)->toBe('-50.00')
        ->and($alacak->bakiye_sonrasi)->toBe('-50.00')
        ->and($this->musteri->fresh()->bakiyeYonu())->toBe(-1)
        ->and(CariHareketi::count())->toBe(2);
});

it('geçersiz tutarı reddeder, düzeltmede açıklama ister, hareketler düzenlenemez ve silinemez', function () {
    expect(fn () => $this->cari->borclandir($this->musteri, '0', CariKaynagi::Duzeltme, 'x'))
        ->toThrow(InvalidArgumentException::class);
    expect(fn () => $this->cari->borclandir($this->musteri, '-5', CariKaynagi::Duzeltme, 'x'))
        ->toThrow(InvalidArgumentException::class);
    expect(fn () => $this->cari->borclandir($this->musteri, '10', CariKaynagi::Duzeltme))
        ->toThrow(InvalidArgumentException::class, 'Düzeltme kaydında açıklama zorunludur.');

    $hareket = $this->cari->borclandir($this->musteri, '10', CariKaynagi::Duzeltme, 'x');

    expect(fn () => $hareket->update(['tutar' => '99']))->toThrow(LogicException::class);
    expect(fn () => $hareket->delete())->toThrow(LogicException::class);
    expect(CariHareketi::count())->toBe(1)
        ->and($this->musteri->fresh()->bakiye)->toBe('10.00');
});

it('açılış bakiyesi tek hareket üretir, tekrar işlenemez ve sonradan değiştirilemez', function () {
    $borclu = Musteri::factory()->create(['acilis_bakiyesi' => '250']);
    $this->cari->acilisBakiyesiIsle($borclu);

    expect($borclu->fresh()->bakiye)->toBe('250.00')
        ->and($borclu->cariHareketler()->count())->toBe(1)
        ->and($borclu->cariHareketler()->first()->kaynak)->toBe(CariKaynagi::Acilis)
        ->and($borclu->cariHareketler()->first()->tip)->toBe(CariTipi::Borc);

    expect(fn () => $this->cari->acilisBakiyesiIsle($borclu))->toThrow(LogicException::class, 'zaten işlenmiş');

    $alacakli = Musteri::factory()->create(['acilis_bakiyesi' => '-40']);
    $this->cari->acilisBakiyesiIsle($alacakli);
    expect($alacakli->fresh()->bakiye)->toBe('-40.00')
        ->and($alacakli->cariHareketler()->first()->tip)->toBe(CariTipi::Alacak);

    $sifir = Musteri::factory()->create(['acilis_bakiyesi' => '0']);
    expect($this->cari->acilisBakiyesiIsle($sifir))->toBeNull()
        ->and($sifir->cariHareketler()->count())->toBe(0);

    expect(fn () => $borclu->update(['acilis_bakiyesi' => '300']))->toThrow(LogicException::class);
});

it('açılış bakiyesi düzeltmesi farkı düzeltme hareketi olarak yazar, açılış hareketine dokunmaz', function () {
    $musteri = Musteri::factory()->create(['firma_adi' => 'Yanlış Girilen', 'acilis_bakiyesi' => '50000']);
    $this->cari->acilisBakiyesiIsle($musteri);

    // Arada başka hareket olsa da yalnızca açılış farkı düzeltilir.
    $this->cari->borclandir($musteri, '5000', CariKaynagi::Fis);

    $hareket = $this->cari->acilisBakiyesiDuzelt($musteri, '40000', 'Yanlış girildi');

    expect($hareket->kaynak)->toBe(CariKaynagi::Duzeltme)
        ->and($hareket->tip)->toBe(CariTipi::Alacak)
        ->and($hareket->tutar)->toBe('10000.00')
        ->and($hareket->bakiye_sonrasi)->toBe('45000.00')
        ->and($hareket->aciklama)->toContain('Yanlış girildi')
        ->and($musteri->acilis_bakiyesi)->toBe('40000.00')
        ->and($musteri->bakiye)->toBe('45000.00')
        ->and($musteri->cariHareketler()->count())->toBe(3);

    $acilis = $musteri->cariHareketler()->where('kaynak', CariKaynagi::Acilis->value)->sole();
    expect($acilis->tutar)->toBe('50000.00');

    $log = Activity::where('log_name', 'cari')->latest('id')->first();
    expect($log?->description)->toContain('Açılış bakiyesi düzeltildi')
        ->and($log->causer_id)->toBe($this->kullanici->id);

    // Yukarı yönlü düzeltme borç yazar; ikinci düzeltme son kayıtlı değere göre hesaplanır.
    $yukari = $this->cari->acilisBakiyesiDuzelt($musteri, '42500.50', 'Eksik girilmiş');

    expect($yukari->tip)->toBe(CariTipi::Borc)
        ->and($yukari->tutar)->toBe('2500.50')
        ->and($musteri->acilis_bakiyesi)->toBe('42500.50')
        ->and($musteri->bakiye)->toBe('47500.50')
        ->and($this->cari->hareketlerdenHesapla($musteri->id))->toBe('47500.50');

    $this->artisan('cari:dogrula')->assertExitCode(0);
});

it('açılış bakiyesi düzeltmesi alacaklı tutara çevirebilir, açılışı olmayan müşteride de çalışır', function () {
    $this->cari->acilisBakiyesiDuzelt($this->musteri, '-300', 'Müşteri alacaklıymış');

    expect($this->musteri->acilis_bakiyesi)->toBe('-300.00')
        ->and($this->musteri->bakiye)->toBe('-300.00')
        ->and($this->musteri->cariHareketler()->sole()->tip)->toBe(CariTipi::Alacak);
});

it('açılış bakiyesi düzeltmesi aynı tutarı, boş açıklamayı ve geçersiz tutarı reddeder', function () {
    $musteri = Musteri::factory()->create(['acilis_bakiyesi' => '250']);
    $this->cari->acilisBakiyesiIsle($musteri);

    expect(fn () => $this->cari->acilisBakiyesiDuzelt($musteri, '250.00', 'x'))
        ->toThrow(InvalidArgumentException::class, 'düzeltilecek fark yok');
    expect(fn () => $this->cari->acilisBakiyesiDuzelt($musteri, '100', '  '))
        ->toThrow(InvalidArgumentException::class, 'açıklama zorunludur');
    expect(fn () => $this->cari->acilisBakiyesiDuzelt($musteri, '12,5', 'x'))
        ->toThrow(InvalidArgumentException::class);
    expect(fn () => $this->cari->acilisBakiyesiDuzelt($musteri, '100', str_repeat('a', 151)))
        ->toThrow(InvalidArgumentException::class, 'en fazla 150');

    expect($musteri->fresh()->acilis_bakiyesi)->toBe('250.00')
        ->and($musteri->fresh()->bakiye)->toBe('250.00')
        ->and($musteri->cariHareketler()->count())->toBe(1);
});

it('fiş kesilince bakiye genel toplam kadar artar, iptalde geri döner', function () {
    $fis = $this->fis->olustur($this->musteri, [
        ['urun' => $this->urun, 'miktar' => '5', 'birim_fiyat' => '100'],
    ], iskonto: '20');

    expect($this->musteri->fresh()->bakiye)->toBe('480.00');

    $hareket = CariHareketi::where('fis_id', $fis->id)->firstOrFail();
    expect($hareket->kaynak)->toBe(CariKaynagi::Fis)
        ->and($hareket->tip)->toBe(CariTipi::Borc)
        ->and($hareket->tutar)->toBe('480.00');

    $this->fis->iptalEt($fis, 'Vazgeçildi');

    expect($this->musteri->fresh()->bakiye)->toBe('0.00')
        ->and(CariHareketi::where('fis_id', $fis->id)->where('kaynak', CariKaynagi::FisIptal->value)->count())->toBe(1);
});

it('fişte stok yetersizse cari bakiye değişmez', function () {
    expect(fn () => $this->fis->olustur($this->musteri, [
        ['urun' => $this->urun, 'miktar' => '60', 'birim_fiyat' => '100'],
    ]))->toThrow(FisStokYetersizException::class);

    expect($this->musteri->fresh()->bakiye)->toBe('0.00')
        ->and(CariHareketi::count())->toBe(0);
});

it('tahsilat bakiyeyi azaltır ve sıralı makbuz numarası alır; iptali geri ekler; tekrar iptal edilemez', function () {
    $this->fis->olustur($this->musteri, [['urun' => $this->urun, 'miktar' => '5', 'birim_fiyat' => '100']]);
    expect($this->musteri->fresh()->bakiye)->toBe('500.00');

    $t1 = $this->tahsilat->kaydet($this->musteri, '200', OdemeYontemi::Nakit, 'Elden');
    $t2 = $this->tahsilat->kaydet($this->musteri, '50', OdemeYontemi::Havale);

    expect($t1->makbuz_no)->toBe("{$this->yil}-000001")
        ->and($t2->makbuz_no)->toBe("{$this->yil}-000002")
        ->and($t1->durum)->toBe(TahsilatDurumu::Aktif)
        ->and($this->musteri->fresh()->bakiye)->toBe('250.00');

    $hareket = CariHareketi::where('tahsilat_id', $t1->id)->firstOrFail();
    expect($hareket->kaynak)->toBe(CariKaynagi::Tahsilat)
        ->and($hareket->tip)->toBe(CariTipi::Alacak)
        ->and($hareket->aciklama)->toContain($t1->makbuz_no);

    expect(fn () => $this->tahsilat->iptalEt($t1, '  '))->toThrow(InvalidArgumentException::class, 'İptal nedeni zorunludur.');

    $iptal = $this->tahsilat->iptalEt($t1, 'Çek karşılıksız çıktı');

    expect($iptal->durum)->toBe(TahsilatDurumu::Iptal)
        ->and($iptal->iptal_eden_id)->toBe($this->kullanici->id)
        ->and($iptal->iptal_nedeni)->toBe('Çek karşılıksız çıktı')
        ->and($this->musteri->fresh()->bakiye)->toBe('450.00')
        ->and(CariHareketi::where('tahsilat_id', $t1->id)->where('kaynak', CariKaynagi::TahsilatIptal->value)->count())->toBe(1);

    $log = Activity::where('log_name', 'tahsilat')->latest('id')->first();
    expect($log?->description)->toContain("Tahsilat {$t1->makbuz_no} iptal edildi");

    expect(fn () => $this->tahsilat->iptalEt($t1, 'Tekrar'))->toThrow(LogicException::class, 'zaten iptal edilmiş');
    expect(fn () => $t2->update(['tutar' => '1']))->toThrow(LogicException::class);
    expect(fn () => $t2->delete())->toThrow(LogicException::class);
    expect(Tahsilat::count())->toBe(2)
        ->and($this->musteri->fresh()->bakiye)->toBe('450.00');
});

it('ondalıklı tutarlarda bakiye kayması olmaz', function () {
    $this->cari->borclandir($this->musteri, '0.1', CariKaynagi::Duzeltme, 'a');
    $this->cari->borclandir($this->musteri, '0.2', CariKaynagi::Duzeltme, 'b');

    expect($this->musteri->fresh()->bakiye)->toBe('0.30');

    $this->cari->alacaklandir($this->musteri, '0.3', CariKaynagi::Duzeltme, 'c');

    expect($this->musteri->fresh()->bakiye)->toBe('0.00');
});

it('bakiyesi sıfırdan farklı müşteri silinemez', function () {
    $this->cari->borclandir($this->musteri, '10', CariKaynagi::Duzeltme, 'x');

    expect(fn () => $this->musteri->fresh()->delete())->toThrow(LogicException::class, 'silinemez');
    expect($this->musteri->fresh()->trashed())->toBeFalse();

    $this->cari->alacaklandir($this->musteri, '10', CariKaynagi::Duzeltme, 'kapanış');
    $this->musteri->fresh()->delete();

    expect(Musteri::withTrashed()->find($this->musteri->id)->trashed())->toBeTrue();
});

it('ekstre devreden bakiye, dönem hareketleri ve kapanışı doğru verir; PDF üretilir', function () {
    $this->cari->borclandir($this->musteri, '100', CariKaynagi::Duzeltme, 'Ocak', Carbon::parse('2026-01-10 10:00'));
    $this->cari->alacaklandir($this->musteri, '30', CariKaynagi::Duzeltme, 'Şubat ödeme', Carbon::parse('2026-02-05 10:00'));
    $this->cari->borclandir($this->musteri, '20', CariKaynagi::Duzeltme, 'Mart', Carbon::parse('2026-03-01 10:00'));

    $subat = $this->cari->ekstre($this->musteri, Carbon::parse('2026-02-01')->startOfDay(), Carbon::parse('2026-02-28')->endOfDay());

    expect($subat['devreden'])->toBe('100.00')
        ->and($subat['satirlar'])->toHaveCount(1)
        ->and($subat['satirlar'][0]['bakiye'])->toBe('70.00')
        ->and($subat['borc_toplam'])->toBe('0.00')
        ->and($subat['alacak_toplam'])->toBe('30.00')
        ->and($subat['kapanis'])->toBe('70.00');

    $tumu = $this->cari->ekstre($this->musteri);

    expect($tumu['devreden'])->toBe('0.00')
        ->and($tumu['satirlar'])->toHaveCount(3)
        ->and($tumu['kapanis'])->toBe('90.00')
        ->and($this->musteri->fresh()->bakiye)->toBe('90.00');

    $this->get(route('musteri.ekstre', ['musteri' => $this->musteri, 'baslangic' => '2026-02-01', 'bitis' => '2026-02-28']))
        ->assertOk()
        ->assertHeader('content-type', 'application/pdf');

    $this->get(route('musteri.ekstre', $this->musteri))->assertOk();
});

it('cari:dogrula tutarsızlığı yakalar ve --duzelt ile hareketlere göre düzeltir', function () {
    $this->cari->borclandir($this->musteri, '100', CariKaynagi::Duzeltme, 'x');

    $this->artisan('cari:dogrula')->assertExitCode(0);

    DB::table('musteriler')->where('id', $this->musteri->id)->update(['bakiye' => '70.00']);

    $this->artisan('cari:dogrula')
        ->expectsOutputToContain('Deneme Tekstil')
        ->assertExitCode(1);

    $this->artisan('cari:dogrula --duzelt')->assertExitCode(0);

    expect($this->musteri->fresh()->bakiye)->toBe('100.00');
    $this->artisan('cari:dogrula')->assertExitCode(0);
});
