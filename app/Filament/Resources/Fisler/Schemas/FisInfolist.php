<?php

namespace App\Filament\Resources\Fisler\Schemas;

use App\Models\Fis;
use App\Models\FisKalemi;
use App\Support\Bicim;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class FisInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make()
                    ->columns(4)
                    ->schema([
                        TextEntry::make('fis_no')
                            ->label('Fiş no')
                            ->weight('bold'),
                        TextEntry::make('tarih')
                            ->label('Tarih')
                            ->dateTime('d.m.Y H:i'),
                        TextEntry::make('musteri.firma_adi')
                            ->label('Müşteri'),
                        TextEntry::make('durum')
                            ->label('Durum')
                            ->badge(),
                        TextEntry::make('odeme_tipi')
                            ->label('Ödeme')
                            ->badge(),
                        TextEntry::make('user.name')
                            ->label('Kesen'),
                        TextEntry::make('aciklama')
                            ->label('Açıklama')
                            ->placeholder('—')
                            ->columnSpan(2),
                    ]),

                Section::make('İptal bilgisi')
                    ->columns(3)
                    ->visible(fn (Fis $record): bool => $record->iptalMi())
                    ->schema([
                        TextEntry::make('iptal_tarihi')
                            ->label('İptal tarihi')
                            ->dateTime('d.m.Y H:i'),
                        TextEntry::make('iptalEden.name')
                            ->label('İptal eden'),
                        TextEntry::make('iptal_nedeni')
                            ->label('Neden'),
                    ]),

                Section::make('Kalemler')
                    ->schema([
                        RepeatableEntry::make('kalemler')
                            ->hiddenLabel()
                            ->schema([
                                TextEntry::make('urun.ad')
                                    ->label('Ürün'),
                                TextEntry::make('miktar')
                                    ->label('Miktar')
                                    ->formatStateUsing(fn ($state, FisKalemi $record): string => Bicim::miktar($state, $record->urun->birim)),
                                TextEntry::make('birim_fiyat')
                                    ->label('Birim fiyat')
                                    ->formatStateUsing(fn ($state): string => Bicim::para($state)),
                                TextEntry::make('tutar')
                                    ->label('Tutar')
                                    ->formatStateUsing(fn ($state): string => Bicim::para($state))
                                    ->weight('bold'),
                            ])
                            ->columns(4),
                    ]),

                Section::make('Toplamlar')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('ara_toplam')
                            ->label('Ara toplam')
                            ->formatStateUsing(fn ($state): string => Bicim::para($state)),
                        TextEntry::make('iskonto')
                            ->label('İskonto')
                            ->formatStateUsing(fn ($state): string => Bicim::para($state)),
                        TextEntry::make('genel_toplam')
                            ->label('Genel toplam')
                            ->formatStateUsing(fn ($state): string => Bicim::para($state))
                            ->weight('bold'),
                    ]),

                // Fiş anındaki cari durum; cari hareket defterinden okunur, sonraki tahsilatlar değiştirmez.
                Section::make('Cari hesap')
                    ->description('Fiş kesildiği andaki durum. Sonraki tahsilatlar bu özeti değiştirmez; güncel bakiye müşteri kartındadır.')
                    ->columns(4)
                    ->schema([
                        TextEntry::make('bakiye_onceki')
                            ->label('Önceki bakiye')
                            ->state(fn (Fis $record): string => Bicim::bakiye($record->bakiyeOzeti()['onceki'])),
                        TextEntry::make('bakiye_fis')
                            ->label('Bu fiş')
                            ->state(fn (Fis $record): string => '+ ' . Bicim::para($record->genel_toplam)),
                        TextEntry::make('pesin_tahsilat')
                            ->label('Peşin tahsilat')
                            ->state(function (Fis $record): string {
                                $tahsilat = $record->pesinTahsilat;

                                if ($tahsilat === null) {
                                    return '—';
                                }

                                $metin = "\u{2212} " . Bicim::para($tahsilat->tutar) . " ({$tahsilat->odeme_yontemi->getLabel()}, makbuz {$tahsilat->makbuz_no})";

                                return $tahsilat->iptalMi() ? $metin . ' (iptal edildi)' : $metin;
                            })
                            ->visible(fn (Fis $record): bool => $record->pesinTahsilat !== null),
                        TextEntry::make('bakiye_sonrasi')
                            ->label('Fiş sonrası bakiye')
                            ->state(fn (Fis $record): string => Bicim::bakiye($record->bakiyeOzeti()['sonrasi']))
                            ->weight('bold')
                            ->color(fn (Fis $record): ?string => match (bccomp($record->bakiyeOzeti()['sonrasi'], '0', 2)) {
                                1 => 'danger',
                                -1 => 'success',
                                default => null,
                            }),
                    ]),
            ]);
    }
}
