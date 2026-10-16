<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('ecommerce_bundle_items')) {
            return;
        }

        Schema::create('ecommerce_bundle_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bundle_id')->constrained('ecommerce_bundles')->cascadeOnDelete();
            // Restricted, not cascaded: silently dropping a component would
            // leave a bundle that no longer contains what it sells.
            $table->foreignId('ecommerce_product_id')->constrained('ecommerce_products')->restrictOnDelete();
            $table->integer('quantity')->default(1);
            $table->timestamps();

            $table->unique(['bundle_id', 'ecommerce_product_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ecommerce_bundle_items');
    }
};
