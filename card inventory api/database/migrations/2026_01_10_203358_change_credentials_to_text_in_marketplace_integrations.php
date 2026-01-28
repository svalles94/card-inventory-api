<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Change credentials column from JSON to TEXT because Laravel's encrypted cast
     * stores encrypted strings, not JSON.
     */
    public function up(): void
    {
        Schema::table('marketplace_integrations', function (Blueprint $table) {
            // MySQL doesn't support direct conversion from JSON to TEXT
            // So we drop and recreate the column
            $table->dropColumn('credentials');
        });
        
        Schema::table('marketplace_integrations', function (Blueprint $table) {
            $table->text('credentials')->after('enabled');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('marketplace_integrations', function (Blueprint $table) {
            $table->dropColumn('credentials');
        });
        
        Schema::table('marketplace_integrations', function (Blueprint $table) {
            $table->json('credentials')->after('enabled');
        });
    }
};
