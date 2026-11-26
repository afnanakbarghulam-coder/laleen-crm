<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('monthly_offers')) {
            return;
        }

        if (! Schema::hasColumn('monthly_offers', 'graphic_post_path')) {
            Schema::table('monthly_offers', function (Blueprint $table) {
                $table->string('graphic_post_path')->nullable()->after('video_shooting_script');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('monthly_offers') && Schema::hasColumn('monthly_offers', 'graphic_post_path')) {
            Schema::table('monthly_offers', function (Blueprint $table) {
                $table->dropColumn('graphic_post_path');
            });
        }
    }
};
