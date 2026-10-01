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
        Schema::create('ecommerce_pricing_models', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ecommerce_product_id')->unique()->constrained('ecommerce_products')->onDelete('cascade');
            $table->decimal('liquid_cost', 10, 2)->default(0);
            $table->decimal('packaging_cost', 10, 2)->default(0);
            $table->decimal('fulfillment_cost', 10, 2)->default(0);
            $table->decimal('selling_price', 10, 2)->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ecommerce_pricing_models');
    }
};
