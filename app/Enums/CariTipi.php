<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/** Borç bakiyeyi artırır (müşteri bize borçlanır), alacak azaltır. */
enum CariTipi: string implements HasColor, HasLabel
{
    case Borc = 'borc';
    case Alacak = 'alacak';

    public function getLabel(): string
    {
        return match ($this) {
            self::Borc => 'Borç',
            self::Alacak => 'Alacak',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Borc => 'danger',
            self::Alacak => 'success',
        };
    }
}
