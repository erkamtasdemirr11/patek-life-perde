<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum CariKaynagi: string implements HasLabel
{
    case Acilis = 'acilis';
    case Fis = 'fis';
    case FisIptal = 'fis_iptal';
    case Tahsilat = 'tahsilat';
    case TahsilatIptal = 'tahsilat_iptal';
    case Duzeltme = 'duzeltme';

    public function getLabel(): string
    {
        return match ($this) {
            self::Acilis => 'Açılış bakiyesi',
            self::Fis => 'Fiş',
            self::FisIptal => 'Fiş iptali',
            self::Tahsilat => 'Tahsilat',
            self::TahsilatIptal => 'Tahsilat iptali',
            self::Duzeltme => 'Düzeltme',
        };
    }
}
