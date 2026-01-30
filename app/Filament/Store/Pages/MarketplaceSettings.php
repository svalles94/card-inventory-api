<?php

namespace App\Filament\Store\Pages;

use App\Models\MarketplaceIntegration;
use App\Models\Store;
use App\Services\Marketplace\ShopifyInventorySync;
use Filament\Actions\Action;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\Wizard;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

class MarketplaceSettings extends Page implements HasForms
{
    use InteractsWithForms;
    
    protected static ?string $navigationIcon = 'heroicon-o-cog-6-tooth';
    
    protected static ?string $navigationLabel = 'Marketplace Setup';
    
    protected static ?string $navigationGroup = 'Settings';
    
    protected static string $view = 'filament.store.pages.marketplace-settings';
    
    protected static ?int $navigationSort = 4;
    
    public ?array $data = [];
    
    public function mount(): void
    {
        $this->form->fill();
        
        // Show success message if redirected from OAuth callback
        if (session('shopify_connected')) {
            $shopName = session('shop_name', 'Shopify');
            Notification::make()
                ->success()
                ->title('Shopify Connected!')
                ->body("Successfully connected to {$shopName}. Your inventory will now sync automatically.")
                ->send();
            
            session()->forget(['shopify_connected', 'shop_name']);
        }
        
        // Show error message if OAuth failed
        if (session('shopify_error')) {
            Notification::make()
                ->danger()
                ->title('Connection Failed')
                ->body(session('shopify_error'))
                ->send();
            
            session()->forget('shopify_error');
        }
    }
    
    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Wizard::make([
                    Wizard\Step::make('Choose Store')
                        ->description('Select which store to configure')
                        ->schema([
                            Select::make('store_id')
                                ->label('Store')
                                ->options(Store::pluck('name', 'id'))
                                ->required()
                                ->live()
                                ->helperText('Select the store you want to connect to marketplaces'),
                        ]),
                    
                    Wizard\Step::make('Choose Marketplace')
                        ->description('Select the marketplace platform')
                        ->schema([
                            Select::make('marketplace')
                                ->options([
                                    'shopify' => '🛍️ Shopify - E-commerce platform',
                                    'ebay' => '🏪 eBay - Auction marketplace',
                                    'tcgplayer' => '🃏 TCGPlayer - Trading card marketplace',
                                    'amazon' => '📦 Amazon - Global marketplace',
                                ])
                                ->required()
                                ->live()
                                ->helperText('Choose which marketplace you want to integrate'),
                        ]),
                    
                    Wizard\Step::make('Shopify Configuration')
                        ->description('Connect your Shopify store')
                        ->schema([
                            TextInput::make('shop_domain')
                                ->label('Shop Domain')
                                ->placeholder('your-store')
                                ->suffix('.myshopify.com')
                                ->required()
                                ->helperText('Enter your Shopify store name (without .myshopify.com). Example: mystore'),
                            
                            \Filament\Forms\Components\Placeholder::make('connect_button')
                                ->label('')
                                ->content(function ($get) {
                                    $storeId = $get('store_id');
                                    $shopDomain = $get('shop_domain');
                                    
                                    if (empty($storeId) || empty($shopDomain)) {
                                        return new \Illuminate\Support\HtmlString('<p class="text-sm text-gray-500 mt-4">Please select a store and enter shop domain first</p>');
                                    }
                                    
                                    $url = route('shopify.oauth.initiate', [
                                        'store_id' => $storeId,
                                        'shop' => $shopDomain
                                    ]);
                                    
                                    return new \Illuminate\Support\HtmlString('
                                        <div class="mt-4">
                                            <a href="' . htmlspecialchars($url) . '" 
                                               class="inline-flex items-center px-4 py-2 bg-[#95BF47] hover:bg-[#7FA63A] text-white font-semibold rounded-lg shadow-md transition-colors">
                                                <svg class="w-5 h-5 mr-2" fill="currentColor" viewBox="0 0 20 20">
                                                    <path d="M10 0C4.477 0 0 4.477 0 10s4.477 10 10 10 10-4.477 10-10S15.523 0 10 0zm0 18c-4.411 0-8-3.589-8-8s3.589-8 8-8 8 3.589 8 8-3.589 8-8 8zm-1-11V9h6V7H9zm0 4v2h6v-2H9z"/>
                                                </svg>
                                                Connect to Shopify
                                            </a>
                                        </div>
                                    ');
                                })
                                ->visible(fn ($get) => !empty($get('shop_domain')) && !empty($get('store_id'))),
                            
                            Section::make('How It Works')
                                ->description('Simple one-click connection')
                                ->schema([
                                    \Filament\Forms\Components\Placeholder::make('instructions')
                                        ->content(new \Illuminate\Support\HtmlString('
                                            <div class="prose dark:prose-invert max-w-none text-sm">
                                                <ol class="list-decimal list-inside space-y-2">
                                                    <li>Enter your Shopify store name above</li>
                                                    <li>Click "Connect to Shopify" button</li>
                                                    <li>You\'ll be redirected to Shopify to authorize the connection</li>
                                                    <li>Click "Install" on the Shopify authorization page</li>
                                                    <li>You\'ll be redirected back and your store will be connected!</li>
                                                </ol>
                                                <p class="mt-4 text-xs text-gray-600 dark:text-gray-400">
                                                    No technical knowledge required. The connection is secure and you can revoke access anytime from your Shopify admin.
                                                </p>
                                            </div>
                                        ')),
                                ])
                                ->collapsible()
                                ->collapsed(),
                            
                            Toggle::make('enabled')
                                ->label('Enable Automatic Sync')
                                ->default(true)
                                ->helperText('Start syncing inventory immediately after connection'),
                        ])
                        ->visible(fn ($get) => $get('marketplace') === 'shopify'),
                    
                    Wizard\Step::make('eBay Configuration')
                        ->description('Enter your eBay developer credentials')
                        ->schema([
                            TextInput::make('credentials.client_id')
                                ->label('Client ID (App ID)')
                                ->required()
                                ->helperText('From eBay Developer Portal'),
                            
                            TextInput::make('credentials.client_secret')
                                ->label('Client Secret')
                                ->password()
                                ->revealable()
                                ->required(),
                            
                            TextInput::make('credentials.oauth_token')
                                ->label('OAuth Token')
                                ->password()
                                ->revealable()
                                ->required(),
                            
                            Toggle::make('enabled')
                                ->label('Enable Automatic Sync')
                                ->default(true),
                        ])
                        ->visible(fn ($get) => $get('marketplace') === 'ebay'),
                ])
                ->submitAction(new \Illuminate\Support\HtmlString('
                    <button type="submit" class="filament-button filament-button-size-md filament-button-primary">
                        Continue
                    </button>
                ')),
            ])
            ->statePath('data');
    }
    
    public function submit(): void
    {
        $data = $this->form->getState();
        
        try {
            // For Shopify, redirect to OAuth instead of saving
            if ($data['marketplace'] === 'shopify') {
                $storeId = $data['store_id'] ?? null;
                $shopDomain = $data['shop_domain'] ?? null;
                
                if (empty($storeId) || empty($shopDomain)) {
                    Notification::make()
                        ->warning()
                        ->title('Missing Information')
                        ->body('Please select a store and enter your shop domain, then click "Connect to Shopify" button.')
                        ->send();
                    return;
                }
                
                // Redirect to OAuth flow using Livewire redirect
                $oauthUrl = route('shopify.oauth.initiate', [
                    'store_id' => $storeId,
                    'shop' => $shopDomain
                ]);
                
                $this->redirect($oauthUrl);
                return;
            }
            
            // For other marketplaces, save normally
            $integration = MarketplaceIntegration::updateOrCreate(
                [
                    'store_id' => $data['store_id'],
                    'marketplace' => $data['marketplace'],
                ],
                [
                    'enabled' => $data['enabled'] ?? true,
                    'credentials' => $data['credentials'] ?? [],
                    'settings' => $data['settings'] ?? [],
                ]
            );
            
            // Test connection for non-Shopify marketplaces if enabled
            if ($data['marketplace'] !== 'shopify' && ($data['enabled'] ?? true)) {
                // Test connection for non-Shopify marketplaces
                // (Add connection testing for eBay, TCGPlayer, Amazon if needed)
            }
            
            Notification::make()
                ->success()
                ->title('Integration Saved')
                ->body("Marketplace integration configured successfully.")
                ->send();
            
            $this->form->fill();
            
        } catch (\Exception $e) {
            Notification::make()
                ->danger()
                ->title('Configuration Failed')
                ->body($e->getMessage())
                ->send();
        }
    }
    
    protected function getHeaderActions(): array
    {
        return [
            Action::make('bulk_sync')
                ->label('Sync All Inventory to Shopify')
                ->icon('heroicon-o-arrow-path')
                ->color('success')
                ->requiresConfirmation()
                ->modalHeading('Bulk Sync to Shopify')
                ->modalDescription('This will create products in Shopify for all inventory items that don\'t have products yet. This may take a while for large inventories.')
                ->modalSubmitActionLabel('Start Sync')
                ->action(function () {
                    $store = \Illuminate\Support\Facades\Auth::user()?->currentStore();
                    
                    if (!$store) {
                        Notification::make()
                            ->danger()
                            ->title('No Store Selected')
                            ->body('Please select a store first')
                            ->send();
                        return;
                    }
                    
                    $integration = MarketplaceIntegration::where('store_id', $store->id)
                        ->where('marketplace', 'shopify')
                        ->where('enabled', true)
                        ->first();
                    
                    if (!$integration) {
                        Notification::make()
                            ->warning()
                            ->title('No Shopify Integration')
                            ->body('Please configure Shopify integration first')
                            ->send();
                        return;
                    }
                    
                    // Count items to sync (check shopify_inventory_item_id on cards table, not inventory)
                    $count = \App\Models\Inventory::whereHas('location.store', fn($q) => $q->where('id', $store->id))
                        ->whereHas('card', function($q) {
                            $q->where('sync_to_shopify', true)
                              ->whereNull('shopify_inventory_item_id');
                        })
                        ->count();
                    
                    if ($count === 0) {
                        Notification::make()
                            ->info()
                            ->title('Nothing to Sync')
                            ->body('All inventory items already have Shopify products')
                            ->send();
                        return;
                    }
                    
                    // Dispatch job or run command
                    Notification::make()
                        ->info()
                        ->title('Sync Started')
                        ->body("Syncing {$count} inventory items to Shopify. Check logs for progress.")
                        ->send();
                    
                    // Run the sync command in background (non-interactive)
                    \Illuminate\Support\Facades\Artisan::call('shopify:sync-inventory', [
                        '--store' => $store->id,
                        '--no-interaction' => true,
                    ]);
                })
                ->visible(fn () => \Illuminate\Support\Facades\Auth::user()?->currentStore() !== null),
            
            Action::make('view_integrations')
                ->label('View All Integrations')
                ->url(route('filament.store.resources.store-marketplace-integrations.index'))
                ->icon('heroicon-o-queue-list')
                ->color('gray'),
        ];
    }
}
