<?php

namespace App\Filament\Resources;

use App\Filament\Resources\MarketplaceIntegrationResource\Pages;
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

class MarketplaceIntegrationResource extends Resource
{
    protected static ?string $model = MarketplaceIntegration::class;

    protected static ?string $navigationIcon = 'heroicon-o-globe-alt';
    
    protected static ?string $navigationLabel = 'Marketplace Integrations';
    
    protected static ?string $navigationGroup = 'Administration';
    
    protected static ?int $navigationSort = 3;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Marketplace Configuration')
                    ->description('Connect your store to online marketplaces for automatic inventory sync')
                    ->schema([
                        Forms\Components\Select::make('store_id')
                            ->relationship('store', 'name')
                            ->required()
                            ->disabled(fn ($record) => $record !== null)
                            ->helperText('Select the store this integration belongs to'),
                        
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
                    ])->columns(3),
                
                // Shopify Credentials
                Forms\Components\Section::make('Shopify Settings')
                    ->description('Get these credentials from your Shopify admin panel')
                    ->schema([
                        Forms\Components\TextInput::make('credentials.shop_url')
                            ->label('Shop URL')
                            ->placeholder('https://your-store.myshopify.com')
                            ->url()
                            ->required()
                            ->helperText('Your Shopify store URL (must include https://)'),
                        
                        Forms\Components\TextInput::make('credentials.access_token')
                            ->label('Admin API Access Token')
                            ->placeholder('shpat_xxxxxxxxxxxxx')
                            ->password()
                            ->revealable()
                            ->required()
                            ->helperText('From Apps → Develop apps → Your app → API credentials'),
                        
                        Forms\Components\TextInput::make('settings.default_location_id')
                            ->label('Default Location ID')
                            ->placeholder('gid://shopify/Location/xxxxx')
                            ->helperText('Optional: Shopify location GID for inventory sync'),
                        
                        Forms\Components\Placeholder::make('shopify_setup')
                            ->label('Setup Instructions')
                            ->content(new \Illuminate\Support\HtmlString('
                                <div class="text-sm space-y-2">
                                    <p class="font-semibold">How to get Shopify credentials:</p>
                                    <ol class="list-decimal ml-4 space-y-1">
                                        <li>Go to your Shopify Admin → Settings → Apps and sales channels</li>
                                        <li>Click "Develop apps" → "Create an app"</li>
                                        <li>Name it "Card Inventory Sync"</li>
                                        <li>Click "Configure Admin API scopes"</li>
                                        <li>Enable: <code>read_inventory</code>, <code>write_inventory</code>, <code>read_products</code>, <code>write_products</code></li>
                                        <li>Click "Install app" and copy the Admin API access token</li>
                                    </ol>
                                </div>
                            ')),
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
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('store.name')
                    ->label('Store')
                    ->sortable()
                    ->searchable(),
                
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
                
                Tables\Columns\TextColumn::make('credentials.shop_url')
                    ->label('Shop URL')
                    ->limit(30)
                    ->visible(fn () => auth()->user()->is_admin ?? true),
                
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
                                'shopify' => (new ShopifyInventorySync($record))->testConnection(),
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
                    ->color('warning')
                    ->requiresConfirmation()
                    ->modalHeading('Trigger Manual Sync')
                    ->modalDescription('This will sync all inventory for this marketplace')
                    ->action(function (MarketplaceIntegration $record) {
                        // TODO: Dispatch batch sync job
                        $record->update(['last_sync_at' => now()]);
                        
                        Notification::make()
                            ->success()
                            ->title('Sync Started')
                            ->body('Inventory sync has been queued')
                            ->send();
                    })
                    ->visible(fn ($record) => $record->enabled),
                
                Tables\Actions\EditAction::make(),
                
                Tables\Actions\DeleteAction::make()
                    ->requiresConfirmation(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
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
            'index' => Pages\ListMarketplaceIntegrations::route('/'),
            'create' => Pages\CreateMarketplaceIntegration::route('/create'),
            'edit' => Pages\EditMarketplaceIntegration::route('/{record}/edit'),
        ];
    }
    
    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::where('enabled', true)->count();
    }
    
    public static function getNavigationBadgeColor(): ?string
    {
        return 'success';
    }
}
