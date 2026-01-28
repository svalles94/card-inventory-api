<?php

namespace App\Filament\Store\Pages;

use App\Models\Card;
use App\Models\Edition;
use App\Models\CardPrice;
use App\Models\Inventory;
use App\Models\Location;
use Filament\Pages\Page;
use Filament\Notifications\Notification;
use Illuminate\Support\Collection;
use Livewire\WithPagination;

class ManageInventory extends Page
{
    use WithPagination;
    
    protected static ?string $navigationIcon = 'heroicon-o-cube';
    protected static ?string $navigationLabel = 'Manage Inventory';
    protected static ?string $title = 'Manage Inventory';
    protected static ?int $navigationSort = 1;
    protected static string $view = 'filament.store.pages.manage-inventory';
    
    // State
    public ?int $selectedLocationId = null;
    public string $selectedGame = 'grand-archive';
    public string $viewMode = 'inventory'; // 'inventory' or 'add'
    public string $displayMode = 'grid'; // 'grid', 'list', or 'table'
    public int $cardsPerPage = 40;
    public string $searchTerm = '';
    public array $filters = [
        'element' => null,
        'class' => null,
        'type' => null,
        'rarity' => null,
        'set' => null,
    ];
    
    // Modal state
    public bool $showCardModal = false;
    public ?string $modalCardId = null;
    public ?array $modalCardData = null;
    public ?array $modalEditions = null;
    public ?array $modalInventory = null;
    public int $quantityToAdd = 1;
    public int $quantityToRemove = 1;
    public bool $isFoil = false;
    public ?string $selectedEditionId = null;
    public ?float $customPrice = null;
    public ?float $marketPrice = null;
    
    // Pending changes tracking
    public array $pendingChanges = []; // ['card_id' => ['name' => 'Card Name', 'change' => +5]]
    public bool $showReviewModal = false;
    
    public function mount(): void
    {
        $firstLocation = Location::first();
        $this->selectedLocationId = $firstLocation?->id;
    }
    
    public function getLocationsProperty(): Collection
    {
        return Location::with('store')->get();
    }
    
    public function getGamesProperty(): array
    {
        return [
            'grand-archive' => 'Grand Archive',
            'gundam' => 'Gundam',
            'riftbound' => 'Riftbound',
        ];
    }
    
    public function getCardsProperty(): Collection
    {
        $query = Card::query()
            ->where('game', $this->selectedGame)
            ->with(['editions.prices', 'inventory' => function ($q) {
                if ($this->selectedLocationId) {
                    $q->where('location_id', $this->selectedLocationId);
                }
            }]);
        
        // Search
        if ($this->searchTerm) {
            $query->where(function ($q) {
                $q->where('name', 'like', '%' . $this->searchTerm . '%')
                  ->orWhere('card_number', 'like', '%' . $this->searchTerm . '%')
                  ->orWhere('set_code', 'like', '%' . $this->searchTerm . '%');
            });
        }
        
        // Filters
        if ($this->filters['element']) {
            $query->whereJsonContains('elements', $this->filters['element']);
        }
        if ($this->filters['class']) {
            $query->whereJsonContains('classes', $this->filters['class']);
        }
        if ($this->filters['type']) {
            $query->whereJsonContains('types', $this->filters['type']);
        }
        if ($this->filters['rarity']) {
            $query->where('rarity', $this->filters['rarity']);
        }
        if ($this->filters['set']) {
            $query->where('set_code', $this->filters['set']);
        }
        
        // Filter by view mode - only filter when viewing inventory
        if ($this->viewMode === 'inventory') {
            if ($this->selectedLocationId) {
                $query->whereHas('inventory', function ($q) {
                    $q->where('location_id', $this->selectedLocationId)
                      ->where('quantity', '>', 0);
                });
            } else {
                // If no location selected and in inventory mode, show nothing
                return collect([]);
            }
        }
        
        return $query->orderBy('name')->limit($this->cardsPerPage)->get();
    }
    
