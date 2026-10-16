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
        Schema::table('monthly_ads', function (Blueprint $table) {
            if (!Schema::hasColumn('monthly_ads', 'creative_asset_link')) {
                $table->string('creative_asset_link')->nullable();
            }
            if (!Schema::hasColumn('monthly_ads', 'chk_hook')) {
                $table->boolean('chk_hook')->default(false);
            }
            if (!Schema::hasColumn('monthly_ads', 'chk_value')) {
                $table->boolean('chk_value')->default(false);
            }
            if (!Schema::hasColumn('monthly_ads', 'chk_risk')) {
                $table->boolean('chk_risk')->default(false);
            }
            if (!Schema::hasColumn('monthly_ads', 'chk_cta')) {
                $table->boolean('chk_cta')->default(false);
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('monthly_ads', function (Blueprint $table) {
            $table->dropColumn(['creative_asset_link', 'chk_hook', 'chk_value', 'chk_risk', 'chk_cta']);
        });
    }
};
