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
            $table->decimal('sold_pakistan', 10, 2)->default(0)->after('stock_pakistan');
            $table->decimal('sold_qatar', 10, 2)->default(0)->after('stock_qatar');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ecommerce_products', function (Blueprint $table) {
            $table->dropColumn(['sold_pakistan', 'sold_qatar']);
        });
    }
};
