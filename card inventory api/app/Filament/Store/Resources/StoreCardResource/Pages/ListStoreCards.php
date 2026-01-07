<?php

namespace App\Filament\Store\Resources\StoreCardResource\Pages;

use App\Filament\Store\Resources\StoreCardResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Facades\Session;
use App\Models\Edition;

class ListStoreCards extends ListRecords
{
    protected static string $resource = StoreCardResource::class;

    public string $viewMode = 'list';
    public ?string $selectedEditionId = null;
    public bool $showFoilPrices = true;

    protected static string $view = 'filament.store.resources.store-card-resource.pages.list-store-cards';

    protected $queryString = [
        'viewMode' => ['except' => 'list'],
        'selectedEditionId' => ['except' => null],
        'showFoilPrices' => ['except' => true],
    ];

    public function mount(): void
    {
        parent::mount();
        $this->viewMode = Session::get('cards_view_mode', 'list');
    }

    public function updatedViewMode(string $value): void
    {
        Session::put('cards_view_mode', $value);
    }
    
    public function updatedSelectedEditionId(): void
    {
        // Reset table when edition changes
        $this->resetTable();
    }
    
    public function getAvailableEditions()
    {
        // Get all editions that have cards
        // This is simpler and performs well since editions are relatively few
        return Edition::query()
            ->whereHas('card')
            ->with('set')
            ->orderBy('collector_number')
            ->orderBy('slug')
            ->get();
    }
    
    public function getEditionLabel(Edition $edition): string
    {
        // Match the pattern used in StoreCardResource
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

    protected function getTableContentGrid(): ?array
    {
        // Don't use Filament's grid - we have our own custom grid view
        return null;
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('review_queue')
                ->label('Queued Updates')
                ->icon('heroicon-o-clipboard-document-list')
                ->color('primary')
                ->url(\App\Filament\Store\Pages\InventoryUpdateQueuePage::getUrl()),
        ];
    }

    public function queueUpdateFromGrid(string $cardId): void
    {
        $card = \App\Models\Card::find($cardId);
        if (!$card) {
            \Filament\Notifications\Notification::make()
                ->title('Card not found')
                ->danger()
                ->send();
            return;
        }

        // Mount the queue_update table action for this card
        $this->mountedTableAction = 'queue_update';
        $this->mountedTableActionRecord = $card->id;
        $this->mountTableAction('queue_update', $card->id);
    }
}

