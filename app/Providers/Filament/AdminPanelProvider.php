<?php

namespace App\Providers\Filament;

use App\Filament\Auth\Giris;
use Filament\Auth\MultiFactor\App\AppAuthentication;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\NavigationItem;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Support\Icons\Heroicon;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('panel')
            ->login(Giris::class)
            ->brandName('Patek Life Perde')
            ->brandLogo(fn (): string => asset('images/logo-160.png'))
            ->brandLogoHeight('2.75rem')
            ->favicon(fn (): string => asset('images/favicon-192.png'))
            ->profile()
            // Sayfa geçişleri tam yenileme yerine Livewire wire:navigate ile yapılır (daha hızlı).
            // PDF ve ekstre gibi dosya üreten rotalar bu moddan hariç tutulur.
            ->spa()
            ->spaUrlExceptions(fn (): array => [
                url('/panel/fisler/*/pdf*'),
                url('/panel/musteriler/*/ekstre*'),
            ])
            ->multiFactorAuthentication([
                AppAuthentication::make()->recoverable(),
            ])
            ->colors([
                'primary' => Color::hex('#0f3a29'),   // logo yeşili
                'warning' => Color::hex('#c49a44'),   // logo altını
            ])
            ->navigationGroups(['Ürünler', 'Stok', 'Satış', 'Cari', 'Geçmiş', 'Sistem'])
            ->navigationItems([
                // Panelden tanıtım sitesine geçiş (yeni sekmede açılır)
                NavigationItem::make('Web Sitesi')
                    ->url(fn (): string => url('/'))
                    ->icon(Heroicon::OutlinedGlobeAlt)
                    ->openUrlInNewTab()
                    ->sort(99),
            ])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                PreventRequestForgery::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}
