<?php

namespace App\Services\Marketplace;

use App\Models\Card;
use App\Models\Inventory;
use App\Models\MarketplaceIntegration;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ShopifyInventorySync
{
    protected MarketplaceIntegration $integration;
    protected string $shopUrl;
    protected string $accessToken;
    
    public function __construct(MarketplaceIntegration $integration)
    {
        $this->integration = $integration;
        $this->shopUrl = $integration->credentials['shop_url'] ?? '';
        $this->accessToken = $integration->credentials['access_token'] ?? '';
    }
    
    /**
     * Test connection to Shopify
     */
    public function testConnection(): bool
    {
        try {
            $query = <<<GRAPHQL
            query {
              shop {
                name
                email
                url
              }
            }
            GRAPHQL;
            
            $response = $this->executeGraphQL($query);
            
            if (isset($response['data']['shop'])) {
                Log::info("Shopify connection test successful", [
                    'shop_name' => $response['data']['shop']['name'],
                ]);
                
                $this->integration->update(['last_sync_at' => now()]);
                
                return true;
            }
            
            throw new \Exception('Invalid response from Shopify');
            
        } catch (\Exception $e) {
            Log::error("Shopify connection test failed", [
                'error' => $e->getMessage(),
            ]);
            
            return false;
        }
    }
    
    /**
     * Sync inventory quantity to Shopify
     */
    public function syncInventory(Inventory $inventory): bool
    {
        try {
            $card = $inventory->card;
            
            // Skip if card doesn't have Shopify mapping or sync is disabled
            if (!$card->shopify_inventory_item_id || !$card->sync_to_shopify) {
                return true; // Not an error, just skip
            }
            
            // Prepare GraphQL mutation
            $mutation = $this->buildInventorySetQuantitiesMutation(
                $card->shopify_inventory_item_id,
                $inventory->shopify_location_id ?? $this->getDefaultLocationId(),
                $inventory->quantity
            );
            
            // Execute GraphQL request
            $response = $this->executeGraphQL($mutation);
            
            if ($this->isSuccessful($response)) {
                $inventory->update([
                    'sync_status' => 'synced',
                    'last_synced_at' => now(),
                    'sync_error' => null,
                ]);
                
                Log::info("Synced inventory to Shopify", [
                    'card_id' => $card->id,
                    'location_id' => $inventory->location_id,
                    'quantity' => $inventory->quantity,
                ]);
                
                return true;
            }
            
            throw new \Exception($this->extractError($response));
            
        } catch (\Exception $e) {
            $inventory->update([
                'sync_status' => 'failed',
                'sync_error' => $e->getMessage(),
            ]);
            
            Log::error("Failed to sync inventory to Shopify", [
                'card_id' => $inventory->card_id,
                'location_id' => $inventory->location_id,
                'error' => $e->getMessage(),
            ]);
            
            return false;
        }
    }
    
    /**
     * Build GraphQL mutation for setting inventory quantities
     */
    protected function buildInventorySetQuantitiesMutation(
        string $inventoryItemId,
        string $locationId,
        int $quantity
    ): string {
        return <<<GRAPHQL
        mutation {
          inventorySetQuantities(input: {
            reason: "correction",
            referenceDocumentUri: "gid://card-inventory-system/InventorySync/{$this->integration->store_id}",
            quantities: [
              {
                inventoryItemId: "{$inventoryItemId}",
                locationId: "{$locationId}",
                quantity: {$quantity}
              }
            ]
          }) {
            inventoryAdjustmentGroup {
              id
              changes {
                name
                delta
                quantityAfterChange
              }
            }
            userErrors {
              message
              field
            }
          }
        }
        GRAPHQL;
    }
    
    /**
     * Execute GraphQL query against Shopify Admin API
     */
    protected function executeGraphQL(string $query): array
    {
        $response = Http::withHeaders([
            'X-Shopify-Access-Token' => $this->accessToken,
            'Content-Type' => 'application/json',
        ])->post("{$this->shopUrl}/admin/api/2024-01/graphql.json", [
            'query' => $query,
        ]);
        
        return $response->json();
    }
    
    /**
     * Check if GraphQL response is successful
     */
    protected function isSuccessful(array $response): bool
    {
        return isset($response['data']) 
            && !isset($response['errors'])
            && empty($response['data']['inventorySetQuantities']['userErrors'] ?? []);
    }
    
    /**
     * Extract error message from response
     */
    protected function extractError(array $response): string
    {
        if (isset($response['errors'])) {
            return collect($response['errors'])->pluck('message')->implode(', ');
        }
        
        if (!empty($response['data']['inventorySetQuantities']['userErrors'])) {
            return collect($response['data']['inventorySetQuantities']['userErrors'])
                ->pluck('message')
                ->implode(', ');
        }
        
        return 'Unknown error occurred';
    }
    
    /**
     * Get default Shopify location ID from integration settings
     */
    protected function getDefaultLocationId(): string
    {
        return $this->integration->settings['default_location_id'] ?? '';
    }
    
    /**
     * Create or update product variant in Shopify
     */
    public function syncProduct(Card $card): bool
    {
        try {
            // If product already exists, update it
            if ($card->shopify_variant_id) {
                return $this->updateProductVariant($card);
            }
            
            // Otherwise, create new product
            return $this->createProduct($card);
            
        } catch (\Exception $e) {
            Log::error("Failed to sync product to Shopify", [
                'card_id' => $card->id,
                'error' => $e->getMessage(),
            ]);
            
            return false;
        }
    }
    
    /**
     * Create new product in Shopify
     */
    protected function createProduct(Card $card): bool
    {
        $mutation = <<<GRAPHQL
        mutation {
          productCreate(input: {
            title: "{$card->name}",
            productType: "Trading Card",
            vendor: "{$this->getVendorName($card->game)}",
            tags: ["{$card->game}", "{$card->set_name}", "{$card->type}", "{$card->class}"],
            variants: [
              {
                sku: "{$card->sku}",
                price: "{$card->inventory->first()?->sell_price ?? 0}",
                inventoryManagement: SHOPIFY
              }
            ]
          }) {
            product {
              id
              variants(first: 1) {
                edges {
                  node {
                    id
                    inventoryItem {
                      id
                    }
                  }
                }
              }
            }
            userErrors {
              message
              field
            }
          }
        }
        GRAPHQL;
        
        $response = $this->executeGraphQL($mutation);
        
        if ($this->isSuccessful($response)) {
            $product = $response['data']['productCreate']['product'];
            $variant = $product['variants']['edges'][0]['node'];
            
            $card->update([
                'shopify_product_id' => $product['id'],
                'shopify_variant_id' => $variant['id'],
                'shopify_inventory_item_id' => $variant['inventoryItem']['id'],
            ]);
            
            return true;
        }
        
        return false;
    }
    
    /**
     * Update existing product variant
     */
    protected function updateProductVariant(Card $card): bool
    {
        $mutation = <<<GRAPHQL
        mutation {
          productVariantUpdate(input: {
            id: "{$card->shopify_variant_id}",
            price: "{$card->inventory->first()?->sell_price ?? 0}"
          }) {
            productVariant {
              id
            }
            userErrors {
              message
              field
            }
          }
        }
        GRAPHQL;
        
        $response = $this->executeGraphQL($mutation);
        
        return $this->isSuccessful($response);
    }
    
    /**
     * Get vendor name based on game
     */
    protected function getVendorName(string $game): string
    {
        return match($game) {
            'grand-archive' => 'Weebs of the Coast',
            'gundam' => 'Bandai',
            'riftbound' => 'Mythic Games',
            default => 'Unknown',
        };
    }
}
