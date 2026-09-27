<?php

namespace App\Models;

use App\Models\Concerns\TurkceLoglanir;
use Database\Factories\MusteriFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use LogicException;

// bakiye bilerek fillable değil: onu yalnızca CariServisi günceller.
#[Fillable(['firma_adi', 'yetkili', 'telefon', 'adres', 'notlar', 'acilis_bakiyesi'])]
class Musteri extends Model
{
    /** @use HasFactory<MusteriFactory> */
    use HasFactory;
    use SoftDeletes;
    use TurkceLoglanir;

    protected $table = 'musteriler';

    protected static string $logEtiketi = 'Müşteri';

    protected static array $loglananAlanlar = ['firma_adi', 'yetkili', 'telefon', 'adres', 'notlar', 'acilis_bakiyesi'];

    protected function casts(): array
    {
        return [
            'acilis_bakiyesi' => 'decimal:2',
            'bakiye' => 'decimal:2',
        ];
    }

    protected static function booted(): void
    {
        // Açılış bakiyesi kayıttan sonra yalnızca CariServisi::acilisBakiyesiDuzelt ile değişir
        // (farkı düzeltme hareketi olarak yazar); başka her güncelleme burada durdurulur.
        static::updating(function (Musteri $musteri): void {
            if ($musteri->isDirty('acilis_bakiyesi')) {
                throw new LogicException('Açılış bakiyesi doğrudan değiştirilemez; müşteri kartındaki "Açılış bakiyesini düzelt" işlemini kullanın.');
            }
        });

        // Bakiyesi sıfır olmayan müşteri silinemez.
        static::deleting(function (Musteri $musteri): void {
            if ($musteri->bakiyeYonu() !== 0) {
                throw new LogicException('Bakiyesi sıfır olmayan müşteri silinemez; önce hesabı kapatın.');
            }
        });
    }

    public function fisler(): HasMany
    {
        return $this->hasMany(Fis::class)->latest('tarih');
    }

    public function cariHareketler(): HasMany
    {
        return $this->hasMany(CariHareketi::class);
    }

    public function tahsilatlar(): HasMany
    {
        return $this->hasMany(Tahsilat::class)->latest('tarih');
    }

    /** 1: müşteri borçlu, -1: müşteri alacaklı (fazla ödemiş), 0: hesap kapalı. */
    public function bakiyeYonu(): int
    {
        return bccomp((string) ($this->bakiye ?? '0'), '0', 2);
    }
}
