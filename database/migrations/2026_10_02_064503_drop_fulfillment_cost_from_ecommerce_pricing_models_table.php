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
            $table->dropColumn('fulfillment_cost');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ecommerce_pricing_models', function (Blueprint $table) {
            $table->decimal('fulfillment_cost', 10, 2)->default(0);
        });
    }
};
