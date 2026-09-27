<?php

namespace App\Filament\Resources\Tahsilatlar\Pages;

use App\Filament\Resources\Tahsilatlar\TahsilatResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListTahsilatlar extends ListRecords
{
    protected static string $resource = TahsilatResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('Yeni tahsilat'),
        ];
    }
}
