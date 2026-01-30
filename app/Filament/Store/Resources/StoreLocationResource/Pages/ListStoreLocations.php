<?php

namespace App\Filament\Store\Resources\StoreLocationResource\Pages;

use App\Filament\Store\Resources\StoreLocationResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListStoreLocations extends ListRecords
{
    protected static string $resource = StoreLocationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->authorize(function () {
                    $currentStore = auth()->user()->currentStore();
                    if (!$currentStore) {
                        return false;
                    }
                    return auth()->user()->can('create', \App\Models\Location::class);
                }),
        ];
    }
}

