<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * Fişin ödeme tipi.
 * Açık hesap: tutar müşterinin borcuna yazılır, sonra tahsilatla kapatılır.
 * Peşin: fişle aynı anda fişe bağlı bir tahsilat makbuzu kesilir; bakiye değişmez.
 */
enum OdemeTipi: string implements HasColor, HasLabel
{
    case AcikHesap = 'acik_hesap';
    case Pesin = 'pesin';

    public function getLabel(): string
    {
        return match ($this) {
            self::AcikHesap => 'Açık hesap',
            self::Pesin => 'Peşin',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::AcikHesap => 'warning',
            self::Pesin => 'success',
        };
    }
}
