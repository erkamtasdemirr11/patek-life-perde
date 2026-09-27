<?php

namespace App\Filament\Resources\Musteriler;

use App\Filament\Resources\Musteriler\Pages\CreateMusteri;
use App\Filament\Resources\Musteriler\Pages\EditMusteri;
use App\Filament\Resources\Musteriler\Pages\ListMusteriler;
use App\Filament\Resources\Musteriler\RelationManagers\CariHareketlerRelationManager;
use App\Models\Musteri;
use App\Services\CariServisi;
use App\Support\Bicim;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\RestoreAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Js;
use InvalidArgumentException;
use LogicException;
use UnitEnum;

class MusteriResource extends Resource
{
    protected static ?string $model = Musteri::class;

    protected static ?string $slug = 'musteriler';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingStorefront;

    protected static string|UnitEnum|null $navigationGroup = 'Cari';

    protected static ?string $navigationLabel = 'Müşteriler';

    protected static ?string $modelLabel = 'Müşteri';

    protected static ?string $pluralModelLabel = 'Müşteriler';

    protected static ?int $navigationSort = 10;

    protected static ?string $recordTitleAttribute = 'firma_adi';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Firma bilgileri')
                    ->columns(2)
                    ->schema([
                        TextInput::make('firma_adi')
                            ->label('Firma adı')
                            ->required()
                            ->maxLength(255),
                        TextInput::make('yetkili')
                            ->label('Yetkili')
                            ->maxLength(255),
                        TextInput::make('telefon')
                            ->label('Telefon')
                            ->tel()
                            ->maxLength(30),
                        Textarea::make('adres')
                            ->label('Adres')
                            ->rows(2)
                            ->columnSpanFull(),
                        Textarea::make('notlar')
                            ->label('Notlar')
                            ->rows(3)
                            ->columnSpanFull(),
                    ]),

