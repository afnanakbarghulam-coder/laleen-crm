<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Freezes each service line's discount into the immutable Sale record at
     * checkout time, so financial reports stay accurate even if the
     * appointment's own service lines are edited again afterwards.
     */
    public function up(): void
    {
        Schema::table('sale_items', function (Blueprint $table) {
            $table->decimal('original_price', 10, 2)->nullable()->after('price');
            $table->decimal('discount_amount', 10, 2)->default(0)->after('original_price');
        });
    }

    public function down(): void
    {
        Schema::table('sale_items', function (Blueprint $table) {
            $table->dropColumn(['original_price', 'discount_amount']);
        });
    }
};
