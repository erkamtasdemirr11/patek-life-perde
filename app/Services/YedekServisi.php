<?php

namespace App\Services;

use App\Enums\CariTipi;
use App\Models\CariHareketi;
use App\Models\Fis;
use App\Models\FisKalemi;
use App\Models\Kategori;
use App\Models\Musteri;
use App\Models\StokHareketi;
use App\Models\Tahsilat;
use App\Models\Urun;
use App\Models\User;
use FilesystemIterator;
use Generator;
use Illuminate\Support\Facades\DB;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;
use PDO;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use ZipArchive;

/**
 * Yedek üretimi: tam yedek (SQL dökümü + ürün görselleri, ZIP) ve okunabilir Excel kopyası.
 * SQL dökümü mysqldump gerektirmez; PHP ile üretilir ve phpMyAdmin / mysql ile geri yüklenir.
 */
class YedekServisi
{
    /** Dökümde yer almayan geçici tablolar. */
    protected const ATLANAN_TABLOLAR = [
        'sessions', 'cache', 'cache_locks', 'jobs', 'job_batches', 'failed_jobs', 'password_reset_tokens',
    ];

    /** ZIP dosyasının yolunu döner. Çağıran, indirdikten sonra dosyayı siler. */
    public function zipOlustur(): string
    {
        $dizin = $this->yedekDizini();
        $zaman = now()->format('Ymd-His');
        $sqlYolu = "{$dizin}/veritabani-{$zaman}.sql";
        $zipYolu = "{$dizin}/patek-yedek-{$zaman}.zip";

        $this->sqlDokumuYaz($sqlYolu);

        $zip = new ZipArchive;

        if ($zip->open($zipYolu, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Yedek dosyası oluşturulamadı.');
        }

        $zip->addFile($sqlYolu, 'veritabani.sql');
        $zip->addFromString('BENIOKU.txt', $this->beniOku());

        $gorselDizini = storage_path('app/public/urunler');

        if (is_dir($gorselDizini)) {
            $dosyalar = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($gorselDizini, FilesystemIterator::SKIP_DOTS),
            );

            foreach ($dosyalar as $dosya) {
                $goreli = ltrim(str_replace('\\', '/', substr($dosya->getPathname(), strlen($gorselDizini))), '/');
                $zip->addFile($dosya->getPathname(), 'gorseller/urunler/' . $goreli);
            }
        }

        $zip->close();
        @unlink($sqlYolu);

        return $zipYolu;
    }

    /** Tüm kalıcı tabloları CREATE TABLE + INSERT olarak dosyaya yazar. */
    public function sqlDokumuYaz(string $yol): void
    {
        $pdo = DB::connection()->getPdo();
        $akis = fopen($yol, 'w');

        if ($akis === false) {
            throw new RuntimeException('SQL dökümü yazılamadı.');
        }

        fwrite($akis, "-- Patek Life Perde veritabanı yedeği\n");
        fwrite($akis, '-- Oluşturulma: ' . now()->format('d.m.Y H:i:s') . "\n");
        fwrite($akis, '-- Veritabanı: ' . DB::connection()->getDatabaseName() . "\n\n");
        fwrite($akis, "SET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS = 0;\nSET SQL_MODE = 'NO_AUTO_VALUE_ON_ZERO';\n\n");

        foreach ($this->tablolar() as $tablo) {
            $olustur = (array) DB::selectOne("SHOW CREATE TABLE `{$tablo}`");

            fwrite($akis, "--\n-- {$tablo}\n--\n");
            fwrite($akis, "DROP TABLE IF EXISTS `{$tablo}`;\n");
            fwrite($akis, $olustur['Create Table'] . ";\n\n");

            $sutunlar = null;
            $paket = [];

            foreach (DB::table($tablo)->cursor() as $satir) {
                $satir = (array) $satir;
                $sutunlar ??= '`' . implode('`, `', array_keys($satir)) . '`';
                $paket[] = '(' . implode(', ', array_map(fn (mixed $deger): string => $this->sqlDeger($pdo, $deger), array_values($satir))) . ')';

                if (count($paket) === 200) {
                    fwrite($akis, "INSERT INTO `{$tablo}` ({$sutunlar}) VALUES\n" . implode(",\n", $paket) . ";\n");
                    $paket = [];
                }
            }

            if ($paket !== []) {
                fwrite($akis, "INSERT INTO `{$tablo}` ({$sutunlar}) VALUES\n" . implode(",\n", $paket) . ";\n");
            }

            fwrite($akis, "\n");
        }

        fwrite($akis, "SET FOREIGN_KEY_CHECKS = 1;\n");
        fclose($akis);
    }

