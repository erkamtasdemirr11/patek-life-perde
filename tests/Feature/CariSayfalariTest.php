<?php

use App\Enums\CariKaynagi;
use App\Enums\OdemeYontemi;
use App\Enums\StokSebebi;
use App\Filament\Resources\Musteriler\MusteriResource;
use App\Filament\Resources\Musteriler\Pages\CreateMusteri;
use App\Filament\Resources\Musteriler\Pages\EditMusteri;
use App\Filament\Resources\Musteriler\Pages\ListMusteriler;
use App\Filament\Resources\Tahsilatlar\Pages\CreateTahsilat;
use App\Filament\Resources\Tahsilatlar\Pages\ListTahsilatlar;
use App\Models\Musteri;
use App\Models\Tahsilat;
use App\Models\Urun;
use App\Models\User;
use App\Services\CariServisi;
use App\Services\FisServisi;
use App\Services\StokServisi;
use App\Services\TahsilatServisi;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;

beforeEach(function () {
    $this->actingAs(User::factory()->create());
    $this->musteri = Musteri::factory()->create(['firma_adi' => 'Deneme Tekstil']);
    $this->urun = Urun::factory()->create(['satis_fiyati' => '100']);
    app(StokServisi::class)->girisYap($this->urun, '50', StokSebebi::Alis);
});

