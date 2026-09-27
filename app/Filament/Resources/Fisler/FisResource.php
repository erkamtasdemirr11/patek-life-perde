<?php

namespace App\Filament\Resources\Fisler;

use App\Filament\Resources\Fisler\Pages\CreateFis;
use App\Filament\Resources\Fisler\Pages\ListFisler;
use App\Filament\Resources\Fisler\Pages\ViewFis;
use App\Filament\Resources\Fisler\Schemas\FisForm;
use App\Filament\Resources\Fisler\Schemas\FisInfolist;
use App\Filament\Resources\Fisler\Tables\FislerTable;
use App\Models\Fis;
use App\Services\FisServisi;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;
use LogicException;
use UnitEnum;

/**
 * Fiş Kayıt: oluşturma + görüntüleme + iptal. Düzenleme ve silme yoktur.
 */
class FisResource extends Resource
{
    protected static ?string $model = Fis::class;

    protected static ?string $slug = 'fisler';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedReceiptPercent;

    protected static string|UnitEnum|null $navigationGroup = 'Satış';

    protected static ?string $navigationLabel = 'Fiş Kayıt';

    protected static ?string $modelLabel = 'Fiş';

    protected static ?string $pluralModelLabel = 'Fişler';

    protected static ?int $navigationSort = 10;

    protected static ?string $recordTitleAttribute = 'fis_no';

    public static function form(Schema $schema): Schema
    {
        return FisForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return FisInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return FislerTable::configure($table);
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

    /** Yeni sekmede 80 mm termal fişi açar. Hem listede hem görüntüleme sayfasında kullanılır. */
    public static function pdfAction(): Action
    {
        return Action::make('pdf')
            ->label('Fiş yazdır')
            ->icon(Heroicon::OutlinedPrinter)
            ->url(fn (Fis $record): string => route('fis.pdf', $record), shouldOpenInNewTab: true);
    }

    /** Aynı fişin A4 şablonu (e-posta veya WhatsApp ile göndermek için). */
    public static function a4Action(): Action
    {
        return Action::make('a4')
            ->label('A4 PDF')
            ->icon(Heroicon::OutlinedDocumentText)
            ->color('gray')
            ->url(fn (Fis $record): string => route('fis.pdf', ['fis' => $record, 'boyut' => 'a4']), shouldOpenInNewTab: true);
    }

    /** İptal: neden zorunlu, stok geri eklenir, tek transaction (FisServisi::iptalEt). */
    public static function iptalAction(): Action
    {
        return Action::make('iptal')
            ->label('İptal et')
            ->icon(Heroicon::OutlinedXCircle)
            ->color('danger')
            ->visible(fn (Fis $record): bool => $record->aktifMi())
            ->schema([
                Textarea::make('iptal_nedeni')
                    ->label('İptal nedeni')
                    ->required()
                    ->rows(3)
                    ->maxLength(255),
            ])
            ->modalHeading(fn (Fis $record): string => "Fiş {$record->fis_no} iptal edilsin mi?")
            ->modalDescription('Fişteki tüm ürünler stoğa geri eklenir. Bu işlem geri alınamaz.')
            ->modalSubmitActionLabel('Fişi iptal et')
            ->action(function (array $data, Fis $record, $livewire): void {
                try {
                    app(FisServisi::class)->iptalEt($record, $data['iptal_nedeni']);
                } catch (LogicException|InvalidArgumentException $e) {
                    Notification::make()
                        ->title('Fiş iptal edilemedi')
                        ->body($e->getMessage())
                        ->danger()
                        ->send();

                    return;
                }

                Notification::make()
                    ->title("Fiş {$record->fis_no} iptal edildi.")
                    ->success()
                    ->send();

                if ($livewire instanceof ViewRecord) {
                    $livewire->redirect(static::getUrl('view', ['record' => $record]));
                }
            });
    }

    public static function getPages(): array
    {
        return [
            'index' => ListFisler::route('/'),
            'create' => CreateFis::route('/create'),
            'view' => ViewFis::route('/{record}'),
        ];
    }
}