    /** Excel dosyasının yolunu döner: her tablo ayrı sayfada, Türkçe başlıklarla. */
    public function excelOlustur(): string
    {
        $yol = $this->yedekDizini() . '/patek-yedek-' . now()->format('Ymd-His') . '.xlsx';

        $writer = new Writer;
        $writer->openToFile($yol);
        $ilkSayfa = true;

        foreach ($this->excelSayfalari() as $ad => [$basliklar, $satirlar]) {
            $sayfa = $ilkSayfa ? $writer->getCurrentSheet() : $writer->addNewSheetAndMakeItCurrent();
            $ilkSayfa = false;
            $sayfa->setName($ad);

            $writer->addRow(Row::fromValues($basliklar));

            foreach ($satirlar as $satir) {
                $writer->addRow(Row::fromValues($satir));
            }
        }

        $writer->close();

        return $yol;
    }

    /**
     * @return Generator<string, array{0: array<string>, 1: iterable<array<int, mixed>>}>
     */
    protected function excelSayfalari(): Generator
    {
        $tarih = fn ($t): ?string => $t?->format('d.m.Y H:i');
        $sayi = fn ($s): ?float => $s === null ? null : (float) $s;
        $evetHayir = fn (bool $b): string => $b ? 'Evet' : 'Hayır';

        yield 'Ürünler' => [
            ['Ürün', 'Kategori', 'Renk', 'Birim', 'Alış fiyatı', 'Satış fiyatı', 'Kritik stok', 'Mevcut stok', 'Aktif', 'Silinme'],
            Urun::withTrashed()->with('kategori')->orderBy('ad')->lazy()->map(fn (Urun $u): array => [
                $u->ad, $u->kategori?->ad, $u->renk, $u->birim->getLabel(),
                $sayi($u->alis_fiyati), $sayi($u->satis_fiyati),
                $sayi($u->kritik_stok), $sayi($u->mevcut_stok), $evetHayir($u->aktif), $tarih($u->deleted_at),
            ]),
        ];

        yield 'Kategoriler' => [
            ['Kategori', 'Sıra'],
            Kategori::orderBy('sira')->lazy()->map(fn (Kategori $k): array => [$k->ad, $k->sira]),
        ];

        yield 'Stok Hareketleri' => [
            ['Tarih', 'Ürün', 'Yön', 'Sebep', 'Miktar', 'Birim', 'Fiş', 'Açıklama', 'İşleyen'],
            StokHareketi::with(['urun', 'user', 'fis'])->orderBy('id')->lazy()->map(fn (StokHareketi $h): array => [
                $tarih($h->created_at), $h->urun->ad, $h->yon->getLabel(), $h->sebep->getLabel(),
                $sayi($h->miktar), $h->urun->birim->kisa(),
                $h->fis?->fis_no, $h->aciklama, $h->user?->name,
            ]),
        ];

        yield 'Müşteriler' => [
            ['Firma', 'Yetkili', 'Telefon', 'Adres', 'Notlar', 'Açılış bakiyesi', 'Bakiye', 'Silinme'],
            Musteri::withTrashed()->orderBy('firma_adi')->lazy()->map(fn (Musteri $m): array => [
                $m->firma_adi, $m->yetkili, $m->telefon, $m->adres, $m->notlar,
                $sayi($m->acilis_bakiyesi), $sayi($m->bakiye), $tarih($m->deleted_at),
            ]),
        ];

        yield 'Fişler' => [
            ['Fiş no', 'Tarih', 'Müşteri', 'Ara toplam', 'İskonto', 'Genel toplam', 'Durum', 'Açıklama', 'Kesen', 'İptal tarihi', 'İptal eden', 'İptal nedeni'],
            Fis::with(['musteri', 'user', 'iptalEden'])->orderBy('id')->lazy()->map(fn (Fis $f): array => [
                $f->fis_no, $tarih($f->tarih), $f->musteri->firma_adi, $sayi($f->ara_toplam), $sayi($f->iskonto),
                $sayi($f->genel_toplam), $f->durum->getLabel(), $f->aciklama, $f->user?->name,
                $tarih($f->iptal_tarihi), $f->iptalEden?->name, $f->iptal_nedeni,
            ]),
        ];

        yield 'Fiş Kalemleri' => [
            ['Fiş no', 'Ürün', 'Miktar', 'Birim fiyat', 'Tutar'],
            FisKalemi::with(['fis', 'urun'])->orderBy('id')->lazy()->map(fn (FisKalemi $k): array => [
                $k->fis->fis_no, $k->urun->ad, $sayi($k->miktar), $sayi($k->birim_fiyat), $sayi($k->tutar),
            ]),
        ];

        yield 'Cari Hareketler' => [
            ['Tarih', 'Müşteri', 'Tip', 'Kaynak', 'Borç', 'Alacak', 'Bakiye sonrası', 'Belge', 'Açıklama', 'İşleyen'],
            CariHareketi::with(['musteri', 'user', 'fis', 'tahsilat'])->orderBy('id')->lazy()->map(fn (CariHareketi $h): array => [
                $tarih($h->tarih), $h->musteri->firma_adi, $h->tip->getLabel(), $h->kaynak->getLabel(),
                $h->tip === CariTipi::Borc ? $sayi($h->tutar) : null,
                $h->tip === CariTipi::Alacak ? $sayi($h->tutar) : null,
                $sayi($h->bakiye_sonrasi), $h->fis?->fis_no ?? $h->tahsilat?->makbuz_no, $h->aciklama, $h->user?->name,
            ]),
        ];

        yield 'Tahsilatlar' => [
            ['Makbuz no', 'Tarih', 'Müşteri', 'Tutar', 'Ödeme yöntemi', 'Durum', 'Açıklama', 'Alan', 'İptal tarihi', 'İptal nedeni'],
            Tahsilat::with(['musteri', 'user'])->orderBy('id')->lazy()->map(fn (Tahsilat $t): array => [
                $t->makbuz_no, $tarih($t->tarih), $t->musteri->firma_adi, $sayi($t->tutar), $t->odeme_yontemi->getLabel(),
                $t->durum->getLabel(), $t->aciklama, $t->user?->name, $tarih($t->iptal_tarihi), $t->iptal_nedeni,
            ]),
        ];

        yield 'Kullanıcılar' => [
            ['Ad Soyad', 'E-posta', 'Aktif'],
            User::orderBy('name')->lazy()->map(fn (User $u): array => [$u->name, $u->email, $evetHayir((bool) $u->aktif)]),
        ];
    }

