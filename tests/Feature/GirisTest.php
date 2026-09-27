<?php

use App\Filament\Auth\Giris;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->kullanici = User::factory()->create([
        'name' => 'Yonetici',
        'email' => 'yonetici@example.com',
        'password' => 'test-sifresi',
    ]);
});

it('panele kullanıcı adıyla giriş yapılır', function () {
    Livewire::test(Giris::class)
        ->fillForm(['giris' => 'Yonetici', 'password' => 'test-sifresi'])
        ->call('authenticate')
        ->assertHasNoFormErrors();

    $this->assertAuthenticatedAs($this->kullanici);
});

it('panele e-posta ile de giriş yapılır', function () {
    Livewire::test(Giris::class)
        ->fillForm(['giris' => 'yonetici@example.com', 'password' => 'test-sifresi'])
        ->call('authenticate')
        ->assertHasNoFormErrors();

    $this->assertAuthenticatedAs($this->kullanici);
});

it('yanlış şifreyle giriş reddedilir', function () {
    Livewire::test(Giris::class)
        ->fillForm(['giris' => 'Yonetici', 'password' => 'yanlis-sifre'])
        ->call('authenticate')
        ->assertHasFormErrors(['giris']);

    $this->assertGuest();
});
