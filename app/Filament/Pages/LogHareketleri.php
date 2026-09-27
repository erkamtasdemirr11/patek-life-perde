<?php

namespace App\Filament\Pages;

use App\Filament\Tables\CsvIndir;
use App\Filament\Tables\TarihAraligiFiltresi;
use App\Models\Fis;
use App\Models\Kategori;
use App\Models\Musteri;
use App\Models\Tahsilat;
use App\Models\Urun;
use App\Models\User;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Spatie\Activitylog\Models\Activity;
use UnitEnum;

class LogHareketleri extends GecmisSayfasi
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    protected static string|UnitEnum|null $navigationGroup = 'Sistem';

    protected static ?string $navigationLabel = 'Log Hareketleri';

    protected static ?string $title = 'Log Hareketleri';

    protected static ?string $slug = 'log-hareketleri';

    protected static ?int $navigationSort = 10;

    public function table(Table $table): Table
    {
        return $table
            ->query(Activity::query()->with(['causer', 'subject']))
            ->columns([
                TextColumn::make('created_at')
                    ->label('Tarih')
                    ->dateTime('d.m.Y H:i:s')
                    ->sortable(),
                TextColumn::make('log_name')
                    ->label('Kaynak')
                    ->badge()
                    ->color('gray')
                    ->formatStateUsing(fn (?string $state): string => static::logAdi($state)),
                TextColumn::make('description')
                    ->label('İşlem')
                    ->searchable()
                    ->wrap(),
                TextColumn::make('konu')
                    ->label('Konu')
                    ->state(fn (Activity $record): ?string => static::konuEtiketi($record))
                    ->placeholder('—'),
                TextColumn::make('causer.name')
                    ->label('Kullanıcı')
                    ->placeholder('Sistem'),
            ])
            ->filters([
                TarihAraligiFiltresi::make('created_at'),
                SelectFilter::make('log_name')
                    ->label('Kaynak')
                    ->options(fn (): array => static::logAdlari()),
                SelectFilter::make('causer_id')
                    ->label('Kullanıcı')
                    ->options(fn (): array => User::query()->orderBy('name')->pluck('name', 'id')->all()),
            ])
            ->recordActions([
                Action::make('detay')
                    ->label('Detay')
                    ->icon(Heroicon::OutlinedEye)
                    ->modalHeading(fn (Activity $record): string => $record->description)
                    ->modalDescription(fn (Activity $record): string => $record->created_at->format('d.m.Y H:i:s') . ' — ' . ($record->causer?->name ?? 'Sistem'))
                    ->modalContent(fn (Activity $record) => view('filament.log-detay', ['kayit' => $record]))
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Kapat'),
            ])
            ->headerActions([
                CsvIndir::aksiyon(
                    'log-hareketleri',
                    ['Tarih', 'Kaynak', 'İşlem', 'Konu', 'Kullanıcı', 'Değişiklikler'],
                    fn (Activity $a): array => [
                        $a->created_at->format('d.m.Y H:i:s'),
                        static::logAdi($a->log_name),
                        $a->description,
                        static::konuEtiketi($a),
                        $a->causer?->name ?? 'Sistem',
                        $a->properties->isNotEmpty() ? json_encode($a->properties, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null,
                    ],
                    ['causer', 'subject'],
                ),
            ])
            ->defaultSort('created_at', 'desc')
            ->paginated([25, 50, 100])
            ->emptyStateHeading('Henüz log kaydı yok');
    }

    /** log_name → Türkçe etiket. */
    public static function logAdi(?string $logName): string
    {
        return match ($logName) {
            'kullanici' => 'Kullanıcı',
            'kategori' => 'Kategori',
            'urun' => 'Ürün',
            'musteri' => 'Müşteri',
            'fis' => 'Fiş',
            'tahsilat' => 'Tahsilat',
            'giris' => 'Giriş / Çıkış',
            'stok' => 'Stok düzeltme',
            'cari' => 'Cari düzeltme',
            'yedek' => 'Yedekleme',
            null, '' => '—',
            default => ucfirst($logName),
        };
    }

    /** Filtre seçenekleri: tabloda geçen log adları. */
    public static function logAdlari(): array
    {
        return Activity::query()
            ->distinct()
            ->orderBy('log_name')
            ->pluck('log_name')
            ->filter()
            ->mapWithKeys(fn (string $ad): array => [$ad => static::logAdi($ad)])
            ->all();
    }

    /** "Ürün: KTN-104 Keten Beyaz" gibi; kayıt silinmişse "Ürün #12". */
    public static function konuEtiketi(Activity $kayit): ?string
    {
        $tur = match ($kayit->subject_type) {
            User::class => 'Kullanıcı',
            Kategori::class => 'Kategori',
            Urun::class => 'Ürün',
            Musteri::class => 'Müşteri',
            Fis::class => 'Fiş',
            Tahsilat::class => 'Tahsilat',
            null => null,
            default => class_basename($kayit->subject_type),
        };

        if ($tur === null) {
            return null;
        }

        $konu = $kayit->subject;

        $ad = match (true) {
            $konu instanceof User => $konu->name,
            $konu instanceof Kategori => $konu->ad,
            $konu instanceof Urun => $konu->ad,
            $konu instanceof Musteri => $konu->firma_adi,
            $konu instanceof Fis => $konu->fis_no,
            $konu instanceof Tahsilat => $konu->makbuz_no,
            default => null,
        };

        return $ad !== null ? "{$tur}: {$ad}" : "{$tur} #{$kayit->subject_id}";
    }
}
