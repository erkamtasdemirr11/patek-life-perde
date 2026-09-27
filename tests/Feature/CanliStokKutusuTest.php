<?php

use App\Enums\StokSebebi;
use App\Filament\Kutular\CanliStokKutusu;
use App\Filament\Pages\StokCikisi;
use App\Filament\Pages\StokGiris;
use App\Filament\Resources\Fisler\Pages\CreateFis;
use App\Filament\Resources\Urunler\Pages\CreateUrun;
use App\Filament\Resources\Urunler\Pages\EditUrun;
use App\Models\Urun;
use App\Models\User;
use App\Services\StokServisi;
use Livewire\Livewire;

beforeEach(function () {
    $this->actingAs(User::factory()->create());
    $this->tul = Urun::factory()->create(['ad' => 'Keten Tül', 'satis_fiyati' => '150', 'kritik_stok' => '10']);
    $this->fon = Urun::factory()->create(['ad' => 'Kadife Fon', 'satis_fiyati' => '890']);
    $this->pasif = Urun::factory()->create(['ad' => 'Pasif Ürün', 'aktif' => false]);
    app(StokServisi::class)->girisYap($this->tul, '4', StokSebebi::Alis);
    app(StokServisi::class)->girisYap($this->fon, '50', StokSebebi::Alis);
});

it('canlı stok kutusu aktif ürünleri stoklarıyla listeler, arar ve istenirse fiyat gösterir', function () {
    Livewire::test(CanliStokKutusu::class, ['baslik' => 'Kayıtlı ürünler', 'fiyatGoster' => true])
        ->assertOk()
        ->assertSee('Kayıtlı ürünler')
        ->assertCanSeeTableRecords([$this->tul, $this->fon])
        ->assertCanNotSeeTableRecords([$this->pasif])
        ->assertSee('4,00 m')
        ->assertSee('890,00 ₺')
        ->searchTable('Keten')
        ->assertCanSeeTableRecords([$this->tul])
        ->assertCanNotSeeTableRecords([$this->fon]);

    Livewire::test(CanliStokKutusu::class, ['fiyatGoster' => false])
        ->assertDontSee('890,00 ₺');
});

it('canlı stok kutusu düzenlenen ürünü listeden çıkarır', function () {
    Livewire::test(CanliStokKutusu::class, ['haricId' => $this->tul->id])
        ->assertCanSeeTableRecords([$this->fon])
        ->assertCanNotSeeTableRecords([$this->tul]);
});

it('kutu, stok giriş/çıkış, fiş kayıt ve ürün sayfalarına gömülüdür', function () {
    foreach ([StokGiris::class, StokCikisi::class, CreateFis::class, CreateUrun::class] as $sayfa) {
        Livewire::test($sayfa)->assertOk()->assertSeeLivewire(CanliStokKutusu::class);
    }

    Livewire::test(EditUrun::class, ['record' => $this->tul->getRouteKey()])
        ->assertOk()
        ->assertSeeLivewire(CanliStokKutusu::class);
});
