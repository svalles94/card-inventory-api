<x-filament-panels::page>
    <div class="space-y-6">
        <!-- Hero Section -->
        <div class="bg-gradient-to-r from-blue-500 to-purple-600 dark:from-blue-600 dark:to-purple-700 rounded-lg p-6 text-white">
            <h2 class="text-2xl font-bold mb-2">🚀 Connect Your Marketplaces</h2>
            <p class="text-white/90">
                Automatically sync your card inventory to Shopify, eBay, TCGPlayer, and more. 
                Set it up once and let the system handle the rest!
            </p>
        </div>

        <!-- Existing Integrations -->
        @php
            $integrations = \App\Models\MarketplaceIntegration::with('store')->get();
        @endphp

        @if($integrations->isNotEmpty())
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
                <h3 class="text-lg font-semibold mb-4">Active Integrations</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                    @foreach($integrations as $integration)
                        <div class="border border-gray-200 dark:border-gray-700 rounded-lg p-4 {{ $integration->enabled ? 'border-l-4 border-l-green-500' : 'opacity-60' }}">
                            <div class="flex items-center justify-between mb-2">
                                <span class="text-lg font-bold">
                                    @switch($integration->marketplace)
                                        @case('shopify') 🛍️ Shopify @break
                                        @case('ebay') 🏪 eBay @break
                                        @case('tcgplayer') 🃏 TCGPlayer @break
                                        @case('amazon') 📦 Amazon @break
                                    @endswitch
                                </span>
                                @if($integration->enabled)
                                    <span class="px-2 py-1 text-xs font-semibold text-green-800 bg-green-100 dark:bg-green-900 dark:text-green-200 rounded">
                                        Active
                                    </span>
                                @else
                                    <span class="px-2 py-1 text-xs font-semibold text-gray-800 bg-gray-100 dark:bg-gray-900 dark:text-gray-200 rounded">
                                        Disabled
                                    </span>
                                @endif
                            </div>
                            <p class="text-sm text-gray-600 dark:text-gray-400 mb-2">
                                {{ $integration->store->name }}
                            </p>
                            @if($integration->last_sync_at)
                                <p class="text-xs text-gray-500">
                                    Last synced: {{ $integration->last_sync_at->diffForHumans() }}
                                </p>
                            @else
                                <p class="text-xs text-gray-500">
                                    Never synced
                                </p>
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        <!-- Setup Wizard -->
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow">
            <div class="p-6">
                <h3 class="text-lg font-semibold mb-4">Add New Integration</h3>
                <form wire:submit="submit">
                    {{ $this->form }}
                    
                    <div class="mt-6">
                        <x-filament::button type="submit" size="lg">
                            Save & Test Connection
                        </x-filament::button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Benefits Section -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
                <div class="text-3xl mb-3">⚡</div>
                <h4 class="font-semibold mb-2">Instant Sync</h4>
                <p class="text-sm text-gray-600 dark:text-gray-400">
                    Inventory updates push to marketplaces in real-time when you adjust quantities
                </p>
            </div>
            
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
                <div class="text-3xl mb-3">🔒</div>
                <h4 class="font-semibold mb-2">Secure Storage</h4>
                <p class="text-sm text-gray-600 dark:text-gray-400">
                    API credentials are encrypted and stored securely in the database
                </p>
            </div>
            
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
                <div class="text-3xl mb-3">🎯</div>
                <h4 class="font-semibold mb-2">Multi-Platform</h4>
                <p class="text-sm text-gray-600 dark:text-gray-400">
                    Connect multiple marketplaces and manage them all from one place
                </p>
            </div>
        </div>
    </div>
</x-filament-panels::page>

