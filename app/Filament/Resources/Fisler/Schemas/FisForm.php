<?php

namespace App\Filament\Resources\Fisler\Schemas;

use App\Enums\OdemeTipi;
use App\Enums\OdemeYontemi;
use App\Filament\Forms\UrunSecimi;
use App\Models\Musteri;
use App\Support\Bicim;
use App\Support\Sayi;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;

/**
 * Fiş kesme formu. Tutar, toplamlar ve fiş sonrası bakiye ekranda anlık hesaplanır (gösterim için);
 * kesin hesap FisServisi'nde bcmath ile yeniden yapılır.
 */
class FisForm
{
    private const TL = "\u{20BA}";

    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make('Fiş bilgileri')
                    ->columns(3)
                    ->schema([
                        Select::make('musteri_id')
                            ->label('Müşteri')
                            ->relationship('musteri', 'firma_adi')
                            ->searchable()
                            ->preload()
                            ->required()
                            ->live()
                            ->helperText(fn (Get $get): ?string => static::musteriBakiyeMetni($get('musteri_id')))
                            ->afterStateUpdated(fn (Get $get, Set $set) => static::toplamlariGuncelle($get, $set))
                            ->createOptionForm([
                                TextInput::make('firma_adi')
                                    ->label('Firma adı')
                                    ->required()
                                    ->maxLength(255),
                                TextInput::make('telefon')
                                    ->label('Telefon')
                                    ->tel()
                                    ->maxLength(30),
                            ]),
                        DateTimePicker::make('tarih')
                            ->label('Tarih')
                            ->default(now())
                            ->seconds(false)
                            ->required(),
                        TextInput::make('aciklama')
                            ->label('Açıklama')
                            ->maxLength(255),
                    ]),

                Repeater::make('kalemler')
                    ->label('Kalemler')
                    ->schema([
                        UrunSecimi::make()
                            ->afterStateUpdated(function (mixed $state, Get $get, Set $set): void {
                                // Birim fiyat ürün kartından gelir ama fişte değiştirilebilir.
                                $set('birim_fiyat', UrunSecimi::bul($state)?->satis_fiyati);
                                static::satiriGuncelle($get, $set);
                            })
                            ->columnSpan(5),
                        TextInput::make('miktar')
                            ->label('Miktar')
                            ->columnSpan(2)
                            ->numeric()
                            ->minValue(0.01)
                            ->step('0.01')
                            ->required()
                            ->live(onBlur: true)
                            ->suffix(fn (Get $get): ?string => UrunSecimi::bul($get('urun_id'))?->birim->kisa())
                            ->afterStateUpdated(fn (Get $get, Set $set) => static::satiriGuncelle($get, $set)),
                        TextInput::make('birim_fiyat')
                            ->label('Birim fiyat')
                            ->columnSpan(2)
                            ->numeric()
                            ->minValue(0)
                            ->step('0.01')
                            ->required()
                            ->suffix(self::TL)
                            ->live(onBlur: true)
                            ->afterStateUpdated(fn (Get $get, Set $set) => static::satiriGuncelle($get, $set)),
                        TextInput::make('tutar')
                            ->label('Tutar')
                            ->columnSpan(3)
                            ->suffix(self::TL)
                            ->disabled()
                            ->dehydrated(false),
                    ])
                    ->columns(12)
                    ->minItems(1)
                    ->defaultItems(1)
                    ->addActionLabel('Kalem ekle')
                    ->reorderable(false)
                    ->live()
                    ->afterStateUpdated(fn (Get $get, Set $set) => static::toplamlariGuncelle($get, $set)),

                Section::make('Toplamlar')
                    ->columns(3)
                    ->schema([
                        TextInput::make('ara_toplam')
                            ->label('Ara toplam')
                            ->suffix(self::TL)
                            ->disabled()
                            ->dehydrated(false),
                        TextInput::make('iskonto')
                            ->label('İskonto')
                            ->numeric()
                            ->minValue(0)
                            ->step('0.01')
                            ->default(0)
                            ->suffix(self::TL)
                            ->live(onBlur: true)
                            ->afterStateUpdated(fn (Get $get, Set $set) => static::toplamlariGuncelle($get, $set)),
                        TextInput::make('genel_toplam')
                            ->label('Genel toplam')
                            ->suffix(self::TL)
                            ->disabled()
                            ->dehydrated(false),
                    ]),

