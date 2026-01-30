<?php

namespace App\Filament\Resources\MarketplaceIntegrationResource\Pages;

use App\Filament\Resources\MarketplaceIntegrationResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListMarketplaceIntegrations extends ListRecords
{
    protected static string $resource = MarketplaceIntegrationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
