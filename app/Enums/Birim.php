<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum Birim: string implements HasLabel
{
    case Metre = 'metre';
    case Adet = 'adet';

    public function getLabel(): string
    {
        return match ($this) {
            self::Metre => 'Metre',
            self::Adet => 'Adet',
        };
    }

    /**
     * Miktarın yanında gösterilen kısa birim: "12,50 m", "3 adet".
     */
    public function kisa(): string
    {
        return match ($this) {
            self::Metre => 'm',
            self::Adet => 'adet',
        };
    }
}
