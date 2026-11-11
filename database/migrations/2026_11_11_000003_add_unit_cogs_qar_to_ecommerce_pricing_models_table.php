<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('ecommerce_pricing_models')) {
            return;
        }

        if (!Schema::hasColumn('ecommerce_pricing_models', 'unit_cogs_qar')) {
            Schema::table('ecommerce_pricing_models', function (Blueprint $table) {
                $table->decimal('unit_cogs_qar', 10, 2)->nullable()->after('box_cost');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('ecommerce_pricing_models') && Schema::hasColumn('ecommerce_pricing_models', 'unit_cogs_qar')) {
            Schema::table('ecommerce_pricing_models', function (Blueprint $table) {
                $table->dropColumn('unit_cogs_qar');
            });
        }
    }
};
