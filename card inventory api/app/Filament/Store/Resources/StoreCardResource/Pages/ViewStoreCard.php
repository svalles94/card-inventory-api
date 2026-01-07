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
    
    protected $queryString = [
        'selectedEditionId' => ['except' => null],
    ];

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
    
    public function updatedSelectedEditionId($value): void
    {
        // This will trigger a re-render to show orientations for the selected edition
        // The form will automatically refresh because of the key() method on the Placeholder
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
    
    public function getCardOrientations()
    {
        // Try selected edition first, then fall back to latest edition, then card
        $edition = null;
        if ($this->selectedEditionId) {
            $edition = Edition::find($this->selectedEditionId);
        }
        
        if (!$edition) {
            $edition = $this->record->editions()
                ->orderByDesc('last_update')
                ->first();
        }
        
        if (!$edition) {
            return [];
        }
        
        $orientations = [];
        
        // Add primary orientation (main card image)
        $primaryImage = $edition->image_url;
        if (!$primaryImage) {
            // Fallback to card image
            $primaryImage = $this->record->image_url;
        }
        
        if ($primaryImage) {
            $orientations[] = [
                'name' => $edition->orientation ?? 'Front',
                'image' => $primaryImage,
                'is_primary' => true,
            ];
        }
        
        // Add other orientations (flip sides)
        if ($edition->other_orientations && is_array($edition->other_orientations)) {
            foreach ($edition->other_orientations as $orientationData) {
                if (isset($orientationData['edition']['image'])) {
                    $imageUrl = $orientationData['edition']['image'];
                    // Convert to full URL if needed
                    if (!filter_var($imageUrl, FILTER_VALIDATE_URL)) {
                        $baseUrl = rtrim('https://api.gatcg.com', '/');
                        $imagePath = ltrim($imageUrl, '/');
                        $imageUrl = $baseUrl . '/' . $imagePath;
                    }
                    
                    $orientations[] = [
                        'name' => $orientationData['name'] ?? 'Flip Side',
                        'image' => $imageUrl,
                        'is_primary' => false,
                    ];
                }
            }
        }
        
        return $orientations;
    }
}

