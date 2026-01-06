@php
    /** @var \App\Filament\Store\Pages\InventoryUpdateQueuePage $this */
@endphp

<style>
    /* Ensure all inputs and selects have visible dark text */
    .fi-section-content input[type="number"],
    .fi-section-content input[type="text"],
    .fi-section-content select,
    table input[type="number"],
    table input[type="text"],
    table select {
        color: #1f2937 !important;
        background-color: white !important;
    }
    
    .fi-section-content input::placeholder,
    table input::placeholder {
        color: #6b7280 !important;
    }
    
    .fi-section-content select option,
    table select option {
        background-color: white !important;
        color: #1f2937 !important;
    }
</style>

<x-filament-panels::page>
    <x-filament::section>
        <x-slot name="heading">Queued Inventory Updates</x-slot>
        <x-slot name="description">Review and apply your queued quantity and sell price changes.</x-slot>

        <form wire:submit.prevent="submit" class="space-y-6">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-700">
                    <thead class="bg-gray-800">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-300 uppercase tracking-wider">Card Art</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-300 uppercase tracking-wider">Card Info</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-300 uppercase tracking-wider">Edition</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-300 uppercase tracking-wider">Foil</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-300 uppercase tracking-wider">Location</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-300 uppercase tracking-wider">Market Price</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-300 uppercase tracking-wider">Buy Price</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-300 uppercase tracking-wider">Sell Price</th>
                            <th class="px-4 py-3 text-center text-xs font-medium text-gray-300 uppercase tracking-wider">Current Qty</th>
                            <th class="px-4 py-3 text-center text-xs font-medium text-gray-300 uppercase tracking-wider">Change</th>
                            <th class="px-4 py-3 text-center text-xs font-medium text-gray-300 uppercase tracking-wider">New Qty</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-300 uppercase tracking-wider">New Sell $</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-300 uppercase tracking-wider">New Buy $</th>
                            <th class="px-4 py-3 text-center text-xs font-medium text-gray-300 uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-gray-900 divide-y divide-gray-700">
                        @foreach($this->data['items'] ?? [] as $index => $item)
                            <tr class="hover:bg-gray-800 transition">
                                <!-- Card Art -->
                                <td class="px-4 py-4 whitespace-nowrap">
                                    @php
                                        $editionId = $item['edition_id'] ?? null;
                                        $isFoil = (bool) ($item['is_foil'] ?? false);
                                        $editionImage = $item['edition_image_url'] ?? null;
                                        $cardImage = $item['card_image_url'] ?? null;
                                        $imageUrl = $editionImage ?: $cardImage ?: '/images/card-placeholder.png';
                                    @endphp
                                    <img src="{{ $imageUrl }}" 
                                         alt="{{ $item['card_name'] ?? 'Card' }}"
                                         class="w-20 h-auto rounded"
                                         style="max-width: 80px;">
                                </td>

                                <!-- Card Info -->
                                <td class="px-4 py-4">
                                    <div class="text-sm font-medium text-white">{{ $item['card_name'] ?? 'N/A' }}</div>
                                    <div class="text-xs text-gray-400">{{ $item['set_code'] ?? '' }} - {{ $item['card_number'] ?? '' }}</div>
                                </td>

                                <!-- Edition -->
                                <td class="px-4 py-4">
                                    <select wire:model.live="data.items.{{ $index }}.edition_id" 
                                            class="bg-white border border-gray-300 rounded px-2 py-1 text-sm text-gray-900 focus:outline-none focus:ring-2 focus:ring-primary-500"
                                            style="color: #1f2937 !important; background-color: white !important; width: 150px; min-width: 120px;"
                                    >
                                        <option value="" style="background-color: white; color: #1f2937;">—</option>
                                        @if(isset($item['card_id']))
                                            @php
                                                $card = \App\Models\Card::find($item['card_id']);
                                                $editions = $card?->editions()->with('set')->get() ?? collect();
                                            @endphp
                                            @foreach($editions as $edition)
                                                @php
                                                    $setName = $edition->set?->name ?? 'Unknown Set';
                                                    $collectorNumber = $edition->collector_number ?? '';
                                                    $label = $setName . ($collectorNumber ? ' - #' . $collectorNumber : '');
                                                @endphp
                                                <option value="{{ $edition->id }}" style="background-color: white; color: #1f2937;">{{ $label }}</option>
                                            @endforeach
                                        @endif
                                    </select>
                                </td>

                                <!-- Foil -->
                                <td class="px-4 py-4">
                                    <select wire:model.live="data.items.{{ $index }}.is_foil" 
                                            class="bg-white border border-gray-300 rounded px-2 py-1 text-sm text-gray-900 focus:outline-none focus:ring-2 focus:ring-primary-500"
                                            style="color: #1f2937 !important; background-color: white !important; width: 120px; min-width: 100px;"
                                    >
                                        <option value="0" style="background-color: white; color: #1f2937;">Normal</option>
                                        <option value="1" style="background-color: white; color: #1f2937;">Foil</option>
                                    </select>
                                </td>

                                <!-- Location -->
                                <td class="px-4 py-4 text-sm text-gray-300">
                                    {{ $item['location_name'] ?? 'N/A' }}
                                </td>

                                <!-- Market Price -->
                                <td class="px-4 py-4 text-sm text-gray-300">
                                    @php
                                        $editionId = $item['edition_id'] ?? null;
                                        $isFoil = (bool) ($item['is_foil'] ?? false);
                                        $edition = $editionId ? \App\Models\Edition::find($editionId) : null;
                                        
                                        $price = $edition?->market_price;
                                        if ($price === null && $edition) {
                                            $editionPrice = $edition->cardPrices()
                                                ->whereNotNull('market_price')
                                                ->when($isFoil, fn($q) => $q->where('sub_type_name', 'like', '%foil%'))
                                                ->when(!$isFoil, fn($q) => $q->where('sub_type_name', 'not like', '%foil%'))
                                                ->orderByDesc('updated_at')
                                                ->first();
                                            $price = $editionPrice?->market_price;
                                        }
                                        
                                        if ($price === null) {
                                            $cardPrices = $item['card_prices'] ?? [];
                                            $latestPrice = collect($cardPrices)->sortByDesc('updated_at')->first();
                                            $price = $latestPrice['market_price'] ?? null;
                                        }
                                    @endphp
                                    {{ $price ? '$' . number_format($price, 2) : 'N/A' }}
                                </td>

                                <!-- Buy Price -->
                                <td class="px-4 py-4 text-sm text-gray-300">
                                    {{ isset($item['buy_price']) && $item['buy_price'] ? '$' . number_format($item['buy_price'], 2) : '—' }}
                                </td>

                                <!-- Sell Price -->
                                <td class="px-4 py-4 text-sm text-gray-300">
                                    {{ isset($item['sell_price']) && $item['sell_price'] ? '$' . number_format($item['sell_price'], 2) : '—' }}
                                </td>

                                <!-- Current Qty -->
                                <td class="px-4 py-4 text-center text-sm font-semibold text-white">
                                    {{ $item['current_quantity'] ?? 0 }}
                                </td>

                                <!-- Change -->
                                <td class="px-4 py-4">
                                    <input type="number" 
                                           wire:model.live="data.items.{{ $index }}.delta_quantity"
                                           class="bg-white border border-gray-300 rounded px-2 py-1 text-sm text-gray-900 w-20 text-center focus:outline-none focus:ring-2 focus:ring-primary-500"
                                           style="color: #1f2937 !important; background-color: white !important;"
                                           value="{{ $item['delta_quantity'] ?? 0 }}">
                                </td>

                                <!-- New Qty -->
                                <td class="px-4 py-4 text-center text-sm font-bold text-white">
                                    @php
                                        $current = (int) ($item['current_quantity'] ?? 0);
                                        $change = (int) ($item['delta_quantity'] ?? 0);
                                        $newQty = max(0, $current + $change);
                                    @endphp
                                    {{ $newQty }}
                                </td>

                                <!-- New Sell $ -->
                                <td class="px-4 py-4">
                                    <input type="number" 
                                           wire:model="data.items.{{ $index }}.sell_price"
                                           step="0.01"
                                           min="0"
                                           class="bg-white border border-gray-300 rounded px-2 py-1 text-sm text-gray-900 focus:outline-none focus:ring-2 focus:ring-primary-500"
                                           style="color: #1f2937 !important; background-color: white !important; width: 80px;"
                                           placeholder="0.00">
                                </td>

                                <!-- New Buy $ -->
                                <td class="px-4 py-4">
                                    <input type="number" 
                                           wire:model="data.items.{{ $index }}.buy_price"
                                           step="0.01"
                                           min="0"
                                           class="bg-white border border-gray-300 rounded px-2 py-1 text-sm text-gray-900 focus:outline-none focus:ring-2 focus:ring-primary-500"
                                           style="color: #1f2937 !important; background-color: white !important; width: 80px;"
                                           placeholder="0.00">
                                </td>

                                <!-- Actions -->
                                <td class="px-4 py-4 text-center">
                                    <button type="button" 
                                            wire:click="removeItem({{ $index }})"
                                            class="text-red-400 hover:text-red-300 transition"
                                            title="Remove from queue">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                                        </svg>
                                    </button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if(empty($this->data['items'] ?? []))
                <div class="text-center py-12 text-gray-400">
                    <p>No items in queue</p>
                </div>
            @endif

            <div class="flex items-center gap-3 mt-6">
                <x-filament::button type="submit" icon="heroicon-o-check-circle" color="primary" size="lg">
                    Apply Updates
                </x-filament::button>
                <x-filament::button wire:click.prevent="callMountedAction('clear_queue')" icon="heroicon-o-trash" color="danger" outlined size="lg">
                    Clear Queue
                </x-filament::button>
            </div>
        </form>
    </x-filament::section>
</x-filament-panels::page>
