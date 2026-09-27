<?php

namespace App\Filament\Pages;

use App\Enums\StokSebebi;
use App\Filament\Kutular\CanliStokKutusu;
use App\Enums\StokYonu;
use App\Exceptions\YetersizStokException;
use App\Filament\Forms\UrunSecimi;
use App\Models\Urun;
use App\Services\StokServisi;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Livewire;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use UnitEnum;

class StokGiris extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowDownTray;

    protected static string|UnitEnum|null $navigationGroup = 'Stok';

    protected static ?string $navigationLabel = 'Stok Giriş';

    protected static ?string $title = 'Stok Giriş';

    protected static ?string $slug = 'stok-giris';

    protected static ?int $navigationSort = 10;

    /** @var array<string, mixed>|null */
    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill();
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make()
                    ->columns(3)
                    ->schema([
                        Select::make('sebep')
                            ->label('Sebep')
                            ->options(StokSebebi::elleSecilebilir(StokYonu::Giris))
                            ->default(StokSebebi::Alis->value)
                            ->required()
                            ->native(false),
                        TextInput::make('aciklama')
                            ->label('Açıklama')
                            ->placeholder('Örn. irsaliye no, tedarikçi')
                            ->maxLength(255)
                            ->columnSpan(2),
                    ]),

                Repeater::make('kalemler')
                    ->label('Ürünler')
                    ->schema([
                        UrunSecimi::make()
                            // Ürün değişince miktar sıfırlanır.
                            ->afterStateUpdated(fn (Set $set) => $set('miktar', null))
                            ->columnSpan(8),
                        TextInput::make('miktar')
                            ->label('Miktar')
                            ->columnSpan(4)
                            ->numeric()
                            ->minValue(0.01)
                            ->step('0.01')
                            ->suffix(fn (Get $get): ?string => UrunSecimi::bul($get('urun_id'))?->birim->kisa())
                            ->required(),
                    ])
                    ->columns(12)
                    ->minItems(1)
                    ->defaultItems(1)
                    ->addActionLabel('Ürün ekle')
                    ->reorderable(false),
            ])
            ->statePath('data');
    }

    /** Solda form, sağda canlı stok kutusu; geniş ekranda 8/4, dar ekranda alt alta. */
    public function content(Schema $schema): Schema
    {
        return $schema
            ->components([
                Grid::make(12)
                    ->schema([
                        Form::make([EmbeddedSchema::make('form')])
                            ->id('form')
                            ->livewireSubmitHandler('kaydet')
                            ->footer([
                                Actions::make([
                                    Action::make('kaydet')
                                        ->label('Stok girişini kaydet')
                                        ->submit('kaydet')
                                        ->keyBindings(['mod+s']),
                                    Action::make('temizle')
                                        ->label('Temizle')
                                        ->color('gray')
                                        ->action(fn () => $this->form->fill()),
                                ]),
                            ])
                            ->columnSpan(['default' => 12, 'xl' => 8]),
                        Livewire::make(CanliStokKutusu::class, ['baslik' => 'Canlı stok'])
                            ->columnSpan(['default' => 12, 'xl' => 4]),
                    ]),
            ]);
    }

    /** Tüm satırlar tek transaction içinde işlenir: biri başarısızsa hiçbiri kaydedilmez. */
    public function kaydet(): void
    {
        $veri = $this->form->getState();
        $sebep = StokSebebi::from($veri['sebep']);
        $aciklama = $veri['aciklama'] ?? null;
        $servis = app(StokServisi::class);

        try {
            DB::transaction(function () use ($veri, $sebep, $aciklama, $servis): void {
                foreach ($veri['kalemler'] as $satir) {
                    $urun = Urun::findOrFail($satir['urun_id']);
                    $servis->girisYap($urun, (string) $satir['miktar'], $sebep, $aciklama);
                }
            });
        } catch (InvalidArgumentException|YetersizStokException $e) {
            Notification::make()
                ->title('Stok girişi kaydedilemedi')
                ->body($e->getMessage())
                ->danger()
                ->persistent()
                ->send();

            return;
        }

        Notification::make()
            ->title(count($veri['kalemler']) . ' ürün için stok girişi kaydedildi.')
            ->success()
            ->send();

        $this->form->fill();
    }
}
