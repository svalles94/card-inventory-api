<?php

namespace App\Filament\Store\Resources\StoreLocationResource\Pages;

use App\Filament\Store\Resources\StoreLocationResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Auth;

class CreateStoreLocation extends CreateRecord
{
    protected static string $resource = StoreLocationResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Automatically set the store_id to the current store
        $currentStore = Auth::user()->currentStore();
        if ($currentStore) {
            $data['store_id'] = $currentStore->id;
        }

        return $data;
    }
}

