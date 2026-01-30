<?php

namespace App\Services\Marketplace;

use App\Models\Card;
use App\Models\Inventory;
use App\Models\MarketplaceIntegration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ShopifyInventorySync
{
    protected MarketplaceIntegration $integration;
    protected string $shopUrl;
    protected ?string $accessToken = null;
    protected ?string $clientId = null;
    protected ?string $clientSecret = null;
    
    public function __construct(MarketplaceIntegration $integration)
    {
        $this->integration = $integration;
        $this->shopUrl = $integration->credentials['shop_url'] ?? '';
        
        // Priority: OAuth token > Client Credentials Grant > Legacy static token
        $oauthToken = $integration->credentials['access_token'] ?? null;
        $this->clientId = $integration->credentials['client_id'] ?? null;
        $this->clientSecret = $integration->credentials['client_secret'] ?? null;
        
        // Debug: Log what credentials we have
        Log::debug('ShopifyInventorySync: Loading credentials', [
            'integration_id' => $integration->id,
            'has_oauth_token' => !empty($oauthToken),
            'oauth_token_length' => $oauthToken ? strlen($oauthToken) : 0,
            'has_client_id' => !empty($this->clientId),
            'has_client_secret' => !empty($this->clientSecret),
            'shop_url' => $this->shopUrl,
            'credentials_keys' => is_array($integration->credentials) ? array_keys($integration->credentials) : 'not_array',
        ]);
        
        if ($oauthToken && !empty($oauthToken) && str_starts_with($this->shopUrl, 'https://')) {
            // Use OAuth token (from OAuth flow) - highest priority
            $this->accessToken = $oauthToken;
            Log::info('ShopifyInventorySync: Using OAuth access token', [
                'integration_id' => $integration->id,
                'token_prefix' => substr($this->accessToken, 0, 10) . '...',
                'token_length' => strlen($this->accessToken),
                'scope' => $integration->credentials['scope'] ?? 'unknown',
                'shop_url' => $this->shopUrl,
            ]);
        } elseif ($this->clientId && $this->clientSecret) {
            // Use OAuth Client Credentials Grant (for manual setup)
            $this->accessToken = $this->getAccessToken();
        } else {
            Log::warning('ShopifyInventorySync: No credentials found', [
                'integration_id' => $integration->id,
                'has_oauth_token' => !empty($oauthToken),
                'oauth_token_value' => $oauthToken ? (substr($oauthToken, 0, 10) . '...') : 'null',
                'has_client_id' => !empty($this->clientId),
                'has_client_secret' => !empty($this->clientSecret),
                'shop_url' => $this->shopUrl,
            ]);
        }
    }
    
    /**
     * Clear cached access token (useful when scopes are updated)
     */
    public function clearAccessTokenCache(): void
    {
        $cacheKey = "shopify_access_token_{$this->integration->id}";
        Cache::forget($cacheKey);
        Log::info('ShopifyInventorySync: Cleared access token cache', [
            'integration_id' => $this->integration->id,
        ]);
    }
    
    /**
     * Get access token using Client Credentials Grant
     * Tokens are cached for 23 hours (tokens expire after 24 hours)
     */
    protected function getAccessToken(): ?string
    {
        $cacheKey = "shopify_access_token_{$this->integration->id}";
        
        // Check cache first
        $cachedToken = Cache::get($cacheKey);
        if ($cachedToken) {
            Log::debug('ShopifyInventorySync: Using cached access token', [
                'integration_id' => $this->integration->id,
            ]);
            return $cachedToken;
        }
        
        // Request new token
        try {
            $shopDomain = str_replace(['https://', 'http://'], '', rtrim($this->shopUrl, '/'));
            
            $response = Http::asForm()->post("https://{$shopDomain}/admin/oauth/access_token", [
                'grant_type' => 'client_credentials',
                'client_id' => $this->clientId,
                'client_secret' => $this->clientSecret,
            ]);
            
            if ($response->successful()) {
                $data = $response->json();
                $token = $data['access_token'] ?? null;
                
                if ($token) {
                    // Cache for 23 hours (tokens expire after 24 hours)
                    Cache::put($cacheKey, $token, now()->addHours(23));
                    
                    $scope = $data['scope'] ?? '';
                    Log::info('ShopifyInventorySync: Successfully obtained access token', [
                        'integration_id' => $this->integration->id,
                        'expires_in' => $data['expires_in'] ?? 'unknown',
                        'scope' => $scope,
                        'has_write_products' => str_contains($scope, 'write_products'),
                        'has_read_products' => str_contains($scope, 'read_products'),
                        'has_write_inventory' => str_contains($scope, 'write_inventory'),
                        'has_read_inventory' => str_contains($scope, 'read_inventory'),
                    ]);
                    
                    // Warn if scope is empty or missing required scopes
                    if (empty($scope)) {
                        Log::warning('ShopifyInventorySync: Access token has empty scope! App may need to be reinstalled after adding scopes.', [
                            'integration_id' => $this->integration->id,
                        ]);
                    } elseif (!str_contains($scope, 'write_products')) {
                        Log::warning('ShopifyInventorySync: Access token missing write_products scope!', [
                            'integration_id' => $this->integration->id,
                            'scope' => $scope,
                        ]);
                    }
                    
                    return $token;
                }
            }
            
            Log::error('ShopifyInventorySync: Failed to get access token', [
                'integration_id' => $this->integration->id,
                'status' => $response->status(),
                'response' => $response->body(),
            ]);
            
        } catch (\Exception $e) {
            Log::error('ShopifyInventorySync: Exception getting access token', [
                'integration_id' => $this->integration->id,
                'error' => $e->getMessage(),
            ]);
        }
        
        return null;
    }
    
    /**
     * Test connection to Shopify
     * @param bool $forceRefresh If true, clears cached token to get fresh one with updated scopes (only for Client Credentials Grant)
     */
    public function testConnection(bool $forceRefresh = false): bool
    {
        try {
            // Only clear cache for Client Credentials Grant tokens, not OAuth tokens
            // OAuth tokens are stored directly and don't need refreshing
            if ($forceRefresh && $this->clientId && $this->clientSecret && empty($this->integration->credentials['access_token'])) {
                $this->clearAccessTokenCache();
                // Force new token generation
                $this->accessToken = $this->getAccessToken();
            }
            
            // Validate shop URL format
            if (empty($this->shopUrl)) {
                throw new \Exception('Shop URL is empty. Please enter your Shopify store URL.');
            }
            
            if (!str_starts_with($this->shopUrl, 'https://')) {
                throw new \Exception('Shop URL must start with https://');
            }
            
            if (!str_ends_with($this->shopUrl, '.myshopify.com')) {
                throw new \Exception('Shop URL must end with .myshopify.com');
            }
            
            // Get access token (will use OAuth if Client ID/Secret provided, or legacy token)
            if (empty($this->accessToken)) {
                if ($this->clientId && $this->clientSecret) {
                    throw new \Exception('Failed to obtain access token. Please check your Client ID and Client Secret.');
                } else {
                    throw new \Exception('Access token is empty. Please provide either Client ID/Secret (recommended) or a legacy access token.');
                }
            }
            
            // Check token prefix (OAuth tokens use shpua_, Client Credentials use different format)
            if (strlen($this->accessToken) > 5) {
                $tokenPrefix = substr($this->accessToken, 0, 5);
                $validPrefixes = ['shpat', 'shpca', 'shpua']; // shpua_ is for OAuth tokens
                if (!in_array($tokenPrefix, $validPrefixes) && !$this->clientId) {
                    Log::warning('Shopify token has unusual prefix', [
                        'prefix' => $tokenPrefix,
                        'expected' => 'shpat_, shpca_, or shpua_',
                        'token_length' => strlen($this->accessToken),
                    ]);
                }
            }
            
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
                    'shop_url' => $response['data']['shop']['url'] ?? null,
                ]);
                
                $this->integration->update(['last_sync_at' => now()]);
                
                return true;
            }
            
            throw new \Exception('Invalid response from Shopify: ' . json_encode($response));
            
        } catch (\Exception $e) {
            Log::error("Shopify connection test failed", [
                'error' => $e->getMessage(),
                'shop_url' => $this->shopUrl,
                'token_prefix' => !empty($this->accessToken) ? substr($this->accessToken, 0, 10) . '...' : 'empty',
                'token_length' => strlen($this->accessToken ?? ''),
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
            
            // Skip if sync is disabled
            if (!$card->sync_to_shopify) {
                return true; // Not an error, just skip
            }
            
            // Ensure product exists and variant exists for this inventory item
            // Product is created once per card, variants are created per inventory item
            if (!$card->shopify_product_id) {
                // Create product (first time for this card)
                if (!$this->createProduct($card)) {
                    Log::warning("Failed to create Shopify product for card", [
                        'card_id' => $card->id,
                    ]);
                    return false;
                }
                $card->refresh();
            } else {
                // Verify product still exists in Shopify (it might have been deleted)
                if (!$this->verifyProductExists($card->shopify_product_id)) {
                    Log::warning("Stored product ID doesn't exist in Shopify, clearing and recreating", [
                        'card_id' => $card->id,
                        'shopify_product_id' => $card->shopify_product_id,
                    ]);
                    // Clear the invalid product ID and all related variant IDs
                    $card->update([
                        'shopify_product_id' => null,
                        'shopify_variant_id' => null,
                        'shopify_inventory_item_id' => null,
                    ]);
                    // Clear variant IDs from all inventory items for this card
                    Inventory::where('card_id', $card->id)
                        ->update([
                            'shopify_variant_id' => null,
                        ]);
                    // Create product fresh
                    if (!$this->createProduct($card)) {
                        Log::warning("Failed to create Shopify product for card after clearing invalid ID", [
                            'card_id' => $card->id,
                        ]);
                        return false;
                    }
                    $card->refresh();
                }
            }
            
            // Ensure variant exists for this specific inventory item
            // Check if this inventory item already has a variant
            if (!$inventory->shopify_variant_id) {
                // Create or find variant for this inventory item
                $variantId = $this->ensureVariantExists($card, $inventory);
                if (!$variantId) {
                    Log::warning("Failed to create/find variant for inventory", [
                        'inventory_id' => $inventory->id,
                        'card_id' => $card->id,
                    ]);
                    return false;
                }
                // Store variant ID on inventory item
                $inventory->update(['shopify_variant_id' => $variantId]);
            }
            
            // Verify variant still exists in Shopify (it might have been deleted)
            $variantInventoryItemId = $this->getVariantInventoryItemId($inventory->shopify_variant_id);
            if (!$variantInventoryItemId) {
                Log::warning("Stored variant ID doesn't exist in Shopify, clearing and recreating", [
                    'inventory_id' => $inventory->id,
                    'variant_id' => $inventory->shopify_variant_id,
                ]);
                // Clear the invalid variant ID
                $inventory->update(['shopify_variant_id' => null]);
                // Create a new variant
                $variantId = $this->ensureVariantExists($card, $inventory);
                if (!$variantId) {
                    Log::warning("Failed to create variant after clearing invalid ID", [
                        'inventory_id' => $inventory->id,
                        'card_id' => $card->id,
                    ]);
                    return false;
                }
                // Store new variant ID
                $inventory->update(['shopify_variant_id' => $variantId]);
                // Get inventory item ID for the new variant
                $variantInventoryItemId = $this->getVariantInventoryItemId($variantId);
                if (!$variantInventoryItemId) {
                    Log::warning("Failed to get inventory item ID for newly created variant", [
                        'variant_id' => $variantId,
                    ]);
                    return false;
                }
            }
            
            // Update variant price if sell_price changed
            if ($inventory->wasChanged('sell_price')) {
                $this->updateVariantPrice($inventory->shopify_variant_id, $inventory->sell_price ?? 0);
            }
            
            // Get location ID - prefer inventory's location, then default
            $locationId = $inventory->shopify_location_id ?? $this->getDefaultLocationId();
            
            if (!$locationId) {
                throw new \Exception('No Shopify location ID available. Please configure a default location in marketplace settings.');
            }
            
            // Ensure inventory tracking is enabled on the variant
            // This sets inventoryManagement to SHOPIFY and activates inventory at the location
            $this->ensureInventoryTrackingEnabled($inventory->shopify_variant_id, $variantInventoryItemId, $locationId);
            
            // Always sync quantity (on creation or update)
            // Prepare GraphQL mutation using variant's inventory item ID
            $mutation = $this->buildInventorySetQuantitiesMutation(
                $variantInventoryItemId,
                $locationId,
                $inventory->quantity
            );
            
            Log::info("Syncing inventory quantity to Shopify", [
                'inventory_id' => $inventory->id,
                'variant_id' => $inventory->shopify_variant_id,
                'inventory_item_id' => $variantInventoryItemId,
                'location_id' => $locationId,
                'quantity' => $inventory->quantity,
            ]);
            
            // Execute GraphQL request
            $response = $this->executeGraphQL($mutation);
            
            // Log the full response for debugging
            Log::debug("Shopify inventorySetQuantities response", [
                'response' => $response,
                'inventory_id' => $inventory->id,
            ]);
            
            if (!$this->isSuccessful($response)) {
                $error = $this->extractError($response);
                Log::error("Failed to sync inventory quantity", [
                    'inventory_id' => $inventory->id,
                    'error' => $error,
                    'response' => $response,
                ]);
                throw new \Exception($error);
            }
            
            // Check if quantity was actually set
            $quantityAfterChange = $response['data']['inventorySetQuantities']['inventoryAdjustmentGroup']['changes'][0]['quantityAfterChange'] ?? null;
            
            Log::info("Successfully synced inventory quantity to Shopify", [
                'card_id' => $card->id,
                'inventory_id' => $inventory->id,
                'location_id' => $inventory->location_id,
                'quantity_sent' => $inventory->quantity,
                'quantity_after_change' => $quantityAfterChange,
                'price_updated' => $inventory->wasChanged('sell_price'),
            ]);
            
            // Mark as synced
                $inventory->update([
                    'sync_status' => 'synced',
                    'last_synced_at' => now(),
                    'sync_error' => null,
                ]);
                
                return true;
            
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
            name: "available",
            reason: "correction",
            ignoreCompareQuantity: true,
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
        
        $status = $response->status();
        $json = $response->json();
        $body = $response->body();
        
        // Log errors for debugging
        if ($response->failed() || isset($json['errors']) || $status !== 200) {
            Log::error('Shopify GraphQL Error', [
                'status' => $status,
                'status_text' => $response->reason(),
                'response_body' => $body,
                'response_json' => $json,
                'query' => substr($query, 0, 500), // First 500 chars of query
                'shop_url' => $this->shopUrl,
            ]);
        }
        
        // If HTTP request failed, throw exception
        if ($response->failed()) {
            throw new \Exception("Shopify API HTTP error: {$status} {$response->reason()}. Response: " . substr($body, 0, 500));
        }
        
        return $json ?: [];
    }
    
    /**
     * Check if GraphQL response is successful
     */
    protected function isSuccessful(array $response): bool
    {
        if (isset($response['errors'])) {
            return false;
        }
        
        // For inventorySetQuantities
        if (isset($response['data']['inventorySetQuantities'])) {
            return empty($response['data']['inventorySetQuantities']['userErrors'] ?? []);
        }
        
        // For productCreate
        if (isset($response['data']['productCreate'])) {
            return empty($response['data']['productCreate']['userErrors'] ?? []);
        }
        
        // For productVariantsBulkCreate
        if (isset($response['data']['productVariantsBulkCreate'])) {
            return empty($response['data']['productVariantsBulkCreate']['userErrors'] ?? []);
        }
        
        return isset($response['data']);
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
     * If not set, fetch the first available location from Shopify
     */
    protected function getDefaultLocationId(): ?string
    {
        // Check integration settings first
        if (!empty($this->integration->settings['default_location_id'])) {
            return $this->integration->settings['default_location_id'];
        }
        
        // Try to fetch locations from Shopify
        try {
            $locations = $this->fetchLocations();
            if (!empty($locations)) {
                // Use the first location and save it as default
                $firstLocationId = $locations[0]['id'];
                $settings = $this->integration->settings ?? [];
                $settings['default_location_id'] = $firstLocationId;
                $this->integration->update(['settings' => $settings]);
                
                Log::info("Auto-selected first Shopify location as default", [
                    'location_id' => $firstLocationId,
                    'location_name' => $locations[0]['name'] ?? 'Unknown'
                ]);
                
                return $firstLocationId;
            }
        } catch (\Exception $e) {
            Log::warning("Failed to fetch Shopify locations", [
                'error' => $e->getMessage()
            ]);
        }
        
        return null;
    }
    
    /**
     * Fetch all locations from Shopify
     */
    public function fetchLocations(): array
    {
        $query = <<<GRAPHQL
        query {
          locations(first: 50) {
            edges {
              node {
                id
                name
                address {
                  address1
                  city
                  province
                  country
                }
              }
            }
          }
        }
        GRAPHQL;
        
        $response = $this->executeGraphQL($query);
        
        if (isset($response['data']['locations']['edges'])) {
            return collect($response['data']['locations']['edges'])
                ->map(function ($edge) {
                    return [
                        'id' => $edge['node']['id'],
                        'name' => $edge['node']['name'],
                        'address' => $edge['node']['address'] ?? null,
                    ];
                })
                ->toArray();
        }
        
        return [];
    }
    
    /**
     * Create or update product variant in Shopify
     * @deprecated This method is kept for backward compatibility but is no longer used
     * Variants are now created per inventory item via ensureVariantExists()
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
     * Ensure variant exists for an inventory item
     * Returns variant ID or null on failure
     * 
     * If this is the first variant for a product, it will batch create all variants
     * using productVariantsBulkCreate with REMOVE_STANDALONE_VARIANT strategy
     */
    protected function ensureVariantExists(Card $card, Inventory $inventory): ?string
    {
        try {
            // Generate SKU for this specific inventory item
            $sku = \App\Services\SkuGenerator::generateForInventory($inventory);
            
            // Get variant options
            $options = $this->getVariantOptions($inventory);
            
            Log::info("Ensuring variant exists", [
                'card_id' => $card->id,
                'inventory_id' => $inventory->id,
                'sku' => $sku,
                'options' => $options,
                'product_id' => $card->shopify_product_id,
            ]);
            
            // Check if variant already exists with this SKU
            $existingVariant = $this->findVariantBySku($card->shopify_product_id, $sku);
            if ($existingVariant) {
                Log::info("Found existing variant by SKU", [
                    'variant_id' => $existingVariant,
                    'sku' => $sku,
                ]);
                return $existingVariant;
            }
            
            // Check if variant exists with the exact same options
            $existingVariantByOptions = $this->findVariantByOptions($card->shopify_product_id, $options);
            if ($existingVariantByOptions) {
                Log::info("Found existing variant with matching options", [
                    'variant_id' => $existingVariantByOptions,
                    'options' => $options,
                ]);
                // Update the existing variant with the new SKU if needed
                $this->updateVariantAfterCreation($existingVariantByOptions, $sku, $options, $inventory);
                return $existingVariantByOptions;
            }
            
            // Check if this product has no variants yet (only default variant exists)
            // If so, batch create all variants for this product at once
            $defaultVariant = $this->findDefaultVariant($card->shopify_product_id);
            if ($defaultVariant) {
                $defaultVariantDetails = $this->getVariantDetails($defaultVariant);
                // If default variant is unused (no SKU), batch create all variants
                if ($defaultVariantDetails && empty($defaultVariantDetails['sku'] ?? '')) {
                    Log::info("Product has unused default variant, batch creating all variants", [
                        'product_id' => $card->shopify_product_id,
                        'card_id' => $card->id,
                    ]);
                    $createdVariants = $this->createAllVariantsForProduct($card);
                    if ($createdVariants && isset($createdVariants[$inventory->id])) {
                        return $createdVariants[$inventory->id];
                    }
                    // If batch creation failed, fall through to individual creation
                }
            }
            
            // Create new variant individually (fallback if batch creation didn't work)
            Log::info("Creating new variant individually", [
                'card_id' => $card->id,
                'inventory_id' => $inventory->id,
                'sku' => $sku,
                'options' => $options,
            ]);
            return $this->createVariant($card, $inventory, $sku, $options);
            
        } catch (\Exception $e) {
            Log::error("Failed to ensure variant exists", [
                'card_id' => $card->id,
                'inventory_id' => $inventory->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return null;
        }
    }
    
    /**
     * Batch create all variants for a product using productVariantsBulkCreate
     * with REMOVE_STANDALONE_VARIANT strategy to remove default variant
     * Returns array mapping inventory_id => variant_id
     */
    protected function createAllVariantsForProduct(Card $card): ?array
    {
        try {
            $productId = $card->shopify_product_id;
            if (!$productId) {
                return null;
            }
            
            // Load all inventory items for this card
            $card->loadMissing(['inventory.edition']);
            $inventoryItems = $card->inventory;
            
            if ($inventoryItems->isEmpty()) {
                return null;
            }
            
            // Get location ID for inventory quantities
            $locationId = $this->getDefaultLocationId();
            $numericLocationId = $locationId ? $this->extractNumericId($locationId) : null;
            
            // Build variants array for GraphQL mutation
            $variants = [];
            $inventoryMap = []; // Map to track which variant belongs to which inventory
            
            foreach ($inventoryItems as $inventory) {
                $sku = \App\Services\SkuGenerator::generateForInventory($inventory);
                $options = $this->getVariantOptions($inventory);
                $sellPrice = $inventory->sell_price ?? 0;
                
                // Build variant input
                $variantInput = [
                    'price' => (string)$sellPrice,
                    'sku' => $sku,
                ];
                
                // Add option values (options will be auto-created by Shopify)
                if (isset($options[0])) {
                    $variantInput['option1'] = $options[0];
                }
                if (isset($options[1])) {
                    $variantInput['option2'] = $options[1];
                }
                
                // Add inventory quantities if we have a location
                if ($numericLocationId && $inventory->quantity > 0) {
                    $variantInput['inventoryQuantities'] = [
                        [
                            'availableQuantity' => $inventory->quantity,
                            'locationId' => $locationId,
                        ],
                    ];
                }
                
                $variants[] = $variantInput;
                $inventoryMap[] = $inventory->id;
            }
            
            // Build GraphQL mutation with REMOVE_STANDALONE_VARIANT strategy
            // Format variants as GraphQL input array
            $variantsGraphQL = [];
            foreach ($variants as $variant) {
                $variantStr = '{';
                $variantStr .= 'price: "' . addslashes($variant['price']) . '"';
                $variantStr .= ', sku: "' . addslashes($variant['sku']) . '"';
                if (isset($variant['option1'])) {
                    $variantStr .= ', option1: "' . addslashes($variant['option1']) . '"';
                }
                if (isset($variant['option2'])) {
                    $variantStr .= ', option2: "' . addslashes($variant['option2']) . '"';
                }
                if (!empty($variant['inventoryQuantities'])) {
                    $invQty = $variant['inventoryQuantities'][0];
                    $variantStr .= ', inventoryQuantities: [{availableQuantity: ' . (int)$invQty['availableQuantity'] . ', locationId: "' . addslashes($invQty['locationId']) . '"}]';
                }
                $variantStr .= '}';
                $variantsGraphQL[] = $variantStr;
            }
            $variantsList = '[' . implode(', ', $variantsGraphQL) . ']';
            
            $mutation = <<<GRAPHQL
            mutation {
              productVariantsBulkCreate(
                productId: "{$productId}",
                variants: {$variantsList},
                strategy: REMOVE_STANDALONE_VARIANT
              ) {
                productVariants {
                  id
                  sku
                  price
                  option1
                  option2
                }
                userErrors {
                  message
                  field
                }
              }
            }
            GRAPHQL;
            
            Log::debug("Batch creating variants for product", [
                'product_id' => $productId,
                'variant_count' => count($variants),
                'mutation' => $mutation,
            ]);
            
            $response = $this->executeGraphQL($mutation);
            
            if (!$this->isSuccessful($response)) {
                $errors = $this->extractError($response);
                Log::error("Failed to batch create variants", [
                    'product_id' => $productId,
                    'errors' => $errors,
                    'response' => $response,
                ]);
                return null;
            }
            
            $createdVariants = $response['data']['productVariantsBulkCreate']['productVariants'] ?? [];
            $userErrors = $response['data']['productVariantsBulkCreate']['userErrors'] ?? [];
            
            if (!empty($userErrors)) {
                Log::warning("User errors in batch variant creation", [
                    'product_id' => $productId,
                    'errors' => $userErrors,
                ]);
            }
            
            // Map created variants back to inventory items by matching SKU
            $result = [];
            foreach ($createdVariants as $variant) {
                $variantSku = $variant['sku'] ?? '';
                // Find matching inventory item by SKU
                foreach ($inventoryItems as $inventory) {
                    $inventorySku = \App\Services\SkuGenerator::generateForInventory($inventory);
                    if ($variantSku === $inventorySku) {
                        $result[$inventory->id] = $variant['id'];
                        // Update inventory item with variant ID
                        $inventory->update(['shopify_variant_id' => $variant['id']]);
                        break;
                    }
                }
            }
            
            Log::info("Batch created variants for product", [
                'product_id' => $productId,
                'created_count' => count($result),
                'total_inventory' => $inventoryItems->count(),
            ]);
            
            return $result;
            
        } catch (\Exception $e) {
            Log::error("Exception in batch variant creation", [
                'card_id' => $card->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return null;
        }
    }
    
    /**
     * Get variant options (Foil/Non-Foil, Edition)
     */
    protected function getVariantOptions(Inventory $inventory): array
    {
        $options = [];
        
        // Option 1: Foil/Non-Foil
        $options[] = $inventory->is_foil ? 'Foil' : 'Non-Foil';
        
        // Option 2: Edition (if multiple editions exist)
        if ($inventory->edition) {
            $editionLabel = $inventory->edition->slug ?? 
                           ($inventory->edition->collector_number ?? 'Default');
            $options[] = ucfirst($editionLabel);
        }
        
        return $options;
    }
    
    /**
     * Find default variant (the one created automatically by Shopify)
     * Returns the first "Default Title" variant, even if it has a SKU (already used)
     */
    protected function findDefaultVariant(string $productId): ?string
    {
        $query = <<<GRAPHQL
        query {
          product(id: "{$productId}") {
            variants(first: 100) {
              edges {
                node {
                  id
                  title
                  sku
                }
              }
            }
          }
        }
        GRAPHQL;
        
        $response = $this->executeGraphQL($query);
        
        if (isset($response['data']['product']['variants']['edges'])) {
            // First, try to find an unused default variant (no SKU)
            foreach ($response['data']['product']['variants']['edges'] as $edge) {
                $variant = $edge['node'];
                if (($variant['title'] ?? '') === 'Default Title' && empty($variant['sku'] ?? '')) {
                    return $variant['id'];
                }
            }
            // If no unused default variant, return the first "Default Title" variant (even if it has a SKU)
            // This allows us to reuse it when we get "Default Title already exists" error
            foreach ($response['data']['product']['variants']['edges'] as $edge) {
                $variant = $edge['node'];
                if (($variant['title'] ?? '') === 'Default Title') {
                    return $variant['id'];
                }
            }
        }
        
        return null;
    }
    
    /**
     * Find existing variant by SKU
     */
    protected function findVariantBySku(string $productId, string $sku): ?string
    {
        $query = <<<GRAPHQL
        query {
          product(id: "{$productId}") {
            variants(first: 100) {
              edges {
                node {
                  id
                  sku
                }
              }
            }
          }
        }
        GRAPHQL;
        
        $response = $this->executeGraphQL($query);
        
        if (isset($response['data']['product']['variants']['edges'])) {
            foreach ($response['data']['product']['variants']['edges'] as $edge) {
                if (($edge['node']['sku'] ?? '') === $sku) {
                    return $edge['node']['id'];
                }
            }
        }
        
        return null;
    }
    
    /**
     * Find existing variant by matching option values
     */
    protected function findVariantByOptions(string $productId, array $options): ?string
    {
        $query = <<<GRAPHQL
        query {
          product(id: "{$productId}") {
            variants(first: 100) {
              edges {
                node {
                  id
                  selectedOptions {
                    name
                    value
                  }
                }
              }
            }
          }
        }
        GRAPHQL;
        
        $response = $this->executeGraphQL($query);
        
        if (isset($response['data']['product']['variants']['edges'])) {
            foreach ($response['data']['product']['variants']['edges'] as $edge) {
                $variant = $edge['node'];
                $selectedOptions = $variant['selectedOptions'] ?? [];
                
                // Build array of option values from variant
                $variantOptionValues = [];
                foreach ($selectedOptions as $selectedOption) {
                    $variantOptionValues[] = $selectedOption['value'] ?? '';
                }
                
                // Compare option values (order matters in Shopify)
                if ($variantOptionValues === $options) {
                    return $variant['id'];
                }
            }
        }
        
        return null;
    }
    
    /**
     * Get variant details (SKU, options, etc.)
     */
    protected function getVariantDetails(string $variantId): ?array
    {
        $query = <<<GRAPHQL
        query {
          productVariant(id: "{$variantId}") {
            id
            sku
            title
            selectedOptions {
              name
              value
            }
          }
        }
        GRAPHQL;
        
        $response = $this->executeGraphQL($query);
        
        return $response['data']['productVariant'] ?? null;
    }
    
    /**
     * Get variant's inventory item ID
     */
    protected function getVariantInventoryItemId(string $variantId): ?string
    {
        $query = <<<GRAPHQL
        query {
          productVariant(id: "{$variantId}") {
            id
            inventoryItem {
              id
            }
          }
        }
        GRAPHQL;
        
        $response = $this->executeGraphQL($query);
        
        return $response['data']['productVariant']['inventoryItem']['id'] ?? null;
    }
    
    /**
     * Create a new variant for an inventory item
     * Uses REST Admin API to create variants with options (GraphQL productVariantsBulkCreate doesn't accept options)
     */
    protected function createVariant(Card $card, Inventory $inventory, string $sku, array $options): ?string
    {
        $productId = $card->shopify_product_id;
        $sellPrice = $inventory->sell_price ?? 0;
        
        // Extract numeric product ID from GID
        $numericProductId = $this->extractNumericId($productId);
        if (!$numericProductId) {
            Log::warning("Could not extract numeric product ID", [
                'product_id' => $productId,
            ]);
            return null;
        }
        
        // Get location ID for inventory quantities
        $locationId = $inventory->shopify_location_id ?? $this->getDefaultLocationId();
        
        // Build option values for REST API
        // Options format: option1, option2, option3
        $option1 = $options[0] ?? '';
        $option2 = $options[1] ?? '';
        
        // Use REST Admin API to create variant with options
        $variantData = [
            'variant' => [
                'product_id' => (int)$numericProductId,
                'price' => (string)$sellPrice,
                'sku' => $sku,
                'inventory_management' => 'shopify',
                'inventory_policy' => 'deny',
                'option1' => $option1,
            ],
        ];
        
        if ($option2) {
            $variantData['variant']['option2'] = $option2;
        }
        
        // If we have a location, set initial inventory quantity
        if ($locationId) {
            $numericLocationId = $this->extractNumericId($locationId);
            if ($numericLocationId) {
                $variantData['variant']['inventory_quantity'] = $inventory->quantity;
            }
        }
        
        Log::debug("Creating variant via REST API", [
            'url' => "{$this->shopUrl}/admin/api/2024-01/products/{$numericProductId}/variants.json",
            'variant_data' => $variantData,
            'product_id' => $productId,
            'numeric_product_id' => $numericProductId,
        ]);
        
        $response = Http::withHeaders([
            'X-Shopify-Access-Token' => $this->accessToken,
            'Content-Type' => 'application/json',
        ])->post("{$this->shopUrl}/admin/api/2024-01/products/{$numericProductId}/variants.json", $variantData);
        
        Log::debug("REST API response", [
            'status' => $response->status(),
            'successful' => $response->successful(),
            'body' => $response->body(),
            'json' => $response->json(),
        ]);
        
        if (!$response->successful()) {
            $errors = $response->json()['errors'] ?? [];
            $errorMessage = is_array($errors) ? json_encode($errors) : (string)$errors;
            
            // Check if error is "Default Title already exists" or similar
            if (stripos($errorMessage, 'Default Title') !== false || stripos($errorMessage, 'already exists') !== false) {
                // Try to find and reuse the existing default variant
                Log::info("Default Title variant already exists, attempting to find and reuse it", [
                    'product_id' => $productId,
                    'sku' => $sku,
                ]);
                $existingDefault = $this->findDefaultVariant($productId);
                if ($existingDefault) {
                    // Update the existing default variant with our SKU and options
                    Log::info("Found existing default variant, updating it", [
                        'variant_id' => $existingDefault,
                        'sku' => $sku,
                    ]);
                    if ($this->updateVariantAfterCreation($existingDefault, $sku, $options, $inventory)) {
                        return $existingDefault;
                    }
                }
            }
            
            Log::error('Failed to create variant via REST API', [
                'product_id' => $productId,
                'sku' => $sku,
                'status' => $response->status(),
                'errors' => $errors,
                'body' => $response->body(),
            ]);
            return null;
        }
        
        $variantResponse = $response->json();
        if (!isset($variantResponse['variant']['id'])) {
            Log::error("Failed to create variant - no variant ID returned", [
                'response' => $variantResponse,
                'card_id' => $card->id,
                'inventory_id' => $inventory->id,
                'sku' => $sku,
            ]);
            return null;
        }
        
        // Convert numeric ID to GID format
        $numericVariantId = $variantResponse['variant']['id'];
        $variantId = "gid://shopify/ProductVariant/{$numericVariantId}";
        $inventoryItemId = isset($variantResponse['variant']['inventory_item_id']) 
            ? "gid://shopify/InventoryItem/{$variantResponse['variant']['inventory_item_id']}"
            : null;
        
        // Activate inventory at location if we have a location ID
        if ($locationId && $inventoryItemId) {
            $this->ensureInventoryTrackingEnabled($variantId, $inventoryItemId, $locationId);
        }
        
        Log::info("Created Shopify variant via REST API", [
            'card_id' => $card->id,
            'inventory_id' => $inventory->id,
            'variant_id' => $variantId,
            'inventory_item_id' => $inventoryItemId,
            'sku' => $sku,
            'options' => $options,
        ]);
        
        return $variantId;
    }
    
    /**
     * Create new product in Shopify
     */
    protected function createProduct(Card $card): bool
    {
        // Escape special characters for GraphQL
        $cardName = addslashes($card->name ?? 'Untitled Card');
        $vendor = addslashes($this->getVendorName($card->game ?? ''));
        
        // Build tags array (GraphQL format: ["tag1", "tag2"])
        $tags = array_filter([
            $card->game ?? null,
            $card->set_name ?? null,
            is_array($card->types) && !empty($card->types) ? $card->types[0] : null,
            is_array($card->classes) && !empty($card->classes) ? $card->classes[0] : null,
        ]);
        
        // Format tags for GraphQL: ["tag1", "tag2"] or []
        $tagsList = !empty($tags) 
            ? '["' . implode('", "', array_map('addslashes', $tags)) . '"]'
            : '[]';
        
        // Get card image URL if available
        $imageUrl = $card->image_url ?? $card->editions()->first()?->image_url ?? null;
        
        // Build description from card data
        $description = $this->buildProductDescription($card);
        
        // Build mutation - create product first, then we'll add media/description separately if needed
        // Shopify's ProductInput doesn't accept description, images, variants, or options directly
        // Options are automatically created when we create variants with different option values
        // We'll create the product with basic info, then update it
        
        $mutation = <<<GRAPHQL
        mutation {
          productCreate(input: {
            title: "{$cardName}",
            productType: "Trading Card",
            vendor: "{$vendor}",
            tags: {$tagsList}
          }) {
            product {
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
        
        // Check for GraphQL errors first
        if (isset($response['errors']) && !empty($response['errors'])) {
            $errors = collect($response['errors'])
                ->map(function($error) {
                    return $error['message'] ?? json_encode($error);
                })
                ->implode(', ');
            Log::error('Shopify GraphQL errors in productCreate', [
                'errors' => $response['errors'],
                'card_id' => $card->id,
            ]);
            throw new \Exception("Shopify API error: {$errors}");
        }
        
        // Check for user errors
        if (isset($response['data']['productCreate']['userErrors']) && 
            !empty($response['data']['productCreate']['userErrors'])) {
            $errors = collect($response['data']['productCreate']['userErrors'])
                ->map(function($error) {
                    return ($error['message'] ?? 'Unknown error') . 
                           (isset($error['field']) ? " (field: {$error['field']})" : '');
                })
                ->implode(', ');
            Log::error('Shopify userErrors in productCreate', [
                'userErrors' => $response['data']['productCreate']['userErrors'],
                'card_id' => $card->id,
            ]);
            throw new \Exception("Shopify product creation failed: {$errors}");
        }
        
        // Check if product was created
        if (!isset($response['data']['productCreate']['product'])) {
            Log::error('Shopify productCreate returned no product', [
                'response' => $response,
                'card_id' => $card->id,
            ]);
            throw new \Exception("Shopify product creation failed: No product returned in response");
        }
        
        $productId = $response['data']['productCreate']['product']['id'];
        
        // Note: Product options will be automatically created when we create variants
        // using productVariantsBulkCreate with REMOVE_STANDALONE_VARIANT strategy
        // This removes the default variant and creates all variants with options in one go
        
        // Update product description if we have one
        if ($description) {
            $escapedDescription = addslashes($description);
            $updateMutation = <<<GRAPHQL
            mutation {
              productUpdate(input: {
                id: "{$productId}",
                descriptionHtml: "{$escapedDescription}"
              }) {
                product {
                  id
                }
                userErrors {
                  message
                  field
                }
              }
            }
            GRAPHQL;
            $this->executeGraphQL($updateMutation); // Don't fail if this fails
        }
        
        // Add media if we have an image
        // Use productCreateMedia mutation (productUpdate doesn't accept media)
        if ($imageUrl) {
            $escapedImageUrl = addslashes($imageUrl);
            $mediaMutation = <<<GRAPHQL
            mutation {
              productCreateMedia(productId: "{$productId}", media: [{
                mediaContentType: IMAGE,
                originalSource: "{$escapedImageUrl}"
              }]) {
                media {
                  id
                }
                mediaUserErrors {
                  message
                  field
                }
                userErrors {
                  message
                  field
                }
              }
            }
            GRAPHQL;
            $this->executeGraphQL($mediaMutation); // Don't fail if this fails
        }
        
        // Save the product ID to the card
        // Variant IDs are stored on individual inventory items
        $card->update([
            'shopify_product_id' => $productId,
        ]);
        
        Log::info("Created Shopify product", [
            'card_id' => $card->id,
            'shopify_product_id' => $productId,
        ]);
        
        return true;
    }
    
    /**
     * Update existing product variant
     */
    protected function updateProductVariant(Card $card): bool
    {
        // Get sell price from first inventory item, or default to 0
        $firstInventory = $card->inventory->first();
        $sellPrice = $firstInventory ? ($firstInventory->sell_price ?? 0) : 0;
        
        $mutation = <<<GRAPHQL
        mutation {
          productVariantUpdate(input: {
            id: "{$card->shopify_variant_id}",
            price: "{$sellPrice}"
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
     * Update variant after creation to set SKU, inventory management, and options
     * Uses REST Admin API because GraphQL productVariantsBulkUpdate doesn't accept sku or inventoryManagement
     */
    protected function updateVariantAfterCreation(string $variantId, string $sku, array $options, Inventory $inventory): bool
    {
        // Extract numeric ID from GID (gid://shopify/ProductVariant/52920709874029 -> 52920709874029)
        $numericVariantId = $this->extractNumericId($variantId);
        if (!$numericVariantId) {
            Log::warning("Could not extract numeric variant ID", [
                'variant_id' => $variantId,
            ]);
            return false;
        }
        
        // Build option values for REST API
        $option1 = $options[0] ?? '';
        $option2 = $options[1] ?? '';
        
        // Use REST Admin API to update variant (GraphQL doesn't support sku/inventoryManagement in bulk update)
        $variantData = [
            'variant' => [
                'sku' => $sku,
                'inventory_management' => 'shopify',
                'inventory_policy' => 'deny',
                'option1' => $option1,
            ],
        ];
        
        if ($option2) {
            $variantData['variant']['option2'] = $option2;
        }
        
        $response = Http::withHeaders([
            'X-Shopify-Access-Token' => $this->accessToken,
            'Content-Type' => 'application/json',
        ])->put("{$this->shopUrl}/admin/api/2024-01/variants/{$numericVariantId}.json", $variantData);
        
        if (!$response->successful()) {
            Log::warning("Failed to update variant after creation via REST API", [
                'variant_id' => $variantId,
                'sku' => $sku,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            return false;
        }
        
        Log::info("Updated variant SKU and inventory management via REST API", [
            'variant_id' => $variantId,
            'sku' => $sku,
        ]);
        
        return true;
    }
    
    /**
     * Extract numeric ID from Shopify GID
     * gid://shopify/ProductVariant/52920709874029 -> 52920709874029
     */
    protected function extractNumericId(string $gid): ?string
    {
        if (preg_match('/\/(\d+)$/', $gid, $matches)) {
            return $matches[1];
        }
        return null;
    }
    
    /**
     * Get product ID from variant ID
     */
    protected function getProductIdFromVariant(string $variantId): ?string
    {
        $query = <<<GRAPHQL
        query {
          productVariant(id: "{$variantId}") {
            id
            product {
              id
            }
          }
        }
        GRAPHQL;
        
        $response = $this->executeGraphQL($query);
        
        return $response['data']['productVariant']['product']['id'] ?? null;
    }
    
    /**
     * Verify that a product exists in Shopify
     */
    protected function verifyProductExists(string $productId): bool
    {
        $query = <<<GRAPHQL
        query {
          product(id: "{$productId}") {
            id
          }
        }
        GRAPHQL;
        
        $response = $this->executeGraphQL($query);
        
        // Check if product exists (no errors and product data returned)
        if (isset($response['errors']) && !empty($response['errors'])) {
            return false;
        }
        
        return isset($response['data']['product']['id']);
    }
    
    /**
     * Update variant price in Shopify
     * Uses productVariantsBulkUpdate (productVariantUpdate is deprecated)
     */
    protected function updateVariantPrice(string $variantId, float $price): bool
    {
        // Get productId from variant
        $productId = $this->getProductIdFromVariant($variantId);
        if (!$productId) {
            Log::warning("Could not get productId for variant in updateVariantPrice", [
                'variant_id' => $variantId,
            ]);
            return false;
        }
        
        $mutation = <<<GRAPHQL
        mutation {
          productVariantsBulkUpdate(productId: "{$productId}", variants: [
            {
              id: "{$variantId}",
              price: "{$price}"
            }
          ]) {
            productVariants {
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
        
        if (!$this->isSuccessful($response)) {
            Log::warning("Failed to update variant price", [
                'variant_id' => $variantId,
                'price' => $price,
                'errors' => $this->extractError($response),
            ]);
            return false;
        }
        
        Log::info("Updated variant price in Shopify", [
            'variant_id' => $variantId,
            'price' => $price,
        ]);
        
        return true;
    }
    
    /**
     * Ensure inventory tracking is enabled on a variant
     * This sets inventoryManagement to SHOPIFY and activates inventory at the location
     */
    protected function ensureInventoryTrackingEnabled(string $variantId, string $inventoryItemId, string $locationId): bool
    {
        // Check if inventory item is tracked
        $query = <<<GRAPHQL
        query {
          productVariant(id: "{$variantId}") {
            id
            inventoryItem {
              id
              tracked
            }
          }
        }
        GRAPHQL;
        
        $response = $this->executeGraphQL($query);
        $variant = $response['data']['productVariant'] ?? null;
        
        if (!$variant) {
            Log::warning("Could not fetch variant to check inventory tracking", [
                'variant_id' => $variantId,
            ]);
            return false;
        }
        
        // Check if tracking is already enabled
        $isTracked = ($variant['inventoryItem']['tracked'] ?? false) === true;
        
        if ($isTracked) {
            Log::debug("Inventory tracking already enabled on variant", [
                'variant_id' => $variantId,
            ]);
            return true;
        }
        
        // Step 1: Set inventoryManagement to SHOPIFY on the variant
        // Use REST Admin API because GraphQL productVariantsBulkUpdate doesn't accept inventoryManagement
        $numericVariantId = $this->extractNumericId($variantId);
        if ($numericVariantId) {
            $restResponse = Http::withHeaders([
                'X-Shopify-Access-Token' => $this->accessToken,
                'Content-Type' => 'application/json',
            ])->put("{$this->shopUrl}/admin/api/2024-01/variants/{$numericVariantId}.json", [
                'variant' => [
                    'inventory_management' => 'shopify',
                    'inventory_policy' => 'deny',
                ],
            ]);
            
            if (!$restResponse->successful()) {
                Log::warning("Failed to set inventoryManagement on variant via REST API", [
                    'variant_id' => $variantId,
                    'status' => $restResponse->status(),
                    'body' => $restResponse->body(),
                ]);
                // Continue anyway - inventoryActivate might still work
            } else {
                Log::info("Set inventoryManagement to SHOPIFY via REST API", [
                    'variant_id' => $variantId,
                ]);
            }
        }
        
        // Step 2: Activate inventory at the location (this enables the "Track quantity" toggle)
        // Note: inventoryActivate doesn't return inventoryItem in the payload
        $activateMutation = <<<GRAPHQL
        mutation {
          inventoryActivate(inventoryItemId: "{$inventoryItemId}", locationId: "{$locationId}") {
            inventoryLevel {
              id
            }
            userErrors {
              message
              field
            }
          }
        }
        GRAPHQL;
        
        $activateResponse = $this->executeGraphQL($activateMutation);
        
        if (!$this->isSuccessful($activateResponse)) {
            Log::warning("Failed to activate inventory at location", [
                'variant_id' => $variantId,
                'inventory_item_id' => $inventoryItemId,
                'location_id' => $locationId,
                'errors' => $this->extractError($activateResponse),
            ]);
            // Don't fail completely - inventoryManagement might still be set
        }
        
        Log::info("Enabled inventory tracking on variant", [
            'variant_id' => $variantId,
            'inventory_item_id' => $inventoryItemId,
            'location_id' => $locationId,
        ]);
        
        return true;
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
    
    /**
     * Build product description from card data
     */
    protected function buildProductDescription(Card $card): string
    {
        $parts = [];
        
        if ($card->set_name) {
            $parts[] = "Set: {$card->set_name}";
        }
        
        if ($card->rarity) {
            $parts[] = "Rarity: {$card->rarity}";
        }
        
        if (is_array($card->types) && !empty($card->types)) {
            $parts[] = "Type: " . implode(', ', $card->types);
        }
        
        if (is_array($card->classes) && !empty($card->classes)) {
            $parts[] = "Class: " . implode(', ', $card->classes);
        }
        
        if ($card->text) {
            $parts[] = "\n{$card->text}";
        }
        
        return implode("\n", $parts);
    }
}
