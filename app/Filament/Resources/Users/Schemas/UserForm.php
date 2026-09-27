<?php

namespace App\Filament\Resources\Users\Schemas;

use App\Models\User;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label('Ad Soyad')
                    ->required()
                    ->maxLength(255),
                TextInput::make('email')
                    ->label('E-posta')
                    ->email()
                    ->required()
                    ->unique(ignoreRecord: true)
                    ->maxLength(255),
                TextInput::make('password')
                    ->label('Parola')
                    ->password()
                    ->revealable()
                    ->required(fn (string $operation): bool => $operation === 'create')
                    ->dehydrated(fn (?string $state): bool => filled($state))
                    ->minLength(8)
                    ->helperText(fn (string $operation): ?string => $operation === 'edit' ? 'Değiştirmek istemiyorsanız boş bırakın.' : null),
                Toggle::make('aktif')
                    ->label('Aktif')
                    ->helperText('Pasif kullanıcı panele giriş yapamaz.')
                    ->default(true)
                    ->disabled(fn (?User $record): bool => $record !== null && $record->is(auth()->user())),
            ]);
    }
}