    protected function sqlDeger(PDO $pdo, mixed $deger): string
    {
        return match (true) {
            $deger === null => 'NULL',
            is_bool($deger) => $deger ? '1' : '0',
            is_int($deger), is_float($deger) => (string) $deger,
            default => $pdo->quote((string) $deger),
        };
    }

    /** @return list<string> */
    protected function tablolar(): array
    {
        $tablolar = array_map(fn (object $satir): string => (string) array_values((array) $satir)[0], DB::select('SHOW TABLES'));

        return array_values(array_diff($tablolar, self::ATLANAN_TABLOLAR));
    }

    protected function yedekDizini(): string
    {
        $dizin = storage_path('app/private/yedekler');

        if (! is_dir($dizin)) {
            mkdir($dizin, 0755, true);
        }

        return $dizin;
    }

    protected function beniOku(): string
    {
        return implode("\n", [
            'PATEK LIFE PERDE — TAM YEDEK',
            'Oluşturulma: ' . now()->format('d.m.Y H:i'),
            '',
            'İÇERİK',
            '  veritabani.sql      Tüm tablolar (yapı + veriler)',
            '  gorseller/urunler/  Ürün görselleri',
            '',
            'GERİ YÜKLEME',
            '  1) Boş bir MySQL veritabanı oluşturun (karakter seti utf8mb4).',
            '  2) veritabani.sql dosyasını içe aktarın:',
            '       phpMyAdmin > İçe Aktar  veya  mysql -u KULLANICI -p VERITABANI < veritabani.sql',
            '  3) gorseller/urunler klasörünü projedeki storage/app/public/urunler klasörüne kopyalayın.',
            '  4) .env dosyasındaki DB_* ayarlarını bu veritabanına yönlendirin; php artisan storage:link çalıştırın.',
            '  5) Kontrol: php artisan stok:dogrula  ve  php artisan cari:dogrula',
            '',
            'NOT: Bu yedek şifre veya .env içermez. Kullanıcı parolaları veritabanında şifrelenmiş (hash) olarak durur.',
            '',
        ]);
    }
}
