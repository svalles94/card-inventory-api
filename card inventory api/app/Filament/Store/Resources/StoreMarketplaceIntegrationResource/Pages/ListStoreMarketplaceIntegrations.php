<?php

namespace App\Filament\Store\Resources\StoreMarketplaceIntegrationResource\Pages;

use App\Filament\Store\Resources\StoreMarketplaceIntegrationResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Facades\Auth;

class ListStoreMarketplaceIntegrations extends ListRecords
{
    protected static string $resource = StoreMarketplaceIntegrationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->authorize(function () {
                    $currentStore = Auth::user()->currentStore();
                    if (!$currentStore) {
                        return false;
                    }
                    return Auth::user()->can('manageIntegrations', $currentStore);
                }),
        ];
    }
}

