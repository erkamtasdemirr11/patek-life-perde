<?php

namespace App\Filament\Resources\Tahsilatlar;

use App\Enums\OdemeYontemi;
use App\Enums\TahsilatDurumu;
use App\Filament\Resources\Fisler\FisResource;
use App\Filament\Resources\Tahsilatlar\Pages\CreateTahsilat;
use App\Filament\Resources\Tahsilatlar\Pages\ListTahsilatlar;
use App\Models\Musteri;
use App\Models\Tahsilat;
use App\Services\TahsilatServisi;
use App\Support\Bicim;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;
use LogicException;
use UnitEnum;

/**
 * Tahsilatlar: oluşturma + görüntüleme + iptal. Düzenleme ve silme yoktur.
 */
class TahsilatResource extends Resource
{
    protected static ?string $model = Tahsilat::class;

    protected static ?string $slug = 'tahsilatlar';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBanknotes;

    protected static string|UnitEnum|null $navigationGroup = 'Cari';

    protected static ?string $navigationLabel = 'Tahsilatlar';

    protected static ?string $modelLabel = 'Tahsilat';

    protected static ?string $pluralModelLabel = 'Tahsilatlar';

    protected static ?int $navigationSort = 20;

    protected static ?string $recordTitleAttribute = 'makbuz_no';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make()
                    ->columns(2)
                    ->schema([
                        Select::make('musteri_id')
                            ->label('Müşteri')
                            ->relationship('musteri', 'firma_adi')
                            ->searchable()
                            ->preload()
                            ->required()
                            ->live()
                            ->helperText(function (Get $get): ?string {
                                $musteri = filled($get('musteri_id')) ? Musteri::find($get('musteri_id')) : null;

                                return $musteri ? 'Güncel bakiye: ' . Bicim::para($musteri->bakiye) : null;
                            }),
                        DateTimePicker::make('tarih')
                            ->label('Tarih')
                            ->default(now())
                            ->seconds(false)
                            ->required(),
                        TextInput::make('tutar')
                            ->label('Tutar')
                            ->numeric()
                            ->minValue(0.01)
                            ->step('0.01')
                            ->suffix('₺')
                            ->required(),
                        Select::make('odeme_yontemi')
                            ->label('Ödeme yöntemi')
                            ->options(OdemeYontemi::class)
                            ->default(OdemeYontemi::Nakit->value)
                            ->required()
                            ->native(false),
                        TextInput::make('aciklama')
                            ->label('Açıklama')
                            ->placeholder('Örn. dekont no, çek bilgisi')
                            ->maxLength(255)
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('makbuz_no')
                    ->label('Makbuz no')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),
                TextColumn::make('tarih')
                    ->label('Tarih')
                    ->dateTime('d.m.Y H:i')
                    ->sortable(),
                TextColumn::make('musteri.firma_adi')
                    ->label('Müşteri')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('fis.fis_no')
                    ->label('Fiş')
                    ->placeholder('—')
                    ->url(fn (Tahsilat $record): ?string => $record->fis ? FisResource::getUrl('view', ['record' => $record->fis]) : null)
                    ->color('primary')
                    ->toggleable(),
                TextColumn::make('tutar')
                    ->label('Tutar')
                    ->formatStateUsing(fn ($state): string => Bicim::para($state))
                    ->alignEnd()
                    ->sortable(),
                TextColumn::make('odeme_yontemi')
                    ->label('Yöntem')
                    ->badge()
                    ->color('gray'),
                TextColumn::make('durum')
                    ->label('Durum')
                    ->badge(),
                TextColumn::make('aciklama')
                    ->label('Açıklama')
                    ->placeholder('—')
                    ->limit(40)
                    ->toggleable(),
                TextColumn::make('user.name')
                    ->label('Alan')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('durum')
                    ->label('Durum')
                    ->options(TahsilatDurumu::class),
                SelectFilter::make('musteri_id')
                    ->label('Müşteri')
                    ->relationship('musteri', 'firma_adi')
                    ->searchable()
                    ->preload(),
            ])
            ->recordActions([
                ViewAction::make(),
                static::iptalAction(),
            ])
            ->defaultSort('tarih', 'desc');
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }

    /** İptal: neden zorunlu, tutar müşterinin borcuna geri yazılır (TahsilatServisi::iptalEt). */
    public static function iptalAction(): Action
    {
        return Action::make('iptal')
            ->label('İptal et')
            ->icon(Heroicon::OutlinedXCircle)
            ->color('danger')
            ->visible(fn (Tahsilat $record): bool => $record->aktifMi())
            ->schema([
                Textarea::make('iptal_nedeni')
                    ->label('İptal nedeni')
                    ->required()
                    ->rows(3)
                    ->maxLength(255),
            ])
            ->modalHeading(fn (Tahsilat $record): string => "Tahsilat {$record->makbuz_no} iptal edilsin mi?")
            ->modalDescription('Tutar müşterinin borcuna geri yazılır. Bu işlem geri alınamaz.')
            ->modalSubmitActionLabel('Tahsilatı iptal et')
            ->action(function (array $data, Tahsilat $record): void {
                try {
                    app(TahsilatServisi::class)->iptalEt($record, $data['iptal_nedeni']);
                } catch (LogicException|InvalidArgumentException $e) {
                    Notification::make()
                        ->title('Tahsilat iptal edilemedi')
                        ->body($e->getMessage())
                        ->danger()
                        ->send();

                    return;
                }

                Notification::make()
                    ->title("Tahsilat {$record->makbuz_no} iptal edildi.")
                    ->success()
                    ->send();
            });
    }

    public static function getPages(): array
    {
        return [
            'index' => ListTahsilatlar::route('/'),
            'create' => CreateTahsilat::route('/create'),
        ];
    }
}
