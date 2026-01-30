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
            $table->boolean('is_foil')->default(false)->after('card_id');
            
            // Drop the old unique constraint
            $table->dropUnique(['location_id', 'card_id']);
            
            // Add new unique constraint that includes is_foil
            $table->unique(['location_id', 'card_id', 'is_foil']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('inventory', function (Blueprint $table) {
            // Drop the unique constraint with is_foil
            $table->dropUnique(['location_id', 'card_id', 'is_foil']);
            
            // Restore the original unique constraint
            $table->unique(['location_id', 'card_id']);
            
            $table->dropColumn('is_foil');
        });
    }
};
