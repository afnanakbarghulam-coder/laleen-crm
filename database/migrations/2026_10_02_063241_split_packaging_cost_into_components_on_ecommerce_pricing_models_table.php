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
        Schema::table('ecommerce_pricing_models', function (Blueprint $table) {
            $table->decimal('bottle_cost', 10, 2)->default(0)->after('liquid_cost');
            $table->decimal('label_cost', 10, 2)->default(0)->after('bottle_cost');
            $table->decimal('pump_cost', 10, 2)->default(0)->after('label_cost');
            $table->decimal('box_cost', 10, 2)->default(0)->after('pump_cost');
        });

        Schema::table('ecommerce_pricing_models', function (Blueprint $table) {
            $table->dropColumn('packaging_cost');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ecommerce_pricing_models', function (Blueprint $table) {
            $table->decimal('packaging_cost', 10, 2)->default(0);
        });

        Schema::table('ecommerce_pricing_models', function (Blueprint $table) {
            $table->dropColumn(['bottle_cost', 'label_cost', 'pump_cost', 'box_cost']);
        });
    }
};
