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
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use UnitEnum;

class StokCikisi extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowUpTray;

    protected static string|UnitEnum|null $navigationGroup = 'Stok';

    protected static ?string $navigationLabel = 'Stok Çıkışı';

    protected static ?string $title = 'Stok Çıkışı';

    protected static ?string $slug = 'stok-cikisi';

    protected static ?int $navigationSort = 30;

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
                            // Satış fişten gelir, burada seçilemez.
                            ->options(StokSebebi::elleSecilebilir(StokYonu::Cikis))
                            ->placeholder('Seçin')
                            ->required()
                            ->native(false),
                        TextInput::make('aciklama')
                            ->label('Açıklama')
                            ->placeholder('Örn. kime numune verildi, firenin nedeni')
                            ->maxLength(255)
                            ->columnSpan(2),
                    ]),

                Repeater::make('kalemler')
                    ->label('Ürünler')
                    ->schema([
                        UrunSecimi::make()
                            ->columnSpan(5),
                        TextInput::make('miktar')
                            ->label('Miktar')
                            ->columnSpan(3)
                            ->numeric()
                            ->minValue(0.01)
                            ->step('0.01')
                            ->suffix(fn (Get $get): ?string => UrunSecimi::bul($get('urun_id'))?->birim->kisa())
                            ->required(),
                    ])
                    ->columns(8)
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
                                        ->label('Stok çıkışını kaydet')
                                        ->color('danger')
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
    /** Tüm satırlar tek transaction içinde işlenir: bir üründe stok yetmezse hiçbiri düşülmez. */
    public function kaydet(): void
    {
        $veri = $this->form->getState();
        $sebep = StokSebebi::from($veri['sebep']);
        $aciklama = $veri['aciklama'] ?? null;
        $servis = app(StokServisi::class);

        try {
            DB::transaction(function () use ($veri, $sebep, $aciklama, $servis): void {
                foreach ($veri['kalemler'] as $satir) {
                    $servis->cikisYap(Urun::findOrFail($satir['urun_id']), (string) $satir['miktar'], $sebep, $aciklama);
                }
            });
        } catch (InvalidArgumentException|YetersizStokException $e) {
            Notification::make()
                ->title('Stok çıkışı kaydedilemedi')
                ->body($e->getMessage())
                ->danger()
                ->persistent()
                ->send();

            return;
        }

        Notification::make()
            ->title(count($veri['kalemler']) . ' ürün için stok çıkışı kaydedildi.')
            ->success()
            ->send();

        $this->form->fill();
    }
}
