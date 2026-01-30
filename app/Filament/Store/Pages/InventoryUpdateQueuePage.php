<?php

namespace App\Filament\Store\Pages;

use App\Models\Edition;
use App\Models\Inventory;
use App\Support\InventoryUpdateQueue as QueueStore;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;

class InventoryUpdateQueuePage extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-list';
    protected static ?string $navigationGroup = 'Inventory';
    protected static ?string $navigationLabel = 'Queued Updates';
    protected static ?int $navigationSort = 3;

    protected static string $view = 'filament.store.pages.inventory-update-queue';

    public ?array $data = [];

    public function mount(): void
    {
        $items = QueueStore::all();

        if (empty($items)) {
            Notification::make()->warning()->title('No queued updates')->send();
            $this->redirect(\App\Filament\Store\Resources\StoreCardResource::getUrl('index'));
            return;
        }

        $hydrated = [];
        foreach ($items as $item) {
            $editionId = $item['edition_id'] ?? null;
            $isFoil = (bool) ($item['is_foil'] ?? false);

            $card = \App\Models\Card::find($item['card_id']);
            $edition = $editionId ? Edition::find($editionId) : null;

            $currentQuery = \App\Models\Inventory::where('card_id', $item['card_id'])
                ->where('location_id', $item['location_id'])
                ->where('is_foil', $isFoil);

            $editionId
                ? $currentQuery->where('edition_id', $editionId)
                : $currentQuery->whereNull('edition_id');

            $current = $currentQuery->first();

            $currentQty = $current?->quantity ?? ($item['current_quantity'] ?? 0);

            $cardPrices = $item['card_prices'] ?? [];
            if (empty($cardPrices) && $card) {
                $cardPrices = $card->cardPrices()
                    ->whereNotNull('market_price')
                    ->orderByDesc('updated_at')
                    ->take(5)
                    ->get(['market_price', 'updated_at'])
                    ->map(fn ($p) => [
                        'market_price' => $p->market_price,
                        'updated_at' => optional($p->updated_at)->toIso8601String(),
                    ])->all();
            }

            $cardImage = $item['card_image_url'] ?? $card?->image_url;
            $editionImage = $item['edition_image_url'] ?? $edition?->image_url;
            $editionLabel = $item['edition_label'] ?? ($edition ? \App\Support\InventoryUpdateQueue::formatEditionLabel($edition) : '—');
            $cardName = $item['card_name'] ?? $card?->name ?? 'Card';
            $setCode = $item['set_code'] ?? $card?->set_code ?? '';
            $cardNumber = $item['card_number'] ?? $card?->card_number ?? '';

            $hydrated[] = array_merge($item, [
                'current_quantity' => $currentQty,
                'card_image_url' => $cardImage,
                'edition_image_url' => $editionImage,
                'card_prices' => $cardPrices,
                'edition_label' => $editionLabel,
                'card_name' => $cardName,
                'set_code' => $setCode,
                'card_number' => $cardNumber,
                'is_foil' => $isFoil,
                'buy_price' => $item['buy_price'] ?? $current?->buy_price ?? null,
            ]);
        }

        // Store items in data array for custom table view
        $this->data['items'] = array_values($hydrated);
        
        // Also fill form for compatibility
        $this->form->fill([
            'items' => array_values($hydrated),
        ]);
    }

    protected function getForms(): array
    {
        return [
            'form' => $this->makeForm()
                ->schema($this->getFormSchema())
                ->statePath('data'),
        ];
    }

    protected function getFormSchema(): array
    {
        return [
            Forms\Components\Repeater::make('items')
                ->label('Queued Changes')
                ->schema([
                    Forms\Components\Placeholder::make('card_art')
                        ->label('Card Art')
                        ->content(function (callable $get): \Illuminate\Support\HtmlString {
                            $editionId = $get('edition_id');
                            $isFoil = (bool) $get('is_foil');
                            $editionImage = $get('edition_image_url') ?? ($editionId ? Edition::find($editionId)?->image_url : null);
                            $cardImage = $get('card_image_url') ?? null;
                            $src = $editionImage ?: $cardImage ?: '/images/card-placeholder.png';

                            $safeSrc = htmlspecialchars($src, ENT_QUOTES, 'UTF-8');
                            $safeAlt = htmlspecialchars((string) $get('card_name'), ENT_QUOTES, 'UTF-8');

                            $shimmerStyle = $isFoil
                                ? 'position:relative;display:inline-block;overflow:hidden;border-radius:8px;' .
                                    'background:linear-gradient(120deg, rgba(255,255,255,0.15) 0%, rgba(255,255,255,0.35) 30%, rgba(255,255,255,0.05) 60%, rgba(255,255,255,0.25) 100%);' .
                                    'background-size:200% 200%;animation:foil-shimmer 1.8s linear infinite;'
                                : 'position:relative;display:inline-block;border-radius:8px;';

                            $img = '<div style="' . $shimmerStyle . '"><img src="' . $safeSrc . '" alt="' . $safeAlt . '" style="max-width: 220px; height: auto; display:block; border-radius: 8px;" /></div>';
                            $keyframes = '<style>@keyframes foil-shimmer {0%{background-position:200% 0;}100%{background-position:-200% 0;}}</style>';

                            return new \Illuminate\Support\HtmlString($keyframes . $img);
                        })
                        ->columnSpanFull(),
                    Forms\Components\Hidden::make('card_id'),
                    Forms\Components\Hidden::make('edition_id'),
                    Forms\Components\Hidden::make('is_foil'),
                    Forms\Components\Hidden::make('location_id'),
                    Forms\Components\Placeholder::make('card_label')
                        ->label('Card')
                        ->content(fn (callable $get): string => $get('card_name') . ' (' . $get('set_code') . ' - ' . $get('card_number') . ')'),
                    Forms\Components\Placeholder::make('edition_label')
                        ->label('Edition')
                        ->content(fn (callable $get): string => (string) ($get('edition_label') ?? '—')),
                    Forms\Components\Placeholder::make('foil_label')
                        ->label('Foil')
                        ->content(fn (callable $get): string => $get('is_foil') ? 'Foil' : 'Normal'),
                    Forms\Components\Placeholder::make('market_price')
                        ->label('TCGPlayer Market Price')
                        ->content(function (callable $get): string {
                            $editionId = $get('edition_id');
                            $isFoil = (bool) $get('is_foil');
                            $cardPrices = $get('card_prices') ?? [];

                            $edition = $editionId ? Edition::find($editionId) : null;

                            $price = $edition?->market_price;
                            if ($price !== null) {
                                return '$' . number_format((float) $price, 2);
                            }

                            $editionPrices = $edition?->cardPrices()
                                ->whereNotNull('market_price')
                                ->orderByDesc('updated_at');

                            if ($editionPrices) {
                                $editionPrice = (clone $editionPrices)
                                    ->when($isFoil, fn ($q) => $q->where('sub_type_name', 'like', '%foil%'))
                                    ->when(! $isFoil, fn ($q) => $q->where('sub_type_name', 'not like', '%foil%'))
                                    ->first() ?? $editionPrices->first();

                                if ($editionPrice?->market_price !== null) {
                                    return '$' . number_format((float) $editionPrice->market_price, 2);
                                }
                            }

                            $latestCardPrice = collect($cardPrices)
                                ->filter(fn ($p) => isset($p['market_price']))
                                ->sortByDesc('updated_at')
                                ->first();

                            return $latestCardPrice && isset($latestCardPrice['market_price'])
                                ? '$' . number_format((float) $latestCardPrice['market_price'], 2)
                                : 'N/A';
                        }),
                    Forms\Components\Placeholder::make('location_label')
                        ->label('Location')
                        ->content(fn (callable $get): string => (string) $get('location_name')),
                    Forms\Components\Placeholder::make('current_quantity')
                        ->label('Current Qty')
                        ->content(fn (callable $get) => (string) ($get('current_quantity') ?? 0)),
                    Forms\Components\TextInput::make('delta_quantity')
                        ->label('Change')
                        ->helperText('Use negative to remove')
                        ->numeric()
                        ->required()
                        ->default(0),
                    Forms\Components\Placeholder::make('result_quantity')
                        ->label('Resulting Qty')
                        ->content(fn (callable $get) => (string) max(0, (int) ($get('current_quantity') ?? 0) + (int) ($get('delta_quantity') ?? 0))),
                    Forms\Components\TextInput::make('sell_price')
                        ->label('Your Price (Optional)')
                        ->numeric()
                        ->prefix('$')
                        ->nullable()
                        ->helperText(function (callable $get) {
                            $current = Inventory::where('card_id', $get('card_id'))
                                ->where('location_id', $get('location_id'))
                                ->where('is_foil', (bool) $get('is_foil'))
                                ->first();
                            
                            if ($current?->sell_price) {
                                return "Current price: $" . number_format($current->sell_price, 2) . ". Leave empty to keep current price.";
                            }
                            
                            return "Leave empty if you don't want to set a price yet.";
                        })
                        ->visible(function (callable $get) {
                            // Always show, but helper text explains it's optional
                            return true;
                        }),
                ])
                ->columns(5)
                ->columnSpanFull(),
        ];
    }

    public function submit(): void
    {
        $store = Auth::user()?->currentStore();
        if (! $store) {
            Notification::make()->danger()->title('Select a store first')->send();
            return;
        }

        $locations = $store->locations()->pluck('id')->all();

        // Get items from data array (from custom table view)
        $items = $this->data['items'] ?? [];

        foreach ($items as $item) {
            $cardId = $item['card_id'] ?? null;
            $editionId = $item['edition_id'] ?? null;
            $isFoil = (bool) ($item['is_foil'] ?? false);
            $locationId = $item['location_id'] ?? null;
            if (! $cardId || ! $locationId || ! in_array($locationId, $locations, true)) {
                continue;
            }

            $delta = (int) ($item['delta_quantity'] ?? 0);
            $sellPriceInput = isset($item['sell_price']) && $item['sell_price'] !== '' && $item['sell_price'] !== null ? (float) $item['sell_price'] : null;
            $buyPriceInput = isset($item['buy_price']) && $item['buy_price'] !== '' && $item['buy_price'] !== null ? (float) $item['buy_price'] : null;

            $currentQuery = \App\Models\Inventory::where('card_id', $cardId)
                ->where('location_id', $locationId)
                ->where('is_foil', $isFoil);

            $editionId
                ? $currentQuery->where('edition_id', $editionId)
                : $currentQuery->whereNull('edition_id');

            $currentInv = $currentQuery->first();

            $currentQty = $currentInv?->quantity ?? 0;
            $newQty = max(0, $currentQty + $delta);
            $sellPriceFinal = $sellPriceInput ?? $currentInv?->sell_price;
            $buyPriceFinal = $buyPriceInput ?? $currentInv?->buy_price;

            Inventory::updateOrCreate(
                [
                    'card_id' => $cardId,
                    'location_id' => $locationId,
                    'is_foil' => $isFoil,
                    'edition_id' => $editionId,
                ],
                [
                    'quantity' => $newQty,
                    'sell_price' => $sellPriceFinal,
                    'buy_price' => $buyPriceFinal,
                ]
            );
        }

        QueueStore::clear();

        Notification::make()->success()->title('Queued updates applied')->send();

        $this->redirect(\App\Filament\Store\Resources\StoreCardResource::getUrl('index'));
    }

    public function removeItem(int $index): void
    {
        $items = $this->data['items'] ?? [];
        
        if (isset($items[$index])) {
            unset($items[$index]);
            $this->data['items'] = array_values($items); // Reindex array
            
            // Also update session queue
            $queueItems = QueueStore::all();
            $keys = array_keys($queueItems);
            if (isset($keys[$index])) {
                unset($queueItems[$keys[$index]]);
                \Illuminate\Support\Facades\Session::put(QueueStore::key(), $queueItems);
            }
        }
    }

    protected function getHeaderActions(): array
    {
        return [
            \Filament\Actions\Action::make('clear_queue')
                ->label('Clear Queue')
                ->icon('heroicon-o-trash')
                ->color('danger')
                ->requiresConfirmation()
                ->action(function () {
                    QueueStore::clear();
                    Notification::make()->success()->title('Queue cleared')->send();
                    $this->redirect(\App\Filament\Store\Resources\StoreCardResource::getUrl('index'));
                }),
        ];
    }
}
