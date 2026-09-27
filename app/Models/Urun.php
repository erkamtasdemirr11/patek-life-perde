<?php

namespace App\Models;

use App\Enums\Birim;
use App\Models\Concerns\TurkceLoglanir;
use Database\Factories\UrunFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

// mevcut_stok bilerek fillable değil: onu yalnızca StokServisi günceller.
#[Fillable([
    'ad', 'kategori_id', 'renk', 'birim',
    'alis_fiyati', 'satis_fiyati', 'kritik_stok',
    'gorsel', 'aciklama', 'aktif',
])]
class Urun extends Model
{
    /** @use HasFactory<UrunFactory> */
    use HasFactory;
    use SoftDeletes;
    use TurkceLoglanir;

    protected $table = 'urunler';

    protected static string $logEtiketi = 'Ürün';

    protected static array $loglananAlanlar = [
        'ad', 'kategori_id', 'renk', 'birim',
        'alis_fiyati', 'satis_fiyati', 'kritik_stok', 'aktif',
    ];

    protected function casts(): array
    {
        return [
            'birim' => Birim::class,
            'alis_fiyati' => 'decimal:2',
            'satis_fiyati' => 'decimal:2',
            'kritik_stok' => 'decimal:2',
            'mevcut_stok' => 'decimal:2',
            'aktif' => 'boolean',
        ];
    }

    public function kategori(): BelongsTo
    {
        return $this->belongsTo(Kategori::class);
    }

    public function hareketler(): HasMany
    {
        return $this->hasMany(StokHareketi::class)->latest('id');
    }

    /** Seçim listelerinde görünecek ürünler: aktif olanlar (silinenler zaten gelmez). */
    public function scopeSecilebilir(Builder $sorgu): Builder
    {
        return $sorgu->where('aktif', true);
    }

    /** Stok kritik seviyeye indi mi? (mevcut ≤ kritik) */
    public function kritikStokta(): bool
    {
        return bccomp((string) ($this->mevcut_stok ?? '0'), (string) ($this->kritik_stok ?? '0'), 2) <= 0;
    }
}
