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
            $table->boolean('is_reel_posted')->default(false)->after('reel_content');
            $table->boolean('are_stories_posted')->default(false)->after('stories_content');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('social_content_plans', function (Blueprint $table) {
            $table->dropColumn(['is_reel_posted', 'are_stories_posted']);
        });
    }
};
