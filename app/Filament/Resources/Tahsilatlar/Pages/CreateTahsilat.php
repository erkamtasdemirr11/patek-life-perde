<?php

namespace App\Filament\Resources\Tahsilatlar\Pages;

use App\Enums\OdemeYontemi;
use App\Filament\Resources\Tahsilatlar\TahsilatResource;
use App\Models\Musteri;
use App\Services\TahsilatServisi;
use Carbon\Carbon;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

class CreateTahsilat extends CreateRecord
{
    protected static string $resource = TahsilatResource::class;

    protected static bool $canCreateAnother = false;

    /** Kayıt TahsilatServisi üzerinden yapılır; sayfa iş mantığı içermez. */
    protected function handleRecordCreation(array $data): Model
    {
        try {
            return app(TahsilatServisi::class)->kaydet(
                Musteri::findOrFail($data['musteri_id']),
                (string) $data['tutar'],
                // Filament, modeldeki enum cast'i sayesinde değeri enum olarak da verebilir.
                $data['odeme_yontemi'] instanceof OdemeYontemi ? $data['odeme_yontemi'] : OdemeYontemi::from($data['odeme_yontemi']),
                $data['aciklama'] ?? null,
                Carbon::parse($data['tarih']),
            );
        } catch (InvalidArgumentException $e) {
            Notification::make()
                ->title('Tahsilat kaydedilemedi')
                ->body($e->getMessage())
                ->danger()
                ->persistent()
                ->send();

            $this->halt();
        }
    }

    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('index');
    }

    protected function getCreatedNotificationTitle(): ?string
    {
        return "Tahsilat {$this->getRecord()->makbuz_no} kaydedildi.";
    }
}
