<?php

namespace App\Services\Marketplace;

use App\Models\Card;
use App\Models\Inventory;
use App\Models\MarketplaceIntegration;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class EbayInventorySync
{
    protected MarketplaceIntegration $integration;
    protected string $clientId;
    protected string $clientSecret;
    protected string $oauthToken;
    protected bool $sandboxMode;
    
    public function __construct(MarketplaceIntegration $integration)
    {
        $this->integration = $integration;
        $this->clientId = $integration->credentials['client_id'] ?? '';
        $this->clientSecret = $integration->credentials['client_secret'] ?? '';
        $this->oauthToken = $integration->credentials['oauth_token'] ?? '';
        $this->sandboxMode = $integration->settings['sandbox_mode'] ?? false;
    }
    
    /**
     * Test connection to eBay
     */
    public function testConnection(): bool
    {
        try {
            $endpoint = $this->getApiEndpoint();
            
            // Test with getUser call
            $response = Http::withHeaders([
                'Authorization' => "Bearer {$this->oauthToken}",
                'Content-Type' => 'application/json',
            ])->get("{$endpoint}/sell/inventory/v1/inventory_item", [
                'limit' => 1,
            ]);
            
            if ($response->successful()) {
                Log::info("eBay connection test successful");
                $this->integration->update(['last_sync_at' => now()]);
                return true;
            }
            
            throw new \Exception($response->json()['errors'][0]['message'] ?? 'Connection failed');
            
        } catch (\Exception $e) {
            Log::error("eBay connection test failed", ['error' => $e->getMessage()]);
            return false;
        }
    }
    
    /**
     * Sync inventory quantity to eBay
     */
    public function syncInventory(Inventory $inventory): bool
    {
        try {
            $card = $inventory->card;
            
            // Skip if card doesn't have eBay mapping or sync is disabled
            if (!$card->sku) {
                Log::warning("Card missing SKU for eBay sync", ['card_id' => $card->id]);
                return true; // Not an error, just skip
            }
            
            $endpoint = $this->getApiEndpoint();
            
            // First, check if inventory item exists
            $existsResponse = Http::withHeaders([
                'Authorization' => "Bearer {$this->oauthToken}",
                'Content-Type' => 'application/json',
            ])->get("{$endpoint}/sell/inventory/v1/inventory_item/{$card->sku}");
            
            if ($existsResponse->status() === 404) {
                // Create inventory item first
                $this->createInventoryItem($card);
            }
            
            // Update quantity using eBay Inventory API
            $response = Http::withHeaders([
                'Authorization' => "Bearer {$this->oauthToken}",
                'Content-Type' => 'application/json',
            ])->put("{$endpoint}/sell/inventory/v1/inventory_item/{$card->sku}", [
                'availability' => [
                    'shipToLocationAvailability' => [
                        'quantity' => $inventory->quantity,
                    ],
                ],
            ]);
            
            if ($response->successful() || $response->status() === 204) {
                $inventory->update([
                    'sync_status' => 'synced',
                    'last_synced_at' => now(),
                    'sync_error' => null,
                ]);
                
                Log::info("Synced inventory to eBay", [
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
            
            Log::error("Failed to sync inventory to eBay", [
                'card_id' => $inventory->card_id,
                'error' => $e->getMessage(),
            ]);
            
            return false;
        }
    }
    
    /**
     * Create inventory item on eBay
     */
    protected function createInventoryItem(Card $card): bool
    {
        try {
            $endpoint = $this->getApiEndpoint();
            
            $response = Http::withHeaders([
                'Authorization' => "Bearer {$this->oauthToken}",
                'Content-Type' => 'application/json',
            ])->put("{$endpoint}/sell/inventory/v1/inventory_item/{$card->sku}", [
                'product' => [
                    'title' => $card->name,
                    'description' => $this->buildDescription($card),
                    'aspects' => [
                        'Game' => [ucwords(str_replace('-', ' ', $card->game))],
                        'Set' => [$card->set_name ?? 'Unknown'],
                        'Card Type' => [$card->type ?? 'Unknown'],
                        'Condition' => ['Near Mint'],
                    ],
                    'brand' => $this->getBrand($card->game),
                    'mpn' => $card->card_number ?? $card->sku,
                ],
                'condition' => 'NEW',
                'availability' => [
                    'shipToLocationAvailability' => [
                        'quantity' => 0, // Will be updated by syncInventory
                    ],
                ],
            ]);
            
            return $response->successful() || $response->status() === 204;
            
        } catch (\Exception $e) {
            Log::error("Failed to create eBay inventory item", [
                'card_id' => $card->id,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }
    
    /**
     * Build product description
     */
    protected function buildDescription(Card $card): string
    {
        $parts = [$card->name];
        
        if ($card->set_name) {
            $parts[] = "from {$card->set_name}";
        }
        
        if ($card->type) {
            $parts[] = "- {$card->type}";
        }
        
        if ($card->rarity) {
            $parts[] = "- Rarity: {$card->rarity}";
        }
        
        return implode(' ', $parts);
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
     * Get API endpoint based on sandbox mode
     */
    protected function getApiEndpoint(): string
    {
        return $this->sandboxMode 
            ? 'https://api.sandbox.ebay.com'
            : 'https://api.ebay.com';
    }
}
