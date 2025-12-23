<?php

namespace App\Services\Marketplace;

use App\Models\Card;
use App\Models\Inventory;
use App\Models\MarketplaceIntegration;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TcgPlayerInventorySync
{
    protected MarketplaceIntegration $integration;
    protected string $apiKey;
    protected string $sellerKey;
    
    public function __construct(MarketplaceIntegration $integration)
    {
        $this->integration = $integration;
        $this->apiKey = $integration->credentials['api_key'] ?? '';
        $this->sellerKey = $integration->credentials['seller_key'] ?? '';
    }
    
    /**
     * Test connection to TCGPlayer
     */
    public function testConnection(): bool
    {
        try {
            // Test with catalog/categories endpoint
            $response = Http::withHeaders([
                'Authorization' => "Bearer {$this->apiKey}",
                'Accept' => 'application/json',
            ])->get('https://api.tcgplayer.com/catalog/categories');
            
            if ($response->successful()) {
                Log::info("TCGPlayer connection test successful");
                $this->integration->update(['last_sync_at' => now()]);
                return true;
            }
            
            throw new \Exception('Connection failed');
            
        } catch (\Exception $e) {
            Log::error("TCGPlayer connection test failed", ['error' => $e->getMessage()]);
            return false;
        }
    }
    
    /**
     * Sync inventory quantity to TCGPlayer
     */
    public function syncInventory(Inventory $inventory): bool
    {
        try {
            $card = $inventory->card;
            
            // Skip if card doesn't have SKU
            if (!$card->sku) {
                Log::warning("Card missing SKU for TCGPlayer sync", ['card_id' => $card->id]);
                return true;
            }
            
            // Skip if card doesn't have TCGPlayer product ID mapping
            // Note: You'll need to add tcgplayer_product_id to cards table or map via SKU
            if (!$card->tcgplayer_product_id ?? null) {
                Log::warning("Card missing TCGPlayer product ID", ['card_id' => $card->id]);
                return true;
            }
            
            // Update inventory via TCGPlayer Seller API
            $response = Http::withHeaders([
                'Authorization' => "Bearer {$this->apiKey}",
                'X-Tcgplayer-Seller-Key' => $this->sellerKey,
                'Content-Type' => 'application/json',
            ])->put("https://api.tcgplayer.com/seller/inventory/products/{$card->tcgplayer_product_id}", [
                'quantity' => $inventory->quantity,
                'price' => $inventory->sell_price ?? 0,
                'condition' => 'Near Mint',
            ]);
            
            if ($response->successful()) {
                $inventory->update([
                    'sync_status' => 'synced',
                    'last_synced_at' => now(),
                    'sync_error' => null,
                ]);
                
                Log::info("Synced inventory to TCGPlayer", [
                    'card_id' => $card->id,
                    'product_id' => $card->tcgplayer_product_id,
                    'quantity' => $inventory->quantity,
                ]);
                
                return true;
            }
            
            throw new \Exception($response->json()['error'] ?? 'Sync failed');
            
        } catch (\Exception $e) {
            $inventory->update([
                'sync_status' => 'failed',
                'sync_error' => $e->getMessage(),
            ]);
            
            Log::error("Failed to sync inventory to TCGPlayer", [
                'card_id' => $inventory->card_id,
                'error' => $e->getMessage(),
            ]);
            
            return false;
        }
    }
    
    /**
     * Search for product on TCGPlayer by name and set
     */
    public function findProduct(Card $card): ?int
    {
        try {
            // Map game to TCGPlayer category ID
            $categoryId = $this->getCategoryId($card->game);
            
            if (!$categoryId) {
                return null;
            }
            
            // Search for product
            $response = Http::withHeaders([
                'Authorization' => "Bearer {$this->apiKey}",
                'Accept' => 'application/json',
            ])->get('https://api.tcgplayer.com/catalog/products', [
                'categoryId' => $categoryId,
                'productName' => $card->name,
                'limit' => 10,
            ]);
            
            if ($response->successful()) {
                $products = $response->json()['results'] ?? [];
                
                // Try to find exact match
                foreach ($products as $product) {
                    if (strtolower($product['name']) === strtolower($card->name)) {
                        return $product['productId'];
                    }
                }
                
                // Return first result if no exact match
                return $products[0]['productId'] ?? null;
            }
            
            return null;
            
        } catch (\Exception $e) {
            Log::error("Failed to search TCGPlayer", [
                'card_id' => $card->id,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }
    
    /**
     * Get TCGPlayer category ID for game
     */
    protected function getCategoryId(string $game): ?int
    {
        // These are example IDs - you'll need to fetch actual TCGPlayer category IDs
        return match($game) {
            'grand-archive' => null, // Grand Archive may not be on TCGPlayer yet
            'gundam' => null, // Gundam Card Game category ID
            'riftbound' => null, // Not on TCGPlayer
            default => null,
        };
    }
    
    /**
     * Apply pricing strategy
     */
    protected function calculatePrice(Inventory $inventory): float
    {
        $strategy = $this->integration->settings['pricing_strategy'] ?? 'market';
        
        return match($strategy) {
            'market' => $inventory->market_price ?? $inventory->sell_price ?? 0,
            'below_market' => ($inventory->market_price ?? 0) * 0.95,
            'fixed' => $inventory->sell_price ?? 0,
            default => $inventory->sell_price ?? 0,
        };
    }
}
