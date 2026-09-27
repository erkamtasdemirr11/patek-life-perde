<?php

namespace App\Models;

use App\Enums\CariKaynagi;
use App\Enums\FisDurumu;
use App\Enums\OdemeTipi;
use App\Models\Concerns\TurkceLoglanir;
use App\Services\CariServisi;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use LogicException;

/**
 * Fiş, resmi fatura değildir; iç kullanım belgesidir.
 * Kaydedildikten sonra düzenlenmez ve silinmez; yalnızca iptal edilir (FisServisi::iptalEt).
 */
#[Fillable(['fis_no', 'musteri_id', 'tarih', 'ara_toplam', 'iskonto', 'genel_toplam', 'odeme_tipi', 'durum', 'aciklama', 'user_id'])]
class Fis extends Model
{
    use TurkceLoglanir;

    protected $table = 'fisler';

    protected static string $logEtiketi = 'Fiş';

    protected static array $loglananAlanlar = ['fis_no', 'musteri_id', 'tarih', 'ara_toplam', 'iskonto', 'genel_toplam', 'odeme_tipi', 'aciklama'];

    /** İptal işleminin değiştirmesine izin verilen alanlar. */
    public const IPTAL_ALANLARI = ['durum', 'iptal_nedeni', 'iptal_eden_id', 'iptal_tarihi'];

    /** @var array{onceki: string, fis: string, pesin: ?Tahsilat, sonrasi: string}|null */
    protected ?array $bakiyeOzetiOnbellek = null;

    protected function casts(): array
    {
        return [
            'tarih' => 'datetime',
            'iptal_tarihi' => 'datetime',
            'ara_toplam' => 'decimal:2',
            'iskonto' => 'decimal:2',
            'genel_toplam' => 'decimal:2',
            'odeme_tipi' => OdemeTipi::class,
            'durum' => FisDurumu::class,
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (Fis $fis): void {
            $izinsiz = array_diff(array_keys($fis->getDirty()), self::IPTAL_ALANLARI);

            if ($izinsiz !== []) {
                throw new LogicException('Fişler kaydedildikten sonra düzenlenemez; yalnızca iptal edilebilir.');
            }
        });

        static::deleting(fn () => throw new LogicException('Fişler silinemez; yalnızca iptal edilebilir.'));
    }

    public function musteri(): BelongsTo
    {
        return $this->belongsTo(Musteri::class)->withTrashed();
    }

    public function kalemler(): HasMany
    {
        return $this->hasMany(FisKalemi::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function iptalEden(): BelongsTo
    {
        return $this->belongsTo(User::class, 'iptal_eden_id');
    }

    public function stokHareketleri(): HasMany
    {
        return $this->hasMany(StokHareketi::class);
    }

    /** Fişin borç kaydı (kaynak: fis). Tutarı sıfır olan fişte bulunmaz. */
    public function cariHareketi(): HasOne
    {
        return $this->hasOne(CariHareketi::class)->where('kaynak', CariKaynagi::Fis->value);
    }

    /** Fişle birlikte kesilen peşin tahsilat; açık hesap fişte bulunmaz. */
    public function pesinTahsilat(): HasOne
    {
        return $this->hasOne(Tahsilat::class);
    }

    public function aktifMi(): bool
    {
        return $this->durum === FisDurumu::Aktif;
    }

    public function iptalMi(): bool
    {
        return $this->durum === FisDurumu::Iptal;
    }

    public function pesinMi(): bool
    {
        return $this->odeme_tipi === OdemeTipi::Pesin;
    }

    /**
     * Fiş anındaki cari durum: önceki bakiye, bu fişin tutarı, varsa peşin tahsilat, fiş sonrası bakiye.
     * Cari hareket defterindeki bakiye_sonrasi alanından okunur; sonraki tahsilatlar bu özeti değiştirmez.
     * Pozitif = müşteri borçlu, negatif = müşteri alacaklı.
     *
     * @return array{onceki: string, fis: string, pesin: ?Tahsilat, sonrasi: string}
     */
    public function bakiyeOzeti(): array
    {
        if ($this->bakiyeOzetiOnbellek !== null) {
            return $this->bakiyeOzetiOnbellek;
        }

        $hareket = $this->cariHareketi;

        if ($hareket !== null) {
            $fisSonrasi = (string) $hareket->bakiye_sonrasi;
            $onceki = bcsub($fisSonrasi, (string) $hareket->tutar, 2);
        } else {
            // Tutarı sıfır olan fiş cariye dokunmaz: o andaki bakiye hareketlerden hesaplanır.
            $onceki = app(CariServisi::class)->hareketlerdenHesapla($this->musteri_id, oncesi: $this->tarih);
            $fisSonrasi = $onceki;
        }

        $pesin = $this->pesinTahsilat;
        $sonrasi = $pesin?->aktifMi() ? bcsub($fisSonrasi, (string) $pesin->tutar, 2) : $fisSonrasi;

        return $this->bakiyeOzetiOnbellek = [
            'onceki' => $onceki,
            'fis' => (string) $this->genel_toplam,
            'pesin' => $pesin,
            'sonrasi' => $sonrasi,
        ];
    }
}
