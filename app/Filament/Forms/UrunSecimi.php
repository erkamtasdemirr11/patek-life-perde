<?php

namespace App\Filament\Forms;

use App\Models\Kategori;
use App\Models\Urun;
use App\Support\Bicim;
use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Utilities\Get;

/**
 * Stok ve fiş ekranlarında ortak ürün seçici.
 * Ürünler kategori → ad sırasıyla, kategori başlıkları altında listelenir;
 * yalnızca aktif ve arşivlenmemiş ürünler görünür.
 */
class UrunSecimi
{
    public static function make(string $ad = 'urun_id'): Select
    {
        return Select::make($ad)
            ->label('Ürün')
            ->options(fn (): array => static::secenekler())
            ->searchable()
            ->required()
            ->distinct()
            ->live()
            ->helperText(function (Get $get) use ($ad): ?string {
                $urun = static::bul($get($ad));

                return $urun ? 'Mevcut stok: ' . Bicim::miktar($urun->mevcut_stok, $urun->birim) : null;
            })
            ->validationMessages([
                'distinct' => 'Aynı ürün birden fazla satırda seçilemez.',
            ]);
    }

    /** @return array<string, array<int, string>> kategori adı → [id => "Ad"] */
    public static function secenekler(): array
    {
        return Kategori::query()
            ->orderBy('sira')->orderBy('ad')
            ->with(['urunler' => fn ($q) => $q->secilebilir()->orderBy('ad')])
            ->get()
            ->filter(fn (Kategori $k) => $k->urunler->isNotEmpty())
            ->mapWithKeys(fn (Kategori $k) => [
                $k->ad => $k->urunler->mapWithKeys(fn (Urun $u) => [$u->id => $u->ad])->all(),
            ])
            ->all();
    }

    public static function bul(mixed $id): ?Urun
    {
        return filled($id) ? Urun::find($id) : null;
    }
}
