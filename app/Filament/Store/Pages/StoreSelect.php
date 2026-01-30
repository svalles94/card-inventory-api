<?php

namespace App\Filament\Store\Pages;

use Filament\Pages\Page;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;

class StoreSelect extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-building-storefront';

    protected static string $view = 'filament.store.pages.store-select';

    protected static bool $shouldRegisterNavigation = false;

    public function getHeading(): string
    {
        return 'Select Store';
    }

    public function getSubheading(): ?string
    {
        $storeCount = Auth::user()->stores()->count();
        return "You have access to {$storeCount} store(s). Select one to continue.";
    }

    public function switchStore($storeId): void
    {
        $user = Auth::user();
        $store = \App\Models\Store::find($storeId);

        if (!$store) {
            $this->dispatch('notify', [
                'type' => 'error',
                'message' => 'Store not found.',
            ]);
            return;
        }

        if (!$user->canAccessStore($store)) {
            $this->dispatch('notify', [
                'type' => 'error',
                'message' => 'You do not have access to this store.',
            ]);
            return;
        }

        // Set current store in session
        Session::put('current_store_id', $store->id);

        // Redirect to dashboard
        $this->redirect(\Filament\Facades\Filament::getPanel('store')->getUrl());
    }
}

