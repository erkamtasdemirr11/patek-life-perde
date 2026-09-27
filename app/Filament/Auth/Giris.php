<?php

namespace App\Filament\Auth;

use Filament\Auth\Pages\Login;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Component;
use Illuminate\Validation\ValidationException;
use SensitiveParameter;

/**
 * Panel girişi: kullanıcı adı (users.name) veya e-posta ile.
 * Girilen değerde @ varsa e-posta, yoksa kullanıcı adı olarak aranır.
 */
class Giris extends Login
{
    protected function getEmailFormComponent(): Component
    {
        return TextInput::make('giris')
            ->label('Kullanıcı adı veya e-posta')
            ->required()
            ->autocomplete('username')
            ->autofocus();
    }

    /** @return array<string, mixed> */
    protected function getCredentialsFromFormData(#[SensitiveParameter] array $data): array
    {
        $giris = trim((string) $data['giris']);

        return [
            (str_contains($giris, '@') ? 'email' : 'name') => $giris,
            'password' => $data['password'],
        ];
    }

    protected function throwFailureValidationException(): never
    {
        throw ValidationException::withMessages([
            'data.giris' => __('filament-panels::auth/pages/login.messages.failed'),
        ]);
    }
}
