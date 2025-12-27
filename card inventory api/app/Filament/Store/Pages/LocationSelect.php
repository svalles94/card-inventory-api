<?php

namespace App\Filament\Store\Pages;

use Filament\Facades\Filament;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;

class LocationSelect extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-map-pin';

    protected static string $view = 'filament.store.pages.location-select';

    protected static ?string $navigationGroup = 'Locations';

    protected static ?string $navigationLabel = 'Switch Location';

    protected static ?int $navigationSort = 0;

    protected static bool $shouldRegisterNavigation = false;

    public function mount(): void
    {
        if (! Auth::check()) {
            return;
        }

        if (! Auth::user()->currentStore()) {
            $this->redirect(route('filament.store.pages.store-select'));
        }
    }

    public function getHeading(): string
    {
        return 'Select Location';
    }

    public function getSubheading(): ?string
    {
        $store = Auth::user()?->currentStore();
        $count = $store?->locations()->count() ?? 0;

        if (! $store) {
            return null;
        }

        return "{$store->name} has {$count} location(s). Pick one to work in.";
    }

    public function switchLocation(int $locationId): void
    {
        $user = Auth::user();
        $store = $user?->currentStore();

        if (! $store) {
            $this->redirect(route('filament.store.pages.store-select'));

            return;
        }

        $location = $store->locations()->find($locationId);

        if (! $location) {
            $this->dispatch('notify', [
                'type' => 'error',
                'message' => 'Location not found for this store.',
            ]);

            return;
        }

        Session::put('current_location_id', $location->id);

        $this->redirect(Filament::getPanel('store')->getUrl());
    }
}
