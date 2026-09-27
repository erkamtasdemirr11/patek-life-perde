<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum TahsilatDurumu: string implements HasColor, HasLabel
{
    case Aktif = 'aktif';
    case Iptal = 'iptal';

    public function getLabel(): string
    {
        return match ($this) {
            self::Aktif => 'Aktif',
            self::Iptal => 'İptal',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Aktif => 'success',
            self::Iptal => 'danger',
        };
    }
}
