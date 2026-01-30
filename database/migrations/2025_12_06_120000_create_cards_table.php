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
        Schema::create('cards', function (Blueprint $table) {
            // Primary Key
            $table->string('id')->primary();

            // Basic Card Information
            $table->text('name')->nullable();
            $table->text('slug')->nullable();
            $table->text('image')->nullable();
            $table->text('image_filename')->nullable();

            // Cost and Stats
            $table->integer('cost_memory')->nullable();
            $table->integer('cost_reserve')->nullable();
            $table->integer('durability')->nullable();
            $table->integer('power')->nullable();
            $table->integer('life')->nullable();
            $table->integer('level')->nullable();
            $table->integer('speed')->nullable();

            // Card Text and Effects
            $table->text('effect')->nullable();
            $table->text('effect_raw')->nullable();
            $table->text('effect_html')->nullable();
            $table->text('flavor')->nullable();
            $table->text('illustrator')->nullable();

            // Card Properties (JSON arrays)
            $table->json('types')->nullable();
            $table->json('subtypes')->nullable();
            $table->json('classes')->nullable();
            $table->json('elements')->nullable();
            $table->json('rule')->nullable();
            $table->json('referenced_by')->nullable();
            $table->json('references')->nullable();

            // Metadata
            $table->integer('rarity')->nullable();
            $table->text('legality')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('last_update')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cards');
    }
};

