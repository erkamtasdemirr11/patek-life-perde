<?php

use App\Enums\StokSebebi;
use App\Enums\StokYonu;
use App\Exceptions\YetersizStokException;
use App\Models\StokHareketi;
use App\Models\Urun;
use App\Models\User;
use App\Services\StokServisi;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $this->kullanici = User::factory()->create();
    $this->actingAs($this->kullanici);

    $this->servis = app(StokServisi::class);
    $this->urun = Urun::factory()->create(['ad' => 'Keten Beyaz']);
});

it('stok girişi stoğu artırır ve hareket kaydı oluşturur', function () {
    $hareket = $this->servis->girisYap($this->urun, '25.5', StokSebebi::Alis, aciklama: 'İlk alış');

    expect($this->urun->mevcut_stok)->toBe('25.50')
        ->and($this->urun->fresh()->mevcut_stok)->toBe('25.50')
        ->and($hareket->yon)->toBe(StokYonu::Giris)
        ->and($hareket->sebep)->toBe(StokSebebi::Alis)
        ->and($hareket->miktar)->toBe('25.50')
        ->and($hareket->aciklama)->toBe('İlk alış')
        ->and($hareket->user_id)->toBe($this->kullanici->id)
        ->and(StokHareketi::count())->toBe(1);
});

it('stok çıkışı stoğu azaltır ve hareket kaydı oluşturur', function () {
    $this->servis->girisYap($this->urun, '30', StokSebebi::Alis);
    $hareket = $this->servis->cikisYap($this->urun, '12.5', StokSebebi::Fire, aciklama: 'Kesim firesi');

    expect($this->urun->fresh()->mevcut_stok)->toBe('17.50')
        ->and($hareket->yon)->toBe(StokYonu::Cikis)
        ->and($hareket->miktar)->toBe('12.50')
        ->and(StokHareketi::count())->toBe(2);
});

it('yetersiz stokta Türkçe mesajlı exception fırlatır ve hiçbir şey kaydetmez', function () {
    $this->servis->girisYap($this->urun, '12.5', StokSebebi::Alis);

    expect(fn () => $this->servis->cikisYap($this->urun, '20', StokSebebi::Numune))
        ->toThrow(YetersizStokException::class, 'Stok yetersiz: Keten Beyaz için mevcut 12,50 m, istenen 20,00 m');

    expect($this->urun->fresh()->mevcut_stok)->toBe('12.50')
        ->and(StokHareketi::count())->toBe(1);
});

it('yetersiz stok exception\'ı eksik miktarı verir', function () {
    $this->servis->girisYap($this->urun, '12.5', StokSebebi::Alis);

    try {
        $this->servis->cikisYap($this->urun, '20', StokSebebi::Numune);
        $this->fail('Exception bekleniyordu.');
    } catch (YetersizStokException $e) {
        expect($e->eksik())->toBe('7.50')
            ->and($e->urun->is($this->urun))->toBeTrue();
    }
});

it('ondalıklı miktarlarda hesap hatası oluşmaz', function () {
    $this->servis->girisYap($this->urun, '0.1', StokSebebi::Alis);
    $this->servis->girisYap($this->urun, '0.2', StokSebebi::Alis);

    expect($this->urun->fresh()->mevcut_stok)->toBe('0.30');

    $this->servis->cikisYap($this->urun, '0.3', StokSebebi::Sayim);

    expect($this->urun->fresh()->mevcut_stok)->toBe('0.00');
});

it('miktar sıfır, negatif veya sayı dışı olamaz', function (string $miktar) {
    expect(fn () => $this->servis->girisYap($this->urun, $miktar, StokSebebi::Alis))
        ->toThrow(InvalidArgumentException::class);

    expect(StokHareketi::count())->toBe(0);
})->with(['0', '0.00', '-5', 'abc', '12,5', '1.234']);

it('sebep, yöne uygun olmak zorundadır', function () {
    expect(fn () => $this->servis->girisYap($this->urun, '5', StokSebebi::Satis))
        ->toThrow(InvalidArgumentException::class, '"Satış" sebebi stok giriş işlemi için kullanılamaz.');

    expect(fn () => $this->servis->cikisYap($this->urun, '5', StokSebebi::Alis))
        ->toThrow(InvalidArgumentException::class);

    expect(StokHareketi::count())->toBe(0);
});

it('stok hareketleri düzenlenemez ve silinemez', function () {
    $hareket = $this->servis->girisYap($this->urun, '10', StokSebebi::Alis);

    expect(fn () => $hareket->update(['miktar' => '99']))->toThrow(LogicException::class);
    expect(fn () => $hareket->delete())->toThrow(LogicException::class);

    expect($hareket->fresh()->miktar)->toBe('10.00')
        ->and(StokHareketi::count())->toBe(1);
});

it('arşivlenmiş ürüne de hareket işlenebilir (fiş iptali için gerekli)', function () {
    $this->servis->girisYap($this->urun, '10', StokSebebi::Alis);
    $this->urun->delete();

    $this->servis->girisYap($this->urun, '2', StokSebebi::FisIptal);

    expect(Urun::withTrashed()->find($this->urun->id)->mevcut_stok)->toBe('12.00');
});

it('stok:dogrula tutarsızlığı yakalar ve --duzelt ile hareketlere göre düzeltir', function () {
    $this->servis->girisYap($this->urun, '10', StokSebebi::Alis);

    $this->artisan('stok:dogrula')->assertExitCode(0);

    // Kural dışı doğrudan yazma: tutarsızlık yaratıyoruz.
    DB::table('urunler')->where('id', $this->urun->id)->update(['mevcut_stok' => '7.00']);

    $this->artisan('stok:dogrula')
        ->expectsOutputToContain('Keten Beyaz')
        ->assertExitCode(1);

    $this->artisan('stok:dogrula --duzelt')->assertExitCode(0);

    expect($this->urun->fresh()->mevcut_stok)->toBe('10.00');

    $this->artisan('stok:dogrula')->assertExitCode(0);
});