                Section::make('Cari hesap')
                    ->columns(2)
                    ->schema([
                        TextInput::make('acilis_bakiyesi')
                            ->label('Açılış bakiyesi')
                            ->numeric()
                            ->step('0.01')
                            ->default(0)
                            ->suffix('₺')
                            ->helperText(fn (string $operation): string => $operation === 'edit'
                                ? 'Yanlış girildiyse yukarıdaki "Açılış bakiyesini düzelt" butonunu kullanın.'
                                : 'Pozitif: müşteri borçlu. Negatif: müşteri alacaklı.')
                            // Yalnızca oluşturmada yazılır; düzenlemede salt okunur.
                            ->disabled(fn (string $operation): bool => $operation === 'edit')
                            ->dehydrated(fn (string $operation): bool => $operation === 'create'),
                        TextInput::make('bakiye')
                            ->label('Güncel bakiye')
                            ->disabled()
                            ->dehydrated(false)
                            ->visible(fn (string $operation): bool => $operation === 'edit')
                            ->formatStateUsing(fn (?Musteri $record): string => Bicim::para($record?->bakiye ?? 0))
                            ->helperText('Bakiye yalnızca fiş, tahsilat ve cari hareketlerle değişir.'),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('firma_adi')
                    ->label('Firma')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),
                TextColumn::make('yetkili')
                    ->label('Yetkili')
                    ->searchable()
                    ->placeholder('—'),
                TextColumn::make('telefon')
                    ->label('Telefon')
                    ->searchable()
                    ->placeholder('—'),
                TextColumn::make('bakiye')
                    ->label('Bakiye')
                    ->formatStateUsing(fn ($state): string => Bicim::para($state))
                    ->color(fn (Musteri $record): ?string => match ($record->bakiyeYonu()) {
                        1 => 'danger',
                        -1 => 'success',
                        default => null,
                    })
                    ->weight(fn (Musteri $record): ?string => $record->bakiyeYonu() !== 0 ? 'bold' : null)
                    ->alignEnd()
                    ->sortable(),
                TextColumn::make('fisler_count')
                    ->label('Fiş')
                    ->counts('fisler')
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('created_at')
                    ->label('Kayıt')
                    ->dateTime('d.m.Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('deleted_at')
                    ->label('Silinme')
                    ->dateTime('d.m.Y H:i')
                    ->placeholder('—')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->recordActions([
                EditAction::make(),
                static::ekstreAction(),
                DeleteAction::make()
                    ->label('Sil')
                    ->modalHeading('Müşteriyi sil')
                    ->modalDescription('Müşteri listeden kaldırılır; fişlerde ve geçmiş kayıtlarda adıyla görünmeye devam eder. "Son silinenler" sekmesinden geri alabilirsiniz.')
                    ->successNotificationTitle('Müşteri silindi')
                    // Bakiyesi sıfır olmayan müşteri silinemez (model de engeller).
                    ->hidden(fn (Musteri $record): bool => $record->bakiyeYonu() !== 0),
                RestoreAction::make()
                    ->label('Geri al')
                    ->successNotificationTitle('Müşteri geri alındı'),
            ])
            ->defaultSort('firma_adi');
    }

    /** Tarih aralıklı ekstre PDF'ini yeni sekmede açar. Boş bırakılırsa tüm hareketler. */
    public static function ekstreAction(): Action
    {
        return Action::make('ekstre')
            ->label('Ekstre')
            ->icon(Heroicon::OutlinedDocumentText)
            ->schema([
                DatePicker::make('baslangic')
                    ->label('Başlangıç'),
                DatePicker::make('bitis')
                    ->label('Bitiş'),
            ])
            ->modalHeading('Müşteri ekstresi')
            ->modalDescription('Tarihleri boş bırakırsanız tüm hareketler listelenir.')
            ->modalSubmitActionLabel('PDF aç')
            ->action(function (array $data, Musteri $record, $livewire): void {
                $url = route('musteri.ekstre', array_filter([
                    'musteri' => $record->getKey(),
                    'baslangic' => $data['baslangic'] ?? null,
                    'bitis' => $data['bitis'] ?? null,
                ]));

                $livewire->js('window.open(' . Js::from($url) . ', "_blank")');
            });
    }

    /**
     * Yanlış girilen açılış bakiyesi: doğru tutar yazılır, fark düzeltme hareketi olur
     * (CariServisi::acilisBakiyesiDuzelt). Açılış hareketi silinmez.
     */
    public static function acilisDuzeltAction(): Action
    {
        return Action::make('acilisDuzelt')
            ->label('Açılış bakiyesini düzelt')
            ->icon(Heroicon::OutlinedPencilSquare)
            ->color('warning')
            ->hidden(fn (Musteri $record): bool => $record->trashed())
            ->fillForm(fn (Musteri $record): array => [
                'kayitli' => Bicim::para($record->acilis_bakiyesi),
            ])
            ->schema([
                TextInput::make('kayitli')
                    ->label('Kayıtlı açılış bakiyesi')
                    ->disabled()
                    ->dehydrated(false),
                TextInput::make('dogru_tutar')
                    ->label('Doğru açılış bakiyesi')
                    ->numeric()
                    ->step('0.01')
                    ->required()
                    ->suffix('₺')
                    ->live(onBlur: true)
                    ->helperText(fn (Get $get, Musteri $record): string => static::acilisDuzeltOnizle($record, $get('dogru_tutar'))),
                Textarea::make('aciklama')
                    ->label('Açıklama')
                    ->required()
                    ->rows(2)
                    ->maxLength(150),
            ])
            ->modalHeading('Açılış bakiyesini düzelt')
            ->modalDescription('Açılış kaydı silinmez; doğru tutarla arasındaki fark düzeltme hareketi olarak yazılır.')
            ->modalSubmitActionLabel('Düzelt')
            ->action(function (array $data, Musteri $record, $livewire): void {
                try {
                    app(CariServisi::class)->acilisBakiyesiDuzelt($record, (string) $data['dogru_tutar'], (string) $data['aciklama']);
                } catch (LogicException|InvalidArgumentException $e) {
                    Notification::make()
                        ->title('Açılış bakiyesi düzeltilemedi')
                        ->body($e->getMessage())
                        ->danger()
                        ->send();

                    return;
                }

                Notification::make()
                    ->title('Açılış bakiyesi düzeltildi.')
                    ->success()
                    ->send();

                // Karttaki açılış bakiyesi, güncel bakiye ve hareket listesi yenilensin.
                if ($livewire instanceof EditRecord) {
                    $livewire->redirect(static::getUrl('edit', ['record' => $record]));
                }
            });
    }

    /** Düzeltme penceresinde yazılacak hareketin ve yeni bakiyenin önizlemesi. Gösterim içindir. */
    protected static function acilisDuzeltOnizle(Musteri $musteri, mixed $dogruTutar): string
    {
        if (! is_scalar($dogruTutar) || ! preg_match('/^-?\d+(\.\d{1,2})?$/', (string) $dogruTutar)) {
            return 'Pozitif: müşteri borçlu. Negatif: müşteri alacaklı.';
        }

        $fark = bcsub((string) $dogruTutar, (string) $musteri->acilis_bakiyesi, 2);

        if (bccomp($fark, '0', 2) === 0) {
            return 'Kayıtlı tutarla aynı; düzeltilecek fark yok.';
        }

        return sprintf(
            '%s düzeltmesi yazılır. Yeni bakiye: %s',
            Bicim::bakiye($fark),
            Bicim::bakiye(bcadd((string) $musteri->bakiye, $fark, 2)),
        );
    }

    /** Silinmiş müşteriler de "Son silinenler" sekmesinde görülebilsin. */
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->withoutGlobalScopes([
            SoftDeletingScope::class,
        ]);
    }

    public static function getRelations(): array
    {
        return [
            CariHareketlerRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListMusteriler::route('/'),
            'create' => CreateMusteri::route('/create'),
            'edit' => EditMusteri::route('/{record}/edit'),
        ];
    }
}
