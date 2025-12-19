<?php

namespace App\Filament\Resources\MarketplaceIntegrationResource\Pages;

use App\Filament\Resources\MarketplaceIntegrationResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditMarketplaceIntegration extends EditRecord
{
    protected static string $resource = MarketplaceIntegrationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
