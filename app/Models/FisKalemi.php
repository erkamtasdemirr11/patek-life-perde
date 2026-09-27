<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/** Fiş kalemi, fişle birlikte yazılır; sonradan değiştirilemez ve silinemez. */
#[Fillable(['fis_id', 'urun_id', 'miktar', 'birim_fiyat', 'tutar'])]
class FisKalemi extends Model
{
    protected $table = 'fis_kalemleri';

    protected function casts(): array
    {
        return [
            'miktar' => 'decimal:2',
            'birim_fiyat' => 'decimal:2',
            'tutar' => 'decimal:2',
        ];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Fiş kalemleri düzenlenemez; fişi iptal edip yeniden kesin.'));
        static::deleting(fn () => throw new LogicException('Fiş kalemleri silinemez; fişi iptal edin.'));
    }

    public function fis(): BelongsTo
    {
        return $this->belongsTo(Fis::class);
    }

    public function urun(): BelongsTo
    {
        return $this->belongsTo(Urun::class)->withTrashed();
    }
}
