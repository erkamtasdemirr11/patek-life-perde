<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/** Çek yalnızca ödeme biçimini belirtir; vade/portföy takibi kapsam dışıdır. */
enum OdemeYontemi: string implements HasLabel
{
    case Nakit = 'nakit';
    case Havale = 'havale';
    case Cek = 'cek';
    case KrediKarti = 'kredi_karti';

    public function getLabel(): string
    {
        return match ($this) {
            self::Nakit => 'Nakit',
            self::Havale => 'Havale / EFT',
            self::Cek => 'Çek',
            self::KrediKarti => 'Kredi kartı',
        };
    }
}
