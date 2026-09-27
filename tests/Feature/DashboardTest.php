<?php

use App\Enums\OdemeYontemi;
use App\Enums\StokSebebi;
use App\Filament\Pages\Dashboard;
use App\Filament\Widgets\KritikStokWidget;
use App\Filament\Widgets\OzetWidget;
use App\Filament\Widgets\SatisGrafigiWidget;
use App\Filament\Widgets\SonFislerWidget;
use App\Filament\Widgets\StokHareketGrafigiWidget;
use App\Models\Musteri;
use App\Models\Urun;
use App\Models\User;
use App\Services\FisServisi;
use App\Services\StokServisi;
use App\Services\TahsilatServisi;
use Livewire\Livewire;

beforeEach(function () {
    $this->actingAs(User::factory()->create());
    $this->musteri = Musteri::factory()->create(['firma_adi' => 'Pano Tekstil']);
    $this->urun = Urun::factory()->create(['ad' => 'Pano Tül', 'satis_fiyati' => '100', 'kritik_stok' => '10']);
    app(StokServisi::class)->girisYap($this->urun, '12', StokSebebi::Alis);
    $this->fis = app(FisServisi::class)->olustur($this->musteri, [['urun' => $this->urun, 'miktar' => '5', 'birim_fiyat' => '100']]);
    app(TahsilatServisi::class)->kaydet($this->musteri, '200', OdemeYontemi::Nakit);
});

it('pano açılır, kısayolları ve tüm widget\'ları gösterir', function () {
    Livewire::test(Dashboard::class)
        ->assertOk()
        ->assertSee('Genel Bakış')
        ->assertSee('Yeni fiş')
        ->assertSee('Stok girişi')
        ->assertSee('Tahsilat');

    $widgetlar = collect(Livewire::test(Dashboard::class)->instance()->getWidgets())
        ->map(fn ($w) => is_string($w) ? $w : $w::class)
        ->all();

    expect($widgetlar)->toContain(OzetWidget::class, SatisGrafigiWidget::class, StokHareketGrafigiWidget::class, SonFislerWidget::class, KritikStokWidget::class)
        ->not->toContain(\Filament\Widgets\AccountWidget::class, \Filament\Widgets\FilamentInfoWidget::class);
});

it('özet widget\'ı satış, alacak ve kritik stoğu doğru sayar', function () {
    Livewire::test(OzetWidget::class)
        ->assertOk()
        ->assertSee('Bugünkü satış')
        ->assertSee('500,00 ₺')          // bugünkü ve aylık satış
        ->assertSee('1 fiş kesildi')
        ->assertSee('Açık alacak')
        ->assertSee('300,00 ₺')          // 500 − 200 tahsilat
        ->assertSee('1 borçlu müşteri')
        ->assertSee('Kritik stok')
        ->assertSee('Yenilenmesi gereken ürün');   // 12 − 5 = 7 ≤ 10
});

it('grafik widget\'ları veriyi hazırlar', function () {
    Livewire::test(SatisGrafigiWidget::class)->assertOk()->assertSee('Son 30 gün satış');
    Livewire::test(StokHareketGrafigiWidget::class)->assertOk()->assertSee('Stok hareketleri');

    $satis = (new ReflectionMethod(SatisGrafigiWidget::class, 'getData'))->invoke(app(SatisGrafigiWidget::class));
    expect($satis['labels'])->toHaveCount(30)
        ->and(end($satis['datasets'][0]['data']))->toBe(500.0);

    $stok = (new ReflectionMethod(StokHareketGrafigiWidget::class, 'getData'))->invoke(app(StokHareketGrafigiWidget::class));
    expect(end($stok['datasets'][0]['data']))->toBe(1)   // bugünkü giriş
        ->and(end($stok['datasets'][1]['data']))->toBe(1);   // bugünkü çıkış (fiş)
});

it('son fişler ve kritik stok widget\'ları kayıtları listeler', function () {
    Livewire::test(SonFislerWidget::class)
        ->assertOk()
        ->assertCanSeeTableRecords([$this->fis])
        ->assertSee('Pano Tekstil');

    Livewire::test(KritikStokWidget::class)
        ->assertOk()
        ->assertCanSeeTableRecords([$this->urun])
        ->assertSee('7,00 m');
});

it('panel menüsünde web sitesine giden bağlantı bulunur', function () {
    $html = $this->get('/panel')->assertOk()->getContent();

    expect($html)->toContain('Web Sitesi')
        ->and(preg_match('/<a[^>]+href="' . preg_quote(url('/'), '/') . '"[^>]+target="_blank"/', $html))->toBe(1);
});
