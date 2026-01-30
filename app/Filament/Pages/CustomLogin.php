<?php

namespace App\Filament\Pages;

use Filament\Forms\Components\TextInput;
use Filament\Pages\Auth\Login;

class CustomLogin extends Login
{
    protected static string $view = 'filament.pages.custom-login';

    public function mount(): void
    {
        if (filament()->auth()->check()) {
            $this->redirect(filament()->getUrl());
            return;
        }
        
        parent::mount();
    }

    protected function getForms(): array
    {
        return [
            'form' => $this->form(
                $this->makeForm()
                    ->schema([
                        TextInput::make('email')
                            ->label('Email Address')
                            ->email()
                            ->required()
                            ->autocomplete()
                            ->autofocus()
                            ->extraInputAttributes(['tabindex' => 1])
                            ->placeholder('Enter your email'),
                        TextInput::make('password')
                            ->label('Password')
                            ->password()
                            ->required()
                            ->extraInputAttributes(['tabindex' => 2])
                            ->placeholder('Enter your password'),
                    ])
                    ->statePath('data'),
            ),
        ];
    }

    public function getCachedSubNavigation(): array
    {
        return [];
    }
}

