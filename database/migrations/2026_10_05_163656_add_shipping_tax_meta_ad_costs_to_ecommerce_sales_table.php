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
            // Order-level costs attributed directly to this sale (not per
            // unit — entered as a total for the whole order), so True Net
            // Profit can be computed per transaction in the Net Profit view.
            $table->decimal('shipping_cost', 10, 2)->nullable()->default(0)->after('unit_outer_box_cost');
            $table->decimal('tax_amount', 10, 2)->nullable()->default(0)->after('shipping_cost');
            $table->decimal('meta_ad_allocation', 10, 2)->nullable()->default(0)->after('tax_amount');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ecommerce_sales', function (Blueprint $table) {
            $table->dropColumn(['shipping_cost', 'tax_amount', 'meta_ad_allocation']);
        });
    }
};
