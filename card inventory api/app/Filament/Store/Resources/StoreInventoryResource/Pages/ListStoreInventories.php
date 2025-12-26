<?php

namespace App\Filament\Store\Resources\StoreInventoryResource\Pages;

use App\Filament\Store\Resources\StoreInventoryResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListStoreInventories extends ListRecords
{
    protected static string $resource = StoreInventoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->authorize(function () {
                    return auth()->user()->can('create', \App\Models\Inventory::class);
                }),
        ];
    }
}

