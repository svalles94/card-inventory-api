<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Add Shopify integration fields to cards table
        Schema::table('cards', function (Blueprint $table) {
            $table->string('shopify_product_id')->nullable()->after('foil_type');
            $table->string('shopify_variant_id')->nullable()->after('shopify_product_id');
            $table->string('shopify_inventory_item_id')->nullable()->after('shopify_variant_id');
            $table->string('sku')->nullable()->unique()->after('card_number');
            $table->boolean('sync_to_shopify')->default(true)->after('shopify_inventory_item_id');
            
            $table->index('shopify_product_id');
            $table->index('shopify_variant_id');
            $table->index('sku');
        });
        
        // Add Shopify integration fields to inventory table
        Schema::table('inventory', function (Blueprint $table) {
            $table->string('shopify_location_id')->nullable()->after('location_id');
            $table->string('shopify_inventory_level_id')->nullable()->after('shopify_location_id');
            $table->timestamp('last_synced_at')->nullable()->after('shopify_inventory_level_id');
            $table->string('sync_status')->default('pending')->after('last_synced_at'); // pending, synced, failed
            $table->text('sync_error')->nullable()->after('sync_status');
            
            $table->index(['location_id', 'sync_status']);
        });
        
        // Add marketplace integration table for future expansions (eBay, TCGPlayer, etc.)
        Schema::create('marketplace_integrations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained('stores')->onDelete('cascade');
            $table->string('marketplace'); // 'shopify', 'ebay', 'tcgplayer', 'amazon'
            $table->boolean('enabled')->default(true);
            $table->json('credentials'); // API keys, tokens, etc. (encrypted)
            $table->json('settings')->nullable(); // Marketplace-specific settings
            $table->timestamp('last_sync_at')->nullable();
            $table->timestamps();
            
            $table->unique(['store_id', 'marketplace']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cards', function (Blueprint $table) {
            $table->dropIndex(['shopify_product_id']);
            $table->dropIndex(['shopify_variant_id']);
            $table->dropIndex(['sku']);
            $table->dropColumn([
                'shopify_product_id',
                'shopify_variant_id',
                'shopify_inventory_item_id',
                'sku',
                'sync_to_shopify'
            ]);
        });
        
        Schema::table('inventory', function (Blueprint $table) {
            $table->dropIndex(['location_id', 'sync_status']);
            $table->dropColumn([
                'shopify_location_id',
                'shopify_inventory_level_id',
                'last_synced_at',
                'sync_status',
                'sync_error'
            ]);
        });
        
        Schema::dropIfExists('marketplace_integrations');
    }
};
