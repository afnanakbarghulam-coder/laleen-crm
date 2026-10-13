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
            $table->text('offer')->nullable()->after('pre_filled_message');
            $table->string('production_person_responsible')->nullable()->after('editing_deadline');
            $table->dropColumn(['performance_target', 'shoot_date', 'team', 'raw_clips_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('monthly_ads', function (Blueprint $table) {
            $table->dropColumn(['offer', 'production_person_responsible']);
            $table->string('performance_target')->nullable();
            $table->date('shoot_date')->nullable();
            $table->string('team')->nullable();
            $table->date('raw_clips_date')->nullable();
        });
    }
};
