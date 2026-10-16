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
            if (!Schema::hasColumn('social_content_plans', 'target_branch')) {
                $table->string('target_branch')->default('All Branches');
            }
            if (!Schema::hasColumn('social_content_plans', 'assigned_to')) {
                $table->string('assigned_to')->nullable();
            }
            if (!Schema::hasColumn('social_content_plans', 'reel_pillar')) {
                $table->string('reel_pillar')->nullable();
            }
            if (!Schema::hasColumn('social_content_plans', 'reel_link')) {
                $table->string('reel_link')->nullable();
            }
            if (!Schema::hasColumn('social_content_plans', 'reel_posted_at')) {
                $table->timestamp('reel_posted_at')->nullable();
            }
            if (!Schema::hasColumn('social_content_plans', 'stories_posted_at')) {
                $table->timestamp('stories_posted_at')->nullable();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('social_content_plans', function (Blueprint $table) {
            $table->dropColumn([
                'target_branch',
                'assigned_to',
                'reel_pillar',
                'reel_link',
                'reel_posted_at',
                'stories_posted_at',
            ]);
        });
    }
};
