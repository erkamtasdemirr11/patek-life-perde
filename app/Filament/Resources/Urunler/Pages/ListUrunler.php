<?php

namespace App\Filament\Resources\Urunler\Pages;

use App\Filament\Resources\Urunler\UrunResource;
use App\Models\Urun;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;

class ListUrunler extends ListRecords
{
    protected static string $resource = UrunResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('Yeni ürün'),
        ];
    }

    /** Silinen ürünler aynı sayfada ayrı sekmede; oradan geri alınabilir. */
    public function getTabs(): array
    {
        return [
            'urunler' => Tab::make('Ürünler')
                ->badge(fn (): int => Urun::query()->count())
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->withoutTrashed()),
            'silinenler' => Tab::make('Son silinenler')
                ->icon(Heroicon::OutlinedTrash)
                ->badge(fn (): int => Urun::onlyTrashed()->count())
                ->badgeColor('danger')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->onlyTrashed()->reorder('deleted_at', 'desc')),
        ];
    }

    public function getDefaultActiveTab(): string|int|null
    {
        return 'urunler';
    }
}
