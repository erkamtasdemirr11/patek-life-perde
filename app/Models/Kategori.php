<?php

namespace App\Models;

use App\Models\Concerns\TurkceLoglanir;
use Database\Factories\KategoriFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['ad', 'sira'])]
class Kategori extends Model
{
    /** @use HasFactory<KategoriFactory> */
    use HasFactory;
    use TurkceLoglanir;

    protected $table = 'kategoriler';

    protected static string $logEtiketi = 'Kategori';

    protected static array $loglananAlanlar = ['ad'];

    public function urunler(): HasMany
    {
        return $this->hasMany(Urun::class);
    }
}
