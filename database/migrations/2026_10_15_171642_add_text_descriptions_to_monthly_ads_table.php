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
            if (!Schema::hasColumn('monthly_ads', 'txt_hook')) {
                $table->text('txt_hook')->nullable();
            }
            if (!Schema::hasColumn('monthly_ads', 'txt_value')) {
                $table->text('txt_value')->nullable();
            }
            if (!Schema::hasColumn('monthly_ads', 'txt_risk')) {
                $table->text('txt_risk')->nullable();
            }
            if (!Schema::hasColumn('monthly_ads', 'txt_cta')) {
                $table->text('txt_cta')->nullable();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('monthly_ads', function (Blueprint $table) {
            $table->dropColumn(['txt_hook', 'txt_value', 'txt_risk', 'txt_cta']);
        });
    }
};
