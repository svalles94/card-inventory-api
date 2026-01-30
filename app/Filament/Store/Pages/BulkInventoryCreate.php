<?php

namespace App\Filament\Store\Pages;

use App\Models\Card;
use App\Models\Inventory;
use App\Models\Location;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Collection;

class BulkInventoryCreate extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-list';
    protected static ?string $navigationGroup = null;
    protected static bool $shouldRegisterNavigation = false;

    protected static string $view = 'filament.store.pages.bulk-inventory-create';

    public ?array $data = [];
    public Collection $cards;

    public function mount(): void
    {
        $ids = Session::get('bulk_inventory_card_ids', []);
        $this->cards = Card::whereIn('id', $ids)->get();

        if ($this->cards->isEmpty()) {
            Notification::make()->warning()->title('Select cards first')->send();
            $this->redirect(\App\Filament\Store\Resources\StoreCardResource::getUrl('index'));
            return;
        }

        $this->form->fill([
            'items' => $this->cards->map(function (Card $card) {
                $currentLocationId = Auth::user()?->currentLocation()?->id;
                $latestMarket = Cache::remember("card:{$card->id}:latest_market_price", 300, function () use ($card) {
                    return $card->cardPrices()
                        ->whereNotNull('market_price')
                        ->orderBy('updated_at', 'desc')
                        ->value('market_price');
                });
                return [
                    'card_id' => $card->id,
                    'card_label' => sprintf('%s (%s - %s)', $card->name, $card->set_code, $card->card_number),
                    'location_id' => $currentLocationId,
                    'quantity' => 0,
                    'sell_price' => null,
                    'buy_price' => null,
                    'market_price' => $latestMarket,
                ];
            })->values()->all(),
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
        $store = Auth::user()?->currentStore();

        return [
            Forms\Components\Repeater::make('items')
                ->label('Selected Cards')
                ->schema([
                    Forms\Components\Hidden::make('card_id'),
                    Forms\Components\Hidden::make('card_label'),
                    Forms\Components\Placeholder::make('card_label_display')
                        ->label('Card')
                        ->content(fn (callable $get): string => (string) ($get('card_label') ?? 'Card')),
                    Forms\Components\Select::make('location_id')
                        ->label('Location')
                        ->required()
                        ->options(function () use ($store) {
                            if (! $store) {
                                return [];
                            }

                            return $store->locations()->pluck('name', 'id');
                        })
                        ->searchable()
                        ->preload(),
                    Forms\Components\TextInput::make('quantity')
                        ->label('Quantity')
                        ->numeric()
                        ->required()
                        ->minValue(0)
                        ->default(0),
                    Forms\Components\TextInput::make('sell_price')
                        ->label('Sell Price')
                        ->numeric()
                        ->prefix('$')
                        ->nullable(),
                    Forms\Components\TextInput::make('buy_price')
                        ->label('Buy Price')
                        ->numeric()
                        ->prefix('$')
                        ->nullable(),
                    Forms\Components\TextInput::make('market_price')
                        ->label('Market Price')
                        ->numeric()
                        ->prefix('$')
                        ->nullable(),
                ])
                ->columns(3)
                ->columnSpanFull(),
        ];
    }

    public function submit(): void
    {
        $data = $this->form->getState();
        $items = Arr::get($data, 'items', []);
        $store = Auth::user()?->currentStore();

        if (! $store) {
            Notification::make()->danger()->title('Select a store to continue')->send();
            return;
        }

        $locationIds = $store->locations()->pluck('id')->all();

        foreach ($items as $item) {
            $cardId = $item['card_id'] ?? null;
            $locationId = $item['location_id'] ?? null;

            if (! $cardId || ! $locationId || ! in_array($locationId, $locationIds, true)) {
                continue;
            }

            $quantity = (int) ($item['quantity'] ?? 0);
            $sellPrice = $item['sell_price'] !== '' ? (float) $item['sell_price'] : null;
            $buyPrice = $item['buy_price'] !== '' ? (float) $item['buy_price'] : null;
            $marketPrice = $item['market_price'] !== '' ? (float) $item['market_price'] : null;

            Inventory::updateOrCreate(
                [
                    'card_id' => $cardId,
                    'location_id' => $locationId,
                ],
                [
                    'quantity' => $quantity,
                    'sell_price' => $sellPrice,
                    'buy_price' => $buyPrice,
                    'market_price' => $marketPrice,
                ]
            );
        }

        Session::forget('bulk_inventory_card_ids');

        Notification::make()->success()->title('Inventory updated')->send();

        $this->redirect(\App\Filament\Store\Pages\InventoryTableView::getUrl());
    }
}
