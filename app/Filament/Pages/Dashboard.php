<?php

namespace App\Filament\Pages;

use App\Filament\Resources\Fisler\FisResource;
use App\Filament\Resources\Tahsilatlar\TahsilatResource;
use Filament\Actions\Action;
use Filament\Pages\Dashboard as TemelDashboard;
use Filament\Support\Icons\Heroicon;

/**
 * Genel Bakış: özet sayılar, satış ve stok grafikleri, son fişler, kritik stok.
 * Widget'lar app/Filament/Widgets altından keşfedilir ve $sort sırasıyla dizilir.
 */
class Dashboard extends TemelDashboard
{
    protected static ?string $title = 'Genel Bakış';

    protected static ?string $navigationLabel = 'Genel Bakış';

    /** 12 kolonlu ızgara: widget'lar columnSpan ile 8/4 ve 6/6 bölünür. */
    public function getColumns(): int|array
    {
        return 12;
    }

    /** Sık kullanılan işlemler için kısayollar. */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('yeniFis')
                ->label('Yeni fiş')
                ->icon(Heroicon::OutlinedReceiptPercent)
                ->url(FisResource::getUrl('create')),
            Action::make('stokGiris')
                ->label('Stok girişi')
                ->icon(Heroicon::OutlinedArrowDownTray)
                ->color('gray')
                ->url(StokGiris::getUrl()),
            Action::make('tahsilat')
                ->label('Tahsilat')
                ->icon(Heroicon::OutlinedBanknotes)
                ->color('gray')
                ->url(TahsilatResource::getUrl('create')),
        ];
    }
}
