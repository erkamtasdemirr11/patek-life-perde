<?php

namespace App\Models;

use App\Enums\StokSebebi;
use App\Enums\StokYonu;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * Stok hareketleri değiştirilemez bir defterdir: yalnızca StokServisi ekler,
 * hiç kimse düzenlemez veya silmez. Hata, ters yönlü yeni bir hareketle düzeltilir.
 */
#[Fillable(['urun_id', 'yon', 'sebep', 'miktar', 'fis_id', 'aciklama', 'user_id'])]
class StokHareketi extends Model
{
    protected $table = 'stok_hareketleri';

    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return [
            'yon' => StokYonu::class,
            'sebep' => StokSebebi::class,
            'miktar' => 'decimal:2',
            'created_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Stok hareketleri düzenlenemez; ters yönlü yeni bir hareket ekleyin.'));
        static::deleting(fn () => throw new LogicException('Stok hareketleri silinemez; ters yönlü yeni bir hareket ekleyin.'));
    }

    public function urun(): BelongsTo
    {
        return $this->belongsTo(Urun::class)->withTrashed();
    }

    public function fis(): BelongsTo
    {
        return $this->belongsTo(Fis::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
