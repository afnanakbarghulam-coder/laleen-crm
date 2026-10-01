<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ecommerce_product_raw_material', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ecommerce_product_id')->constrained('ecommerce_products')->onDelete('cascade');
            $table->foreignId('ecommerce_raw_material_id')->constrained('ecommerce_raw_materials')->onDelete('cascade');
            $table->decimal('quantity_required', 12, 4);
            $table->timestamps();

            $table->unique(['ecommerce_product_id', 'ecommerce_raw_material_id'], 'ecommerce_product_raw_material_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ecommerce_product_raw_material');
    }
};
