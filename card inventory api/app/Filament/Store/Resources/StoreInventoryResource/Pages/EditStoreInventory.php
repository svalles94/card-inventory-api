<?php

namespace App\Filament\Store\Resources\StoreInventoryResource\Pages;

use App\Filament\Store\Resources\StoreInventoryResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditStoreInventory extends EditRecord
{
    protected static string $resource = StoreInventoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}

