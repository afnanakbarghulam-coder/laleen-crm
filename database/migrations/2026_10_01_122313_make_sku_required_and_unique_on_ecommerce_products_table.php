<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Backfill any existing rows left without a SKU before the column becomes
        // required, so this migration never fails against real data.
        DB::table('ecommerce_products')
            ->where(function ($query) {
                $query->whereNull('sku')->orWhere('sku', '');
            })
            ->orderBy('id')
            ->get(['id'])
            ->each(function ($row) {
                DB::table('ecommerce_products')
                    ->where('id', $row->id)
                    ->update(['sku' => 'SKU-' . $row->id]);
            });

        Schema::table('ecommerce_products', function (Blueprint $table) {
            $table->dropColumn('sku');
        });

        Schema::table('ecommerce_products', function (Blueprint $table) {
            $table->string('sku')->after('name');
            $table->unique('sku');
        });
    }

    public function down(): void
    {
        Schema::table('ecommerce_products', function (Blueprint $table) {
            $table->dropUnique(['sku']);
            $table->dropColumn('sku');
        });

        Schema::table('ecommerce_products', function (Blueprint $table) {
            $table->string('sku')->nullable()->after('name');
        });
    }
};
