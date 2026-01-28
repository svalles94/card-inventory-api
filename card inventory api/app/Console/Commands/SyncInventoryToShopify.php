<?php

namespace App\Console\Commands;

use App\Models\Inventory;
use App\Models\MarketplaceIntegration;
use App\Services\Marketplace\ShopifyInventorySync;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class SyncInventoryToShopify extends Command
{
    protected $signature = 'shopify:sync-inventory 
                            {--store= : Store ID to sync}
                            {--location= : Location ID to sync (optional)}
                            {--limit= : Limit number of items to sync}
                            {--force : Force sync even if already synced}';

    protected $description = 'Sync all inventory items to Shopify (creates products if needed)';

    public function handle()
    {
        $storeId = $this->option('store');
        $locationId = $this->option('location');
        $limit = $this->option('limit');
        $force = $this->option('force');

        // Get Shopify integration
        $integration = MarketplaceIntegration::where('marketplace', 'shopify')
            ->where('enabled', true)
            ->when($storeId, fn($q) => $q->where('store_id', $storeId))
            ->first();

        if (!$integration) {
            $this->error('No enabled Shopify integration found' . ($storeId ? " for store {$storeId}" : ''));
            return 1;
        }

        $this->info("Found Shopify integration for store: {$integration->store_id}");

        // Build inventory query
        // If force is true, sync ALL inventory items (even if they have products/variants)
        // This ensures quantities are always updated
        $query = Inventory::query()
            ->whereHas('location.store', function($q) use ($integration) {
                $q->where('id', $integration->store_id);
            })
            ->whereHas('card', function($q) {
                $q->where('sync_to_shopify', true);
            })
            // If not forcing, only sync items that need products/variants created
            ->when(!$force, function($q) {
                $hasVariantIdColumn = Schema::hasColumn('inventory', 'shopify_variant_id');
                
                // Items without products OR items with products but without variants
                $q->where(function($subQuery) use ($hasVariantIdColumn) {
                    $subQuery->whereHas('card', fn($q) => $q->whereNull('shopify_product_id'));
                    
                    if ($hasVariantIdColumn) {
                        $subQuery->orWhere(function($q) {
                            $q->whereHas('card', fn($q) => $q->whereNotNull('shopify_product_id'))
                              ->whereNull('shopify_variant_id');
                        });
                    }
                });
            })
            ->when($locationId, fn($q) => $q->where('location_id', $locationId))
            ->with(['card', 'location']);

        $total = $query->count();
        
        if ($limit) {
            $query->limit((int)$limit);
        }

        $this->info("Found {$total} inventory items to sync" . ($limit ? " (processing {$limit})" : ''));

        if ($total === 0) {
            $this->warn('No inventory items found to sync');
            return 0;
        }

        // Skip confirmation when called non-interactively (e.g., from web interface)
        if ($this->input->isInteractive() && !$this->option('no-interaction')) {
            if (!$this->confirm("Proceed with syncing {$total} items to Shopify?")) {
                $this->info('Cancelled');
                return 0;
            }
        } else {
            $this->info("Starting sync of {$total} items to Shopify...");
        }

        $service = new ShopifyInventorySync($integration);
        $bar = $this->output->createProgressBar($total);
        $bar->start();

        $synced = 0;
        $failed = 0;
        $errors = [];

        foreach ($query->get() as $inventory) {
            try {
                $result = $service->syncInventory($inventory);
                
                if ($result) {
                    $synced++;
                } else {
                    $failed++;
                    $errorMsg = $inventory->sync_error ?? 'Unknown error';
                    $errors[] = "Card: {$inventory->card->name} - {$errorMsg}";
                }
            } catch (\Exception $e) {
                $failed++;
                $errors[] = "Card: {$inventory->card->name} - {$e->getMessage()}";
                Log::error("Bulk sync failed for inventory", [
                    'inventory_id' => $inventory->id,
                    'card_id' => $inventory->card_id,
                    'card_name' => $inventory->card->name ?? 'Unknown',
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        $this->info("Sync completed!");
        $this->info("✓ Synced: {$synced}");
        $this->info("✗ Failed: {$failed}");

        if (!empty($errors)) {
            $this->newLine();
            $this->warn('Errors:');
            $maxErrors = $this->option('verbose') ? 50 : 10;
            foreach (array_slice($errors, 0, $maxErrors) as $error) {
                $this->line("  - {$error}");
            }
            if (count($errors) > $maxErrors) {
                $this->line("  ... and " . (count($errors) - $maxErrors) . " more");
            }
            $this->newLine();
            $this->info("Run with --verbose flag to see more details, or check storage/logs/laravel.log for full error messages");
        }

        return 0;
    }
}

