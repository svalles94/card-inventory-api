<?php

namespace App\Console\Commands;

use App\Models\Card;
use App\Models\Inventory;
use Illuminate\Console\Command;

class ClearShopifyReferences extends Command
{
    protected $signature = 'shopify:clear-references 
                            {--variants-only : Only clear variant IDs, keep product IDs}
                            {--confirm : Skip confirmation prompt}';

    protected $description = 'Clear all Shopify product/variant IDs from database (use before re-syncing with new structure)';

    public function handle()
    {
        $variantsOnly = $this->option('variants-only');
        
        if (!$this->option('confirm')) {
            $message = $variantsOnly 
                ? 'This will clear all Shopify variant IDs from the database (products will be kept). Continue?'
                : 'This will clear all Shopify product/variant IDs from the database. Continue?';
            
            if (!$this->confirm($message)) {
                $this->info('Cancelled');
                return 0;
            }
        }

        if ($variantsOnly) {
            $this->info('Clearing only variant IDs (keeping products)...');
            
            // Clear variant IDs from cards table (legacy, shouldn't be there but clear just in case)
            $cardsUpdated = Card::whereNotNull('shopify_variant_id')
                ->orWhereNotNull('shopify_inventory_item_id')
                ->update([
                    'shopify_variant_id' => null,
                    'shopify_inventory_item_id' => null,
                ]);

            $this->info("Cleared variant references from {$cardsUpdated} cards");

            // Clear variant IDs from inventory table
            $inventoryUpdated = Inventory::whereNotNull('shopify_variant_id')
                ->update([
                    'shopify_variant_id' => null,
                ]);

            $this->info("Cleared variant IDs from {$inventoryUpdated} inventory items");
            $this->info('✓ Done! Variants will be recreated with proper options on next sync.');
            $this->info('⚠ Note: Existing variants in Shopify will be reused if they match by SKU.');
        } else {
            $this->info('Clearing all Shopify references...');

            // Clear from cards table
            $cardsUpdated = Card::whereNotNull('shopify_product_id')
                ->orWhereNotNull('shopify_variant_id')
                ->orWhereNotNull('shopify_inventory_item_id')
                ->update([
                    'shopify_product_id' => null,
                    'shopify_variant_id' => null,
                    'shopify_inventory_item_id' => null,
                ]);

            $this->info("Cleared references from {$cardsUpdated} cards");

            // Clear from inventory table
            $inventoryUpdated = Inventory::whereNotNull('shopify_variant_id')
                ->update([
                    'shopify_variant_id' => null,
                ]);

            $this->info("Cleared references from {$inventoryUpdated} inventory items");

            $this->info('✓ Done! You can now delete old products in Shopify and re-sync.');
            $this->warn('⚠ Remember to delete the old products in your Shopify admin panel!');
        }

        return 0;
    }
}

