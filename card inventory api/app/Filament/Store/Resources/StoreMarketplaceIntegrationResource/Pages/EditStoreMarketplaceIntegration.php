<?php

namespace App\Filament\Store\Resources\StoreMarketplaceIntegrationResource\Pages;

use App\Filament\Store\Resources\StoreMarketplaceIntegrationResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Auth;

class EditStoreMarketplaceIntegration extends EditRecord
{
    protected static string $resource = StoreMarketplaceIntegrationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make()
                ->authorize(function () {
                    $currentStore = Auth::user()->currentStore();
                    if (!$currentStore) {
                        return false;
                    }
                    return Auth::user()->can('manageIntegrations', $currentStore);
                }),
        ];
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
            'You do not have permission to edit marketplace integrations.'
        );
    }
}

