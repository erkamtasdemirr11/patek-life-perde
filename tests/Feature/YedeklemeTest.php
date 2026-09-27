<?php

use App\Enums\OdemeYontemi;
use App\Enums\StokSebebi;
use App\Filament\Pages\Yedekleme;
use App\Models\Musteri;
use App\Models\Urun;
use App\Models\User;
use App\Services\FisServisi;
use App\Services\StokServisi;
use App\Services\TahsilatServisi;
use App\Services\YedekServisi;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use OpenSpout\Reader\XLSX\Reader;
use Spatie\Activitylog\Models\Activity;

beforeEach(function () {
    $this->actingAs(User::factory()->create());
    $this->musteri = Musteri::factory()->create(['firma_adi' => 'Deneme Tekstil']);
    $this->urun = Urun::factory()->create(['ad' => 'Keten Beyaz', 'satis_fiyati' => '100']);
    app(StokServisi::class)->girisYap($this->urun, '50', StokSebebi::Alis, "Tırnak 'içeren' açıklama");
    app(FisServisi::class)->olustur($this->musteri, [['urun' => $this->urun, 'miktar' => '5', 'birim_fiyat' => '100']]);
    app(TahsilatServisi::class)->kaydet($this->musteri, '200', OdemeYontemi::Nakit);
    $this->servis = app(YedekServisi::class);
});

afterEach(function () {
    foreach (glob(storage_path('app/private/yedekler/*')) ?: [] as $dosya) {
        @unlink($dosya);
    }
});

it('tam yedek ZIP içinde SQL dökümü ve geri yükleme talimatı bulunur', function () {
    $yol = $this->servis->zipOlustur();
    expect(file_exists($yol))->toBeTrue();

    $zip = new ZipArchive;
    expect($zip->open($yol))->toBeTrue();

    $sql = $zip->getFromName('veritabani.sql');
    expect($sql)->toBeString()
        ->toContain('CREATE TABLE `urunler`')
        ->toContain('INSERT INTO `urunler`')
        ->toContain('Keten Beyaz')
        ->toContain('CREATE TABLE `stok_hareketleri`')
        ->toContain('CREATE TABLE `fisler`')
        ->toContain('CREATE TABLE `cari_hareketler`')
        ->toContain("Tırnak \\'içeren\\' açıklama")
        ->not->toContain('CREATE TABLE `sessions`')
        ->not->toContain('CREATE TABLE `cache`');

    expect($zip->getFromName('BENIOKU.txt'))->toContain('GERİ YÜKLEME');
    $zip->close();
});

it('SQL dökümü boş bir veritabanına geri yüklenebilir', function () {
    $sqlYolu = storage_path('app/private/yedekler/geri-yukleme-deneme.sql');
    @mkdir(dirname($sqlYolu), 0755, true);
    $this->servis->sqlDokumuYaz($sqlYolu);

    $geriDb = DB::connection()->getDatabaseName() . '_geri';
    DB::statement("CREATE DATABASE IF NOT EXISTS `{$geriDb}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    config(['database.connections.geri' => array_merge(config('database.connections.mysql'), ['database' => $geriDb])]);

    try {
        DB::connection('geri')->unprepared(file_get_contents($sqlYolu));

        expect(DB::connection('geri')->table('urunler')->count())->toBe(DB::table('urunler')->count())
            ->and(DB::connection('geri')->table('stok_hareketleri')->count())->toBe(DB::table('stok_hareketleri')->count())
            ->and(DB::connection('geri')->table('fisler')->value('fis_no'))->toBe(DB::table('fisler')->value('fis_no'))
            ->and(DB::connection('geri')->table('musteriler')->value('bakiye'))->toBe(DB::table('musteriler')->value('bakiye'))
            ->and(DB::connection('geri')->table('stok_hareketleri')->value('aciklama'))->toBe("Tırnak 'içeren' açıklama");
    } finally {
        DB::purge('geri');
        DB::statement("DROP DATABASE IF EXISTS `{$geriDb}`");
    }
});

it('Excel yedeği tüm sayfaları ve verileri içerir', function () {
    $yol = $this->servis->excelOlustur();
    expect(file_exists($yol))->toBeTrue();

    $reader = new Reader;
    $reader->open($yol);

    $sayfalar = [];
    foreach ($reader->getSheetIterator() as $sayfa) {
        $satirlar = [];
        foreach ($sayfa->getRowIterator() as $satir) {
            $satirlar[] = $satir->toArray();
        }
        $sayfalar[$sayfa->getName()] = $satirlar;
    }
    $reader->close();

    expect(array_keys($sayfalar))->toBe(['Ürünler', 'Kategoriler', 'Stok Hareketleri', 'Müşteriler', 'Fişler', 'Fiş Kalemleri', 'Cari Hareketler', 'Tahsilatlar', 'Kullanıcılar'])
        ->and($sayfalar['Ürünler'][0][0])->toBe('Ürün')
        ->and($sayfalar['Ürünler'][1][0])->toBe('Keten Beyaz')
        ->and($sayfalar['Ürünler'][1][7])->toEqual(45)
        ->and($sayfalar['Müşteriler'][1][6])->toEqual(300)
        ->and(count($sayfalar['Stok Hareketleri']))->toBe(3)
        ->and(count($sayfalar['Cari Hareketler']))->toBe(3);
});

it('yedekleme sayfası ZIP ve Excel indirir ve log yazar', function () {
    Livewire::test(Yedekleme::class)
        ->assertOk()
        ->assertSee('Tam yedek (ZIP)')
        ->callAction('zip')
        ->assertFileDownloaded()
        ->callAction('excel')
        ->assertFileDownloaded();

    expect(Activity::where('log_name', 'yedek')->count())->toBe(2)
        ->and(Activity::where('log_name', 'yedek')->latest('id')->first()->description)->toBe('Excel yedeği indirildi');
});
