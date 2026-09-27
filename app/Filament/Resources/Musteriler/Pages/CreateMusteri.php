<?php

namespace App\Filament\Resources\Musteriler\Pages;

use App\Filament\Resources\Musteriler\MusteriResource;
use App\Services\CariServisi;
use Filament\Resources\Pages\CreateRecord;

class CreateMusteri extends CreateRecord
{
    protected static string $resource = MusteriResource::class;

    /** Açılış bakiyesi girildiyse tek bir cari harekete çevrilir. */
    protected function afterCreate(): void
    {
        app(CariServisi::class)->acilisBakiyesiIsle($this->getRecord());
    }
}
