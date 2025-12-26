<x-filament-panels::page>
    <div class="space-y-6">
        <div class="text-center mb-8">
            <h2 class="text-2xl font-bold text-gray-900 dark:text-white mb-2">
                Select a Store
            </h2>
            <p class="text-gray-600 dark:text-gray-400">
                Choose which store you want to manage
            </p>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            @foreach(auth()->user()->stores as $store)
                @php
                    $userRole = auth()->user()->storeRoles()
                        ->where('store_id', $store->id)
                        ->first();
                    $role = $userRole ? $userRole->role : 'base';
                    $isCurrentStore = session('current_store_id') == $store->id;
                @endphp

                <div class="bg-white dark:bg-gray-800 rounded-lg shadow-lg border-2 {{ $isCurrentStore ? 'border-primary-500' : 'border-gray-200 dark:border-gray-700' }} p-6 hover:shadow-xl transition-shadow">
                    <div class="flex items-start justify-between mb-4">
                        <div>
                            <h3 class="text-xl font-bold text-gray-900 dark:text-white mb-1">
                                {{ $store->name }}
                            </h3>
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                                {{ $role === 'owner' ? 'bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-200' : '' }}
                                {{ $role === 'admin' ? 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200' : '' }}
                                {{ $role === 'base' ? 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-200' : '' }}">
                                {{ ucfirst($role) }}
                            </span>
                        </div>
                        @if($isCurrentStore)
                            <svg class="h-6 w-6 text-primary-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                        @endif
                    </div>

                    <div class="space-y-2 mb-4">
                        @if($store->email)
                            <div class="flex items-center text-sm text-gray-600 dark:text-gray-400">
                                <svg class="h-4 w-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                                </svg>
                                {{ $store->email }}
                            </div>
                        @endif
                        @if($store->phone)
                            <div class="flex items-center text-sm text-gray-600 dark:text-gray-400">
                                <svg class="h-4 w-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/>
                                </svg>
                                {{ $store->phone }}
                            </div>
                        @endif
                        <div class="flex items-center text-sm text-gray-600 dark:text-gray-400">
                            <svg class="h-4 w-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>
                            </svg>
                            {{ $store->locations()->count() }} location(s)
                        </div>
                    </div>

                    <button 
                        wire:click="switchStore({{ $store->id }})"
                        class="w-full bg-primary-600 hover:bg-primary-700 text-white font-medium py-2 px-4 rounded-lg transition-colors {{ $isCurrentStore ? 'opacity-50 cursor-not-allowed' : '' }}"
                        {{ $isCurrentStore ? 'disabled' : '' }}>
                        @if($isCurrentStore)
                            Currently Selected
                        @else
                            Select Store
                        @endif
                    </button>
                </div>
            @endforeach
        </div>

        @if(auth()->user()->stores()->count() === 0)
            <div class="text-center py-12">
                <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4"/>
                </svg>
                <h3 class="mt-2 text-sm font-medium text-gray-900 dark:text-white">No stores</h3>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    You don't have access to any stores yet.
                </p>
            </div>
        @endif
    </div>
</x-filament-panels::page>