    public function getFilterOptionsProperty(): array
    {
        $cards = Card::where('game', $this->selectedGame)->get();
        
        return [
            'elements' => $cards->pluck('elements')->flatten()->unique()->filter()->sort()->values()->toArray(),
            'classes' => $cards->pluck('classes')->flatten()->unique()->filter()->sort()->values()->toArray(),
            'types' => $cards->pluck('types')->flatten()->unique()->filter()->sort()->values()->toArray(),
            'sets' => $cards->pluck('set_code')->unique()->filter()->sort()->values()->toArray(),
            'rarities' => [
                1 => 'Common',
                2 => 'Uncommon',
                3 => 'Rare',
                4 => 'Super Rare',
            ],
        ];
    }
    
    public function selectLocation(int $locationId): void
    {
        $this->selectedLocationId = $locationId;
        $this->resetPage();
    }
    
    public function selectGame(string $game): void
    {
        $this->selectedGame = $game;
        $this->clearFilters();
        $this->resetPage();
    }
    
    public function setViewMode(string $mode): void
    {
        $this->viewMode = $mode;
        $this->resetPage();
    }
    
    public function setDisplayMode(string $mode): void
    {
        $this->displayMode = $mode;
    }
    
    public function updateCardsPerPage(int $perPage): void
    {
        $this->cardsPerPage = min(max($perPage, 5), 250);
        $this->resetPage();
    }
    
    public function quickUpdateInventory(string $cardId, string $action, bool $isFoil = false): void
    {
        if (!$this->selectedLocationId) {
            Notification::make()
                ->title('Please select a location')
                ->danger()
                ->send();
            return;
        }
        
        $inventory = Inventory::where('location_id', $this->selectedLocationId)
            ->where('card_id', $cardId)
            ->where('is_foil', $isFoil)
            ->first();
        
        $currentQty = $inventory?->quantity ?? 0;
        
        $newQty = match($action) {
            'add' => $currentQty + 1,
            'remove' => max(0, $currentQty - 1),
            'add5' => $currentQty + 5,
            'remove5' => max(0, $currentQty - 5),
            default => $currentQty,
        };
        
        Inventory::updateOrCreate(
            [
                'location_id' => $this->selectedLocationId,
                'card_id' => $cardId,
                'is_foil' => $isFoil,
            ],
            [
                'quantity' => $newQty,
            ]
        );
    }
    
    public function clearFilters(): void
    {
        $this->filters = [
            'element' => null,
            'class' => null,
            'type' => null,
            'rarity' => null,
            'set' => null,
        ];
        $this->searchTerm = '';
    }
    
    public function openCardModal(string $cardId): void
    {
        $card = Card::with(['editions.prices', 'inventory' => function ($q) {
            if ($this->selectedLocationId) {
                $q->where('location_id', $this->selectedLocationId);
            }
        }])->find($cardId);
        
        if (!$card) {
            Notification::make()
                ->title('Card not found')
                ->danger()
                ->send();
            return;
        }
        
        $this->modalCardId = $cardId;
        $this->modalCardData = $card->toArray();
        
        // Safely load editions with prices (handle if prices don't exist yet)
        $editions = $card->editions()->get();
        if ($editions->isNotEmpty()) {
            $editions->load('prices');
        }
        $this->modalEditions = $editions->toArray();
        
        $this->modalInventory = $card->inventory->toArray();
        
        // Set default edition to first one
        if (!empty($this->modalEditions)) {
            $this->selectedEditionId = $this->modalEditions[0]['id'] ?? null;
        }
        
        // Load sell price if exists
        $inventory = $card->inventory->first();
        $this->customPrice = $inventory?->sell_price;
        
        // Calculate initial market price
        $this->updateMarketPrice();
        
        $this->quantityToAdd = 1;
        $this->quantityToRemove = 1;
        $this->isFoil = false;
        $this->showCardModal = true;
    }
    
    public function closeCardModal(): void
    {
        $this->showCardModal = false;
        $this->modalCardId = null;
        $this->modalCardData = null;
        $this->modalEditions = null;
        $this->modalInventory = null;
        $this->isFoil = false;
        $this->selectedEditionId = null;
        $this->customPrice = null;
        $this->marketPrice = null;
    }
    
    public function setCustomPrice(?float $price): void
    {
        $this->customPrice = $price;
    }
    