                Section::make('Ödeme')
                    ->description('Açık hesap: tutar müşterinin borcuna yazılır, ay sonu veya vadesinde tahsil edilir. Peşin: fişle birlikte tahsilat makbuzu kesilir, bakiye değişmez.')
                    ->columns(3)
                    ->schema([
                        Select::make('odeme_tipi')
                            ->label('Ödeme tipi')
                            ->options(OdemeTipi::class)
                            ->default(OdemeTipi::AcikHesap->value)
                            ->required()
                            ->native(false)
                            ->live()
                            ->afterStateUpdated(fn (Get $get, Set $set) => static::toplamlariGuncelle($get, $set)),
                        Select::make('odeme_yontemi')
                            ->label('Ödeme yöntemi')
                            ->options(OdemeYontemi::class)
                            ->default(OdemeYontemi::Nakit->value)
                            ->native(false)
                            ->visible(fn (Get $get): bool => static::pesinMi($get('odeme_tipi')))
                            ->required(fn (Get $get): bool => static::pesinMi($get('odeme_tipi'))),
                        TextInput::make('fis_sonrasi_bakiye')
                            ->label('Fiş sonrası bakiye')
                            ->placeholder('Müşteri seçin')
                            ->disabled()
                            ->dehydrated(false)
                            ->helperText('Kaydedince müşterinin bakiyesi bu olur.'),
                    ]),
            ]);
    }

    /** Satır içinden çağrılır: satırın tutarını, ardından form toplamlarını günceller. */
    protected static function satiriGuncelle(Get $get, Set $set): void
    {
        $tutar = static::tutarHesapla($get('miktar'), $get('birim_fiyat'));
        $set('tutar', $tutar === null ? null : Bicim::sayi($tutar));
        static::toplamlariGuncelle($get, $set, '../../');
    }

    /**
     * Ara toplam, genel toplam ve fiş sonrası bakiye önizlemesini yeniden hesaplar.
     * $kok: form köküne göreli yol; kökten '', repeater satırından '../../'.
     */
    protected static function toplamlariGuncelle(Get $get, Set $set, string $kok = ''): void
    {
        $araToplam = '0.00';

        foreach ((array) $get($kok . 'kalemler') as $kalem) {
            $tutar = static::tutarHesapla($kalem['miktar'] ?? null, $kalem['birim_fiyat'] ?? null);

            if ($tutar !== null) {
                $araToplam = bcadd($araToplam, $tutar, 2);
            }
        }

        $iskonto = static::sayiMi($get($kok . 'iskonto'))
            ? bcadd((string) $get($kok . 'iskonto'), '0', 2)
            : '0.00';

        $genelToplam = bcsub($araToplam, $iskonto, 2);

        // Gösterim Türkçe biçimde (1.250,50); hesap string/bcmath ile yapılır.
        $set($kok . 'ara_toplam', Bicim::sayi($araToplam));
        $set($kok . 'genel_toplam', Bicim::sayi($genelToplam));
        $set($kok . 'fis_sonrasi_bakiye', static::bakiyeOnizle($get($kok . 'musteri_id'), $get($kok . 'odeme_tipi'), $genelToplam));
    }

    /** Müşteri seçiminin altında güncel bakiye. */
    protected static function musteriBakiyeMetni(mixed $musteriId): ?string
    {
        $musteri = filled($musteriId) ? Musteri::find($musteriId) : null;

        return $musteri ? 'Güncel bakiye: ' . Bicim::bakiye($musteri->bakiye) : null;
    }

    /** Açık hesapta bakiye fiş tutarı kadar artar; peşinde değişmez. Gösterim içindir. */
    protected static function bakiyeOnizle(mixed $musteriId, mixed $odemeTipi, string $genelToplam): ?string
    {
        $musteri = filled($musteriId) ? Musteri::find($musteriId) : null;

        if ($musteri === null) {
            return null;
        }

        $bakiye = (string) $musteri->bakiye;
        $sonrasi = static::pesinMi($odemeTipi) ? $bakiye : bcadd($bakiye, $genelToplam, 2);

        return Bicim::bakiye($sonrasi);
    }

    protected static function pesinMi(mixed $deger): bool
    {
        $tip = $deger instanceof OdemeTipi ? $deger : OdemeTipi::tryFrom((string) $deger);

        return $tip === OdemeTipi::Pesin;
    }

    protected static function tutarHesapla(mixed $miktar, mixed $fiyat): ?string
    {
        if (! static::sayiMi($miktar) || ! static::sayiMi($fiyat)) {
            return null;
        }

        return Sayi::carp((string) $miktar, (string) $fiyat);
    }

    protected static function sayiMi(mixed $deger): bool
    {
        return filled($deger) && preg_match('/^\d+(\.\d{1,2})?$/', (string) $deger) === 1;
    }
}
