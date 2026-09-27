<?php

namespace App\Filament\Resources\Musteriler\Pages;

use App\Filament\Resources\Musteriler\MusteriResource;
use App\Models\Musteri;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;

class ListMusteriler extends ListRecords
{
    protected static string $resource = MusteriResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('Yeni müşteri'),
        ];
    }

    /** Silinen müşteriler aynı sayfada ayrı sekmede; oradan geri alınabilir. */
    public function getTabs(): array
    {
        return [
            'musteriler' => Tab::make('Müşteriler')
                ->badge(fn (): int => Musteri::query()->count())
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->withoutTrashed()),
            'silinenler' => Tab::make('Son silinenler')
                ->icon(Heroicon::OutlinedTrash)
                ->badge(fn (): int => Musteri::onlyTrashed()->count())
                ->badgeColor('danger')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->onlyTrashed()->reorder('deleted_at', 'desc')),
        ];
    }

    public function getDefaultActiveTab(): string|int|null
    {
        return 'musteriler';
    }
}
