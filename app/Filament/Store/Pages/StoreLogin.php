<?php

namespace App\Filament\Store\Pages;

use Filament\Forms\Components\TextInput;
use Filament\Pages\Auth\Login;

class StoreLogin extends Login
{
    protected static string $view = 'filament.store.pages.login';

    public function mount(): void
    {
        if (filament()->auth()->check()) {
            $user = filament()->auth()->user();
            
            // If user is platform admin, redirect to admin panel
            if ($user->isPlatformAdmin()) {
                $this->redirect(filament('admin')->getUrl());
                return;
            }
            
            // If user has stores, redirect to store panel
            if ($user->stores()->count() > 0) {
                $this->redirect(filament()->getUrl());
                return;
            }
            
            // User has no stores - redirect to registration
            $this->redirect(route('store.register'));
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

