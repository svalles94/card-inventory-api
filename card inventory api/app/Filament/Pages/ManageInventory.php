<?php

namespace App\Filament\Pages;

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
    protected static string $view = 'filament.pages.manage-inventory';
    
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
    
    public function quickUpdateInventory(string $cardId, string $action): void
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
        
        // Load custom price if exists
        $inventory = $card->inventory->first();
        $this->customPrice = $inventory?->custom_price;
        
        // Get market price from first edition's first price
        $this->marketPrice = null;
        if (!empty($this->modalEditions)) {
            $firstEdition = $this->modalEditions[0];
            if (!empty($firstEdition['prices'])) {
                $this->marketPrice = $firstEdition['prices'][0]['market_price'] ?? null;
            }
        }
        
        $this->quantityToAdd = 1;
        $this->showCardModal = true;
    }
    
    public function closeCardModal(): void
    {
        $this->showCardModal = false;
        $this->modalCardId = null;
        $this->modalCardData = null;
        $this->modalEditions = null;
        $this->modalInventory = null;
        $this->customPrice = null;
        $this->marketPrice = null;
    }
    
    public function setCustomPrice(?float $price): void
    {
        $this->customPrice = $price;
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
        
        // Track pending change
        $cardId = $this->modalCardId;
        $cardName = $this->modalCardData['name'] ?? 'Unknown Card';
        
        if (!isset($this->pendingChanges[$cardId])) {
            $this->pendingChanges[$cardId] = [
                'name' => $cardName,
                'change' => 0,
            ];
        }
        
        $this->pendingChanges[$cardId]['change'] += $this->quantityToAdd;
        
        // Include custom price if set
        if ($this->customPrice !== null) {
            $this->pendingChanges[$cardId]['custom_price'] = $this->customPrice;
        }
        
        // Force Livewire to detect the change
        $this->pendingChanges = $this->pendingChanges;
        
        $notification = Notification::make()
            ->title("Queued +{$this->quantityToAdd} to {$cardName}")
            ->body("Total pending: " . count($this->pendingChanges) . " cards")
            ->success();
            
        if ($this->customPrice !== null) {
            $notification->body("Total pending: " . count($this->pendingChanges) . " cards | Price: $" . number_format($this->customPrice, 2));
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
        
        // Track pending change
        $cardId = $this->modalCardId;
        $cardName = $this->modalCardData['name'] ?? 'Unknown Card';
        
        if (!isset($this->pendingChanges[$cardId])) {
            $this->pendingChanges[$cardId] = [
                'name' => $cardName,
                'change' => 0,
            ];
        }
        
        $this->pendingChanges[$cardId]['change'] -= $this->quantityToRemove;
        
        // Force Livewire to detect the change
        $this->pendingChanges = $this->pendingChanges;
        
        Notification::make()
            ->title("Queued -{$this->quantityToRemove} from {$cardName}")
            ->body("Total pending: " . count($this->pendingChanges) . " cards")
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
        
        foreach ($this->pendingChanges as $cardId => $change) {
            $inventory = Inventory::where('location_id', $this->selectedLocationId)
                ->where('card_id', $cardId)
                ->first();
            
            $currentQty = $inventory?->quantity ?? 0;
            $newQty = max(0, $currentQty + $change['change']);
            
            $updateData = [
                'quantity' => $newQty,
            ];
            
            // Include custom price if it was set
            if (isset($change['custom_price'])) {
                $updateData['custom_price'] = $change['custom_price'];
            }
            
            Inventory::updateOrCreate(
                [
                    'location_id' => $this->selectedLocationId,
                    'card_id' => $cardId,
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
        $this->closeReviewModal();
    }
    
    public function cancelPendingChanges(): void
    {
        $this->pendingChanges = [];
        $this->closeReviewModal();
        
        Notification::make()
            ->title('Changes cancelled')
            ->warning()
            ->send();
    }
}
