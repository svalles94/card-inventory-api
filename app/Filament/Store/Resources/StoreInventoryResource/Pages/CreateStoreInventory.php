<?php

namespace App\Filament\Store\Resources\StoreInventoryResource\Pages;

use App\Filament\Store\Resources\StoreInventoryResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Auth;

class CreateStoreInventory extends CreateRecord
{
    protected static string $resource = StoreInventoryResource::class;

    public function mount(): void
    {
        parent::mount();
        
        // Pre-fill card_id if provided in query string
        $cardId = request()->query('card_id');
        if ($cardId) {
            $market = StoreInventoryResource::getLatestMarketPrice($cardId);
            $this->form->fill([
                'card_id' => $cardId,
                'market_price' => $market,
            ]);
        }
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Ensure the location belongs to the current store
        $currentStore = Auth::user()->currentStore();
        if ($currentStore) {
            // Verify location belongs to current store
            $location = \App\Models\Location::find($data['location_id'] ?? null);
            if ($location && $location->store_id !== $currentStore->id) {
                throw new \Exception('Location does not belong to your store.');
            }
        }

        return $data;
    }
}

