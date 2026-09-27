<?php

use App\Enums\StokSebebi;
use App\Filament\Pages\CanliStok;
use App\Filament\Widgets\KritikStokWidget;
use App\Filament\Widgets\OzetWidget;
use App\Models\Urun;
use App\Models\User;
use App\Services\StokServisi;
use Livewire\Livewire;

beforeEach(function () {
    $this->actingAs(User::factory()->create());
});

it('canlı stok yalnızca aktif ve arşivlenmemiş ürünleri listeler', function () {
    $aktif = Urun::factory()->create(['ad' => 'Aktif Ürün']);
    $pasif = Urun::factory()->create(['ad' => 'Pasif Ürün', 'aktif' => false]);
    $arsivli = Urun::factory()->create(['ad' => 'Arşivli Ürün']);
    $arsivli->delete();

    Livewire::test(CanliStok::class)
        ->assertOk()
        ->assertCanSeeTableRecords([$aktif])
        ->assertCanNotSeeTableRecords([$pasif, $arsivli]);
});

it('"sadece kritik" filtresi kritik seviyedeki ürünleri getirir', function () {
    $servis = app(StokServisi::class);
    $kritik = Urun::factory()->create(['ad' => 'Azalan', 'kritik_stok' => '10']);
    $yeterli = Urun::factory()->create(['ad' => 'Bol', 'kritik_stok' => '10']);
    $servis->girisYap($kritik, '5', StokSebebi::Alis);
    $servis->girisYap($yeterli, '50', StokSebebi::Alis);

    Livewire::test(CanliStok::class)
        ->assertCanSeeTableRecords([$kritik, $yeterli])
        ->filterTable('kritik')
        ->assertCanSeeTableRecords([$kritik])
        ->assertCanNotSeeTableRecords([$yeterli]);
});

it('pano widget\'ları kritik stoğu doğru sayar ve listeler', function () {
    $servis = app(StokServisi::class);
    $kritik = Urun::factory()->create(['ad' => 'Azalan', 'kritik_stok' => '10']);
    $yeterli = Urun::factory()->create(['ad' => 'Bol', 'kritik_stok' => '10']);
    $servis->girisYap($kritik, '5', StokSebebi::Alis);
    $servis->girisYap($yeterli, '50', StokSebebi::Alis);

    Livewire::test(OzetWidget::class)
        ->assertOk()
        ->assertSee('Bugünkü satış')
        ->assertSee('Kritik stok')
        ->assertSee('Yenilenmesi gereken ürün');

    Livewire::test(KritikStokWidget::class)
        ->assertOk()
        ->assertCanSeeTableRecords([$kritik])
        ->assertCanNotSeeTableRecords([$yeterli])
        ->assertSee('5,00 m');
});
