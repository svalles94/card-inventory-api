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
        Schema::create('sets', function (Blueprint $table) {
            $table->string('id')->primary(); // String primary key (e.g., "GA-001")
            $table->text('name'); // Set name (e.g., "Awakening")
            $table->text('prefix')->nullable(); // Set prefix (e.g., "GA")
            $table->text('language')->nullable(); // Language code
            $table->date('release_date')->nullable(); // When the set was released
            $table->string('image')->nullable(); // URL or path to set image
            $table->timestamp('created_at')->nullable();
            $table->timestamp('last_update')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sets');
    }
};

