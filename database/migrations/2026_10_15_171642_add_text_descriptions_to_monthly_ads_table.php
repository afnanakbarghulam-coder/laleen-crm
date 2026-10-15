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
            $table->text('txt_hook')->nullable()->after('chk_hook');
            $table->text('txt_value')->nullable()->after('chk_value');
            $table->text('txt_risk')->nullable()->after('chk_risk');
            $table->text('txt_cta')->nullable()->after('chk_cta');
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