    /**
     * Update market price based on current foil status and selected edition
     */
    public function updateMarketPrice(): void
    {
        $this->marketPrice = null;
        
        if (!$this->modalCardId || empty($this->modalEditions)) {
            return;
        }
        
        $card = Card::find($this->modalCardId);
        if (!$card) {
            return;
        }
        
        // Try to get price from selected edition, or first edition if none selected
        $editionId = $this->selectedEditionId;
        if (!$editionId && !empty($this->modalEditions)) {
            $editionId = $this->modalEditions[0]['id'] ?? null;
        }
        
        if ($editionId) {
            $edition = \App\Models\Edition::find($editionId);
            if ($edition) {
                // Try to get price from card_prices matching foil status
                $priceQuery = $edition->cardPrices()
                    ->whereNotNull('market_price')
                    ->orderByDesc('updated_at');
                
                // Filter by foil status
                if ($this->isFoil) {
                    $priceQuery->where('sub_type_name', 'like', '%foil%');
                } else {
                    $priceQuery->where('sub_type_name', 'not like', '%foil%');
                }
                
                $cardPrice = $priceQuery->first();
                if ($cardPrice) {
                    $this->marketPrice = $cardPrice->market_price;
                    return;
                }
                
                // Fallback to edition's market_price if no card_prices match
                if ($edition->market_price !== null) {
                    $this->marketPrice = $edition->market_price;
                    return;
                }
            }
        }
        
        // Fallback to card-level prices
        $cardPriceQuery = $card->cardPrices()
            ->whereNotNull('market_price')
            ->orderByDesc('updated_at');
        
        if ($this->isFoil) {
            $cardPriceQuery->where('sub_type_name', 'like', '%foil%');
        } else {
            $cardPriceQuery->where('sub_type_name', 'not like', '%foil%');
        }
        
        $cardPrice = $cardPriceQuery->first();
        if ($cardPrice) {
            $this->marketPrice = $cardPrice->market_price;
        }
    }
    
    /**
     * Toggle foil status and update market price
     */
    public function updatedIsFoil(): void
    {
        $this->updateMarketPrice();
        
        // Also update custom price if it was based on market price
        if ($this->marketPrice && $this->customPrice) {
            // Optionally keep the same percentage adjustment
            // Or just reset to market price
            // For now, we'll leave custom price as is
        }
    }
    
    /**
     * Set selected edition and update market price
     */
    public function setSelectedEdition(?string $editionId): void
    {
        $this->selectedEditionId = $editionId;
        $this->updateMarketPrice();
    }
    
    /**
     * Update market price when edition changes
     */
    public function updatedSelectedEditionId(): void
    {
        $this->updateMarketPrice();
    }
    
    public function applyPricePercentage(int $percentage): void
    {
        if ($this->marketPrice) {
            $multiplier = 1 + ($percentage / 100);
            $this->customPrice = round($this->marketPrice * $multiplier, 2);
        } else {
            Notification::make()
                ->title('No market price available')
                ->warning()
                ->send();
        }
    }
    
    public function updateInventory(string $cardId, int $quantity): void
    {
        if (!$this->selectedLocationId) {
            Notification::make()
                ->title('Please select a location')
                ->danger()
                ->send();
            return;
        }
        
        $inventory = Inventory::updateOrCreate(
            [
                'location_id' => $this->selectedLocationId,
                'card_id' => $cardId,
            ],
            [
                'quantity' => max(0, $quantity),
            ]
        );
        
        Notification::make()
            ->title('Inventory updated')
            ->success()
            ->send();
        
        $this->closeCardModal();
    }
    
