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
        Schema::table('cards', function (Blueprint $table) {
            $table->string('game')->default('grand-archive')->after('id')->index();
            $table->string('set_code')->nullable()->after('game');
            $table->string('set_name')->nullable()->after('set_code');
            $table->string('card_number')->nullable()->after('set_name');
            $table->string('foil_type')->nullable()->after('rarity');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cards', function (Blueprint $table) {
            $table->dropColumn(['game', 'set_code', 'set_name', 'card_number', 'foil_type']);
        });
    }
};
