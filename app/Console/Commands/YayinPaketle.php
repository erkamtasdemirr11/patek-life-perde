<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use ZipArchive;

/**
 * Sunucuya elle yüklenecek yayın paketini (ZIP) üretir; Git ve Composer gerektirmez.
 * İçerik: uygulama kodu, vendor, derlenmiş public/build, boş storage iskeleti.
 * Dışarıda kalanlar: .env, node_modules, tests, loglar, önbellekler, yüklenmiş görseller.
 */
class YayinPaketle extends Command
{
    protected $signature = 'yayin:paketle {--vendorsuz : vendor klasörünü pakete koyma (sunucuda composer install çalıştırılır)}';

    protected $description = 'Sunucuya yüklenecek yayın paketini (ZIP) üretir.';

    private const KLASORLER = ['app', 'bootstrap', 'config', 'database', 'public', 'resources', 'routes', 'storage', 'vendor'];

    private const DOSYALAR = ['artisan', 'composer.json', 'composer.lock', '.env.production.example', 'YAYIN.md'];

    /** Sunucuda üretilen ya da gizli kalması gereken içerikler (paket içi göreli yola göre). */
    private const HARIC = [
        '#^storage/logs/.+#',
        '#^storage/framework/(cache|sessions|views|testing)/.+#',
        '#^storage/app/(public|private)/.+#',
        '#^bootstrap/cache/.+#',
        '#^public/storage(/|$)#',
        '#^public/hot$#',
        '#^database/.+[.]sqlite$#',
        '#(^|/)(Thumbs[.]db|[.]DS_Store)$#',
    ];

    public function handle(): int
    {
        if (! File::exists(public_path('build/manifest.json'))) {
            $this->error('public/build bulunamadı. Önce "npm run build" çalıştırın.');

            return self::FAILURE;
        }

        $klasor = base_path('yayin-paketi');
        File::ensureDirectoryExists($klasor);
        $yol = $klasor . DIRECTORY_SEPARATOR . 'patek-yayin-' . now()->format('Ymd-Hi') . '.zip';

        $zip = new ZipArchive;

        if ($zip->open($yol, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            $this->error("ZIP oluşturulamadı: {$yol}");

            return self::FAILURE;
        }

        $adet = 0;

        foreach (self::KLASORLER as $ad) {
            if ($ad === 'vendor' && $this->option('vendorsuz')) {
                continue;
            }

            $adet += $this->klasoruEkle($zip, base_path($ad), $ad);
        }

        foreach (self::DOSYALAR as $ad) {
            $zip->addFile(base_path($ad), $ad);
            $adet++;
        }

        // Laravel'in yazacağı boş klasörler pakette hazır gelsin.
        foreach (['storage/app/public', 'storage/framework/cache/data', 'storage/framework/sessions', 'storage/framework/views', 'storage/logs', 'bootstrap/cache'] as $bos) {
            $zip->addEmptyDir($bos);
        }

        $zip->close();

        $this->info(sprintf('Paket hazır: %s (%s MB, %d dosya)', $yol, number_format(filesize($yol) / 1048576, 1, ',', '.'), $adet));
        $this->line('Sonraki adım: YAYIN.md, "İlk yayın" bölümü.');

        return self::SUCCESS;
    }

    /** Klasörü, hariç tutma kurallarını uygulayarak pakete ekler; eklenen dosya sayısını döner. */
    protected function klasoruEkle(ZipArchive $zip, string $kaynak, string $onek): int
    {
        if (! File::isDirectory($kaynak)) {
            return 0;
        }

        $adet = 0;
        $yineleyici = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($kaynak, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST,
        );

        /** @var SplFileInfo $dosya */
        foreach ($yineleyici as $dosya) {
            $goreli = $onek . '/' . str_replace(DIRECTORY_SEPARATOR, '/', substr($dosya->getPathname(), strlen($kaynak) + 1));

            if ($this->haricMi($goreli)) {
                continue;
            }

            if ($dosya->isDir()) {
                $zip->addEmptyDir($goreli);

                continue;
            }

            $zip->addFile($dosya->getPathname(), $goreli);
            $adet++;
        }

        return $adet;
    }

    protected function haricMi(string $yol): bool
    {
        foreach (self::HARIC as $kural) {
            if (preg_match($kural, $yol) === 1) {
                return true;
            }
        }

        return false;
    }
}
