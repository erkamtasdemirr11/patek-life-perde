<?php

namespace App\Filament\Resources\Urunler\Pages;

use App\Filament\Kutular\CanliStokKutusu;
use App\Filament\Resources\Urunler\UrunResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Livewire;
use Filament\Schemas\Schema;

class EditUrun extends EditRecord
{
    protected static string $resource = UrunResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->label('Sil')
                ->modalHeading('Ürünü sil')
                ->modalDescription('Ürün listeden kaldırılır; geçmiş kayıtlarda adıyla görünmeye devam eder. "Son silinenler" sekmesinden geri alabilirsiniz.'),
            RestoreAction::make()->label('Geri al'),
        ];
    }

    /** Solda ürün formu, sağda diğer ürünler (düzenlenen ürün hariç). */
    public function content(Schema $schema): Schema
    {
        return $schema
            ->components([
                Grid::make(12)
                    ->schema([
                        $this->getFormContentComponent()
                            ->columnSpan(['default' => 12, 'xl' => 8]),
                        Livewire::make(CanliStokKutusu::class, [
                            'baslik' => 'Diğer ürünler',
                            'fiyatGoster' => true,
                            'haricId' => $this->getRecord()->getKey(),
                        ])
                            ->columnSpan(['default' => 12, 'xl' => 4]),
                    ]),
                $this->getRelationManagersContentComponent(),
            ]);
    }
}
