<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * How many services the customer actually picks (and pays for) out of
     * the combo's eligible service pool - e.g. "choose any 6" from a pool of
     * 10 catalog services. Null means "take every service in the pool"
     * (the original all-included behavior), so existing combos keep working
     * unchanged.
     */
    public function up(): void
    {
        Schema::table('combos', function (Blueprint $table) {
            $table->unsignedInteger('quantity_included')->nullable()->after('price');
        });
    }

    public function down(): void
    {
        Schema::table('combos', function (Blueprint $table) {
            $table->dropColumn('quantity_included');
        });
    }
};
