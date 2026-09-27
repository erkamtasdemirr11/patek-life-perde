<?php

use App\Models\Kategori;
use App\Models\Urun;

it('robots.txt paneli kapatır ve sitemap adresini verir', function () {
    $this->get('/robots.txt')
        ->assertOk()
        ->assertHeader('Content-Type', 'text/plain; charset=UTF-8')
        ->assertSee('Disallow: /panel')
        ->assertSee(route('site.sitemap'));
});

it('sitemap.xml ana sayfa, katalog ve ürünü olan kategorileri listeler', function () {
    $dolu = Kategori::factory()->create(['ad' => 'Tül Grubu']);
    $bos = Kategori::factory()->create(['ad' => 'Boş Grup']);
    Urun::factory()->create(['kategori_id' => $dolu->id]);

    $this->get('/sitemap.xml')
        ->assertOk()
        ->assertHeader('Content-Type', 'application/xml; charset=UTF-8')
        ->assertSee('<?xml version="1.0" encoding="UTF-8"?>', escape: false)
        ->assertSee('<loc>' . url('/') . '</loc>', escape: false)
        ->assertSee('<loc>' . route('site.katalog') . '</loc>', escape: false)
        ->assertSee('<loc>' . route('site.katalog', ['kategori' => $dolu->id]) . '</loc>', escape: false)
        ->assertDontSee('kategori=' . $bos->id);
});

it('sayfalar SEO etiketleri taşır; arama sonuçları dizine eklenmez', function () {
    $kategori = Kategori::factory()->create(['ad' => 'Fon Grubu']);
    Urun::factory()->create(['ad' => 'Kadife Fon', 'kategori_id' => $kategori->id]);

    $this->get('/')
        ->assertOk()
        ->assertSee('<link rel="canonical" href="' . url('/') . '">', escape: false)
        ->assertSee('property="og:url"', escape: false)
        ->assertSee('"@type":"LocalBusiness"', escape: false)
        ->assertSee('rel="preload" as="image"', escape: false)
        ->assertDontSee('name="robots"', escape: false);

    $this->get(route('site.katalog', ['kategori' => $kategori->id]))
        ->assertOk()
        ->assertSee('<title>Fon Grubu — Ürün Kataloğu — ' . config('firma.ad') . '</title>', escape: false)
        ->assertSee('<link rel="canonical" href="' . route('site.katalog', ['kategori' => $kategori->id]) . '">', escape: false);

    $this->get(route('site.katalog', ['q' => 'kadife']))
        ->assertOk()
        ->assertSee('<meta name="robots" content="noindex,follow">', escape: false);
});

it('olmayan sayfa Türkçe 404 sayfası döner', function () {
    $this->get('/boyle-bir-sayfa-yok')
        ->assertNotFound()
        ->assertSee('Sayfa bulunamadı')
        ->assertSee('Ana sayfaya dön');
});

it('yayin:paketle sunucuya yüklenecek ZIP paketini üretir; .env pakete girmez', function () {
    $oncekiler = glob(base_path('yayin-paketi/patek-yayin-*.zip')) ?: [];

    $this->artisan('yayin:paketle --vendorsuz')->assertExitCode(0);

    // Yalnızca bu testin ürettiği dosyayı bul ve sonunda sil; kullanıcının gerçek paketine dokunma.
    $yeniler = array_values(array_diff(glob(base_path('yayin-paketi/patek-yayin-*.zip')) ?: [], $oncekiler));
    expect($yeniler)->toHaveCount(1);

    $yol = $yeniler[0];
    $zip = new \ZipArchive;

    expect($zip->open($yol))->toBeTrue()
        ->and($zip->locateName('artisan'))->not->toBeFalse()
        ->and($zip->locateName('public/build/manifest.json'))->not->toBeFalse()
        ->and($zip->locateName('public/.htaccess'))->not->toBeFalse()
        ->and($zip->locateName('.env.production.example'))->not->toBeFalse()
        ->and($zip->locateName('YAYIN.md'))->not->toBeFalse()
        ->and($zip->locateName('.env'))->toBeFalse()
        ->and($zip->locateName('vendor/autoload.php'))->toBeFalse()
        ->and($zip->locateName('tests/Pest.php'))->toBeFalse();

    $zip->close();
    unlink($yol);
});
