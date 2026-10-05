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
        Schema::table('ecommerce_sales', function (Blueprint $table) {
            // Snapshot of the product's BOM unit cost (Liquid + Bottle + Label
            // + Box + Pump) at the moment of sale, so historical COGS/profit
            // stays accurate even if the Pricing & Profit model changes later.
            $table->decimal('unit_cogs', 10, 2)->nullable()->after('unit_price');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ecommerce_sales', function (Blueprint $table) {
            $table->dropColumn('unit_cogs');
        });
    }
};
