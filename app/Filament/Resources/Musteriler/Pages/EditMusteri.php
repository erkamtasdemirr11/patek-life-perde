<?php

namespace App\Filament\Resources\Musteriler\Pages;

use App\Filament\Resources\Musteriler\MusteriResource;
use App\Models\Musteri;
use Filament\Actions\DeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;

class EditMusteri extends EditRecord
{
    protected static string $resource = MusteriResource::class;

    protected function getHeaderActions(): array
    {
        return [
            MusteriResource::ekstreAction(),
            MusteriResource::acilisDuzeltAction(),
            DeleteAction::make()
                ->label('Sil')
                ->modalHeading('Müşteriyi sil')
                ->modalDescription('Müşteri listeden kaldırılır; fişlerde ve geçmiş kayıtlarda adıyla görünmeye devam eder. "Son silinenler" sekmesinden geri alabilirsiniz.')
                ->hidden(fn (Musteri $record): bool => $record->bakiyeYonu() !== 0),
            RestoreAction::make()->label('Geri al'),
        ];
    }
}
