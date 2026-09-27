<?php

use Illuminate\Support\Facades\Route;

// Tanıtım sitesi ve herkese açık ürün kataloğu
Route::get('/', [\App\Http\Controllers\SiteController::class, 'anasayfa'])->name('site.anasayfa');
Route::get('/urunler', [\App\Http\Controllers\SiteController::class, 'katalog'])->name('site.katalog');

// Fiş PDF'i: yalnızca panele giriş yapmış kullanıcılar açabilir.
Route::get('/panel/fisler/{fis}/pdf', \App\Http\Controllers\FisPdfController::class)
    ->middleware(\Filament\Http\Middleware\Authenticate::class)
    ->name('fis.pdf');

// Müşteri ekstresi PDF'i: silinmiş müşteri için de alınabilir.
Route::get('/panel/musteriler/{musteri}/ekstre', \App\Http\Controllers\MusteriEkstreController::class)
    ->withTrashed()
    ->middleware(\Filament\Http\Middleware\Authenticate::class)
    ->name('musteri.ekstre');

// Arama motorları için robots.txt ve sitemap.xml (statik dosya yok, içerik veritabanından üretilir)
Route::get('/robots.txt', [\App\Http\Controllers\SiteController::class, 'robots'])->name('site.robots');
Route::get('/sitemap.xml', [\App\Http\Controllers\SiteController::class, 'sitemap'])->name('site.sitemap');
