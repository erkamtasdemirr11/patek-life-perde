<?php

namespace App\Filament\Pages;

use App\Models\CariHareketi;
use App\Models\Fis;
use App\Models\Musteri;
use App\Models\StokHareketi;
use App\Models\Tahsilat;
use App\Models\Urun;
use App\Services\YedekServisi;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use UnitEnum;

/**
 * Yedekler yöneticinin bilgisayarına indirilir; sunucuda saklanmaz, dış servise gönderilmez.
 */
class Yedekleme extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArchiveBoxArrowDown;

    protected static string|UnitEnum|null $navigationGroup = 'Sistem';

    protected static ?string $navigationLabel = 'Yedekleme';

    protected static ?string $title = 'Yedekleme';

    protected static ?string $slug = 'yedekleme';

    protected static ?int $navigationSort = 20;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('zip')
                ->label('Tam yedek indir (ZIP)')
                ->icon(Heroicon::OutlinedArchiveBoxArrowDown)
                ->action(function (): BinaryFileResponse {
                    $yol = app(YedekServisi::class)->zipOlustur();
                    $this->logla('Tam yedek (ZIP) indirildi', $yol);

                    return response()->download($yol)->deleteFileAfterSend();
                }),
            Action::make('excel')
                ->label('Excel yedeği indir')
                ->icon(Heroicon::OutlinedTableCells)
                ->color('gray')
                ->action(function (): BinaryFileResponse {
                    $yol = app(YedekServisi::class)->excelOlustur();
                    $this->logla('Excel yedeği indirildi', $yol);

                    return response()->download($yol)->deleteFileAfterSend();
                }),
        ];
    }

    public function content(Schema $schema): Schema
    {
        $ozet = sprintf(
            'Şu an: %d ürün, %d stok hareketi, %d müşteri, %d fiş, %d tahsilat, %d cari hareket.',
            Urun::withTrashed()->count(),
            StokHareketi::count(),
            Musteri::withTrashed()->count(),
            Fis::count(),
            Tahsilat::count(),
            CariHareketi::count(),
        );

        return $schema
            ->components([
                Section::make('Tam yedek (ZIP)')
                    ->description('Veritabanının tamamı (SQL dökümü) ve ürün görselleri. Sistemi sıfırdan geri kurmak için gereken tek dosya budur; şifre içeren dosyalar dahil değildir.')
                    ->schema([
                        Text::make('Sağ üstteki "Tam yedek indir (ZIP)" butonuyla oluşturulur ve bilgisayarınıza iner. Geri yükleme adımları ZIP içindeki BENIOKU.txt dosyasında yazar.'),
                        Text::make('Yedek sunucuda tutulmaz. Düzenli olarak — en az haftada bir — indirip güvenli bir yerde (harici disk, bulut klasörü) saklayın.'),
                    ]),

                Section::make('Excel yedeği (.xlsx)')
                    ->description('Ürünler, kategoriler, stok hareketleri, müşteriler, fişler, fiş kalemleri, cari hareketler, tahsilatlar ve kullanıcılar ayrı sayfalarda.')
                    ->schema([
                        Text::make('Okumak, süzmek ve arşivlemek içindir. Excel dosyasından sistem geri kurulamaz; geri yükleme yalnızca ZIP içindeki SQL ile yapılır.'),
                    ]),

                Section::make('Özet')
                    ->schema([
                        Text::make($ozet),
                        Text::make('Her indirme Log Hareketleri sayfasına "Yedekleme" kaynağıyla yazılır.'),
                    ]),
            ]);
    }

    protected function logla(string $mesaj, string $yol): void
    {
        activity('yedek')
            ->causedBy(auth()->user())
            ->withProperties(['dosya' => basename($yol), 'boyut_bayt' => filesize($yol)])
            ->log($mesaj);
    }
}
