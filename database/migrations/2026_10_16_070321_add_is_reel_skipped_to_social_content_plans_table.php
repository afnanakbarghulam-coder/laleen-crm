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
        Schema::table('social_content_plans', function (Blueprint $table) {
            if (!Schema::hasColumn('social_content_plans', 'is_reel_skipped')) {
                $table->boolean('is_reel_skipped')->default(false);
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('social_content_plans', function (Blueprint $table) {
            $table->dropColumn('is_reel_skipped');
        });
    }
};
