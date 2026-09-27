<?php

namespace App\Filament\Resources\Urunler\Schemas;

use App\Enums\Birim;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class UrunForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make('Temel bilgiler')
                    ->columns(3)
                    ->schema([
                        TextInput::make('ad')
                            ->label('Ürün adı')
                            ->required()
                            ->maxLength(255)
                            ->columnSpan(2),
                        Select::make('kategori_id')
                            ->label('Kategori')
                            ->relationship('kategori', 'ad')
                            ->searchable()
                            ->preload()
                            ->required()
                            ->createOptionForm([
                                TextInput::make('ad')
                                    ->label('Kategori adı')
                                    ->required()
                                    ->unique()
                                    ->maxLength(100),
                            ]),
                        Select::make('birim')
                            ->label('Birim')
                            ->options(Birim::class)
                            ->default(Birim::Metre->value)
                            ->required()
                            ->native(false),
                        TextInput::make('renk')
                            ->label('Renk')
                            ->maxLength(100),
                        Toggle::make('aktif')
                            ->label('Aktif')
                            ->helperText('Pasif ürün, stok ve fiş ekranlarındaki seçim listelerinde görünmez.')
                            ->default(true)
                            ->columnSpanFull(),
                    ]),

                Section::make('Fiyat ve kritik stok')
                    ->description('Mevcut stok burada değiştirilmez; Stok Giriş / Stok Çıkışı sayfalarından işlenir ve Canlı Stok\'ta görünür.')
                    ->columns(3)
                    ->schema([
                        TextInput::make('alis_fiyati')
                            ->label('Alış fiyatı')
                            ->numeric()
                            ->minValue(0)
                            ->step('0.01')
                            ->suffix('₺'),
                        TextInput::make('satis_fiyati')
                            ->label('Satış fiyatı')
                            ->numeric()
                            ->minValue(0)
                            ->step('0.01')
                            ->suffix('₺'),
                        TextInput::make('kritik_stok')
                            ->label('Kritik stok')
                            ->numeric()
                            ->minValue(0)
                            ->step('0.01')
                            ->default(0)
                            ->required()
                            ->helperText('Stok bu seviyeye inince ürün kırmızı gösterilir.'),
                    ]),

                Section::make('Görsel ve açıklama')
                    ->columns(2)
                    ->schema([
                        FileUpload::make('gorsel')
                            ->label('Görsel')
                            ->image()
                            ->disk('public')
                            ->directory('urunler')
                            ->visibility('public')
                            ->maxSize(2048)
                            ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
                            ->helperText('JPG, PNG veya WebP; en fazla 2 MB.'),
                        Textarea::make('aciklama')
                            ->label('Açıklama')
                            ->rows(5),
                    ]),
            ]);
    }
}
