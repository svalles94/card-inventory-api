<?php

namespace App\Filament\Store\Pages;

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

        $this->form->fill([
            'items' => array_values($items),
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
                    Forms\Components\Hidden::make('card_id'),
                    Forms\Components\Hidden::make('location_id'),
                    Forms\Components\Placeholder::make('card_label')
                        ->label('Card')
                        ->content(fn (callable $get): string => $get('card_name') . ' (' . $get('set_code') . ' - ' . $get('card_number') . ')'),
                    Forms\Components\Placeholder::make('location_label')
                        ->label('Location')
                        ->content(fn (callable $get): string => (string) $get('location_name')),
                    Forms\Components\TextInput::make('quantity')
                        ->label('Quantity')
                        ->numeric()
                        ->required()
                        ->minValue(0),
                    Forms\Components\TextInput::make('sell_price')
                        ->label('Sell Price')
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
        $store = Auth::user()?->currentStore();
        if (! $store) {
            Notification::make()->danger()->title('Select a store first')->send();
            return;
        }

        $locations = $store->locations()->pluck('id')->all();

        $items = Arr::get($this->form->getState(), 'items', []);

        foreach ($items as $item) {
            $cardId = $item['card_id'] ?? null;
            $locationId = $item['location_id'] ?? null;
            if (! $cardId || ! $locationId || ! in_array($locationId, $locations, true)) {
                continue;
            }

            $quantity = (int) ($item['quantity'] ?? 0);
            $sellPrice = $item['sell_price'] !== '' ? (float) $item['sell_price'] : null;

            Inventory::updateOrCreate(
                [
                    'card_id' => $cardId,
                    'location_id' => $locationId,
                ],
                [
                    'quantity' => $quantity,
                    'sell_price' => $sellPrice,
                ]
            );
        }

        QueueStore::clear();

        Notification::make()->success()->title('Queued updates applied')->send();

        $this->redirect(\App\Filament\Store\Resources\StoreCardResource::getUrl('index'));
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
