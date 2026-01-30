<?php

namespace App\Services\Marketplace;

use App\Models\Card;
use App\Models\Inventory;
use App\Models\MarketplaceIntegration;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AmazonInventorySync
{
    protected MarketplaceIntegration $integration;
    protected string $merchantId;
    protected string $marketplaceId;
    protected string $accessKeyId;
    protected string $secretAccessKey;
    protected string $roleArn;
    
    public function __construct(MarketplaceIntegration $integration)
    {
        $this->integration = $integration;
        $this->merchantId = $integration->credentials['merchant_id'] ?? '';
        $this->marketplaceId = $integration->credentials['marketplace_id'] ?? '';
        $this->accessKeyId = $integration->credentials['access_key_id'] ?? '';
        $this->secretAccessKey = $integration->credentials['secret_access_key'] ?? '';
        $this->roleArn = $integration->credentials['role_arn'] ?? '';
    }
    
    /**
     * Test connection to Amazon SP-API
     */
    public function testConnection(): bool
    {
        try {
            // Test with getMarketplaceParticipations endpoint
            $accessToken = $this->getAccessToken();
            
            $response = Http::withHeaders([
                'x-amz-access-token' => $accessToken,
                'Content-Type' => 'application/json',
            ])->get('https://sellingpartnerapi-na.amazon.com/sellers/v1/marketplaceParticipations');
            
            if ($response->successful()) {
                Log::info("Amazon connection test successful");
                $this->integration->update(['last_sync_at' => now()]);
                return true;
            }
            
            throw new \Exception('Connection failed');
            
        } catch (\Exception $e) {
            Log::error("Amazon connection test failed", ['error' => $e->getMessage()]);
            return false;
        }
    }
    
    /**
     * Sync inventory quantity to Amazon
     */
    public function syncInventory(Inventory $inventory): bool
    {
        try {
            $card = $inventory->card;
            
            // Skip if card doesn't have SKU or ASIN
            if (!$card->sku) {
                Log::warning("Card missing SKU for Amazon sync", ['card_id' => $card->id]);
                return true;
            }
            
            $accessToken = $this->getAccessToken();
            
            // Use Amazon Listings API to update inventory
            $response = Http::withHeaders([
                'x-amz-access-token' => $accessToken,
                'Content-Type' => 'application/json',
            ])->patch("https://sellingpartnerapi-na.amazon.com/listings/2021-08-01/items/{$this->merchantId}/{$card->sku}", [
                'productType' => 'TRADING_CARDS',
                'patches' => [
                    [
                        'op' => 'replace',
                        'path' => '/attributes/fulfillment_availability',
                        'value' => [
                            [
                                'fulfillment_channel_code' => 'DEFAULT',
                                'quantity' => $inventory->quantity,
                            ],
                        ],
                    ],
                ],
                'marketplaceIds' => [$this->marketplaceId],
            ]);
            
            if ($response->successful() || $response->status() === 202) {
                $inventory->update([
                    'sync_status' => 'synced',
                    'last_synced_at' => now(),
                    'sync_error' => null,
                ]);
                
                Log::info("Synced inventory to Amazon", [
                    'card_id' => $card->id,
                    'sku' => $card->sku,
                    'quantity' => $inventory->quantity,
                ]);
                
                return true;
            }
            
            throw new \Exception($response->json()['errors'][0]['message'] ?? 'Sync failed');
            
        } catch (\Exception $e) {
            $inventory->update([
                'sync_status' => 'failed',
                'sync_error' => $e->getMessage(),
            ]);
            
            Log::error("Failed to sync inventory to Amazon", [
                'card_id' => $inventory->card_id,
                'error' => $e->getMessage(),
            ]);
            
            return false;
        }
    }
    
    /**
     * Create product listing on Amazon
     */
    public function createListing(Card $card, Inventory $inventory): bool
    {
        try {
            $accessToken = $this->getAccessToken();
            
            $response = Http::withHeaders([
                'x-amz-access-token' => $accessToken,
                'Content-Type' => 'application/json',
            ])->put("https://sellingpartnerapi-na.amazon.com/listings/2021-08-01/items/{$this->merchantId}/{$card->sku}", [
                'productType' => 'TRADING_CARDS',
                'requirements' => 'LISTING',
                'attributes' => [
                    'condition_type' => [
                        [
                            'value' => 'new_new',
                        ],
                    ],
                    'item_name' => [
                        [
                            'value' => $card->name,
                            'language_tag' => 'en_US',
                            'marketplace_id' => $this->marketplaceId,
                        ],
                    ],
                    'brand' => [
                        [
                            'value' => $this->getBrand($card->game),
                            'marketplace_id' => $this->marketplaceId,
                        ],
                    ],
                    'manufacturer' => [
                        [
                            'value' => $this->getBrand($card->game),
                            'marketplace_id' => $this->marketplaceId,
                        ],
                    ],
                    'item_type_name' => [
                        [
                            'value' => 'Trading Card',
                            'marketplace_id' => $this->marketplaceId,
                        ],
                    ],
                    'purchasable_offer' => [
                        [
                            'marketplace_id' => $this->marketplaceId,
                            'currency' => 'USD',
                            'our_price' => [
                                [
                                    'schedule' => [
                                        [
                                            'value_with_tax' => $inventory->sell_price ?? 0,
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                    'fulfillment_availability' => [
                        [
                            'fulfillment_channel_code' => 'DEFAULT',
                            'quantity' => $inventory->quantity,
                        ],
                    ],
                ],
            ]);
            
            return $response->successful() || $response->status() === 202;
            
        } catch (\Exception $e) {
            Log::error("Failed to create Amazon listing", [
                'card_id' => $card->id,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }
    
    /**
     * Get OAuth access token for Amazon SP-API
     * This is a simplified version - actual implementation requires:
     * 1. AWS Signature Version 4
     * 2. LWA (Login with Amazon) token exchange
     * 3. STS AssumeRole for credentials
     */
    protected function getAccessToken(): string
    {
        try {
            // Exchange refresh token for access token via LWA
            $response = Http::asForm()->post('https://api.amazon.com/auth/o2/token', [
                'grant_type' => 'refresh_token',
                'refresh_token' => $this->integration->credentials['refresh_token'] ?? '',
                'client_id' => $this->accessKeyId,
                'client_secret' => $this->secretAccessKey,
            ]);
            
            if ($response->successful()) {
                return $response->json()['access_token'];
            }
            
            throw new \Exception('Failed to get access token');
            
        } catch (\Exception $e) {
            Log::error("Failed to get Amazon access token", ['error' => $e->getMessage()]);
            throw $e;
        }
    }
    
    /**
     * Get brand name for game
     */
    protected function getBrand(string $game): string
    {
        return match($game) {
            'grand-archive' => 'Weebs of the Coast',
            'gundam' => 'Bandai',
            'riftbound' => 'Mythic Games',
            default => 'Trading Card Game',
        };
    }
    
    /**
     * Search for product on Amazon
     */
    public function searchProduct(Card $card): ?string
    {
        try {
            $accessToken = $this->getAccessToken();
            
            // Search using Catalog Items API
            $response = Http::withHeaders([
                'x-amz-access-token' => $accessToken,
                'Content-Type' => 'application/json',
            ])->get('https://sellingpartnerapi-na.amazon.com/catalog/2022-04-01/items', [
                'marketplaceIds' => $this->marketplaceId,
                'keywords' => $card->name,
                'includedData' => 'summaries',
            ]);
            
            if ($response->successful()) {
                $items = $response->json()['items'] ?? [];
                return $items[0]['asin'] ?? null;
            }
            
            return null;
            
        } catch (\Exception $e) {
            Log::error("Failed to search Amazon", [
                'card_id' => $card->id,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }
}
