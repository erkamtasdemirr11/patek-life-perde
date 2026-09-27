<?php

use App\Models\Kategori;
use App\Models\Urun;

beforeEach(function () {
    $this->tul = Kategori::factory()->create(['ad' => 'Tül Grubu', 'sira' => 1]);
    $this->fon = Kategori::factory()->create(['ad' => 'Fon Grubu', 'sira' => 2]);

    $this->aktifTul = Urun::factory()->create(['ad' => 'Keten Tül', 'renk' => 'Krem', 'kategori_id' => $this->tul->id, 'satis_fiyati' => '149.90']);
    $this->aktifFon = Urun::factory()->create(['ad' => 'Kadife Fon', 'kategori_id' => $this->fon->id]);
    $this->pasif = Urun::factory()->create(['ad' => 'Pasif Perde', 'kategori_id' => $this->tul->id, 'aktif' => false]);
    $this->silinen = Urun::factory()->create(['ad' => 'Silinen Perde', 'kategori_id' => $this->tul->id]);
    $this->silinen->delete();
});

it('katalog yalnızca aktif ürünleri listeler; fiyat ve stok göstermez', function () {
    $this->get(route('site.katalog'))
        ->assertOk()
        ->assertSee('Ürün Kataloğu')
        ->assertSee('Keten Tül')
        ->assertSee('Kadife Fon')
        ->assertDontSee('ile satılır')
        ->assertDontSee('Pasif Perde')
        ->assertDontSee('Silinen Perde')
        ->assertDontSee('149,90')
        ->assertDontSee('Mevcut stok')
        ->assertSee('data-solid', escape: false)
        ->assertSee('2 ürün');
});

it('katalog kategoriye göre süzer ve arar', function () {
    $this->get(route('site.katalog', ['kategori' => $this->fon->id]))
        ->assertOk()
        ->assertSee('Kadife Fon')
        ->assertDontSee('Keten Tül')
        ->assertSee('1 ürün');

    $this->get(route('site.katalog', ['q' => 'keten']))
        ->assertOk()
        ->assertSee('Keten Tül')
        ->assertDontSee('Kadife Fon');

    $this->get(route('site.katalog', ['q' => 'olmayan-urun']))
        ->assertOk()
        ->assertSee('Bu ölçütlere uygun ürün bulunamadı.');
});

it('menüdeki Ürünler bağlantısı kataloğa gider ve ana sayfa şeridi ürünleri iki kez (kesintisiz döngü) içerir', function () {
    Urun::factory()->count(3)->create(['kategori_id' => $this->tul->id]);   // şerit için en az 4 aktif ürün

    $yanit = $this->get('/')->assertOk()->assertSee(route('site.katalog'), escape: false);
    $html = $yanit->getContent();

    expect(substr_count($html, 'class="akis-grup"'))->toBe(2)
        ->and(substr_count($html, 'Keten Tül'))->toBeGreaterThanOrEqual(2)
        ->and($html)->toContain('Tüm kataloğu gör');
});

it('yeterli ürün yoksa şerit yapılandırmadaki ürün gruplarını gösterir', function () {
    Urun::query()->forceDelete();

    $html = $this->get('/')->assertOk()->getContent();

    foreach (config('site.urun_gruplari') as $grup) {
        expect(substr_count($html, $grup['ad']))->toBeGreaterThanOrEqual(2);
    }
});
