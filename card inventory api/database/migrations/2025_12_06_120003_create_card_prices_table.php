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
        Schema::create('card_prices', function (Blueprint $table) {
            $table->id(); // Auto-incrementing primary key
            $table->string('card_id'); // Foreign key to cards table
            $table->string('edition_id')->nullable(); // Foreign key to editions table (nullable for card-level pricing)
            $table->integer('tcgplayer_product_id'); // TCGPlayer product ID
            $table->string('sub_type_name'); // Variant type: "Normal", "Foil", "Holo", etc.
            $table->decimal('market_price', 8, 2)->nullable(); // Current market price
            $table->decimal('low_price', 8, 2)->nullable(); // Low price
            $table->decimal('high_price', 8, 2)->nullable(); // High price
            $table->timestamp('last_updated')->nullable(); // When price was last updated
            $table->timestamps(); // created_at, updated_at

            // Indexes for performance
            $table->index('card_id');
            $table->index('edition_id');
            $table->index('tcgplayer_product_id');
            $table->index('sub_type_name');
            
            // Foreign key constraints
            $table->foreign('card_id')->references('id')->on('cards')->onDelete('cascade');
            $table->foreign('edition_id')->references('id')->on('editions')->onDelete('cascade');
            
            // Unique constraint: one price entry per card+edition+product+subtype combination
            $table->unique(['card_id', 'edition_id', 'tcgplayer_product_id', 'sub_type_name'], 'unique_card_pricing');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('card_prices');
    }
};

