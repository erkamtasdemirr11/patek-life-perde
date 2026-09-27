<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum StokYonu: string implements HasColor, HasLabel
{
    case Giris = 'giris';
    case Cikis = 'cikis';

    public function getLabel(): string
    {
        return match ($this) {
            self::Giris => 'Giriş',
            self::Cikis => 'Çıkış',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Giris => 'success',
            self::Cikis => 'danger',
        };
    }
}
