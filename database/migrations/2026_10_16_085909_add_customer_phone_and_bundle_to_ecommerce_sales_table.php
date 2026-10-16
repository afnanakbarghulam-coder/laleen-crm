<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ecommerce_sales', function (Blueprint $table) {
            // Drives the 30-day WhatsApp refill queue.
            if (!Schema::hasColumn('ecommerce_sales', 'customer_phone')) {
                $table->string('customer_phone')->nullable();
            }

            // A bundle sale is still written as one row per child product so
            // every downstream calculation (COGS rollup, stock, Net Profit,
            // channel filters) keeps working untouched. This column tags
            // those rows so the UI can group them back into one order.
            if (!Schema::hasColumn('ecommerce_sales', 'ecommerce_bundle_id')) {
                $table->foreignId('ecommerce_bundle_id')->nullable()->constrained('ecommerce_bundles')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('ecommerce_sales', function (Blueprint $table) {
            if (Schema::hasColumn('ecommerce_sales', 'ecommerce_bundle_id')) {
                $table->dropConstrainedForeignId('ecommerce_bundle_id');
            }
            if (Schema::hasColumn('ecommerce_sales', 'customer_phone')) {
                $table->dropColumn('customer_phone');
            }
        });
    }
};
