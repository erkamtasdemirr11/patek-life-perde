<?php

use App\Enums\StokSebebi;
use App\Enums\StokYonu;
use App\Filament\Pages\StokCikisi;
use App\Filament\Pages\StokGiris;
use App\Models\StokHareketi;
use App\Models\Urun;
use App\Models\User;
use App\Services\StokServisi;

use Livewire\Livewire;

beforeEach(function () {
    $this->actingAs(User::factory()->create());
    $this->urun = Urun::factory()->create(['ad' => 'Keten Beyaz']);
    $this->urun2 = Urun::factory()->create(['ad' => 'Fon Krem']);
});

it('stok giriş sayfası birden fazla ürüne giriş yapar', function () {
    Livewire::test(StokGiris::class)
        ->fillForm([
            'sebep' => StokSebebi::Alis->value,
            'aciklama' => 'Toptancıdan alım',
            'kalemler' => [
                ['urun_id' => $this->urun->id, 'miktar' => '25.5'],
                ['urun_id' => $this->urun2->id, 'miktar' => '10'],
            ],
        ])
        ->call('kaydet')
        ->assertHasNoFormErrors()
        ->assertNotified();

    expect($this->urun->fresh()->mevcut_stok)->toBe('25.50')
        ->and($this->urun2->fresh()->mevcut_stok)->toBe('10.00')
        ->and(StokHareketi::count())->toBe(2)
        ->and(StokHareketi::first()->aciklama)->toBe('Toptancıdan alım');
});

it('stok giriş sayfası ürün ve miktar olmadan kaydetmez', function () {
    Livewire::test(StokGiris::class)
        ->fillForm([
            'sebep' => StokSebebi::Alis->value,
            'kalemler' => [
                ['urun_id' => null, 'miktar' => null],
            ],
        ])
        ->call('kaydet')
        ->assertHasFormErrors();

    expect(StokHareketi::count())->toBe(0);
});

it('stok çıkışı sayfası stoğu düşer ve satış sebebi sunmaz', function () {
    app(StokServisi::class)->girisYap($this->urun, '30', StokSebebi::Alis);

    expect(StokSebebi::elleSecilebilir(StokYonu::Cikis))->not->toHaveKey('satis')
        ->and(StokSebebi::elleSecilebilir(StokYonu::Cikis))->not->toHaveKey('fis_iptal');

    Livewire::test(StokCikisi::class)
        ->fillForm([
            'sebep' => StokSebebi::Fire->value,
            'kalemler' => [
                ['urun_id' => $this->urun->id, 'miktar' => '12.5'],
            ],
        ])
        ->call('kaydet')
        ->assertHasNoFormErrors()
        ->assertNotified();

    expect($this->urun->fresh()->mevcut_stok)->toBe('17.50')
        ->and(StokHareketi::where('yon', StokYonu::Cikis)->count())->toBe(1);
});

it('stok çıkışında bir satır yetersizse hiçbir satır işlenmez', function () {
    $servis = app(StokServisi::class);
    $servis->girisYap($this->urun, '30', StokSebebi::Alis);
    $servis->girisYap($this->urun2, '5', StokSebebi::Alis);

    Livewire::test(StokCikisi::class)
        ->fillForm([
            'sebep' => StokSebebi::Numune->value,
            'kalemler' => [
                ['urun_id' => $this->urun->id, 'miktar' => '10'],   // yeterli
                ['urun_id' => $this->urun2->id, 'miktar' => '8'],   // yetersiz (5 var)
            ],
        ])
        ->call('kaydet')
        ->assertNotified();

    // Yeterli olan ilk satır da geri alınmış olmalı.
    expect($this->urun->fresh()->mevcut_stok)->toBe('30.00')
        ->and($this->urun2->fresh()->mevcut_stok)->toBe('5.00')
        ->and(StokHareketi::where('yon', StokYonu::Cikis)->count())->toBe(0);
});
