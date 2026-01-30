<?php

namespace App\Filament\Store\Resources;

use App\Filament\Store\Resources\StoreMarketplaceIntegrationResource\Pages;
use App\Models\MarketplaceIntegration;
use App\Services\Marketplace\ShopifyInventorySync;
use App\Services\Marketplace\EbayInventorySync;
use App\Services\Marketplace\TcgPlayerInventorySync;
use App\Services\Marketplace\AmazonInventorySync;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;

class StoreMarketplaceIntegrationResource extends Resource
{
    protected static ?string $model = MarketplaceIntegration::class;

    protected static ?string $navigationIcon = 'heroicon-o-globe-alt';
    
    protected static ?string $navigationLabel = 'Marketplace Integrations';
    
    protected static ?string $navigationGroup = 'Marketplace';
    
    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        $currentStore = Auth::user()->currentStore();
        
        if (!$currentStore) {
            return $form->schema([]);
        }
        
        return $form
            ->schema([
                Forms\Components\Section::make('Marketplace Configuration')
                    ->description('Connect your store to online marketplaces for automatic inventory sync')
                    ->schema([
                        Forms\Components\Select::make('marketplace')
                            ->options([
                                'shopify' => 'Shopify',
                                'ebay' => 'eBay',
                                'tcgplayer' => 'TCGPlayer',
                                'amazon' => 'Amazon',
                            ])
                            ->required()
                            ->disabled(fn ($record) => $record !== null)
                            ->live()
                            ->helperText('Choose the marketplace platform'),
                        
                        Forms\Components\Toggle::make('enabled')
                            ->label('Enable Sync')
                            ->default(true)
                            ->helperText('Turn off to pause syncing without deleting credentials'),
                    ])->columns(2),
                
                // Shopify Credentials
                Forms\Components\Section::make('Shopify Settings')
                    ->description('Connect your Shopify store using OAuth (recommended) or manual setup')
                    ->schema([
                        // Show connection status if already connected
                        Forms\Components\Placeholder::make('connection_status')
                            ->label('Connection Status')
                            ->content(function ($record) {
                                if ($record && $record->marketplace === 'shopify' && !empty($record->credentials['access_token'])) {
                                    $shopUrl = $record->credentials['shop_url'] ?? 'Unknown';
                                    $shopName = str_replace(['https://', '.myshopify.com'], '', $shopUrl);
                                    return new \Illuminate\Support\HtmlString('
                                        <div class="p-3 bg-green-50 dark:bg-green-900/20 rounded-lg">
                                            <p class="text-sm text-green-800 dark:text-green-200">
                                                <strong>✓ Connected to:</strong> ' . htmlspecialchars($shopName) . '
                                            </p>
                                        </div>
                                    ');
                                }
                                return new \Illuminate\Support\HtmlString('
                                    <div class="p-3 bg-yellow-50 dark:bg-yellow-900/20 rounded-lg">
                                        <p class="text-sm text-yellow-800 dark:text-yellow-200">
                                            <strong>Not connected.</strong> Use OAuth button below or manual setup.
                                        </p>
                                    </div>
                                ');
                            })
                            ->visible(fn (Forms\Get $get) => $get('marketplace') === 'shopify'),
                        
                        // OAuth Connect Button
                        Forms\Components\Placeholder::make('oauth_connect')
                            ->label('OAuth Connection (Recommended)')
                            ->content(function ($record, Forms\Get $get) {
                                $currentStore = Auth::user()->currentStore();
                                if (!$currentStore) {
                                    return new \Illuminate\Support\HtmlString('<p class="text-sm text-gray-500">Please select a store first</p>');
                                }
                                
                                $shopDomain = $get('shop_domain') ?? '';
                                $isConnected = $record && !empty($record->credentials['access_token']);
                                
                                if ($isConnected) {
                                    return new \Illuminate\Support\HtmlString('
                                        <div class="mt-2">
                                            <a href="' . route('shopify.oauth.initiate', ['store_id' => $currentStore->id, 'shop' => $shopDomain ?: 'your-store']) . '" 
                                               class="inline-flex items-center px-4 py-2 bg-gray-500 hover:bg-gray-600 text-white font-semibold rounded-lg shadow-md transition-colors text-sm">
                                                Reconnect to Shopify
                                            </a>
                                        </div>
                                    ');
                                }
                                
                                return new \Illuminate\Support\HtmlString('
                                    <div class="mt-2">
                                        <p class="text-sm text-gray-600 dark:text-gray-400 mb-2">Enter shop domain below, then click Connect</p>
                                        <a href="' . route('shopify.oauth.initiate', ['store_id' => $currentStore->id, 'shop' => $shopDomain ?: 'your-store']) . '" 
                                           class="inline-flex items-center px-4 py-2 bg-[#95BF47] hover:bg-[#7FA63A] text-white font-semibold rounded-lg shadow-md transition-colors text-sm">
                                            <svg class="w-4 h-4 mr-2" fill="currentColor" viewBox="0 0 20 20">
                                                <path d="M10 0C4.477 0 0 4.477 0 10s4.477 10 10 10 10-4.477 10-10S15.523 0 10 0zm0 18c-4.411 0-8-3.589-8-8s3.589-8 8-8 8 3.589 8 8-3.589 8-8 8zm-1-11V9h6V7H9zm0 4v2h6v-2H9z"/>
                                            </svg>
                                            Connect to Shopify
                                        </a>
                                    </div>
                                ');
                            })
                            ->visible(fn (Forms\Get $get) => $get('marketplace') === 'shopify'),
                        
                        Forms\Components\TextInput::make('shop_domain')
                            ->label('Shop Domain (for OAuth)')
                            ->placeholder('your-store')
                            ->suffix('.myshopify.com')
                            ->helperText('Enter your Shopify store name (without .myshopify.com) for OAuth connection')
                            ->visible(fn (Forms\Get $get) => $get('marketplace') === 'shopify'),
                        
                        Forms\Components\Section::make('Manual Setup (Advanced)')
                            ->description('Only use if OAuth doesn\'t work')
                    ->schema([
                        Forms\Components\TextInput::make('credentials.shop_url')
                            ->label('Shop URL')
                            ->placeholder('https://your-store.myshopify.com')
                            ->url()
                                    ->helperText('Your Shopify store URL'),
                                
                                Forms\Components\TextInput::make('credentials.client_id')
                                    ->label('Client ID')
                                    ->helperText('From Partners dashboard'),
                                
                                Forms\Components\TextInput::make('credentials.client_secret')
                                    ->label('Client Secret')
                                    ->password()
                                    ->revealable()
                                    ->helperText('From Partners dashboard'),
                        
                        Forms\Components\TextInput::make('credentials.access_token')
                                    ->label('Access Token')
                            ->password()
                            ->revealable()
                                    ->helperText('Legacy static token'),
                            ])
                            ->collapsible()
                            ->collapsed()
                            ->visible(fn (Forms\Get $get) => $get('marketplace') === 'shopify'),
                        
                        Forms\Components\TextInput::make('settings.default_location_id')
                            ->label('Default Location ID')
                            ->placeholder('gid://shopify/Location/xxxxx')
                            ->helperText('Optional: Auto-selected after OAuth connection'),
                    ])
                    ->columns(1)
                    ->visible(fn (Forms\Get $get) => $get('marketplace') === 'shopify'),
                
                // eBay Credentials
                Forms\Components\Section::make('eBay Settings')
                    ->description('Get these credentials from eBay Developer Program')
                    ->schema([
                        Forms\Components\TextInput::make('credentials.client_id')
                            ->label('Client ID (App ID)')
                            ->required()
                            ->helperText('From eBay Developer Portal'),
                        
                        Forms\Components\TextInput::make('credentials.client_secret')
                            ->label('Client Secret (Cert ID)')
                            ->password()
                            ->revealable()
                            ->required(),
                        
                        Forms\Components\TextInput::make('credentials.oauth_token')
                            ->label('User OAuth Token')
                            ->password()
                            ->revealable()
                            ->required()
                            ->helperText('OAuth 2.0 User Access Token'),
                        
                        Forms\Components\Toggle::make('settings.sandbox_mode')
                            ->label('Use Sandbox Mode')
                            ->default(false)
                            ->helperText('Test with eBay sandbox environment'),
                    ])
                    ->columns(1)
                    ->visible(fn (Forms\Get $get) => $get('marketplace') === 'ebay'),
                
                // TCGPlayer Credentials
                Forms\Components\Section::make('TCGPlayer Settings')
                    ->description('Get these credentials from TCGPlayer Seller Portal')
                    ->schema([
                        Forms\Components\TextInput::make('credentials.api_key')
                            ->label('API Key')
                            ->password()
                            ->revealable()
                            ->required()
                            ->helperText('From TCGPlayer Seller Portal → API Access'),
                        
                        Forms\Components\TextInput::make('credentials.seller_key')
                            ->label('Seller Key')
                            ->password()
                            ->revealable()
                            ->required(),
                        
                        Forms\Components\Select::make('settings.pricing_strategy')
                            ->label('Pricing Strategy')
                            ->options([
                                'market' => 'Match Market Price',
                                'below_market' => '5% Below Market',
                                'fixed' => 'Fixed Prices',
                            ])
                            ->default('market'),
                    ])
                    ->columns(1)
                    ->visible(fn (Forms\Get $get) => $get('marketplace') === 'tcgplayer'),
                
                // Amazon Credentials
                Forms\Components\Section::make('Amazon Settings')
                    ->description('Get these credentials from Amazon Seller Central')
                    ->schema([
                        Forms\Components\TextInput::make('credentials.merchant_id')
                            ->label('Merchant ID')
                            ->required()
                            ->helperText('Your Amazon Seller ID'),
                        
                        Forms\Components\TextInput::make('credentials.marketplace_id')
                            ->label('Marketplace ID')
                            ->required()
                            ->helperText('e.g., ATVPDKIKX0DER for US'),
                        
                        Forms\Components\TextInput::make('credentials.access_key_id')
                            ->label('AWS Access Key ID')
                            ->required(),
                        
                        Forms\Components\TextInput::make('credentials.secret_access_key')
                            ->label('AWS Secret Access Key')
                            ->password()
                            ->revealable()
                            ->required(),
                        
                        Forms\Components\TextInput::make('credentials.role_arn')
                            ->label('IAM Role ARN')
                            ->required()
                            ->helperText('Amazon SP-API role ARN'),
                    ])
                    ->columns(1)
                    ->visible(fn (Forms\Get $get) => $get('marketplace') === 'amazon'),
                
                Forms\Components\Section::make('Sync Status')
                    ->schema([
                        Forms\Components\Placeholder::make('last_sync_at')
                            ->label('Last Synced')
                            ->content(fn ($record) => $record?->last_sync_at?->diffForHumans() ?? 'Never'),
                        
                        Forms\Components\Placeholder::make('created_at')
                            ->label('Created')
                            ->content(fn ($record) => $record?->created_at?->format('M d, Y H:i') ?? '-'),
                    ])
                    ->columns(2)
                    ->hidden(fn ($record) => $record === null),
            ]);
    }

    public static function table(Table $table): Table
    {
        $currentStore = Auth::user()->currentStore();
        
        if (!$currentStore) {
            return $table;
        }
        
        return $table
            ->modifyQueryUsing(function (Builder $query) use ($currentStore) {
                // Only show integrations for the current store
                return $query->where('store_id', $currentStore->id);
            })
            ->columns([
                Tables\Columns\TextColumn::make('marketplace')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => ucfirst($state))
                    ->color(fn (string $state): string => match ($state) {
                        'shopify' => 'success',
                        'ebay' => 'warning',
                        'tcgplayer' => 'info',
                        'amazon' => 'danger',
                        default => 'gray',
                    })
                    ->icon(fn (string $state): string => match ($state) {
                        'shopify' => 'heroicon-o-shopping-bag',
                        'ebay' => 'heroicon-o-shopping-cart',
                        'tcgplayer' => 'heroicon-o-rectangle-stack',
                        'amazon' => 'heroicon-o-cube',
                        default => 'heroicon-o-globe-alt',
                    }),
                
                Tables\Columns\IconColumn::make('enabled')
                    ->label('Status')
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-x-circle')
                    ->trueColor('success')
                    ->falseColor('danger'),
                
                Tables\Columns\TextColumn::make('last_sync_at')
                    ->label('Last Synced')
                    ->dateTime('M d, Y H:i')
                    ->sortable()
                    ->placeholder('Never')
                    ->description(fn ($record) => $record->last_sync_at?->diffForHumans()),
                
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime('M d, Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('marketplace')
                    ->options([
                        'shopify' => 'Shopify',
                        'ebay' => 'eBay',
                        'tcgplayer' => 'TCGPlayer',
                        'amazon' => 'Amazon',
                    ]),
                
                Tables\Filters\TernaryFilter::make('enabled')
                    ->label('Status')
                    ->placeholder('All integrations')
                    ->trueLabel('Enabled only')
                    ->falseLabel('Disabled only'),
            ])
            ->actions([
                Tables\Actions\Action::make('test_connection')
                    ->label('Test')
                    ->icon('heroicon-o-signal')
                    ->color('info')
                    ->requiresConfirmation()
                    ->modalHeading('Test Marketplace Connection')
                    ->modalDescription(fn ($record) => "Test the connection to {$record->marketplace}")
                    ->action(function (MarketplaceIntegration $record) {
                        try {
                            $success = match($record->marketplace) {
                                'shopify' => (new ShopifyInventorySync($record))->testConnection(false), // Don't force refresh for OAuth tokens
                                'ebay' => (new EbayInventorySync($record))->testConnection(),
                                'tcgplayer' => (new TcgPlayerInventorySync($record))->testConnection(),
                                'amazon' => (new AmazonInventorySync($record))->testConnection(),
                                default => throw new \Exception("Unsupported marketplace: {$record->marketplace}"),
                            };
                            
                            if ($success) {
                                Notification::make()
                                    ->success()
                                    ->title('Connection Successful')
                                    ->body("Successfully connected to {$record->marketplace}")
                                    ->send();
                            } else {
                                throw new \Exception('Connection test returned false');
                            }
                        } catch (\Exception $e) {
                            Notification::make()
                                ->danger()
                                ->title('Connection Failed')
                                ->body($e->getMessage())
                                ->send();
                        }
                    }),
                
                Tables\Actions\Action::make('sync_now')
                    ->label('Sync Now')
                    ->icon('heroicon-o-arrow-path')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading(fn ($record) => 'Sync Inventory to ' . ucfirst($record->marketplace ?? 'Marketplace'))
                    ->modalDescription(function (MarketplaceIntegration $record) {
                        if ($record->marketplace === 'shopify') {
                            // Count items that need syncing:
                            // 1. Items without products (need product creation)
                            // 2. Items with products but without variants (need variant creation)
                            // 3. Items with variants but quantities need updating (always sync quantities)
                            $needsProduct = \App\Models\Inventory::whereHas('location.store', fn($q) => $q->where('id', $record->store_id))
                                ->whereHas('card', function($q) {
                                    $q->where('sync_to_shopify', true)
                                      ->whereNull('shopify_product_id');
                                })
                                ->count();
                            
                            $needsVariant = 0;
                            if (\Illuminate\Support\Facades\Schema::hasColumn('inventory', 'shopify_variant_id')) {
                                $needsVariant = \App\Models\Inventory::whereHas('location.store', fn($q) => $q->where('id', $record->store_id))
                                    ->whereHas('card', function($q) {
                                        $q->where('sync_to_shopify', true)
                                          ->whereNotNull('shopify_product_id');
                                    })
                                    ->whereNull('shopify_variant_id')
                                    ->count();
                            }
                            
                            $total = $needsProduct + $needsVariant;
                            
                            if ($total === 0) {
                                return "All inventory items have products and variants. This will update inventory quantities for all items.";
                            }
                            
                            return "This will create {$needsProduct} products and {$needsVariant} variants, and update inventory quantities for all items. This may take a while for large inventories.";
                        }
                        return 'This will sync all inventory for this marketplace.';
                    })
                    ->action(function (MarketplaceIntegration $record) {
                        if ($record->marketplace === 'shopify') {
                            // Always sync - this will:
                            // 1. Create products for items without them
                            // 2. Create variants for items without them
                            // 3. Update inventory quantities for all items
                            
                            // Count total inventory items for this store
                            $totalCount = \App\Models\Inventory::whereHas('location.store', fn($q) => $q->where('id', $record->store_id))
                                ->whereHas('card', fn($q) => $q->where('sync_to_shopify', true))
                                ->count();
                            
                            // Run the sync command with --force to sync all items (including those with products/variants)
                            \Illuminate\Support\Facades\Artisan::call('shopify:sync-inventory', [
                                '--store' => $record->store_id,
                                '--force' => true, // Force sync even if products/variants exist
                                '--no-interaction' => true,
                            ]);
                            
                            $record->update(['last_sync_at' => now()]);
                        
                        Notification::make()
                            ->success()
                            ->title('Sync Started')
                                ->body("Syncing {$totalCount} inventory items to Shopify. This will create/update products, variants, and quantities. Check logs for progress.")
                                ->send();
                        } else {
                            // For other marketplaces, just update timestamp for now
                            $record->update(['last_sync_at' => now()]);
                            
                            Notification::make()
                                ->info()
                                ->title('Sync Not Implemented')
                                ->body('Sync for this marketplace is not yet implemented')
                            ->send();
                        }
                    })
                    ->visible(fn ($record) => $record->enabled),
                
                Tables\Actions\EditAction::make()
                    ->authorize(function (MarketplaceIntegration $record) {
                        $currentStore = Auth::user()->currentStore();
                        if (!$currentStore) {
                            return false;
                        }
                        return Auth::user()->can('manageIntegrations', $currentStore);
                    }),
                
                Tables\Actions\DeleteAction::make()
                    ->authorize(function (MarketplaceIntegration $record) {
                        $currentStore = Auth::user()->currentStore();
                        if (!$currentStore) {
                            return false;
                        }
                        return Auth::user()->can('manageIntegrations', $currentStore);
                    })
                    ->requiresConfirmation(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->authorize(function () {
                            $currentStore = Auth::user()->currentStore();
                            if (!$currentStore) {
                                return false;
                            }
                            return Auth::user()->can('manageIntegrations', $currentStore);
                        }),
                ]),
            ])
            ->emptyStateHeading('No marketplace integrations yet')
            ->emptyStateDescription('Connect your store to online marketplaces to automatically sync inventory.')
            ->emptyStateIcon('heroicon-o-globe-alt');
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListStoreMarketplaceIntegrations::route('/'),
            'create' => Pages\CreateStoreMarketplaceIntegration::route('/create'),
            'edit' => Pages\EditStoreMarketplaceIntegration::route('/{record}/edit'),
        ];
    }
}

