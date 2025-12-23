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
        Schema::table('inventory', function (Blueprint $table) {
            // Drop the unique constraint first
            $table->dropUnique(['location_id', 'card_id']);
            
            // Change card_id from unsignedBigInteger to string
            $table->string('card_id')->change();
            
            // Add foreign key constraint to cards table
            $table->foreign('card_id')->references('id')->on('cards')->onDelete('cascade');
            
            // Re-add the unique constraint
            $table->unique(['location_id', 'card_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('inventory', function (Blueprint $table) {
            // Drop constraints
            $table->dropForeign(['card_id']);
            $table->dropUnique(['location_id', 'card_id']);
            
            // Change back to unsignedBigInteger
            $table->unsignedBigInteger('card_id')->change();
            
            // Re-add unique constraint
            $table->unique(['location_id', 'card_id']);
        });
    }
};
