<?php

namespace App\Filament\Store\Resources\StoreUserResource\Pages;

use App\Filament\Store\Resources\StoreUserResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Facades\Auth;

class ListStoreUsers extends ListRecords
{
    protected static string $resource = StoreUserResource::class;

    protected function getHeaderActions(): array
    {
        $currentStore = Auth::user()->currentStore();
        
        return [
            Actions\CreateAction::make()
                ->authorize(function () use ($currentStore) {
                    if (!$currentStore) {
                        return false;
                    }
                    // Only owners can add users
                    return Auth::user()->hasRole($currentStore, 'owner');
                }),
        ];
    }
}

