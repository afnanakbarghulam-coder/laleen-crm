<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ecommerce_products', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->decimal('selling_price', 10, 2)->default(0);

            // Formulation / liquid
            $table->decimal('liquid_cost_per_ml', 10, 4)->default(0);
            $table->decimal('volume_ml', 10, 2)->default(0);

            // Primary packaging
            $table->decimal('bottle_cost', 10, 2)->default(0);
            $table->decimal('pump_cost', 10, 2)->default(0);

            // Secondary packaging
            $table->decimal('label_cost', 10, 2)->default(0);
            $table->decimal('box_cost', 10, 2)->default(0);

            // Labor & shipping
            $table->decimal('labor_cost', 10, 2)->default(0);
            $table->decimal('shipping_cost', 10, 2)->default(0);

            $table->decimal('payment_gateway_fee_percent', 5, 2)->default(2.5);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ecommerce_products');
    }
};
