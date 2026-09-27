<?php

use App\Enums\StokSebebi;
use App\Enums\OdemeTipi;
use App\Enums\OdemeYontemi;
use App\Models\Tahsilat;
use App\Filament\Resources\Fisler\Pages\CreateFis;
use App\Filament\Resources\Fisler\Pages\ListFisler;
use App\Filament\Resources\Fisler\Pages\ViewFis;
use App\Filament\Resources\Musteriler\Pages\CreateMusteri;
use App\Models\Fis;
use App\Models\Musteri;
use App\Models\Urun;
use App\Models\User;
use App\Services\FisServisi;
use App\Services\StokServisi;
use Livewire\Livewire;

beforeEach(function () {
    $this->actingAs(User::factory()->create());
    $this->musteri = Musteri::factory()->create(['firma_adi' => 'Deneme Tekstil']);
    $this->urun = Urun::factory()->create(['ad' => 'Keten Beyaz', 'satis_fiyati' => '100']);
    app(StokServisi::class)->girisYap($this->urun, '50', StokSebebi::Alis);
});

it('fiş kayıt sayfası fiş oluşturur, stoğu düşer ve görüntülemeye yönlendirir', function () {
    Livewire::test(CreateFis::class)
        ->fillForm([
            'musteri_id' => $this->musteri->id,
            'tarih' => now()->format('Y-m-d H:i:s'),
            'aciklama' => 'Panelden kesildi',
            'kalemler' => [
                ['urun_id' => $this->urun->id, 'miktar' => '2.5', 'birim_fiyat' => '100'],
            ],
            'iskonto' => '10',
        ])
        ->call('create')
        ->assertHasNoFormErrors()
        ->assertRedirect();

    $fis = Fis::firstOrFail();

    expect($fis->fis_no)->toBe(now()->year . '-000001')
        ->and($fis->ara_toplam)->toBe('250.00')
        ->and($fis->genel_toplam)->toBe('240.00')
        ->and($fis->aciklama)->toBe('Panelden kesildi')
        ->and($fis->kalemler)->toHaveCount(1)
        ->and($this->urun->fresh()->mevcut_stok)->toBe('47.50');
});

it('fiş kayıt sayfası stok yetersizse fiş oluşturmaz ve uyarır', function () {
    Livewire::test(CreateFis::class)
        ->fillForm([
            'musteri_id' => $this->musteri->id,
            'tarih' => now()->format('Y-m-d H:i:s'),
            'kalemler' => [
                ['urun_id' => $this->urun->id, 'miktar' => '60', 'birim_fiyat' => '100'],
            ],
            'iskonto' => '0',
        ])
        ->call('create')
        ->assertNotified();

    expect(Fis::count())->toBe(0)
        ->and($this->urun->fresh()->mevcut_stok)->toBe('50.00');
});

it('fiş kayıt sayfası müşteri ve kalem olmadan kaydetmez', function () {
    Livewire::test(CreateFis::class)
        ->fillForm([
            'musteri_id' => null,
            'kalemler' => [
                ['urun_id' => null, 'miktar' => null, 'birim_fiyat' => null],
            ],
        ])
        ->call('create')
        ->assertHasFormErrors();

    expect(Fis::count())->toBe(0);
});

it('fiş listesi ve görüntüleme sayfası açılır, görüntülemeden iptal edilir', function () {
    $fis = app(FisServisi::class)->olustur($this->musteri, [
        ['urun' => $this->urun, 'miktar' => '5', 'birim_fiyat' => '100'],
    ]);

    Livewire::test(ListFisler::class)
        ->assertOk()
        ->assertCanSeeTableRecords([$fis]);

    Livewire::test(ViewFis::class, ['record' => $fis->getRouteKey()])
        ->assertOk()
        ->assertSee($fis->fis_no)
        ->assertSee('Deneme Tekstil')
        ->callAction('iptal', data: ['iptal_nedeni' => 'Yanlış kesildi'])
        ->assertHasNoActionErrors();

    expect($fis->fresh()->iptalMi())->toBeTrue()
        ->and($fis->fresh()->iptal_nedeni)->toBe('Yanlış kesildi')
        ->and($this->urun->fresh()->mevcut_stok)->toBe('50.00');
});

it('iptal edilmiş fişte iptal butonu görünmez', function () {
    $servis = app(FisServisi::class);
    $fis = $servis->olustur($this->musteri, [
        ['urun' => $this->urun, 'miktar' => '5', 'birim_fiyat' => '100'],
    ]);
    $servis->iptalEt($fis, 'Deneme');

    Livewire::test(ViewFis::class, ['record' => $fis->getRouteKey()])
        ->assertActionHidden('iptal')
        ->assertSee('İptal bilgisi');
});

it('müşteri sayfasından müşteri oluşturulur', function () {
    Livewire::test(CreateMusteri::class)
        ->fillForm([
            'firma_adi' => 'Yeni Firma',
            'yetkili' => 'Ali Veli',
            'telefon' => '0555 000 00 00',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(Musteri::where('firma_adi', 'Yeni Firma')->exists())->toBeTrue();
});

it('fiş kayıt sayfasında peşin seçilince fişle birlikte tahsilat makbuzu kesilir', function () {
    Livewire::test(CreateFis::class)
        ->fillForm([
            'musteri_id' => $this->musteri->id,
            'tarih' => now()->format('Y-m-d H:i:s'),
            'kalemler' => [
                ['urun_id' => $this->urun->id, 'miktar' => '2', 'birim_fiyat' => '100'],
            ],
            'iskonto' => '0',
            'odeme_tipi' => OdemeTipi::Pesin->value,
            'odeme_yontemi' => OdemeYontemi::Nakit->value,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $fis = Fis::firstOrFail();

    expect($fis->odeme_tipi)->toBe(OdemeTipi::Pesin)
        ->and(Tahsilat::where('fis_id', $fis->id)->exists())->toBeTrue()
        ->and($this->musteri->fresh()->bakiye)->toBe('0.00');
});

it('fiş görüntüleme sayfası cari hesap bölümünü gösterir', function () {
    $fis = app(FisServisi::class)->olustur($this->musteri, [
        ['urun' => $this->urun, 'miktar' => '5', 'birim_fiyat' => '100'],
    ]);

    Livewire::test(ViewFis::class, ['record' => $fis->getRouteKey()])
        ->assertOk()
        ->assertSee('Cari hesap')
        ->assertSee('Önceki bakiye')
        ->assertSee('Fiş sonrası bakiye')
        ->assertSee('Açık hesap');

    expect($this->musteri->fresh()->bakiye)->toBe('500.00');
});
