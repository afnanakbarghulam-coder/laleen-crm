<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('social_content_plans') && !Schema::hasColumn('social_content_plans', 'late_replies_count')) {
            Schema::table('social_content_plans', function (Blueprint $table) {
                $table->unsignedInteger('late_replies_count')->default(0)->after('is_reel_skipped');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('social_content_plans') && Schema::hasColumn('social_content_plans', 'late_replies_count')) {
            Schema::table('social_content_plans', function (Blueprint $table) {
                $table->dropColumn('late_replies_count');
            });
        }
    }
};
