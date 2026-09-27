<?php

namespace App\Models;

use App\Enums\CariKaynagi;
use App\Enums\CariTipi;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * Cari hareketler değiştirilemez bir defterdir: yalnızca CariServisi ekler,
 * hiç kimse düzenlemez veya silmez. Hata, ters yönlü "düzeltme" kaydıyla giderilir.
 */
#[Fillable(['musteri_id', 'tarih', 'tip', 'kaynak', 'tutar', 'bakiye_sonrasi', 'fis_id', 'tahsilat_id', 'aciklama', 'user_id'])]
class CariHareketi extends Model
{
    protected $table = 'cari_hareketler';

    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return [
            'tarih' => 'datetime',
            'tip' => CariTipi::class,
            'kaynak' => CariKaynagi::class,
            'tutar' => 'decimal:2',
            'bakiye_sonrasi' => 'decimal:2',
            'created_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Cari hareketler düzenlenemez; ters yönlü bir düzeltme kaydı ekleyin.'));
        static::deleting(fn () => throw new LogicException('Cari hareketler silinemez; ters yönlü bir düzeltme kaydı ekleyin.'));
    }

    public function musteri(): BelongsTo
    {
        return $this->belongsTo(Musteri::class)->withTrashed();
    }

    public function fis(): BelongsTo
    {
        return $this->belongsTo(Fis::class);
    }

    public function tahsilat(): BelongsTo
    {
        return $this->belongsTo(Tahsilat::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
