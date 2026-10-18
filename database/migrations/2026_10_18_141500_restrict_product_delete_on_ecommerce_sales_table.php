<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ecommerce_sales', function (Blueprint $table) {
            $table->dropForeign(['ecommerce_product_id']);
            $table->foreign('ecommerce_product_id')
                ->references('id')
                ->on('ecommerce_products')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('ecommerce_sales', function (Blueprint $table) {
            $table->dropForeign(['ecommerce_product_id']);
            $table->foreign('ecommerce_product_id')
                ->references('id')
                ->on('ecommerce_products')
                ->cascadeOnDelete();
        });
    }
};
