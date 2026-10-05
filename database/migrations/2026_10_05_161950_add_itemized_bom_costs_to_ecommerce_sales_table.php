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
            // Itemized snapshot of the product's BOM cost per unit at the
            // moment of sale — the per-component breakdown behind unit_cogs.
            $table->decimal('unit_liquid_cost', 10, 2)->nullable()->after('unit_cogs');
            $table->decimal('unit_bottle_cost', 10, 2)->nullable()->after('unit_liquid_cost');
            $table->decimal('unit_label_cost', 10, 2)->nullable()->after('unit_bottle_cost');
            $table->decimal('unit_pump_cost', 10, 2)->nullable()->after('unit_label_cost');
            $table->decimal('unit_outer_box_cost', 10, 2)->nullable()->after('unit_pump_cost');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ecommerce_sales', function (Blueprint $table) {
            $table->dropColumn([
                'unit_liquid_cost',
                'unit_bottle_cost',
                'unit_label_cost',
                'unit_pump_cost',
                'unit_outer_box_cost',
            ]);
        });
    }
};
