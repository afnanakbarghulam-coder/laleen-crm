<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ecommerce_outbounds', function (Blueprint $table) {
            $table->string('customer_name')->nullable()->after('ecommerce_product_id');
            $table->string('contact_number')->nullable()->after('customer_name');
            $table->decimal('price', 10, 2)->nullable()->after('quantity');
        });
    }

    public function down(): void
    {
        Schema::table('ecommerce_outbounds', function (Blueprint $table) {
            $table->dropColumn(['customer_name', 'contact_number', 'price']);
        });
    }
};