    public function addToInventory(): void
    {
        if (!$this->selectedLocationId || !$this->modalCardId) {
            Notification::make()
                ->title('Invalid request')
                ->danger()
                ->send();
            return;
        }
        
        // Track pending change with foil status as part of the key
        $cardId = $this->modalCardId;
        $cardName = $this->modalCardData['name'] ?? 'Unknown Card';
        $changeKey = $cardId . ($this->isFoil ? '_foil' : '_normal');
        
        if (!isset($this->pendingChanges[$changeKey])) {
            $this->pendingChanges[$changeKey] = [
                'card_id' => $cardId,
                'name' => $cardName,
                'is_foil' => $this->isFoil,
                'change' => 0,
            ];
        }
        
        $this->pendingChanges[$changeKey]['change'] += $this->quantityToAdd;
        
        // Include sell price if set
        if ($this->customPrice !== null) {
            $this->pendingChanges[$changeKey]['sell_price'] = $this->customPrice;
        }
        
        // Force Livewire to detect the change
        $this->pendingChanges = $this->pendingChanges;
        
        $foilText = $this->isFoil ? ' (Foil)' : '';
        $notification = Notification::make()
            ->title("Queued +{$this->quantityToAdd} to {$cardName}{$foilText}")
            ->body("Total pending: " . count($this->pendingChanges) . " changes")
            ->success();
            
        if ($this->customPrice !== null) {
            $notification->body("Total pending: " . count($this->pendingChanges) . " changes | Price: $" . number_format($this->customPrice, 2));
        }
        
        $notification->send();
        
        $this->closeCardModal();
    }
    
    public function removeFromInventory(): void
    {
        if (!$this->selectedLocationId || !$this->modalCardId) {
            Notification::make()
                ->title('Invalid request')
                ->danger()
                ->send();
            return;
        }
        
        // Track pending change with foil status as part of the key
        $cardId = $this->modalCardId;
        $cardName = $this->modalCardData['name'] ?? 'Unknown Card';
        $changeKey = $cardId . ($this->isFoil ? '_foil' : '_normal');
        
        if (!isset($this->pendingChanges[$changeKey])) {
            $this->pendingChanges[$changeKey] = [
                'card_id' => $cardId,
                'name' => $cardName,
                'is_foil' => $this->isFoil,
                'change' => 0,
            ];
        }
        
        $this->pendingChanges[$changeKey]['change'] -= $this->quantityToRemove;
        
        // Force Livewire to detect the change
        $this->pendingChanges = $this->pendingChanges;
        
        $foilText = $this->isFoil ? ' (Foil)' : '';
        Notification::make()
            ->title("Queued -{$this->quantityToRemove} from {$cardName}{$foilText}")
            ->body("Total pending: " . count($this->pendingChanges) . " changes")
            ->warning()
            ->send();
        
        $this->closeCardModal();
    }
    
    public function openReviewModal(): void
    {
        if (empty($this->pendingChanges)) {
            Notification::make()
                ->title('No changes to review')
                ->warning()
                ->send();
            return;
        }
        
        $this->showReviewModal = true;
    }
    
    public function closeReviewModal(): void
    {
        $this->showReviewModal = false;
        $this->dispatch('$refresh');
    }
    
    public function applyPendingChanges(): void
    {
        if (!$this->selectedLocationId) {
            Notification::make()
                ->title('Please select a location')
                ->danger()
                ->send();
            return;
        }
        
        $updatedCount = 0;
        
        foreach ($this->pendingChanges as $changeKey => $change) {
            $cardId = $change['card_id'];
            $isFoil = $change['is_foil'] ?? false;
            
            $inventory = Inventory::where('location_id', $this->selectedLocationId)
                ->where('card_id', $cardId)
                ->where('is_foil', $isFoil)
                ->first();
            
            $currentQty = $inventory?->quantity ?? 0;
            $newQty = max(0, $currentQty + $change['change']);
            
            $updateData = [
                'quantity' => $newQty,
            ];
            
            // Include sell price if it was set
            if (isset($change['sell_price'])) {
                $updateData['sell_price'] = $change['sell_price'];
            }
            
            Inventory::updateOrCreate(
                [
                    'location_id' => $this->selectedLocationId,
                    'card_id' => $cardId,
                    'is_foil' => $isFoil,
                ],
                $updateData
            );
            
            $updatedCount++;
        }
        
        Notification::make()
            ->title("Updated {$updatedCount} card(s) in inventory")
            ->success()
            ->send();
        
        $this->pendingChanges = [];
        $this->showReviewModal = false;
        $this->dispatch('$refresh');
    }
    
    public function cancelPendingChanges(): void
    {
        $this->pendingChanges = [];
        $this->showReviewModal = false;
        
        Notification::make()
            ->title('Changes cancelled')
            ->warning()
            ->send();
            
        $this->dispatch('$refresh');
    }
}
