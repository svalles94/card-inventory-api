<?php

namespace App\Filament\Store\Resources\StoreCardResource\Pages;

use App\Filament\Store\Resources\StoreCardResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Support\Facades\Auth;
use App\Models\Edition;

class ViewStoreCard extends ViewRecord
{
    protected static string $resource = StoreCardResource::class;
    
    protected static string $view = 'filament.store.resources.store-card-resource.pages.view-store-card';
    
    public ?string $selectedEditionId = null;

    protected function getHeaderActions(): array
    {
        $currentStore = Auth::user()->currentStore();
        $user = Auth::user();
        
        return [
            Actions\Action::make('add_to_inventory')
                ->label('Add to Inventory')
                ->icon('heroicon-o-plus-circle')
                ->color('success')
                ->url(function () {
                    return \App\Filament\Store\Resources\StoreInventoryResource::getUrl('create', [
                        'card_id' => $this->record->id,
                    ]);
                })
                ->visible(function () use ($currentStore, $user) {
                    if (!$currentStore) return false;
                    return $user->hasRole($currentStore, 'owner') || $user->hasRole($currentStore, 'admin');
                }),
        ];
    }
    
    public function mount(int | string $record): void
    {
        parent::mount($record);
        
        // Set default edition to the latest one
        $defaultEdition = $this->record->editions()
            ->orderByDesc('last_update')
            ->value('id');
        $this->selectedEditionId = $defaultEdition;
    }
    
    public function getFoilPrice(): ?float
    {
        if (!$this->selectedEditionId) {
            // Fallback to card-level foil price
            return $this->record->cardPrices()
                ->whereNotNull('market_price')
                ->where('sub_type_name', 'like', '%foil%')
                ->orderByDesc('updated_at')
                ->first()?->market_price;
        }
        
        $edition = Edition::find($this->selectedEditionId);
        if (!$edition) {
            return null;
        }
        
        $price = $edition->cardPrices()
            ->whereNotNull('market_price')
            ->where('sub_type_name', 'like', '%foil%')
            ->orderByDesc('updated_at')
            ->first();
        
        if ($price) {
            return $price->market_price;
        }
        
        // Fallback to edition market_price
        return $edition->market_price;
    }
    
    public function getNonFoilPrice(): ?float
    {
        if (!$this->selectedEditionId) {
            // Fallback to card-level non-foil price
            return $this->record->cardPrices()
                ->whereNotNull('market_price')
                ->where('sub_type_name', 'not like', '%foil%')
                ->orderByDesc('updated_at')
                ->first()?->market_price;
        }
        
        $edition = Edition::find($this->selectedEditionId);
        if (!$edition) {
            return null;
        }
        
        $price = $edition->cardPrices()
            ->whereNotNull('market_price')
            ->where('sub_type_name', 'not like', '%foil%')
            ->orderByDesc('updated_at')
            ->first();
        
        if ($price) {
            return $price->market_price;
        }
        
        // Fallback to edition market_price
        return $edition->market_price;
    }
    
    public function getAvailableEditions()
    {
        return $this->record->editions()
            ->orderByDesc('last_update')
            ->orderByDesc('created_at')
            ->get();
    }
    
    public function getEditionLabel(Edition $edition): string
    {
        $rarityText = match ($edition->rarity) {
            1 => 'Common',
            2 => 'Uncommon',
            3 => 'Rare',
            4 => 'Super Rare',
            default => '—',
        };

        $label = trim(implode(' · ', array_filter([
            $edition->slug,
            $edition->collector_number,
            $rarityText,
        ])));

        return $label ?: $edition->id;
    }
    
    public function getEditionFoilPrice(Edition $edition): ?float
    {
        $price = $edition->cardPrices()
            ->whereNotNull('market_price')
            ->where('sub_type_name', 'like', '%foil%')
            ->orderByDesc('updated_at')
            ->first();
        
        if ($price) {
            return $price->market_price;
        }
        
        // Fallback to edition market_price
        return $edition->market_price;
    }
    
    public function getEditionNonFoilPrice(Edition $edition): ?float
    {
        $price = $edition->cardPrices()
            ->whereNotNull('market_price')
            ->where('sub_type_name', 'not like', '%foil%')
            ->orderByDesc('updated_at')
            ->first();
        
        if ($price) {
            return $price->market_price;
        }
        
        // Fallback to edition market_price
        return $edition->market_price;
    }
}

