<x-filament-panels::page>
    <div class="space-y-6">
        <!-- Store Info Card -->
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
            <h2 class="text-xl font-semibold mb-4">Store Information</h2>
            @php
                $store = auth()->user()->currentStore();
            @endphp
            
            @if($store)
                <dl class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                        <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Store Name</dt>
                        <dd class="mt-1 text-lg font-semibold text-gray-900 dark:text-white">{{ $store->name }}</dd>
                    </div>
                    @if($store->email)
                    <div>
                        <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Email</dt>
                        <dd class="mt-1 text-sm text-gray-900 dark:text-white">{{ $store->email }}</dd>
                    </div>
                    @endif
                    @if($store->phone)
                    <div>
                        <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Phone</dt>
                        <dd class="mt-1 text-sm text-gray-900 dark:text-white">{{ $store->phone }}</dd>
                    </div>
                    @endif
                </dl>
            @else
                <p class="text-gray-500 dark:text-gray-400">No store selected</p>
            @endif
        </div>

        <!-- Quick Stats -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            @php
                $store = auth()->user()->currentStore();
                $locationCount = $store ? $store->locations()->count() : 0;
                $inventoryCount = $store ? \App\Models\Inventory::whereHas('location', function ($q) use ($store) {
                    $q->where('store_id', $store->id);
                })->sum('quantity') : 0;
                $lowStockCount = $store ? \App\Models\Inventory::whereHas('location', function ($q) use ($store) {
                    $q->where('store_id', $store->id);
                })->where('quantity', '<', 4)->where('quantity', '>', 0)->count() : 0;
            @endphp

            <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <svg class="h-8 w-8 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>
                        </svg>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Locations</p>
                        <p class="text-2xl font-semibold text-gray-900 dark:text-white">{{ $locationCount }}</p>
                    </div>
                </div>
            </div>

            <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <svg class="h-8 w-8 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/>
                        </svg>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Total Inventory</p>
                        <p class="text-2xl font-semibold text-gray-900 dark:text-white">{{ number_format($inventoryCount) }}</p>
                    </div>
                </div>
            </div>

            <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <svg class="h-8 w-8 text-yellow-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                        </svg>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Low Stock Items</p>
                        <p class="text-2xl font-semibold text-gray-900 dark:text-white">{{ $lowStockCount }}</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-filament-panels::page>

