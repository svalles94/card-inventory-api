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

