<?php

namespace App\Observers;

use App\Models\Inventory;
use App\Models\MarketplaceIntegration;
use App\Services\Marketplace\ShopifyInventorySync;
use App\Services\Marketplace\EbayInventorySync;
use App\Services\Marketplace\TcgPlayerInventorySync;
use App\Services\Marketplace\AmazonInventorySync;
use Illuminate\Support\Facades\Log;

class InventoryObserver
{
    /**
     * Handle the Inventory "updated" event.
     * This fires whenever inventory quantity changes.
     */
    public function updated(Inventory $inventory): void
    {
        // Only sync if quantity actually changed
        if (!$inventory->wasChanged('quantity')) {
            return;
        }
        
        // Get store from location
        $store = $inventory->location->store;
        
        // Get all enabled marketplace integrations for this store
        $integrations = MarketplaceIntegration::where('store_id', $store->id)
            ->where('enabled', true)
            ->get();
        
        foreach ($integrations as $integration) {
            $this->syncToMarketplace($inventory, $integration);
        }
    }
    
    /**
     * Sync inventory to marketplace
     */
    protected function syncToMarketplace(Inventory $inventory, MarketplaceIntegration $integration): void
    {
        try {
            match ($integration->marketplace) {
                'shopify' => $this->syncToShopify($inventory, $integration),
                'ebay' => $this->syncToEbay($inventory, $integration),
                'tcgplayer' => $this->syncToTcgPlayer($inventory, $integration),
                'amazon' => $this->syncToAmazon($inventory, $integration),
                default => Log::warning("Unknown marketplace: {$integration->marketplace}"),
            };
        } catch (\Exception $e) {
            Log::error("Failed to sync to {$integration->marketplace}", [
                'inventory_id' => $inventory->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
    
    /**
     * Sync to Shopify
     */
    protected function syncToShopify(Inventory $inventory, MarketplaceIntegration $integration): void
    {
        $service = new ShopifyInventorySync($integration);
        $service->syncInventory($inventory);
    }
    
    /**
     * Sync to eBay
     */
    protected function syncToEbay(Inventory $inventory, MarketplaceIntegration $integration): void
    {
        $service = new EbayInventorySync($integration);
        $service->syncInventory($inventory);
    }
    
    /**
     * Sync to TCGPlayer
     */
    protected function syncToTcgPlayer(Inventory $inventory, MarketplaceIntegration $integration): void
    {
        $service = new TcgPlayerInventorySync($integration);
        $service->syncInventory($inventory);
    }
    
    /**
     * Sync to Amazon
     */
    protected function syncToAmazon(Inventory $inventory, MarketplaceIntegration $integration): void
    {
        $service = new AmazonInventorySync($integration);
        $service->syncInventory($inventory);
    }
}
