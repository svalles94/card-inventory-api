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
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('location_id')->constrained('locations')->onDelete('cascade');
            $table->string('shopify_order_id')->unique()->nullable();
            $table->string('order_number')->nullable();
            $table->string('customer_name')->nullable();
            $table->string('customer_email')->nullable();
            $table->decimal('total_amount', 10, 2);
            $table->decimal('subtotal_amount', 10, 2);
            $table->decimal('tax_amount', 10, 2)->nullable();
            $table->string('currency', 3)->default('USD');
            $table->string('status')->default('pending'); // pending, fulfilled, cancelled
            $table->string('payment_status')->nullable(); // paid, pending, refunded
            $table->timestamp('ordered_at');
            $table->timestamp('fulfilled_at')->nullable();
            $table->json('shopify_data')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
