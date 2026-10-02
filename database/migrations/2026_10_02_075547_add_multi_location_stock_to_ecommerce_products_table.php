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
        Schema::table('ecommerce_products', function (Blueprint $table) {
            $table->renameColumn('current_stock', 'stock_pakistan');
        });

        Schema::table('ecommerce_products', function (Blueprint $table) {
            $table->decimal('stock_qatar', 10, 2)->default(0)->after('stock_pakistan');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ecommerce_products', function (Blueprint $table) {
            $table->dropColumn('stock_qatar');
        });

        Schema::table('ecommerce_products', function (Blueprint $table) {
            $table->renameColumn('stock_pakistan', 'current_stock');
        });
    }
};
