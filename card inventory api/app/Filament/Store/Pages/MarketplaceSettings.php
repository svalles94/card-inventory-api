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
                        ->description('Enter your Shopify credentials')
                        ->schema([
                            TextInput::make('credentials.shop_url')
                                ->label('Shop URL')
                                ->placeholder('https://your-store.myshopify.com')
                                ->url()
                                ->required()
                                ->suffixIcon('heroicon-o-globe-alt')
                                ->helperText('Your full Shopify store URL including https://'),
                            
                            TextInput::make('credentials.access_token')
                                ->label('Admin API Access Token')
                                ->placeholder('shpat_xxxxxxxxxxxxx')
                                ->password()
                                ->revealable()
                                ->required()
                                ->suffixIcon('heroicon-o-key')
                                ->helperText('Generate this from Shopify Admin → Apps → Develop apps'),
                            
                            TextInput::make('settings.default_location_id')
                                ->label('Default Location ID (Optional)')
                                ->placeholder('gid://shopify/Location/xxxxx')
                                ->helperText('Leave empty to sync to all locations'),
                            
                            Toggle::make('enabled')
                                ->label('Enable Automatic Sync')
                                ->default(true)
                                ->helperText('Start syncing inventory immediately after setup'),
                            
                            Section::make('Setup Instructions')
                                ->description('Follow these steps to get your Shopify credentials')
                                ->schema([
                                    \Filament\Forms\Components\Placeholder::make('instructions')
                                        ->content(new \Illuminate\Support\HtmlString('
                                            <div class="prose dark:prose-invert max-w-none">
                                                <h4>How to create a Shopify app:</h4>
                                                <ol class="space-y-2">
                                                    <li>
                                                        <strong>Go to Shopify Admin</strong>
                                                        <p class="text-sm text-gray-600 dark:text-gray-400">Settings → Apps and sales channels</p>
                                                    </li>
                                                    <li>
                                                        <strong>Develop apps</strong>
                                                        <p class="text-sm text-gray-600 dark:text-gray-400">Click "Develop apps" button (you may need to enable this first)</p>
                                                    </li>
                                                    <li>
                                                        <strong>Create app</strong>
                                                        <p class="text-sm text-gray-600 dark:text-gray-400">Click "Create an app" and name it "Card Inventory Sync"</p>
                                                    </li>
                                                    <li>
                                                        <strong>Configure scopes</strong>
                                                        <p class="text-sm text-gray-600 dark:text-gray-400">Click "Configure Admin API scopes" and enable:</p>
                                                        <ul class="list-disc ml-6 text-sm">
                                                            <li><code>read_inventory</code> - Read inventory</li>
                                                            <li><code>write_inventory</code> - Update inventory</li>
                                                            <li><code>read_products</code> - Read products</li>
                                                            <li><code>write_products</code> - Create/update products</li>
                                                        </ul>
                                                    </li>
                                                    <li>
                                                        <strong>Install app</strong>
                                                        <p class="text-sm text-gray-600 dark:text-gray-400">Click "Install app" and copy the Admin API access token that appears</p>
                                                    </li>
                                                    <li>
                                                        <strong>Paste credentials above</strong>
                                                        <p class="text-sm text-gray-600 dark:text-gray-400">Enter your shop URL and the access token in the form above</p>
                                                    </li>
                                                </ol>
                                            </div>
                                        ')),
                                ])
                                ->collapsible()
                                ->collapsed(),
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
                ->submitAction(new \Illuminate\Support\HtmlString('<button type="submit" class="filament-button filament-button-size-md">Save & Test Connection</button>')),
            ])
            ->statePath('data');
    }
    
    public function submit(): void
    {
        $data = $this->form->getState();
        
        try {
            // Check if integration already exists
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
            
            // Test the connection
            if ($data['marketplace'] === 'shopify' && ($data['enabled'] ?? true)) {
                $service = new ShopifyInventorySync($integration);
                
                if ($service->testConnection()) {
                    Notification::make()
                        ->success()
                        ->title('Integration Saved & Tested')
                        ->body("Successfully connected to {$data['marketplace']}! Inventory will now sync automatically.")
                        ->send();
                } else {
                    Notification::make()
                        ->warning()
                        ->title('Integration Saved')
                        ->body('Credentials saved but connection test failed. Please verify your settings.')
                        ->send();
                }
            } else {
                Notification::make()
                    ->success()
                    ->title('Integration Saved')
                    ->body("Marketplace integration configured successfully.")
                    ->send();
            }
            
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
            Action::make('view_integrations')
                ->label('View All Integrations')
                ->url(route('filament.store.resources.store-marketplace-integrations.index'))
                ->icon('heroicon-o-queue-list')
                ->color('gray'),
        ];
    }
}
