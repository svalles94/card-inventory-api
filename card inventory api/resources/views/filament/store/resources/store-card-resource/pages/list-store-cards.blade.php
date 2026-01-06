@php
    /** @var \App\Filament\Store\Resources\StoreCardResource\Pages\ListStoreCards $this */
@endphp

@if($this->viewMode === 'grid')
    {{-- GRID VIEW: True card grid like screenshot --}}
    <x-filament-panels::page>
        <div class="space-y-6">
            {{-- View Mode Switcher --}}
            <div class="flex items-center gap-2 mb-4">
                <span class="text-sm font-medium text-gray-700 dark:text-gray-300">View:</span>
                <div class="flex gap-1">
                    <button 
                        wire:click="$set('viewMode', 'grid')"
                        class="px-4 py-2 text-sm font-medium rounded-md transition-colors {{ $this->viewMode === 'grid' ? 'bg-primary-600 text-white shadow-md' : 'bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-300 hover:bg-gray-300 dark:hover:bg-gray-600' }}">
                        Grid
                    </button>
                    <button 
                        wire:click="$set('viewMode', 'list')"
                        class="px-4 py-2 text-sm font-medium rounded-md transition-colors {{ $this->viewMode === 'list' ? 'bg-primary-600 text-white shadow-md' : 'bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-300 hover:bg-gray-300 dark:hover:bg-gray-600' }}">
                        List
                    </button>
                </div>
            </div>

            {{-- Search and Filters - Only show search/filters, hide table completely --}}
            <div class="mb-4">
                <style>
                    .grid-view-table-wrapper .fi-ta-table-container,
                    .grid-view-table-wrapper .fi-ta-pagination,
                    .grid-view-table-wrapper table {
                        display: none !important;
                    }
                </style>
                <div class="grid-view-table-wrapper">
                    {{ $this->table }}
                </div>
            </div>

            {{-- True Grid of Cards - Horizontal layout like screenshot --}}
            <div class="grid grid-cols-4 sm:grid-cols-6 md:grid-cols-8 lg:grid-cols-10 xl:grid-cols-12 2xl:grid-cols-14 gap-3" style="grid-template-columns: repeat(auto-fill, minmax(100px, 1fr));">
                @php
                    $records = $this->getTableRecords();
                @endphp
                @forelse($records as $card)
                    <div class="group flex flex-col">
                        {{-- Card Image - Full card image like screenshot --}}
                        <a 
                            href="{{ \App\Filament\Store\Resources\StoreCardResource::getUrl('view', ['record' => $card]) }}"
                            class="block relative aspect-[2.5/3.5] w-full overflow-hidden rounded-lg shadow-lg hover:shadow-xl transition-all hover:scale-110 bg-gray-900"
                            style="max-width: 120px; margin: 0 auto;"
                        >
                            @if($card->image_url)
                                <img 
                                    src="{{ $card->image_url }}" 
                                    alt="{{ $card->name }}"
                                    class="w-full h-full object-cover"
                                    loading="lazy"
                                />
                            @else
                                <div class="w-full h-full flex items-center justify-center text-gray-500 bg-gray-800">
                                    <svg class="w-12 h-12" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                    </svg>
                                </div>
                            @endif

                            {{-- Market Price Badge --}}
                            @php
                                $latestPrice = $card->cardPrices()
                                    ->whereNotNull('market_price')
                                    ->orderBy('updated_at', 'desc')
                                    ->first();
                                $marketPrice = $latestPrice?->market_price;
                            @endphp
                            @if($marketPrice)
                                <div class="absolute top-2 right-2 bg-primary-600 text-white text-xs font-bold px-2 py-1 rounded shadow-lg">
                                    ${{ number_format($marketPrice, 2) }}
                                </div>
                            @endif
                        </a>

                        {{-- Card Information Below Image - Always visible --}}
                        <div class="mt-2 px-1">
                            {{-- Card Name --}}
                            <a 
                                href="{{ \App\Filament\Store\Resources\StoreCardResource::getUrl('view', ['record' => $card]) }}"
                                class="block"
                            >
                                <h3 class="text-xs font-semibold text-gray-900 dark:text-white mb-1 line-clamp-2 hover:text-primary-600 dark:hover:text-primary-400 transition-colors" title="{{ $card->name }}">
                                    {{ $card->name }}
                                </h3>
                            </a>

                            {{-- Set Code and Card Number --}}
                            @if($card->set_code || $card->card_number)
                                <div class="flex items-center gap-1.5 mb-1">
                                    @if($card->set_code)
                                        <span class="text-xs text-gray-600 dark:text-gray-400 font-medium">
                                            {{ $card->set_code }}
                                        </span>
                                    @endif
                                    @if($card->card_number)
                                        <span class="text-xs text-gray-500 dark:text-gray-500">
                                            #{{ $card->card_number }}
                                        </span>
                                    @endif
                                </div>
                            @endif

                            {{-- Quick Actions --}}
                            <div class="flex items-center gap-1 mt-1 flex-wrap">
                                <a 
                                    href="{{ \App\Filament\Store\Resources\StoreCardResource::getUrl('view', ['record' => $card]) }}"
                                    class="text-xs text-primary-600 dark:text-primary-400 hover:underline"
                                    title="View Details"
                                >
                                    View
                                </a>
                                <span class="text-gray-300 dark:text-gray-600">•</span>
                                <a 
                                    href="{{ \App\Filament\Store\Resources\StoreInventoryResource::getUrl('create', ['card_id' => $card->id]) }}"
                                    class="text-xs text-success-600 dark:text-success-400 hover:underline"
                                    title="Add to Inventory"
                                >
                                    Add
                                </a>
                                <span class="text-gray-300 dark:text-gray-600">•</span>
                                <button 
                                    wire:click="queueUpdateFromGrid('{{ $card->id }}')"
                                    class="text-xs text-warning-600 dark:text-warning-400 hover:underline cursor-pointer"
                                    title="Queue Update"
                                >
                                    Queue
                                </button>
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="col-span-full text-center py-12">
                        <p class="text-gray-500 dark:text-gray-400">No cards found</p>
                    </div>
                @endforelse
            </div>

            {{-- Pagination --}}
            <div class="mt-6">
                {{ $this->table->getRecords()->links() }}
            </div>
        </div>
    </x-filament-panels::page>
@else
    {{-- LIST VIEW: Standard Filament table --}}
    <x-filament-panels::page>
        <div class="space-y-6">
            {{-- View Mode Switcher --}}
            <div class="flex items-center gap-2 mb-4">
                <span class="text-sm font-medium text-gray-700 dark:text-gray-300">View:</span>
                <div class="flex gap-1">
                    <button 
                        wire:click="$set('viewMode', 'grid')"
                        class="px-4 py-2 text-sm font-medium rounded-md transition-colors {{ $this->viewMode === 'grid' ? 'bg-primary-600 text-white shadow-md' : 'bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-300 hover:bg-gray-300 dark:hover:bg-gray-600' }}">
                        Grid
                    </button>
                    <button 
                        wire:click="$set('viewMode', 'list')"
                        class="px-4 py-2 text-sm font-medium rounded-md transition-colors {{ $this->viewMode === 'list' ? 'bg-primary-600 text-white shadow-md' : 'bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-300 hover:bg-gray-300 dark:hover:bg-gray-600' }}">
                        List
                    </button>
                </div>
            </div>

            {{-- Standard Filament Table --}}
            {{ $this->table }}
        </div>
    </x-filament-panels::page>
@endif

