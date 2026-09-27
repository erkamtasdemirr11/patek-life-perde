<?php

use App\Enums\OdemeYontemi;
use App\Enums\StokSebebi;
use App\Filament\Pages\CariHareketler;
use App\Filament\Pages\FisGecmisi;
use App\Filament\Pages\LogHareketleri;
use App\Filament\Pages\StokGecmisi;
use App\Models\CariHareketi;
use App\Models\Musteri;
use App\Models\StokHareketi;
use App\Models\Urun;
use App\Models\User;
use App\Services\FisServisi;
use App\Services\StokServisi;
use App\Services\TahsilatServisi;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;

beforeEach(function () {
    $this->actingAs(User::factory()->create(['name' => 'Test Kullanıcı']));
    $this->musteri = Musteri::factory()->create(['firma_adi' => 'Deneme Tekstil']);
    $this->urun = Urun::factory()->create(['ad' => 'Keten Beyaz', 'satis_fiyati' => '100']);

    app(StokServisi::class)->girisYap($this->urun, '50', StokSebebi::Alis, 'İlk alım');
    $this->fis = app(FisServisi::class)->olustur($this->musteri, [['urun' => $this->urun, 'miktar' => '5', 'birim_fiyat' => '100']]);
    app(TahsilatServisi::class)->kaydet($this->musteri, '200', OdemeYontemi::Nakit);
});

it('stok geçmişi hareketleri listeler, tarih filtresi çalışır ve CSV indirir', function () {
    $hareketler = StokHareketi::all();
    expect($hareketler)->toHaveCount(2);

    Livewire::test(StokGecmisi::class)
        ->assertOk()
        ->assertCanSeeTableRecords($hareketler)
        ->assertSee('50,00 m')
        ->assertSee($this->fis->fis_no)
        ->filterTable('tarih', ['baslangic' => now()->addDay()->toDateString()])
        ->assertCanNotSeeTableRecords($hareketler)
        ->filterTable('tarih', ['baslangic' => now()->subDay()->toDateString(), 'bitis' => now()->toDateString()])
        ->assertCanSeeTableRecords($hareketler)
        ->callTableAction('csv')
        ->assertFileDownloaded();
});

it('fiş geçmişi fişleri listeler ve CSV indirir', function () {
    Livewire::test(FisGecmisi::class)
        ->assertOk()
        ->assertCanSeeTableRecords([$this->fis])
        ->assertSee('Deneme Tekstil')
        ->assertTableActionExists('goruntule')
        ->assertTableActionDoesNotExist('iptal')
        ->callTableAction('csv')
        ->assertFileDownloaded();
});

it('cari hareketler fiş ve tahsilat kayıtlarını listeler', function () {
    $hareketler = CariHareketi::all();
    expect($hareketler)->toHaveCount(2);

    Livewire::test(CariHareketler::class)
        ->assertOk()
        ->assertCanSeeTableRecords($hareketler)
        ->assertSee('Deneme Tekstil')
        ->filterTable('tip', 'alacak')
        ->assertCanSeeTableRecords($hareketler->where('tip.value', 'alacak'))
        ->assertCanNotSeeTableRecords($hareketler->where('tip.value', 'borc'))
        ->callTableAction('csv')
        ->assertFileDownloaded();
});

it('log hareketleri logları listeler, detay açar ve CSV indirir', function () {
    $log = Activity::where('log_name', 'urun')->latest('id')->firstOrFail();

    Livewire::test(LogHareketleri::class)
        ->assertOk()
        ->assertCanSeeTableRecords([$log])
        ->assertSee('Ürün oluşturuldu')
        ->assertSee('Keten Beyaz')
        ->callTableAction('detay', $log)
        ->assertHasNoTableActionErrors()
        ->filterTable('log_name', 'urun')
        ->assertCanSeeTableRecords([$log])
        ->callTableAction('csv')
        ->assertFileDownloaded();
});

it('geçmiş sayfalarında oluşturma, düzenleme ve silme aksiyonu yoktur', function () {
    foreach ([StokGecmisi::class, FisGecmisi::class, CariHareketler::class, LogHareketleri::class] as $sayfa) {
        Livewire::test($sayfa)
            ->assertTableActionDoesNotExist('edit')
            ->assertTableActionDoesNotExist('delete')
            ->assertTableActionDoesNotExist('create');
    }
});
