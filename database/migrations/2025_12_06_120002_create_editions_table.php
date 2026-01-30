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
        Schema::create('editions', function (Blueprint $table) {
            $table->string('id')->primary(); // String primary key
            $table->string('card_id')->nullable(); // Foreign key to cards table
            $table->string('set_id')->nullable(); // Foreign key to sets table
            $table->text('collector_number')->nullable(); // Card number in set (e.g., "001", "002")
            $table->text('configuration')->nullable(); // Card configuration/variant info
            
            // Edition-specific text fields
            $table->text('effect')->nullable(); // Edition-specific effect text
            $table->text('effect_html')->nullable(); // HTML formatted effect
            $table->text('effect_raw')->nullable(); // Raw effect text
            $table->text('flavor')->nullable(); // Flavor text
            $table->text('illustrator')->nullable(); // Illustrator name
            
            // Orientation fields
            $table->text('orientation')->nullable(); // Card orientation
            $table->json('other_orientations')->nullable(); // Array of other orientations
            
            $table->text('image')->nullable(); // Edition-specific image URL
            $table->integer('rarity')->nullable(); // Rarity level (integer)
            $table->text('slug')->nullable(); // URL-friendly slug
            
            // TCGPlayer integration fields
            $table->integer('tcgplayer_product_id')->nullable();
            $table->string('tcgplayer_sku')->nullable();
            $table->decimal('market_price', 10, 2)->nullable();
            $table->decimal('tcgplayer_low_price', 8, 2)->nullable();
            $table->decimal('tcgplayer_high_price', 8, 2)->nullable();
            $table->timestamp('last_price_update')->nullable();
            
            $table->timestamp('created_at')->nullable();
            $table->timestamp('last_update')->nullable();

            // Foreign keys
            $table->foreign('card_id')->references('id')->on('cards')->onDelete('cascade');
            $table->foreign('set_id')->references('id')->on('sets')->onDelete('cascade');
            
            // Index for TCGPlayer lookups
            $table->index('tcgplayer_product_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('editions');
    }
};

