<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ecommerce_raw_materials', function (Blueprint $table) {
            $table->decimal('initial_stock', 12, 2)->default(0)->after('current_stock');
        });

        // Backfill existing rows — they predate this column, so treat their
        // current stock at migration time as what they originally started with.
        DB::table('ecommerce_raw_materials')->update(['initial_stock' => DB::raw('current_stock')]);
    }

    public function down(): void
    {
        Schema::table('ecommerce_raw_materials', function (Blueprint $table) {
            $table->dropColumn('initial_stock');
        });
    }
};
