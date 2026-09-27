<?php

namespace App\Filament\Resources\Urunler\Pages;

use App\Filament\Kutular\CanliStokKutusu;
use App\Filament\Resources\Urunler\UrunResource;
use Filament\Resources\Pages\CreateRecord;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Livewire;
use Filament\Schemas\Schema;

class CreateUrun extends CreateRecord
{
    protected static string $resource = UrunResource::class;

    /** Solda ürün formu, sağda kayıtlı ürünler kutusu (kod çakışmasını ve stokları görmek için). */
    public function content(Schema $schema): Schema
    {
        return $schema
            ->components([
                Grid::make(12)
                    ->schema([
                        $this->getFormContentComponent()
                            ->columnSpan(['default' => 12, 'xl' => 8]),
                        Livewire::make(CanliStokKutusu::class, ['baslik' => 'Kayıtlı ürünler', 'fiyatGoster' => true])
                            ->columnSpan(['default' => 12, 'xl' => 4]),
                    ]),
            ]);
    }
}
