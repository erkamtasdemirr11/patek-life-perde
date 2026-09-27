<?php

namespace App\Providers;

use App\Filament\Kutular\CanliStokKutusu;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Canlıda tüm bağlantılar https ile üretilir; Hostinger'de HTTPS zorunludur.
        if ($this->app->isProduction()) {
            URL::forceScheme('https');
        }

        $this->girisCikisLogla();

        // Form sayfalarının yanındaki canlı stok kutusu (panel widget'ı değil, elle kaydedilir).
        Livewire::component('canli-stok-kutusu', CanliStokKutusu::class);
    }

    /**
     * Giriş ve çıkış olaylarını log hareketlerine yazar.
     */
    protected function girisCikisLogla(): void
    {
        Event::listen(Login::class, function (Login $event): void {
            activity('giris')
                ->causedBy($event->user)
                ->withProperties(['ip' => request()->ip()])
                ->log('Giriş yapıldı');
        });

        Event::listen(Logout::class, function (Logout $event): void {
            if ($event->user === null) {
                return;
            }

            activity('giris')
                ->causedBy($event->user)
                ->withProperties(['ip' => request()->ip()])
                ->log('Çıkış yapıldı');
        });
    }
}
