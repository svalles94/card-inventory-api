<x-filament-widgets::widget>
    <x-filament::section>
        <div class="space-y-4">
            <div class="flex items-center justify-between">
                <h3 class="text-lg font-semibold">🔥 Hot Cards</h3>
                <span class="text-xs text-gray-500 dark:text-gray-400">Last 7 days</span>
            </div>
            
            @php
                $hotCards = $this->getHotCards();
            @endphp
            
            @if($hotCards->isEmpty())
                <div class="text-center py-8 text-gray-500 dark:text-gray-400">
                    <svg class="mx-auto h-12 w-12 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"/>
                    </svg>
                    No trending cards yet
                </div>
            @else
                <div class="space-y-2">
                    @foreach($hotCards as $item)
                        @if($item->card)
                            <div class="flex items-center justify-between p-3 bg-gray-50 dark:bg-gray-800 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700 transition">
                                <div class="flex-1">
                                    <div class="font-medium text-sm">{{ $item->card->name }}</div>
                                    <div class="text-xs text-gray-500 dark:text-gray-400">
                                        {{ ucwords(str_replace('-', ' ', $item->card->game)) }}
                                        @if($item->card->set_name)
                                            • {{ $item->card->set_name }}
                                        @endif
                                    </div>
                                </div>
                                <div class="text-right">
                                    <div class="font-bold text-lg text-orange-600 dark:text-orange-400">
                                        {{ $item->total_sold }}
                                    </div>
                                    <div class="text-xs text-gray-500 dark:text-gray-400">
                                        {{ $item->days_sold }} days
                                    </div>
                                </div>
                            </div>
                        @endif
                    @endforeach
                </div>
            @endif
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