it('müşteri oluştururken girilen açılış bakiyesi cari harekete dönüşür', function () {
    Livewire::test(CreateMusteri::class)
        ->fillForm([
            'firma_adi' => 'Açılışlı Firma',
            'acilis_bakiyesi' => '150',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $musteri = Musteri::where('firma_adi', 'Açılışlı Firma')->firstOrFail();

    expect($musteri->bakiye)->toBe('150.00')
        ->and($musteri->cariHareketler()->count())->toBe(1)
        ->and($musteri->cariHareketler()->first()->kaynak)->toBe(CariKaynagi::Acilis);
});

it('müşteri düzenleme sayfası açılır ve cari hareketleri listeler', function () {
    app(CariServisi::class)->borclandir($this->musteri, '75', CariKaynagi::Duzeltme, 'Devir');

    Livewire::test(EditMusteri::class, ['record' => $this->musteri->getRouteKey()])
        ->assertOk()
        ->assertSee('Güncel bakiye')
        ->assertActionHidden('delete')
        ->assertActionVisible('ekstre');
});

it('müşteri kartından açılış bakiyesi düzeltilir, silinmiş müşteride buton görünmez', function () {
    $musteri = Musteri::factory()->create(['firma_adi' => 'Yanlış Girilen', 'acilis_bakiyesi' => '50000']);
    app(CariServisi::class)->acilisBakiyesiIsle($musteri);

    Livewire::test(EditMusteri::class, ['record' => $musteri->getRouteKey()])
        ->assertActionVisible('acilisDuzelt')
        ->callAction('acilisDuzelt', data: [
            'dogru_tutar' => '40000',
            'aciklama' => 'Yanlış girildi',
        ])
        ->assertHasNoActionErrors()
        ->assertRedirect(MusteriResource::getUrl('edit', ['record' => $musteri]));

    expect($musteri->fresh()->acilis_bakiyesi)->toBe('40000.00')
        ->and($musteri->fresh()->bakiye)->toBe('40000.00')
        ->and($musteri->cariHareketler()->where('kaynak', CariKaynagi::Duzeltme->value)->sole()->tutar)->toBe('10000.00');

    // Açıklama zorunlu.
    Livewire::test(EditMusteri::class, ['record' => $musteri->getRouteKey()])
        ->callAction('acilisDuzelt', data: ['dogru_tutar' => '30000', 'aciklama' => ''])
        ->assertHasActionErrors(['aciklama' => 'required']);

    expect($musteri->fresh()->bakiye)->toBe('40000.00');

    $silinen = Musteri::factory()->create(['firma_adi' => 'Silinen']);
    $silinen->delete();

    Livewire::test(EditMusteri::class, ['record' => $silinen->getRouteKey()])
        ->assertActionHidden('acilisDuzelt');
});

it('tahsilat sayfası tahsilat kaydeder ve bakiyeyi düşer', function () {
    app(FisServisi::class)->olustur($this->musteri, [['urun' => $this->urun, 'miktar' => '5', 'birim_fiyat' => '100']]);

    Livewire::test(CreateTahsilat::class)
        ->fillForm([
            'musteri_id' => $this->musteri->id,
            'tarih' => now()->format('Y-m-d H:i:s'),
            'tutar' => '200',
            'odeme_yontemi' => OdemeYontemi::Havale->value,
            'aciklama' => 'Dekont 123',
        ])
        ->call('create')
        ->assertHasNoFormErrors()
        ->assertRedirect();

    $tahsilat = Tahsilat::firstOrFail();

    expect($tahsilat->makbuz_no)->toBe(now()->year . '-000001')
        ->and($tahsilat->odeme_yontemi)->toBe(OdemeYontemi::Havale)
        ->and($this->musteri->fresh()->bakiye)->toBe('300.00');
});

it('tahsilat listesinden iptal edilir ve iptal edilende buton görünmez', function () {
    app(CariServisi::class)->borclandir($this->musteri, '500', CariKaynagi::Duzeltme, 'Devir');
    $tahsilat = app(TahsilatServisi::class)->kaydet($this->musteri, '200', OdemeYontemi::Nakit);
    expect($this->musteri->fresh()->bakiye)->toBe('300.00');

    Livewire::test(ListTahsilatlar::class)
        ->assertCanSeeTableRecords([$tahsilat])
        ->callTableAction('iptal', $tahsilat, data: ['iptal_nedeni' => 'Hatalı giriş'])
        ->assertHasNoTableActionErrors()
        ->assertTableActionHidden('iptal', $tahsilat);

    expect($tahsilat->fresh()->iptalMi())->toBeTrue()
        ->and($this->musteri->fresh()->bakiye)->toBe('500.00');
});

it('müşteri listesinde bakiyesi olan müşteride sil gizlidir', function () {
    app(CariServisi::class)->borclandir($this->musteri, '100', CariKaynagi::Duzeltme, 'Devir');
    $temiz = Musteri::factory()->create(['firma_adi' => 'Temiz Hesap']);

    Livewire::test(ListMusteriler::class)
        ->assertCanSeeTableRecords([$this->musteri, $temiz])
        ->assertTableActionHidden('delete', $this->musteri)
        ->assertTableActionVisible('delete', $temiz);
});

it('müşteri "Sil" ile silinir, "Son silinenler" sekmesinden "Geri al" ile döner ve log "silindi" der', function () {
    $temiz = Musteri::factory()->create(['firma_adi' => 'Temiz Hesap']);

    Livewire::test(ListMusteriler::class)
        ->assertOk()
        ->assertSee('Son silinenler')
        ->callTableAction('delete', $temiz)
        ->assertHasNoTableActionErrors();

    expect($temiz->fresh()->trashed())->toBeTrue()
        ->and(Activity::where('subject_type', Musteri::class)->where('subject_id', $temiz->id)->latest('id')->first()->description)->toBe('Müşteri silindi');

    Livewire::test(ListMusteriler::class)
        ->assertCanNotSeeTableRecords([$temiz])
        ->set('activeTab', 'silinenler')
        ->assertCanSeeTableRecords([$temiz])
        ->assertCanNotSeeTableRecords([$this->musteri])
        ->callTableAction('restore', $temiz)
        ->assertHasNoTableActionErrors();

    expect($temiz->fresh()->trashed())->toBeFalse()
        ->and(Activity::where('subject_type', Musteri::class)->where('subject_id', $temiz->id)->latest('id')->first()->description)->toBe('Müşteri geri alındı');
});

it('müşteri kartından "Sil" listeye yönlendirir, silinen kartı ve ekstresi açılmaya devam eder', function () {
    $temiz = Musteri::factory()->create(['firma_adi' => 'Temiz Hesap']);

    Livewire::test(EditMusteri::class, ['record' => $temiz->getRouteKey()])
        ->callAction('delete')
        ->assertHasNoErrors()
        ->assertRedirect(MusteriResource::getUrl('index'));

    expect($temiz->fresh()->trashed())->toBeTrue();

    $this->get(MusteriResource::getUrl('index'))->assertOk();
    $this->get(MusteriResource::getUrl('edit', ['record' => $temiz]))->assertOk();
    $this->get(route('musteri.ekstre', ['musteri' => $temiz->id]))->assertOk();
});
