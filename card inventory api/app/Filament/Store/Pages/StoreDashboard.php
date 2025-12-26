<?php

namespace App\Filament\Store\Pages;

use Filament\Pages\Dashboard as BaseDashboard;
use Filament\Widgets\Widget;
use Illuminate\Support\Facades\Auth;

class StoreDashboard extends BaseDashboard
{
    protected static ?string $navigationIcon = 'heroicon-o-home';

    protected static string $view = 'filament.store.pages.dashboard';

    protected function getHeaderWidgets(): array
    {
        return [
            \App\Filament\Store\Widgets\StoreSwitcherWidget::class,
        ];
    }

    public function getHeading(): string
    {
        $store = Auth::user()->currentStore();
        return $store ? "{$store->name} Dashboard" : 'Store Dashboard';
    }

    public function getSubheading(): ?string
    {
        $store = Auth::user()->currentStore();
        if (!$store) {
            return 'No store selected';
        }

        $locationCount = $store->locations()->count();
        $inventoryCount = \App\Models\Inventory::whereHas('location', function ($q) use ($store) {
            $q->where('store_id', $store->id);
        })->sum('quantity');

        return "{$locationCount} location(s) • {$inventoryCount} total inventory items";
    }
}

