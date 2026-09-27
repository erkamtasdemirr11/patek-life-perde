<?php

namespace App\Models\Concerns;

use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * Modeli activitylog ile Türkçe açıklamalarla loglar.
 * Kullanan model şu iki özelliği tanımlar:
 *   protected static string $logEtiketi = 'Ürün';
 *   protected static array $loglananAlanlar = ['ad', ...];
 */
trait TurkceLoglanir
{
    use LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName(static::logAdi())
            ->logOnly(static::$loglananAlanlar)
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->setDescriptionForEvent(fn (string $olay): string => static::$logEtiketi . ' ' . match ($olay) {
                'created' => 'oluşturuldu',
                'updated' => 'güncellendi',
                'deleted' => 'silindi',
                'restored' => 'geri alındı',
                default => $olay,
            });
    }

    /** Log adı: sınıf adının küçük harfli hali (Urun → urun). */
    protected static function logAdi(): string
    {
        return strtolower(class_basename(static::class));
    }
}
