<?php

namespace App\Filament\Store\Resources\StoreMarketplaceIntegrationResource\Pages;

use App\Filament\Store\Resources\StoreMarketplaceIntegrationResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Auth;

class CreateStoreMarketplaceIntegration extends CreateRecord
{
    protected static string $resource = StoreMarketplaceIntegrationResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Automatically set the store_id to the current store
        $currentStore = Auth::user()->currentStore();
        if ($currentStore) {
            $data['store_id'] = $currentStore->id;
        }

        return $data;
    }

    protected function authorizeAccess(): void
    {
        $currentStore = Auth::user()->currentStore();
        if (!$currentStore) {
            abort(403, 'No store selected');
        }

        abort_unless(
            Auth::user()->can('manageIntegrations', $currentStore),
            403,
            'You do not have permission to create marketplace integrations.'
        );
    }
}

