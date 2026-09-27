<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum StokSebebi: string implements HasLabel
{
    case Alis = 'alis';
    case Satis = 'satis';
    case Fire = 'fire';
    case Numune = 'numune';
    case Iade = 'iade';
    case Sayim = 'sayim';
    case FisIptal = 'fis_iptal';

    public function getLabel(): string
    {
        return match ($this) {
            self::Alis => 'Alış',
            self::Satis => 'Satış',
            self::Fire => 'Fire',
            self::Numune => 'Numune',
            self::Iade => 'İade',
            self::Sayim => 'Sayım',
            self::FisIptal => 'Fiş iptali',
        };
    }

    /**
     * Bu sebep hangi yönde kullanılabilir?
     * Sayım hem giriş hem çıkış olabilir; diğerleri tek yönlüdür.
     *
     * @return list<StokYonu>
     */
    public function yonler(): array
    {
        return match ($this) {
            self::Alis, self::Iade, self::FisIptal => [StokYonu::Giris],
            self::Satis, self::Fire, self::Numune => [StokYonu::Cikis],
            self::Sayim => [StokYonu::Giris, StokYonu::Cikis],
        };
    }

    public function uygunMu(StokYonu $yon): bool
    {
        return in_array($yon, $this->yonler(), true);
    }

    /**
     * Stok Giriş / Stok Çıkışı sayfalarında elle seçilebilen sebepler.
     * Satış ve fiş iptali yalnızca fiş işlemlerinden gelir, elle seçilemez.
     *
     * @return array<string, string> value → etiket (Filament Select için)
     */
    public static function elleSecilebilir(StokYonu $yon): array
    {
        $secenekler = [];

        foreach (self::cases() as $sebep) {
            if ($sebep === self::Satis || $sebep === self::FisIptal || ! $sebep->uygunMu($yon)) {
                continue;
            }

            $secenekler[$sebep->value] = $sebep->getLabel();
        }

        return $secenekler;
    }
}
