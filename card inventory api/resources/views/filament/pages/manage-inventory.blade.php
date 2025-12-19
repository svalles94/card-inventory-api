<x-filament-panels::page>
    <div class="flex gap-6">
        {{-- Left Sidebar --}}
        <div class="w-64 space-y-6 shrink-0">
            {{-- Location Selector --}}
            <div class="p-4 bg-white dark:bg-gray-800 rounded-lg shadow">
                <h3 class="text-lg font-semibold mb-3 text-gray-900 dark:text-white">
                    Current Location
                </h3>
                
                @if($this->getAccessibleLocations()->isNotEmpty())
                    @php
                        $currentLocation = $this->getAccessibleLocations()->firstWhere('id', $selectedLocationId);
                    @endphp
                    
                    @if($currentLocation)
                        <div class="mb-4 p-3 bg-primary-50 dark:bg-primary-900/20 rounded-lg border border-primary-200 dark:border-primary-800">
                            <div class="text-sm font-medium text-primary-700 dark:text-primary-400">
                                {{ $currentLocation->store->name }}
                            </div>
                            <div class="text-lg font-bold text-primary-900 dark:text-primary-300">
                                {{ $currentLocation->name }}
                            </div>
                        </div>
                    @endif
                    
                    <div class="space-y-2">
                        <label class="text-sm font-medium text-gray-700 dark:text-gray-300">
                            Switch Location
                        </label>
                        <select 
                            wire:model.live="selectedLocationId"
                            class="w-full rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-900 focus:border-primary-500 focus:ring-primary-500"
                        >
                            @foreach($this->getAccessibleLocations() as $location)
                                <option value="{{ $location->id }}">
                                    {{ $location->store->name }} - {{ $location->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                @else
                    <div class="text-sm text-gray-500 dark:text-gray-400">
                        No locations available
                    </div>
                @endif
            </div>
            
            {{-- Game Selector --}}
            <div class="p-4 bg-white dark:bg-gray-800 rounded-lg shadow">
                <h3 class="text-lg font-semibold mb-3 text-gray-900 dark:text-white">
                    Card Game
                </h3>
                
                <div class="space-y-2">
                    <button 
                        wire:click="selectGame('grand-archive')"
                        class="w-full text-left px-4 py-3 rounded-lg transition-colors {{ $selectedGame === 'grand-archive' ? 'bg-primary-100 dark:bg-primary-900/30 border-2 border-primary-500 text-primary-900 dark:text-primary-300 font-semibold' : 'bg-gray-50 dark:bg-gray-900 hover:bg-gray-100 dark:hover:bg-gray-700 text-gray-700 dark:text-gray-300' }}"
                    >
                        <div class="flex items-center gap-2">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
                            </svg>
                            Grand Archive
                        </div>
                    </button>
                    
                    <button 
                        wire:click="selectGame('gundam')"
                        class="w-full text-left px-4 py-3 rounded-lg transition-colors {{ $selectedGame === 'gundam' ? 'bg-primary-100 dark:bg-primary-900/30 border-2 border-primary-500 text-primary-900 dark:text-primary-300 font-semibold' : 'bg-gray-50 dark:bg-gray-900 hover:bg-gray-100 dark:hover:bg-gray-700 text-gray-700 dark:text-gray-300' }}"
                    >
                        <div class="flex items-center gap-2">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                            </svg>
                            Gundam
                        </div>
                    </button>
                    
                    <button 
                        wire:click="selectGame('riftbound')"
                        class="w-full text-left px-4 py-3 rounded-lg transition-colors {{ $selectedGame === 'riftbound' ? 'bg-primary-100 dark:bg-primary-900/30 border-2 border-primary-500 text-primary-900 dark:text-primary-300 font-semibold' : 'bg-gray-50 dark:bg-gray-900 hover:bg-gray-100 dark:hover:bg-gray-700 text-gray-700 dark:text-gray-300' }}"
                    >
                        <div class="flex items-center gap-2">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
                            </svg>
                            Riftbound
                        </div>
                    </button>
                </div>
            </div>
            
            {{-- Quick Stats --}}
            <div class="p-4 bg-white dark:bg-gray-800 rounded-lg shadow">
                <h3 class="text-sm font-semibold mb-3 text-gray-600 dark:text-gray-400 uppercase tracking-wide">
                    Quick Stats
                </h3>
                
                @if($selectedLocationId)
                    @php
                        $locationInventory = \App\Models\Inventory::where('location_id', $selectedLocationId)
                            ->whereHas('card', fn($q) => $q->where('game', $selectedGame));
                        $totalCards = $locationInventory->sum('quantity');
                        $uniqueCards = $locationInventory->where('quantity', '>', 0)->count();
                        $lowStock = $locationInventory->where('quantity', '>', 0)->where('quantity', '<', 4)->count();
                    @endphp
                    
                    <div class="space-y-3">
                        <div class="flex justify-between items-center">
                            <span class="text-sm text-gray-600 dark:text-gray-400">Total Cards:</span>
                            <span class="text-lg font-bold text-gray-900 dark:text-white">{{ number_format($totalCards) }}</span>
                        </div>
                        <div class="flex justify-between items-center">
                            <span class="text-sm text-gray-600 dark:text-gray-400">Unique Cards:</span>
                            <span class="text-lg font-bold text-success-600 dark:text-success-400">{{ number_format($uniqueCards) }}</span>
                        </div>
                        <div class="flex justify-between items-center">
                            <span class="text-sm text-gray-600 dark:text-gray-400">Low Stock:</span>
                            <span class="text-lg font-bold {{ $lowStock > 0 ? 'text-danger-600 dark:text-danger-400' : 'text-gray-400' }}">
                                {{ number_format($lowStock) }}
                            </span>
                        </div>
                    </div>
                @else
                    <div class="text-sm text-gray-500 dark:text-gray-400">
                        Select a location to view stats
                    </div>
                @endif
            </div>
        </div>
        
        {{-- Main Content Area --}}
        <div class="flex-1 min-w-0">
            <div class="mb-4 flex justify-between items-center">
                <div>
                    <h2 class="text-2xl font-bold text-gray-900 dark:text-white">
                        {{ ucwords(str_replace('-', ' ', $selectedGame)) }} Cards
                    </h2>
                    <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                        Browse and manage your card inventory
                    </p>
                </div>
                
                <div>
                    <button 
                        wire:click="toggleEditMode"
                        class="px-4 py-2 rounded-lg font-semibold transition-colors {{ $editMode ? 'bg-success-600 hover:bg-success-700 text-white' : 'bg-primary-600 hover:bg-primary-700 text-white' }}"
                    >
                        @if($editMode)
                            <span class="flex items-center gap-2">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                </svg>
                                Edit Mode Active
                            </span>
                        @else
                            <span class="flex items-center gap-2">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                </svg>
                                Enable Edit Mode
                            </span>
                        @endif
                    </button>
                </div>
            </div>
            
            @if(!$selectedLocationId)
                <div class="p-8 text-center bg-white dark:bg-gray-800 rounded-lg shadow">
                    <svg class="w-16 h-16 mx-auto text-gray-400 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                    </svg>
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-2">
                        Select a Location
                    </h3>
                    <p class="text-gray-500 dark:text-gray-400">
                        Choose a location from the sidebar to start managing inventory
                    </p>
                </div>
            @else
                {{ $this->table }}
            @endif
        </div>
    </div>
</x-filament-panels::page>

