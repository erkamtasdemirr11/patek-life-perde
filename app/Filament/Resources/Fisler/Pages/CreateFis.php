<?php

namespace App\Filament\Resources\Fisler\Pages;

use App\Enums\OdemeTipi;
use App\Enums\OdemeYontemi;
use App\Exceptions\FisStokYetersizException;
use App\Filament\Kutular\CanliStokKutusu;
use App\Filament\Resources\Fisler\FisResource;
use App\Models\Musteri;
use App\Services\FisServisi;
use Carbon\Carbon;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Livewire;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\HtmlString;
use InvalidArgumentException;

class CreateFis extends CreateRecord
{
    protected static string $resource = FisResource::class;

    protected static bool $canCreateAnother = false;

    /** Kayıt doğrudan FisServisi üzerinden yapılır; sayfa iş mantığı içermez. */
    protected function handleRecordCreation(array $data): Model
    {
        // Filament, modeldeki enum cast'i sayesinde değerleri enum olarak da verebilir.
        $odemeTipi = ($data['odeme_tipi'] ?? null) instanceof OdemeTipi
            ? $data['odeme_tipi']
            : OdemeTipi::from($data['odeme_tipi'] ?? OdemeTipi::AcikHesap->value);

        $odemeYontemi = null;

        if ($odemeTipi === OdemeTipi::Pesin && filled($data['odeme_yontemi'] ?? null)) {
            $odemeYontemi = $data['odeme_yontemi'] instanceof OdemeYontemi
                ? $data['odeme_yontemi']
                : OdemeYontemi::from($data['odeme_yontemi']);
        }

        try {
            return app(FisServisi::class)->olustur(
                Musteri::findOrFail($data['musteri_id']),
                array_map(fn (array $kalem): array => [
                    'urun' => (int) $kalem['urun_id'],
                    'miktar' => (string) $kalem['miktar'],
                    'birim_fiyat' => (string) $kalem['birim_fiyat'],
                ], array_values($data['kalemler'] ?? [])),
                iskonto: filled($data['iskonto'] ?? null) ? (string) $data['iskonto'] : '0',
                aciklama: $data['aciklama'] ?? null,
                tarih: Carbon::parse($data['tarih']),
                odemeTipi: $odemeTipi,
                odemeYontemi: $odemeYontemi,
            );
        } catch (FisStokYetersizException|InvalidArgumentException $e) {
            Notification::make()
                ->title('Fiş kaydedilemedi')
                ->body(new HtmlString(nl2br(e($e->getMessage()))))
                ->danger()
                ->persistent()
                ->send();

            $this->halt();
        }
    }

    /** Solda fiş formu, sağda canlı stok ve fiyat kutusu. */
    public function content(Schema $schema): Schema
    {
        return $schema
            ->components([
                Grid::make(12)
                    ->schema([
                        $this->getFormContentComponent()
                            ->columnSpan(['default' => 12, 'xl' => 8]),
                        Livewire::make(CanliStokKutusu::class, ['baslik' => 'Canlı stok ve fiyatlar', 'fiyatGoster' => true])
                            ->columnSpan(['default' => 12, 'xl' => 4]),
                    ]),
            ]);
    }

    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('view', ['record' => $this->getRecord()]);
    }

    protected function getCreatedNotificationTitle(): ?string
    {
        return "Fiş {$this->getRecord()->fis_no} kaydedildi.";
    }
}
