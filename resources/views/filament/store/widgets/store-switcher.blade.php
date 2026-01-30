<x-filament-widgets::widget>
    <x-filament::section>
        <x-slot name="heading">
            <div class="flex items-center justify-between">
                <span>Current Store</span>
                @if($this->getStores()->count() > 1)
                    <span class="text-xs text-gray-500 dark:text-gray-400">
                        {{ $this->getStores()->count() }} stores available
                    </span>
                @endif
            </div>
        </x-slot>

        <div class="space-y-4">
            @php
                $currentStore = $this->getCurrentStore();
            @endphp

            @if($currentStore)
                <div class="flex items-center justify-between p-4 bg-gray-50 dark:bg-gray-800 rounded-lg">
                    <div>
                        <h3 class="font-semibold text-gray-900 dark:text-white">{{ $currentStore->name }}</h3>
                        <p class="text-sm text-gray-600 dark:text-gray-400">
                            {{ $currentStore->locations()->count() }} location(s)
                        </p>
                    </div>
                    @if($this->getStores()->count() > 1)
                        <a href="{{ \Filament\Facades\Filament::getPanel('store')->getUrl('store-select') }}" 
                           class="text-primary-600 hover:text-primary-700 dark:text-primary-400 text-sm font-medium">
                            Switch Store
                        </a>
                    @endif
                </div>
            @else
                <div class="text-center py-4">
                    <p class="text-sm text-gray-500 dark:text-gray-400">No store selected</p>
                    @if($this->getStores()->count() > 0)
                        <a href="{{ \Filament\Facades\Filament::getPanel('store')->getUrl('store-select') }}" 
                           class="mt-2 inline-block text-primary-600 hover:text-primary-700 dark:text-primary-400 text-sm font-medium">
                            Select a store
                        </a>
                    @endif
                </div>
            @endif
        </div>
    </x-filament::section>
</x-filament-widgets::widget>

