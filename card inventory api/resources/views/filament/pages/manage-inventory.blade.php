<x-filament-panels::page>
    <div class="space-y-6">
        {{-- Header Controls --}}
        <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-6">
            <div class="flex flex-col lg:flex-row gap-4 items-start lg:items-center justify-between">
                {{-- Location Selector --}}
                <div class="flex-1 min-w-0">
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                        Location
                    </label>
                    <select 
                        wire:model.live="selectedLocationId"
                        class="w-full rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300"
                    >
                        @foreach($this->locations as $location)
                            <option value="{{ $location->id }}">
                                {{ $location->store->name ?? 'N/A' }} - {{ $location->name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                {{-- Game Selector --}}
                <div class="flex-1 min-w-0">
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                        Game
                    </label>
                    <select 
                        wire:model.live="selectedGame"
                        class="w-full rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300"
                    >
                        @foreach($this->games as $gameKey => $gameName)
                            <option value="{{ $gameKey }}">{{ $gameName }}</option>
                        @endforeach
                    </select>
                </div>

                {{-- View Mode Toggle --}}
                <div class="flex-1 min-w-0">
                    <label class="block text-sm font-medium text-white mb-2">
                        View Mode
                    </label>
                    <div class="flex gap-2">
                        <button
                            wire:click="setViewMode('inventory')"
                            class="flex-1 px-4 py-2 rounded-lg font-semibold transition {{ $viewMode === 'inventory' ? 'bg-blue-600 text-white shadow-lg' : 'bg-gray-700 text-gray-300 hover:bg-gray-600' }}"
                        >
                            My Inventory
                        </button>
                        <button
                            wire:click="setViewMode('add')"
                            class="flex-1 px-4 py-2 rounded-lg font-semibold transition {{ $viewMode === 'add' ? 'bg-blue-600 text-white shadow-lg' : 'bg-gray-700 text-gray-300 hover:bg-gray-600' }}"
                        >
                            Add Cards
                        </button>
                    </div>
                </div>

                {{-- Display Mode Toggle --}}
                <div class="flex-1 min-w-0">
                    <label class="block text-sm font-medium text-white mb-2">
                        Display Mode
                    </label>
                    <div class="flex gap-2">
                        <button
                            wire:click="setDisplayMode('grid')"
                            class="flex-1 px-3 py-2 rounded-lg font-semibold transition {{ $displayMode === 'grid' ? 'bg-blue-600 text-white shadow-lg' : 'bg-gray-700 text-gray-300 hover:bg-gray-600' }}"
                            title="Grid View"
                        >
                            <svg class="w-6 h-6 mx-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z" />
                            </svg>
                        </button>
                        <button
                            wire:click="setDisplayMode('list')"
                            class="flex-1 px-3 py-2 rounded-lg font-semibold transition {{ $displayMode === 'list' ? 'bg-blue-600 text-white shadow-lg' : 'bg-gray-700 text-gray-300 hover:bg-gray-600' }}"
                            title="List View"
                        >
                            <svg class="w-6 h-6 mx-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M4 6h16M4 12h16M4 18h16" />
                            </svg>
                        </button>
                        <button
                            wire:click="setDisplayMode('table')"
                            class="flex-1 px-3 py-2 rounded-lg font-semibold transition {{ $displayMode === 'table' ? 'bg-blue-600 text-white shadow-lg' : 'bg-gray-700 text-gray-300 hover:bg-gray-600' }}"
                            title="Table View"
                        >
                            <svg class="w-6 h-6 mx-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M3 10h18M3 14h18m-9-4v8m-7 0h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z" />
                            </svg>
                        </button>
                    </div>
                </div>
            </div>

            {{-- Cards Per Page (for grid view) --}}
            @if($displayMode === 'grid')
                <div class="mt-4 flex items-center justify-between gap-3">
                    <div class="flex items-center gap-3">
                        <label class="text-sm font-medium text-gray-700 dark:text-gray-300">
                            Cards per page:
                        </label>
                        <select 
                            wire:model.live="cardsPerPage"
                            class="rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300"
                        >
                            <option value="20">20</option>
                            <option value="40">40</option>
                            <option value="60">60</option>
                            <option value="100">100</option>
                            <option value="200">200</option>
                            <option value="250">250</option>
                        </select>
                    </div>
                    
                    {{-- Update Stock Button (only in Add Cards mode) --}}
                    @if($viewMode === 'add' && !empty($pendingChanges))
                        <button
                            wire:click="openReviewModal"
                            class="px-6 py-3 bg-green-600 hover:bg-green-700 text-white font-bold rounded-lg transition flex items-center gap-2 shadow-lg animate-pulse"
                        >
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                            </svg>
                            Update Stock ({{ count($pendingChanges) }})
                        </button>
                    @endif
                </div>
            @else
                {{-- Update Stock Button for list/table views (only in Add Cards mode) --}}
                @if($viewMode === 'add' && !empty($pendingChanges))
                    <div class="mt-4 flex justify-end">
                        <button
                            wire:click="openReviewModal"
                            class="px-6 py-3 bg-green-600 hover:bg-green-700 text-white font-bold rounded-lg transition flex items-center gap-2 shadow-lg animate-pulse"
                        >
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                            </svg>
                            Update Stock ({{ count($pendingChanges) }})
                        </button>
                    </div>
                @endif
            @endif
        </div>

        {{-- Search and Filters --}}
        <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-6">
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-6 gap-4">
                {{-- Search --}}
                <div class="lg:col-span-2">
                    <input 
                        type="text"
                        wire:model.live.debounce.300ms="searchTerm"
                        placeholder="Search cards..."
                        class="w-full rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300"
                    />
                </div>

                {{-- Element Filter --}}
                <div>
                    <select 
                        wire:model.live="filters.element"
                        class="w-full rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300"
                    >
                        <option value="">All Elements</option>
                        @foreach($this->filterOptions['elements'] as $element)
                            <option value="{{ $element }}">{{ $element }}</option>
                        @endforeach
                    </select>
                </div>

                {{-- Class Filter --}}
                <div>
                    <select 
                        wire:model.live="filters.class"
                        class="w-full rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300"
                    >
                        <option value="">All Classes</option>
                        @foreach($this->filterOptions['classes'] as $class)
                            <option value="{{ $class }}">{{ $class }}</option>
                        @endforeach
                    </select>
                </div>

                {{-- Type Filter --}}
                <div>
                    <select 
                        wire:model.live="filters.type"
                        class="w-full rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300"
                    >
                        <option value="">All Types</option>
                        @foreach($this->filterOptions['types'] as $type)
                            <option value="{{ $type }}">{{ $type }}</option>
                        @endforeach
                    </select>
                </div>

                {{-- Clear Filters Button --}}
                <div>
                    <button
                        wire:click="clearFilters"
                        class="w-full px-4 py-2 bg-red-600 hover:bg-red-700 text-white font-semibold rounded-lg transition"
                    >
                        Clear Filters
                    </button>
                </div>
            </div>
        </div>

        {{-- GRID VIEW --}}
        @if($displayMode === 'grid')
            <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-6">
                <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 xl:grid-cols-6 gap-4">
                    @forelse($this->cards as $card)
                        <div 
                            wire:click="openCardModal('{{ $card->id }}')"
                            class="group relative cursor-pointer transition-transform hover:scale-105 hover:z-10"
                        >
                            {{-- Card Image --}}
                            <div class="relative aspect-[2.5/3.5] rounded-lg overflow-hidden bg-gray-900 shadow-lg">
                                @if($card->image)
                                    <img 
                                        src="{{ $card->image }}" 
                                        alt="{{ $card->name }}"
                                        class="w-full h-full object-cover"
                                        loading="lazy"
                                    />
                                @else
                                    <div class="w-full h-full flex items-center justify-center text-gray-500">
                                        <svg class="w-12 h-12" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                        </svg>
                                    </div>
                                @endif

                                {{-- Stock Badge --}}
                                @php
                                    $qty = $card->inventory->first()?->quantity ?? 0;
                                    $badgeColor = $qty === 0 ? 'bg-gray-500' : ($qty < 4 ? 'bg-red-500' : ($qty < 10 ? 'bg-yellow-500' : 'bg-green-500'));
                                @endphp
                                <div class="absolute top-2 right-2">
                                    <span class="px-2 py-1 text-xs font-bold text-white rounded {{ $badgeColor }} shadow">
                                        {{ $qty }}
                                    </span>
                                </div>
                            </div>

                            {{-- Card Info (on hover) --}}
                            <div class="absolute inset-0 bg-black bg-opacity-90 opacity-0 group-hover:opacity-100 transition-opacity rounded-lg p-3 flex flex-col justify-between">
                                <div>
                                    <h3 class="text-white font-bold text-sm mb-1 line-clamp-2">{{ $card->name }}</h3>
                                    <p class="text-gray-300 text-xs">{{ $card->set_code }} #{{ $card->card_number }}</p>
                                </div>
                                <div class="space-y-1">
                                    @if($card->types)
                                        <div class="flex flex-wrap gap-1">
                                            @foreach(is_array($card->types) ? $card->types : [$card->types] as $type)
                                                <span class="px-2 py-0.5 text-xs bg-blue-600 text-white rounded">{{ $type }}</span>
                                            @endforeach
                                        </div>
                                    @endif
                                    @if($card->elements)
                                        <div class="flex flex-wrap gap-1">
                                            @foreach(is_array($card->elements) ? $card->elements : [$card->elements] as $element)
                                                <span class="px-2 py-0.5 text-xs bg-purple-600 text-white rounded">{{ $element }}</span>
                                            @endforeach
                                        </div>
                                    @endif
                                </div>
                            </div>
                        </div>
                    @empty
                        <div class="col-span-full text-center py-12 text-gray-500 dark:text-gray-400">
                            @if($viewMode === 'inventory')
                                No cards in inventory. Switch to "Add Cards" mode to add some!
                            @else
                                No cards found. Try adjusting your filters.
                            @endif
                        </div>
                    @endforelse
                </div>
            </div>
        @endif

        {{-- LIST VIEW --}}
        @if($displayMode === 'list')
            <div class="bg-white dark:bg-gray-800 shadow rounded-lg overflow-hidden">
                <div class="divide-y divide-gray-200 dark:divide-gray-700">
                    @forelse($this->cards as $card)
                        @php
                            $qty = $card->inventory->first()?->quantity ?? 0;
                        @endphp
                        <div class="p-4 hover:bg-gray-700/50 transition flex items-center gap-4">
                            {{-- Card Name --}}
                            <div class="flex-1 min-w-0">
                                <h3 class="font-semibold text-gray-900 dark:text-white truncate">{{ $card->name }}</h3>
                                <p class="text-sm text-gray-500 dark:text-gray-400">
                                    {{ $card->set_code }} #{{ $card->card_number }}
                                </p>
                            </div>

                            {{-- Types/Elements --}}
                            <div class="hidden md:flex gap-2">
                                @if($card->types)
                                    @foreach((is_array($card->types) ? array_slice($card->types, 0, 2) : [$card->types]) as $type)
                                        <span class="px-2 py-1 text-xs bg-blue-100 dark:bg-blue-900 text-blue-800 dark:text-blue-200 rounded">{{ $type }}</span>
                                    @endforeach
                                @endif
                            </div>

                            {{-- Quantity Display --}}
                            <div class="text-center min-w-[60px]">
                                <div class="text-2xl font-bold {{ $qty > 0 ? 'text-blue-600 dark:text-blue-400' : 'text-gray-400' }}">
                                    {{ $qty }}
                                </div>
                                <div class="text-xs text-gray-500 dark:text-gray-400">in stock</div>
                            </div>

                            {{-- Quick Actions --}}
                            <div class="flex gap-2">
                                <button
                                    wire:click="quickUpdateInventory('{{ $card->id }}', 'remove')"
                                    class="p-2 rounded bg-red-100 dark:bg-red-900 text-red-600 dark:text-red-300 hover:bg-red-200 dark:hover:bg-red-800 transition"
                                    title="Remove 1"
                                >
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 12H4" />
                                    </svg>
                                </button>
                                <button
                                    wire:click="quickUpdateInventory('{{ $card->id }}', 'add')"
                                    class="p-2 rounded bg-green-100 dark:bg-green-900 text-green-600 dark:text-green-300 hover:bg-green-200 dark:hover:bg-green-800 transition"
                                    title="Add 1"
                                >
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                                    </svg>
                                </button>
                                <button
                                    wire:click="openCardModal('{{ $card->id }}')"
                                    class="p-2 rounded bg-blue-100 dark:bg-blue-900 text-blue-600 dark:text-blue-300 hover:bg-blue-200 dark:hover:bg-blue-800 transition"
                                    title="View Details"
                                >
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                    </svg>
                                </button>
                            </div>
                        </div>
                    @empty
                        <div class="text-center py-12 text-gray-500 dark:text-gray-400">
                            @if($viewMode === 'inventory')
                                No cards in inventory. Switch to "Add Cards" mode to add some!
                            @else
                                No cards found. Try adjusting your filters.
                            @endif
                        </div>
                    @endforelse
                </div>
            </div>
        @endif

        {{-- TABLE VIEW --}}
        @if($displayMode === 'table')
            <div class="bg-white dark:bg-gray-800 shadow rounded-lg overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead class="bg-gray-50 dark:bg-gray-700">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                    Card
                                </th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                    Set / #
                                </th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                    Type
                                </th>
                                <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                    Quantity
                                </th>
                                <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                    Actions
                                </th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                            @forelse($this->cards as $card)
                                @php
                                    $qty = $card->inventory->first()?->quantity ?? 0;
                                @endphp
                                <tr class="hover:bg-gray-700/50 transition">
                                    <td class="px-4 py-3">
                                        <div class="flex items-center gap-3">
                                            {{-- Small Card Image --}}
                                            <div class="w-12 h-16 rounded overflow-hidden bg-gray-900 flex-shrink-0">
                                                @if($card->image)
                                                    <img 
                                                        src="{{ $card->image }}" 
                                                        alt="{{ $card->name }}"
                                                        class="w-full h-full object-cover"
                                                        loading="lazy"
                                                    />
                                                @else
                                                    <div class="w-full h-full flex items-center justify-center text-gray-500">
                                                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                                        </svg>
                                                    </div>
                                                @endif
                                            </div>
                                            <div>
                                                <div class="font-semibold text-gray-900 dark:text-white">{{ $card->name }}</div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-4 py-3 text-sm text-gray-600 dark:text-gray-300">
                                        {{ $card->set_code }} #{{ $card->card_number }}
                                    </td>
                                    <td class="px-4 py-3">
                                        @if($card->types)
                                            <div class="flex flex-wrap gap-1">
                                                @foreach((is_array($card->types) ? array_slice($card->types, 0, 2) : [$card->types]) as $type)
                                                    <span class="px-2 py-1 text-xs bg-blue-100 dark:bg-blue-900 text-blue-800 dark:text-blue-200 rounded">{{ $type }}</span>
                                                @endforeach
                                            </div>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3">
                                        <div class="flex items-center justify-center gap-2">
                                            <button
                                                wire:click="quickUpdateInventory('{{ $card->id }}', 'remove5')"
                                                class="p-1 rounded bg-red-100 dark:bg-red-900 text-red-600 dark:text-red-300 hover:bg-red-200 dark:hover:bg-red-800 transition text-xs"
                                                title="Remove 5"
                                            >
                                                -5
                                            </button>
                                            <button
                                                wire:click="quickUpdateInventory('{{ $card->id }}', 'remove')"
                                                class="p-1 rounded bg-red-100 dark:bg-red-900 text-red-600 dark:text-red-300 hover:bg-red-200 dark:hover:bg-red-800 transition"
                                                title="Remove 1"
                                            >
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 12H4" />
                                                </svg>
                                            </button>
                                            <span class="text-xl font-bold {{ $qty > 0 ? 'text-blue-600 dark:text-blue-400' : 'text-gray-400' }} min-w-[3rem] text-center">
                                                {{ $qty }}
                                            </span>
                                            <button
                                                wire:click="quickUpdateInventory('{{ $card->id }}', 'add')"
                                                class="p-1 rounded bg-green-100 dark:bg-green-900 text-green-600 dark:text-green-300 hover:bg-green-200 dark:hover:bg-green-800 transition"
                                                title="Add 1"
                                            >
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                                                </svg>
                                            </button>
                                            <button
                                                wire:click="quickUpdateInventory('{{ $card->id }}', 'add5')"
                                                class="p-1 rounded bg-green-100 dark:bg-green-900 text-green-600 dark:text-green-300 hover:bg-green-200 dark:hover:bg-green-800 transition text-xs"
                                                title="Add 5"
                                            >
                                                +5
                                            </button>
                                        </div>
                                    </td>
                                    <td class="px-4 py-3 text-center">
                                        <button
                                            wire:click="openCardModal('{{ $card->id }}')"
                                            class="inline-flex items-center px-3 py-1 rounded bg-blue-100 dark:bg-blue-900 text-blue-600 dark:text-blue-300 hover:bg-blue-200 dark:hover:bg-blue-800 transition text-sm"
                                        >
                                            View Details
                                        </button>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="text-center py-12 text-gray-500 dark:text-gray-400">
                                        @if($viewMode === 'inventory')
                                            No cards in inventory. Switch to "Add Cards" mode to add some!
                                        @else
                                            No cards found. Try adjusting your filters.
                                        @endif
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        @endif

        {{-- Card Detail Modal --}}
        @if($showCardModal && $modalCardData)
            <div 
                class="fixed inset-0 z-50 overflow-y-auto"
                x-data="{ show: @entangle('showCardModal'), selectedEdition: 0 }"
                x-show="show"
                x-cloak
            >
                <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:p-0">
                    {{-- Overlay --}}
                    <div 
                        class="fixed inset-0 transition-opacity bg-black bg-opacity-95"
                        wire:click="closeCardModal"
                        x-show="show"
                        x-transition:enter="ease-out duration-300"
                        x-transition:enter-start="opacity-0"
                        x-transition:enter-end="opacity-100"
                        x-transition:leave="ease-in duration-200"
                        x-transition:leave-start="opacity-100"
                        x-transition:leave-end="opacity-0"
                    ></div>

                    {{-- Modal --}}
                    <div 
                        class="relative inline-block w-full max-w-6xl my-8 overflow-hidden text-left align-middle transition-all transform shadow-2xl rounded-lg"
                        style="background-color: #1f2937;"
                        x-show="show"
                        x-transition:enter="ease-out duration-300"
                        x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                        x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
                        x-transition:leave="ease-in duration-200"
                        x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100"
                        x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                        @click.stop
                    >
                        {{-- Close Button --}}
                        <button 
                            wire:click="closeCardModal"
                            class="absolute top-4 right-4 z-10 text-gray-400 hover:text-white transition"
                        >
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>

                        <div class="flex flex-col lg:flex-row max-h-[90vh]">
                            {{-- LEFT SIDE: Card Image & Edition Info --}}
                            <div class="lg:w-2/5 bg-gray-900 p-6 space-y-4 overflow-y-auto">
                                {{-- Available Editions Dropdown --}}
                                @if($modalEditions && count($modalEditions) > 0)
                                    <div>
                                        <label class="block text-sm font-medium text-gray-300 mb-2">
                                            Available Editions
                                        </label>
                                        <select 
                                            x-model="selectedEdition"
                                            class="w-full rounded-lg border-gray-600 bg-gray-800 text-white"
                                        >
                                            @foreach($modalEditions as $index => $edition)
                                                <option value="{{ $index }}">
                                                    {{ $edition['name'] ?? 'Standard Edition' }} #{{ $modalCardData['card_number'] }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </div>
                                @endif

                                {{-- Card Image --}}
                                <div class="flex items-center justify-center">
                                    @if($modalCardData['image'])
                                        <img 
                                            src="{{ $modalCardData['image'] }}" 
                                            alt="{{ $modalCardData['name'] }}"
                                            class="w-full max-w-sm rounded-lg shadow-2xl"
                                        />
                                    @else
                                        <div class="w-full max-w-sm aspect-[2.5/3.5] bg-gray-800 rounded-lg flex items-center justify-center border-2 border-gray-700">
                                            <span class="text-gray-500">No Image Available</span>
                                        </div>
                                    @endif
                                </div>

                                {{-- Edition Information Panel --}}
                                @if($modalEditions && count($modalEditions) > 0)
                                    @foreach($modalEditions as $index => $edition)
                                        <div x-show="selectedEdition == {{ $index }}" class="bg-gray-800 border-l-4 border-blue-500 rounded p-4 space-y-2">
                                            <h3 class="text-lg font-bold text-white mb-3">Edition Information</h3>
                                            
                                            {{-- Edition Name with Rarity Badge --}}
                                            <div class="flex items-center gap-2 mb-2">
                                                <span class="text-blue-400 font-semibold">{{ $edition['name'] ?? 'Standard Edition' }}</span>
                                                @php
                                                    $rarity = $modalCardData['rarity'] ?? 'common';
                                                    $rarityColors = [
                                                        'common' => 'bg-gray-500',
                                                        'uncommon' => 'bg-green-500',
                                                        'rare' => 'bg-blue-500',
                                                        'super rare' => 'bg-purple-500',
                                                        'ultra rare' => 'bg-yellow-500'
                                                    ];
                                                    $badgeColor = $rarityColors[strtolower($rarity)] ?? 'bg-gray-500';
                                                @endphp
                                                <span class="px-2 py-1 text-xs font-bold text-white rounded {{ $badgeColor }}">
                                                    {{ strtoupper(substr($rarity, 0, 1)) }}
                                                </span>
                                            </div>

                                            {{-- Edition Details --}}
                                            <div class="space-y-1 text-sm text-gray-300">
                                                <div><span class="text-gray-400">Prefix:</span> {{ $edition['prefix'] ?? 'N/A' }}</div>
                                                <div><span class="text-gray-400">#{{ $modalCardData['card_number'] ?? 'N/A' }}</span></div>
                                                <div><span class="text-gray-400">Config:</span> {{ $edition['config'] ?? 'default' }}</div>
                                            </div>
                                        </div>
                                    @endforeach
                                @endif
                            </div>

                            {{-- RIGHT SIDE: Card Details, Effect, Pricing --}}
                            <div class="lg:w-3/5 p-6 space-y-6 overflow-y-auto">
                                {{-- Card Title --}}
                                <div class="border-b border-gray-700 pb-4">
                                    <h2 class="text-3xl font-bold text-white mb-1">
                                        {{ $modalCardData['name'] }}
                                    </h2>
                                </div>

                                {{-- Card Details Grid --}}
                                <div class="bg-gray-900 rounded-lg p-4">
                                    <h3 class="text-lg font-semibold text-white mb-3">Card Details</h3>
                                    <div class="grid grid-cols-2 gap-3 text-sm">
                                        {{-- Element --}}
                                        <div>
                                            <span class="text-gray-400">Element:</span>
                                            <div class="mt-1">
                                                @if($modalCardData['elements'])
                                                    @foreach(is_array($modalCardData['elements']) ? $modalCardData['elements'] : [$modalCardData['elements']] as $element)
                                                        <span class="inline-flex items-center gap-1 text-white">
                                                            <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                                                                <circle cx="10" cy="10" r="8"/>
                                                            </svg>
                                                            {{ strtoupper($element) }}
                                                        </span>
                                                    @endforeach
                                                @else
                                                    <span class="text-gray-500">-</span>
                                                @endif
                                            </div>
                                        </div>

                                        {{-- Classes --}}
                                        <div>
                                            <span class="text-gray-400">Classes:</span>
                                            <div class="mt-1 text-white">
                                                {{ is_array($modalCardData['classes']) ? strtoupper(implode(', ', $modalCardData['classes'])) : strtoupper($modalCardData['classes'] ?? '-') }}
                                            </div>
                                        </div>

                                        {{-- Types --}}
                                        <div>
                                            <span class="text-gray-400">Types:</span>
                                            <div class="mt-1 text-white">
                                                {{ is_array($modalCardData['types']) ? strtoupper(implode(', ', $modalCardData['types'])) : strtoupper($modalCardData['types'] ?? '-') }}
                                            </div>
                                        </div>

                                        {{-- Subtypes --}}
                                        <div>
                                            <span class="text-gray-400">Subtypes:</span>
                                            <div class="mt-1 text-white">
                                                {{ is_array($modalCardData['subtypes']) ? strtoupper(implode(', ', $modalCardData['subtypes'])) : strtoupper($modalCardData['subtypes'] ?? '-') }}
                                            </div>
                                        </div>

                                        {{-- Memory Cost --}}
                                        <div>
                                            <span class="text-gray-400">Memory Cost:</span>
                                            <div class="mt-1 text-white">{{ $modalCardData['memory_cost'] ?? '-' }}</div>
                                        </div>

                                        {{-- Reserve Cost --}}
                                        <div>
                                            <span class="text-gray-400">Reserve Cost:</span>
                                            <div class="mt-1 text-white">{{ $modalCardData['reserve_cost'] ?? '-' }}</div>
                                        </div>

                                        {{-- Power --}}
                                        <div>
                                            <span class="text-gray-400">Power:</span>
                                            <div class="mt-1 text-white">{{ $modalCardData['power'] ?? '-' }}</div>
                                        </div>

                                        {{-- Life --}}
                                        <div>
                                            <span class="text-gray-400">Life:</span>
                                            <div class="mt-1 text-white">{{ $modalCardData['life'] ?? '-' }}</div>
                                        </div>

                                        {{-- Rarity --}}
                                        <div class="col-span-2">
                                            <span class="text-gray-400">Rarity:</span>
                                            <div class="mt-1 text-white">{{ $modalCardData['rarity'] ?? '-' }}</div>
                                        </div>
                                    </div>
                                </div>

                                {{-- Effect Text --}}
                                @if(!empty($modalCardData['effect_text']))
                                    <div class="bg-gray-900 rounded-lg p-4">
                                        <h3 class="text-lg font-semibold text-white mb-3">Effect</h3>
                                        <p class="text-gray-300 text-sm leading-relaxed whitespace-pre-line">
                                            {{ $modalCardData['effect_text'] }}
                                        </p>
                                    </div>
                                @endif

                                {{-- Pricing Section --}}
                                <div class="bg-gray-900 rounded-lg p-4">
                                    <h3 class="text-lg font-semibold text-white mb-3">Pricing</h3>
                                    
                                    @if($modalEditions && count($modalEditions) > 0)
                                        @foreach($modalEditions as $index => $edition)
                                            <div x-show="selectedEdition == {{ $index }}">
                                                @if(!empty($edition['prices']) && count($edition['prices']) > 0)
                                                    @foreach($edition['prices'] as $price)
                                                        <div class="grid grid-cols-3 gap-4 text-center">
                                                            <div>
                                                                <div class="text-xs text-gray-400 mb-1">Market</div>
                                                                <div class="text-2xl font-bold text-green-400">
                                                                    ${{ number_format($price['market_price'] ?? 0, 2) }}
                                                                </div>
                                                            </div>
                                                            <div>
                                                                <div class="text-xs text-gray-400 mb-1">Low</div>
                                                                <div class="text-2xl font-bold text-blue-400">
                                                                    ${{ number_format($price['low_price'] ?? 0, 2) }}
                                                                </div>
                                                            </div>
                                                            <div>
                                                                <div class="text-xs text-gray-400 mb-1">High</div>
                                                                <div class="text-2xl font-bold text-red-400">
                                                                    ${{ number_format($price['high_price'] ?? 0, 2) }}
                                                                </div>
                                                            </div>
                                                        </div>
                                                    @endforeach
                                                @else
                                                    <p class="text-gray-400 text-center py-4">No pricing data available for this edition</p>
                                                @endif
                                            </div>
                                        @endforeach
                                    @else
                                        <p class="text-gray-400 text-center py-4">No pricing data available</p>
                                    @endif
                                </div>

                                {{-- Current Inventory & Add/Remove to Inventory --}}
                                <div class="space-y-4">
                                    {{-- Current Inventory Display --}}
                                    @if($modalInventory && count($modalInventory) > 0)
                                        <div class="bg-blue-900/30 border border-blue-700 rounded-lg p-4">
                                            <div class="text-sm text-blue-300 mb-1">Current Inventory</div>
                                            <div class="text-3xl font-bold text-blue-400">
                                                {{ $modalInventory[0]['quantity'] ?? 0 }} in stock
                                            </div>
                                            @if($modalInventory[0]['custom_price'])
                                                <div class="text-sm text-green-300 mt-2">
                                                    Your Price: ${{ number_format($modalInventory[0]['custom_price'], 2) }}
                                                </div>
                                            @endif
                                        </div>
                                    @endif

                                    {{-- Custom Pricing Section --}}
                                    <div class="bg-gray-900 border border-gray-700 rounded-lg p-4">
                                        <h4 class="text-lg font-semibold text-white mb-3">Set Your Price</h4>
                                        
                                        @if($marketPrice)
                                            <div class="mb-3 text-sm text-gray-400">
                                                Market Price: <span class="text-green-400 font-semibold">${{ number_format($marketPrice, 2) }}</span>
                                            </div>
                                        @endif

                                        {{-- Custom Price Input --}}
                                        <div class="mb-3">
                                            <label class="block text-sm font-medium text-gray-300 mb-2">Custom Price</label>
                                            <div class="flex">
                                                <span class="flex items-center px-3 bg-gray-200 text-black font-semibold rounded-l-lg border border-r-0 border-gray-400">$</span>
                                                <input 
                                                    type="number" 
                                                    wire:model.live="customPrice"
                                                    step="0.01"
                                                    min="0"
                                                    placeholder="{{ $marketPrice ? number_format($marketPrice, 2) : '0.00' }}"
                                                    class="flex-1 rounded-r-lg border-gray-400 bg-gray-200 text-black placeholder-gray-600 focus:border-blue-500 focus:ring-blue-500 font-semibold"
                                                />
                                            </div>
                                        </div>

                                        {{-- Quick Price Buttons --}}
                                        @if($marketPrice)
                                            <div class="space-y-2">
                                                <label class="block text-sm font-medium text-gray-300">Quick Adjust</label>
                                                <div class="grid grid-cols-2 gap-2">
                                                    <button
                                                        wire:click="applyPricePercentage(-10)"
                                                        class="px-3 py-2 bg-red-600 hover:bg-red-700 text-white text-sm font-semibold rounded-lg transition"
                                                    >
                                                        -10% (${{ number_format($marketPrice * 0.9, 2) }})
                                                    </button>
                                                    <button
                                                        wire:click="applyPricePercentage(-5)"
                                                        class="px-3 py-2 bg-red-500 hover:bg-red-600 text-white text-sm font-semibold rounded-lg transition"
                                                    >
                                                        -5% (${{ number_format($marketPrice * 0.95, 2) }})
                                                    </button>
                                                    <button
                                                        wire:click="applyPricePercentage(5)"
                                                        class="px-3 py-2 bg-green-500 hover:bg-green-600 text-white text-sm font-semibold rounded-lg transition"
                                                    >
                                                        +5% (${{ number_format($marketPrice * 1.05, 2) }})
                                                    </button>
                                                    <button
                                                        wire:click="applyPricePercentage(10)"
                                                        class="px-3 py-2 bg-green-600 hover:bg-green-700 text-white text-sm font-semibold rounded-lg transition"
                                                    >
                                                        +10% (${{ number_format($marketPrice * 1.10, 2) }})
                                                    </button>
                                                </div>
                                                <button
                                                    wire:click="setCustomPrice({{ $marketPrice }})"
                                                    class="w-full px-3 py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-semibold rounded-lg transition"
                                                >
                                                    Use Market Price (${{ number_format($marketPrice, 2) }})
                                                </button>
                                            </div>
                                        @endif
                                    </div>

                                    {{-- Add to Inventory --}}
                                    <div class="bg-blue-900/30 border border-blue-700 rounded-lg p-4">
                                        <label class="block text-sm font-medium text-blue-300 mb-2">Add to Inventory</label>
                                        <div class="flex gap-3">
                                            <input 
                                                type="number" 
                                                wire:model="quantityToAdd"
                                                min="1"
                                                placeholder="Quantity"
                                                class="flex-1 rounded-lg border-gray-400 bg-gray-200 text-black placeholder-gray-600 font-semibold focus:border-blue-500 focus:ring-blue-500"
                                            />
                                            <button
                                                wire:click="addToInventory"
                                                class="px-6 py-2 bg-green-600 hover:bg-green-700 text-white font-semibold rounded-lg transition"
                                            >
                                                + Add
                                            </button>
                                        </div>
                                        @if($customPrice)
                                            <div class="mt-2 text-xs text-green-300">
                                                Will be added at ${{ number_format($customPrice, 2) }} each
                                            </div>
                                        @endif
                                    </div>

                                    {{-- Remove from Inventory --}}
                                    <div class="bg-red-900/30 border border-red-700 rounded-lg p-4">
                                        <label class="block text-sm font-medium text-red-300 mb-2">Remove from Inventory</label>
                                        <div class="flex gap-3">
                                            <input 
                                                type="number" 
                                                wire:model="quantityToRemove"
                                                min="1"
                                                placeholder="Quantity"
                                                class="flex-1 rounded-lg border-gray-400 bg-gray-200 text-black placeholder-gray-600 font-semibold focus:border-blue-500 focus:ring-blue-500"
                                            />
                                            <button
                                                wire:click="removeFromInventory"
                                                class="px-6 py-2 bg-red-600 hover:bg-red-700 text-white font-semibold rounded-lg transition"
                                            >
                                                - Remove
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        @endif

        {{-- Review Changes Modal --}}
        @if($showReviewModal)
            <div 
                class="fixed inset-0 z-50 overflow-y-auto"
                x-data="{ show: @entangle('showReviewModal') }"
                x-show="show"
                x-cloak
            >
                <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:p-0">
                    {{-- Overlay --}}
                    <div 
                        class="fixed inset-0 transition-opacity bg-black bg-opacity-95"
                        wire:click="closeReviewModal"
                        x-show="show"
                        x-transition:enter="ease-out duration-300"
                        x-transition:enter-start="opacity-0"
                        x-transition:enter-end="opacity-100"
                        x-transition:leave="ease-in duration-200"
                        x-transition:leave-start="opacity-100"
                        x-transition:leave-end="opacity-0"
                    ></div>

                    {{-- Modal --}}
                    <div 
                        class="relative inline-block w-full max-w-3xl my-8 overflow-hidden text-left align-middle transition-all transform shadow-2xl rounded-lg"
                        style="background-color: #1f2937;"
                        x-show="show"
                        x-transition:enter="ease-out duration-300"
                        x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                        x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
                        x-transition:leave="ease-in duration-200"
                        x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100"
                        x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                        @click.stop
                    >
                        <div class="p-6">
                            {{-- Header --}}
                            <div class="flex items-center justify-between mb-6">
                                <h2 class="text-2xl font-bold text-white">Review Stock Changes</h2>
                                <button 
                                    wire:click="closeReviewModal"
                                    class="text-gray-400 hover:text-white transition"
                                >
                                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                    </svg>
                                </button>
                            </div>

                            {{-- Changes List --}}
                            <div class="bg-gray-900 rounded-lg p-4 mb-6 max-h-96 overflow-y-auto">
                                <div class="space-y-3">
                                    @foreach($pendingChanges as $cardId => $change)
                                        <div class="p-3 bg-gray-800 rounded-lg">
                                            <div class="flex items-center justify-between">
                                                <div class="flex-1">
                                                    <div class="font-semibold text-white">{{ $change['name'] }}</div>
                                                    <div class="text-xs text-gray-400">Card ID: {{ $cardId }}</div>
                                                    @if(isset($change['custom_price']))
                                                        <div class="text-sm text-green-400 mt-1">
                                                            Price: ${{ number_format($change['custom_price'], 2) }}
                                                        </div>
                                                    @endif
                                                </div>
                                                <div class="text-right">
                                                    @if($change['change'] > 0)
                                                        <span class="text-2xl font-bold text-green-400">+{{ $change['change'] }}</span>
                                                    @else
                                                        <span class="text-2xl font-bold text-red-400">{{ $change['change'] }}</span>
                                                    @endif
                                                </div>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            </div>

                            {{-- Summary --}}
                            <div class="bg-blue-900/30 border border-blue-700 rounded-lg p-4 mb-6">
                                <div class="text-center">
                                    <div class="text-sm text-blue-300 mb-1">Total Cards Affected</div>
                                    <div class="text-3xl font-bold text-blue-400">{{ count($pendingChanges) }}</div>
                                </div>
                            </div>

                            {{-- Action Buttons --}}
                            <div class="flex gap-3">
                                <button
                                    wire:click="cancelPendingChanges"
                                    class="flex-1 px-6 py-3 bg-gray-700 hover:bg-gray-600 text-white font-semibold rounded-lg transition"
                                >
                                    Not Yet
                                </button>
                                <button
                                    wire:click="applyPendingChanges"
                                    class="flex-1 px-6 py-3 bg-green-600 hover:bg-green-700 text-white font-semibold rounded-lg transition"
                                >
                                    Update Stock
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        @endif
    </div>
</x-filament-panels::page>

