<?php

namespace App\Filament\Resources\Fisler\Pages;

use App\Filament\Resources\Fisler\FisResource;
use Filament\Resources\Pages\ViewRecord;

class ViewFis extends ViewRecord
{
    protected static string $resource = FisResource::class;

    protected function getHeaderActions(): array
    {
        return [
            FisResource::pdfAction(),
            FisResource::a4Action(),
            FisResource::iptalAction(),
        ];
    }
}
