<?php

use App\Filament\Resources\Urunler\Pages\CreateUrun;
use App\Filament\Resources\Urunler\Pages\ListUrunler;
use App\Models\Kategori;
use App\Models\Urun;
use App\Models\User;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;

beforeEach(function () {
    $this->actingAs(User::factory()->create());
    $this->aktif = Urun::factory()->create(['ad' => 'Aktif Tül']);
    $this->silinen = Urun::factory()->create(['ad' => 'Silinen Tül']);
    $this->silinen->delete();
});

it('ürün listesi "Ürünler" sekmesinde aktifleri, "Son silinenler" sekmesinde silinenleri gösterir', function () {
    Livewire::test(ListUrunler::class)
        ->assertOk()
        ->assertSee('Son silinenler')
        ->assertCanSeeTableRecords([$this->aktif])
        ->assertCanNotSeeTableRecords([$this->silinen])
        ->set('activeTab', 'silinenler')
        ->assertCanSeeTableRecords([$this->silinen])
        ->assertCanNotSeeTableRecords([$this->aktif]);
});

it('ürün "Sil" ile silinir, silinenler sekmesinden "Geri al" ile döner ve log "silindi" der', function () {
    Livewire::test(ListUrunler::class)
        ->callTableAction('delete', $this->aktif)
        ->assertHasNoTableActionErrors();

    expect($this->aktif->fresh()->trashed())->toBeTrue()
        ->and(Activity::where('subject_id', $this->aktif->id)->latest('id')->first()->description)->toBe('Ürün silindi');

    Livewire::test(ListUrunler::class)
        ->set('activeTab', 'silinenler')
        ->assertCanSeeTableRecords([$this->aktif, $this->silinen])
        ->callTableAction('restore', $this->aktif)
        ->assertHasNoTableActionErrors();

    expect($this->aktif->fresh()->trashed())->toBeFalse()
        ->and(Activity::where('subject_id', $this->aktif->id)->latest('id')->first()->description)->toBe('Ürün geri alındı');
});

it('ürün formu sadeleşti: ürün kodu, desen, en, varsayılan top metresi ve mevcut stok alanları yok', function () {
    $kategori = Kategori::factory()->create();

    Livewire::test(CreateUrun::class)
        ->assertOk()
        ->assertFormFieldDoesNotExist('urun_kodu')
        ->assertFormFieldExists('renk')
        ->assertFormFieldExists('kritik_stok')
        ->assertFormFieldDoesNotExist('desen')
        ->assertFormFieldDoesNotExist('en_cm')
        ->assertFormFieldDoesNotExist('varsayilan_top_metresi')
        ->assertFormFieldDoesNotExist('mevcut_stok')
        ->fillForm([
            'ad' => 'Yeni Tül',
            'kategori_id' => $kategori->id,
            'birim' => 'metre',
            'renk' => 'Krem',
            'satis_fiyati' => '120',
            'kritik_stok' => '5',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $urun = Urun::where('ad', 'Yeni Tül')->firstOrFail();

    expect($urun->renk)->toBe('Krem')
        ->and($urun->mevcut_stok)->toBe('0.00')
        ->and($urun->kritik_stok)->toBe('5.00');
});
