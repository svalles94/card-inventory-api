@php
    /** @var \App\Filament\Store\Resources\StoreCardResource\Pages\ViewStoreCard $this */
@endphp

<x-filament-panels::page>
    {{-- Card Images with Orientations (Flip Cards) - At the top --}}
    @php
        $orientations = $this->getCardOrientations();
    @endphp
    @if(!empty($orientations))
        <div class="mb-6 bg-white dark:bg-gray-800 rounded-lg shadow p-6">
            <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Card Images</h3>
            
            @if(count($orientations) > 1)
                <p class="text-sm text-gray-600 dark:text-gray-400 mb-4">This card has multiple sides (flip card). All sides share the same inventory.</p>
            @endif
            
            {{-- Edition Selector --}}
            @if($this->getAvailableEditions()->count() > 1)
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                        Select Edition
                    </label>
                    <select 
                        wire:model.live="selectedEditionId"
                        class="w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-800 dark:text-white text-sm focus:border-primary-500 focus:ring-primary-500"
                    >
                        @foreach($this->getAvailableEditions() as $edition)
                            <option value="{{ $edition->id }}" {{ $this->selectedEditionId == $edition->id ? 'selected' : '' }}>
                                {{ $this->getEditionLabel($edition) }}
                            </option>
                        @endforeach
                    </select>
                </div>
            @endif
            
            {{-- Card Images Grid --}}
            <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4">
                @foreach($orientations as $orientation)
                    <div class="text-center">
                        @if(count($orientations) > 1)
                            <div class="text-xs font-medium text-gray-700 dark:text-gray-300 mb-2">
                                {{ $orientation['name'] }}
                                @if($orientation['is_primary'])
                                    <span class="ml-1 text-xs text-primary-600 dark:text-primary-400">(Primary)</span>
                                @endif
                            </div>
                        @endif
                        <a href="{{ $orientation['image'] }}" target="_blank">
                            <img 
                                src="{{ $orientation['image'] }}" 
                                alt="{{ $orientation['name'] }}"
                                class="w-full max-w-[150px] rounded-lg shadow-lg mx-auto"
                                loading="lazy"
                            />
                        </a>
                    </div>
                @endforeach
            </div>
        </div>
    @endif
    
    {{-- Standard Filament View Form --}}
    {{ $this->form }}
    
    {{-- Editions Table with Prices --}}
    <x-filament::section>
        <x-slot name="heading">
            All Editions & Prices
        </x-slot>
        
        <x-slot name="description">
            View foil and non-foil market prices for all editions of this card
        </x-slot>
        
        <div class="overflow-x-auto">
            <table class="w-full divide-y divide-gray-200 dark:divide-gray-700">
                <thead class="bg-gray-50 dark:bg-gray-800">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                            Edition
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                            Collector #
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                            Rarity
                        </th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                            TCGPlayer Market (Foil)
                        </th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                            TCGPlayer Market (Non-Foil)
                        </th>
                    </tr>
                </thead>
                <tbody class="bg-white dark:bg-gray-900 divide-y divide-gray-200 dark:divide-gray-700">
                    @forelse($this->getAvailableEditions() as $edition)
                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-800">
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900 dark:text-white">
                                {{ $edition->slug ?: 'N/A' }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                {{ $edition->collector_number ?: 'N/A' }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                @php
                                    $rarityText = match ($edition->rarity) {
                                        1 => 'Common',
                                        2 => 'Uncommon',
                                        3 => 'Rare',
                                        4 => 'Super Rare',
                                        default => '—',
                                    };
                                    $rarityColor = match ($edition->rarity) {
                                        1 => 'bg-gray-500',
                                        2 => 'bg-green-500',
                                        3 => 'bg-yellow-500',
                                        4 => 'bg-purple-500',
                                        default => 'bg-gray-500',
                                    };
                                @endphp
                                <span class="px-2 py-1 text-xs font-semibold text-white rounded {{ $rarityColor }}">
                                    {{ $rarityText }}
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-right font-medium text-gray-900 dark:text-white">
                                @php
                                    $foilPrice = $this->getEditionFoilPrice($edition);
                                @endphp
                                {{ $foilPrice ? '$' . number_format($foilPrice, 2) : 'N/A' }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-right font-medium text-gray-900 dark:text-white">
                                @php
                                    $nonFoilPrice = $this->getEditionNonFoilPrice($edition);
                                @endphp
                                {{ $nonFoilPrice ? '$' . number_format($nonFoilPrice, 2) : 'N/A' }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-6 py-4 text-center text-sm text-gray-500 dark:text-gray-400">
                                No editions found
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-filament::section>
</x-filament-panels::page>

