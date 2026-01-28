<?php

namespace App\Http\Controllers\Shopify;

use App\Http\Controllers\Controller;
use App\Models\MarketplaceIntegration;
use App\Models\Store;
use App\Services\Marketplace\ShopifyInventorySync;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class ShopifyOAuthController extends Controller
{
    /**
     * Initiate Shopify OAuth flow
     */
    public function initiate(Request $request)
    {
        $request->validate([
            'store_id' => 'required|exists:stores,id',
            'shop' => 'required|string', // e.g., "mystore" or "mystore.myshopify.com"
        ]);

        $store = Store::findOrFail($request->store_id);
        
        // Verify user has access to this store
        if (!Auth::user()->canAccessStore($store)) {
            abort(403, 'You do not have access to this store');
        }

        // Normalize shop domain
        $shop = $this->normalizeShopDomain($request->shop);
        
        // Generate state token for CSRF protection
        $state = Str::random(40);
        session([
            'shopify_oauth_state' => $state,
            'shopify_oauth_store_id' => $store->id,
            'shopify_oauth_shop' => $shop,
        ]);

        // Build OAuth URL
        $clientId = config('services.shopify.client_id');
        $redirectUri = config('services.shopify.redirect_uri');
        $scopes = 'read_products,write_products,read_inventory,write_inventory,read_locations';

        $authUrl = "https://{$shop}/admin/oauth/authorize?" . http_build_query([
            'client_id' => $clientId,
            'scope' => $scopes,
            'redirect_uri' => $redirectUri,
            'state' => $state,
        ]);

        Log::info('Shopify OAuth initiated', [
            'store_id' => $store->id,
            'shop' => $shop,
        ]);

        return redirect($authUrl);
    }

    /**
     * Handle Shopify OAuth callback
     */
    public function callback(Request $request)
    {
        // Verify state token
        $state = $request->query('state');
        $sessionState = session('shopify_oauth_state');
        
        if (!$state || $state !== $sessionState) {
            Log::error('Shopify OAuth state mismatch', [
                'received' => $state,
                'expected' => $sessionState,
            ]);
            abort(403, 'Invalid state parameter');
        }

        $code = $request->query('code');
        $shop = session('shopify_oauth_shop');
        $storeId = session('shopify_oauth_store_id');

        if (!$code || !$shop || !$storeId) {
            Log::error('Shopify OAuth callback missing parameters', [
                'has_code' => !empty($code),
                'has_shop' => !empty($shop),
                'has_store_id' => !empty($storeId),
            ]);
            abort(400, 'Missing required parameters');
        }

        // Exchange code for access token
        try {
            $clientId = config('services.shopify.client_id');
            $clientSecret = config('services.shopify.client_secret');
            $redirectUri = config('services.shopify.redirect_uri');

            $response = Http::asForm()->post("https://{$shop}/admin/oauth/access_token", [
                'client_id' => $clientId,
                'client_secret' => $clientSecret,
                'code' => $code,
            ]);

            if (!$response->successful()) {
                Log::error('Shopify OAuth token exchange failed', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                    'shop' => $shop,
                ]);
                throw new \Exception('Failed to exchange authorization code for access token');
            }

            $data = $response->json();
            $accessToken = $data['access_token'] ?? null;
            $scope = $data['scope'] ?? '';

            if (!$accessToken) {
                throw new \Exception('No access token in response');
            }

            // Store or update integration
            $integration = MarketplaceIntegration::updateOrCreate(
                [
                    'store_id' => $storeId,
                    'marketplace' => 'shopify',
                ],
                [
                    'enabled' => true,
                    'credentials' => [
                        'shop_url' => "https://{$shop}",
                        'access_token' => $accessToken,
                        'scope' => $scope,
                    ],
                    'settings' => [],
                ]
            );
            
            // Refresh the model to ensure credentials are loaded
            $integration->refresh();
            
            Log::info('Shopify OAuth integration saved', [
                'integration_id' => $integration->id,
                'store_id' => $storeId,
                'shop' => $shop,
                'has_token' => !empty($accessToken),
                'token_length' => strlen($accessToken),
                'scope' => $scope,
            ]);

            // Test connection and fetch locations
            $service = new ShopifyInventorySync($integration);
            $connectionSuccess = $service->testConnection();

            if ($connectionSuccess) {
                try {
                    $locations = $service->fetchLocations();
                    if (!empty($locations)) {
                        $settings = $integration->settings ?? [];
                        $settings['default_location_id'] = $locations[0]['id'];
                        $integration->update(['settings' => $settings]);
                    }
                } catch (\Exception $e) {
                    Log::warning('Failed to fetch locations after OAuth', [
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            // Clear OAuth session data
            session()->forget([
                'shopify_oauth_state',
                'shopify_oauth_store_id',
                'shopify_oauth_shop',
            ]);

            Log::info('Shopify OAuth completed successfully', [
                'store_id' => $storeId,
                'shop' => $shop,
                'has_write_products' => str_contains($scope, 'write_products'),
            ]);

            // Redirect back to marketplace settings with success message
            return redirect()
                ->route('filament.store.pages.marketplace-settings')
                ->with('shopify_connected', true)
                ->with('shop_name', $shop);

        } catch (\Exception $e) {
            Log::error('Shopify OAuth callback error', [
                'error' => $e->getMessage(),
                'shop' => $shop,
            ]);

            return redirect()
                ->route('filament.store.pages.marketplace-settings')
                ->with('shopify_error', $e->getMessage());
        }
    }

    /**
     * Normalize shop domain (remove .myshopify.com if present, add if missing)
     */
    protected function normalizeShopDomain(string $shop): string
    {
        $shop = strtolower(trim($shop));
        $shop = str_replace(['https://', 'http://'], '', $shop);
        $shop = rtrim($shop, '/');
        
        // Remove .myshopify.com if present
        if (str_ends_with($shop, '.myshopify.com')) {
            $shop = str_replace('.myshopify.com', '', $shop);
        }
        
        // Add .myshopify.com
        return $shop . '.myshopify.com';
    }
}

