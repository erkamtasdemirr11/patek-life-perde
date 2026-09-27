<?php

namespace App\Models;

use App\Enums\OdemeYontemi;
use App\Enums\TahsilatDurumu;
use App\Models\Concerns\TurkceLoglanir;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use LogicException;

/**
 * Tahsilat kaydedildikten sonra düzenlenmez ve silinmez; yalnızca iptal edilir
 * (TahsilatServisi::iptalEt). Cari karşılığı CariServisi tarafından yazılır.
 * fis_id doluysa bu, fişle birlikte kesilmiş peşin tahsilattır.
 */
#[Fillable(['makbuz_no', 'musteri_id', 'fis_id', 'tarih', 'tutar', 'odeme_yontemi', 'durum', 'aciklama', 'user_id'])]
class Tahsilat extends Model
{
    use TurkceLoglanir;

    protected $table = 'tahsilatlar';

    protected static string $logEtiketi = 'Tahsilat';

    protected static array $loglananAlanlar = ['makbuz_no', 'musteri_id', 'fis_id', 'tarih', 'tutar', 'odeme_yontemi', 'aciklama'];

    /** İptal işleminin değiştirmesine izin verilen alanlar. */
    public const IPTAL_ALANLARI = ['durum', 'iptal_nedeni', 'iptal_eden_id', 'iptal_tarihi'];

    protected function casts(): array
    {
        return [
            'tarih' => 'datetime',
            'iptal_tarihi' => 'datetime',
            'tutar' => 'decimal:2',
            'odeme_yontemi' => OdemeYontemi::class,
            'durum' => TahsilatDurumu::class,
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (Tahsilat $tahsilat): void {
            $izinsiz = array_diff(array_keys($tahsilat->getDirty()), self::IPTAL_ALANLARI);

            if ($izinsiz !== []) {
                throw new LogicException('Tahsilatlar kaydedildikten sonra düzenlenemez; yalnızca iptal edilebilir.');
            }
        });

        static::deleting(fn () => throw new LogicException('Tahsilatlar silinemez; yalnızca iptal edilebilir.'));
    }

    public function musteri(): BelongsTo
    {
        return $this->belongsTo(Musteri::class)->withTrashed();
    }

    /** Peşin tahsilatın bağlı olduğu fiş; elle girilen tahsilatta boş. */
    public function fis(): BelongsTo
    {
        return $this->belongsTo(Fis::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function iptalEden(): BelongsTo
    {
        return $this->belongsTo(User::class, 'iptal_eden_id');
    }

    public function cariHareketi(): HasOne
    {
        return $this->hasOne(CariHareketi::class)->where('kaynak', 'tahsilat');
    }

    public function aktifMi(): bool
    {
        return $this->durum === TahsilatDurumu::Aktif;
    }

    public function iptalMi(): bool
    {
        return $this->durum === TahsilatDurumu::Iptal;
    }
}
