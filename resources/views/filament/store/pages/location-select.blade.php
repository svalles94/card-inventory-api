<x-filament-panels::page>
    @php
        $store = auth()->user()->currentStore();
        $locations = $store?->locations()->get() ?? collect();
        $currentLocationId = session('current_location_id');
    @endphp

    <div class="space-y-6">
        <div class="text-center mb-8">
            <h2 class="text-2xl font-bold text-gray-900 dark:text-white mb-2">
                Select a Location
            </h2>
            <p class="text-gray-600 dark:text-gray-400">
                Choose which location you're currently working in for {{ $store?->name ?? 'your store' }}
            </p>
        </div>

        @if(!$store)
            <div class="text-center py-12">
                <p class="text-sm text-gray-500 dark:text-gray-400">No store selected. Please pick a store first.</p>
                <a href="{{ route('filament.store.pages.store-select') }}" class="mt-4 inline-flex items-center px-4 py-2 rounded-lg bg-primary-600 text-white hover:bg-primary-700">
                    Go to Store Select
                </a>
            </div>
        @else
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                @forelse($locations as $location)
                    @php
                        $isCurrent = $currentLocationId == $location->id;
                    @endphp
                    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-lg border-2 {{ $isCurrent ? 'border-primary-500' : 'border-gray-200 dark:border-gray-700' }} p-6 hover:shadow-xl transition-shadow">
                        <div class="flex items-start justify-between mb-4">
                            <div>
                                <h3 class="text-xl font-bold text-gray-900 dark:text-white mb-1">
                                    {{ $location->name }}
                                </h3>
                                <p class="text-sm text-gray-600 dark:text-gray-400">
                                    {{ $location->address ?? 'No address on file' }}
                                </p>
                            </div>
                            @if($isCurrent)
                                <svg class="h-6 w-6 text-primary-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                </svg>
                            @endif
                        </div>

                        <div class="space-y-2 mb-4">
                            @if($location->phone)
                                <div class="flex items-center text-sm text-gray-600 dark:text-gray-400">
                                    <svg class="h-4 w-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/>
                                    </svg>
                                    {{ $location->phone }}
                                </div>
                            @endif
                        </div>

                        <button
                            wire:click="switchLocation({{ $location->id }})"
                            class="w-full bg-primary-600 hover:bg-primary-700 text-white font-medium py-2 px-4 rounded-lg transition-colors {{ $isCurrent ? 'opacity-50 cursor-not-allowed' : '' }}"
                            {{ $isCurrent ? 'disabled' : '' }}
                        >
                            @if($isCurrent)
                                Currently Selected
                            @else
                                Select Location
                            @endif
                        </button>
                    </div>
                @empty
                    <div class="col-span-full text-center py-12">
                        <p class="text-sm text-gray-500 dark:text-gray-400">No locations yet. Create one to begin.</p>
                        <a href="{{ \App\Filament\Store\Resources\StoreLocationResource::getUrl() }}" class="mt-4 inline-flex items-center px-4 py-2 rounded-lg bg-primary-600 text-white hover:bg-primary-700">
                            Manage Locations
                        </a>
                    </div>
                @endforelse
            </div>
        @endif
    </div>
</x-filament-panels::page>
