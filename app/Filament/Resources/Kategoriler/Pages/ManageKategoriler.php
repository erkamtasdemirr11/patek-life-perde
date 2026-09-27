<?php

namespace App\Filament\Resources\Kategoriler\Pages;

use App\Filament\Resources\Kategoriler\KategoriResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageKategoriler extends ManageRecords
{
    protected static string $resource = KategoriResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
