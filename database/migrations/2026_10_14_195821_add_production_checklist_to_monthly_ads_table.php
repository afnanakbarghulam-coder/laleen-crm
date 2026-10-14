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
            $table->string('creative_asset_link')->nullable()->after('production_person_responsible');
            $table->boolean('chk_hook')->default(false)->after('creative_asset_link');
            $table->boolean('chk_value')->default(false)->after('chk_hook');
            $table->boolean('chk_risk')->default(false)->after('chk_value');
            $table->boolean('chk_cta')->default(false)->after('chk_risk');
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
