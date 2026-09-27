<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Schema;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;

/**
 * Salt okunur geçmiş listeleri için ortak taban: sayfa içeriği yalnızca tablodur;
 * oluşturma, düzenleme veya silme aksiyonu bulunmaz.
 */
abstract class GecmisSayfasi extends Page implements HasTable
{
    use InteractsWithTable;

    public function content(Schema $schema): Schema
    {
        return $schema
            ->components([
                EmbeddedTable::make(),
            ]);
    }
}
