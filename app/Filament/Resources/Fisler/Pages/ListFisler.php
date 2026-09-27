<?php

namespace App\Filament\Resources\Fisler\Pages;

use App\Filament\Resources\Fisler\FisResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListFisler extends ListRecords
{
    protected static string $resource = FisResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('Yeni fiş'),
        ];
    }
}
