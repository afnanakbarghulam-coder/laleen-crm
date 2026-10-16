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
            $table->date('raw_data_deadline')->nullable();
            $table->date('final_creative_deadline')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('monthly_ads', function (Blueprint $table) {
            $table->dropColumn(['raw_data_deadline', 'final_creative_deadline']);
        });
    }
};
