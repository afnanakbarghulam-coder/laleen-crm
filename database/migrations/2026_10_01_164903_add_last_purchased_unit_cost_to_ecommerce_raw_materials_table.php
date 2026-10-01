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
        Schema::table('ecommerce_raw_materials', function (Blueprint $table) {
            $table->decimal('last_purchased_unit_cost', 10, 4)->nullable()->after('unit_of_measure');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ecommerce_raw_materials', function (Blueprint $table) {
            $table->dropColumn('last_purchased_unit_cost');
        });
    }
};
