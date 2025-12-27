<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inventory', function (Blueprint $table) {
            if (! Schema::hasColumn('inventory', 'edition_id')) {
                $table->string('edition_id')->default('')->after('card_id');
            }

            // New unique constraint with edition and foil
            $table->unique(['location_id', 'card_id', 'edition_id', 'is_foil'], 'inventory_location_card_edition_foil_unique');
        });
    }

    public function down(): void
    {
        Schema::table('inventory', function (Blueprint $table) {
            // Drop the new unique if present
            try {
                $table->dropUnique('inventory_location_card_edition_foil_unique');
            } catch (\Throwable $e) {
                try {
                    $table->dropUnique(['location_id', 'card_id', 'edition_id', 'is_foil']);
                } catch (\Throwable $ignored) {
                    // ignore
                }
            }

            // Restore prior unique on location/card/is_foil when possible
            try {
                $table->unique(['location_id', 'card_id', 'is_foil']);
            } catch (\Throwable $e) {
                // ignore if index already exists or cannot be created
            }

            // Drop column
            if (Schema::hasColumn('inventory', 'edition_id')) {
                $table->dropColumn('edition_id');
            }
        });
    }
};
