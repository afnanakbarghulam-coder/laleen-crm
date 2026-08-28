<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Content KPI entries become an ongoing per-creator calendar, independent
     * of any report (matching the Ad Leads Log / Agent Shift Log precedent):
     * entries are logged directly and edited any time, and reports are just
     * a saved (creator, date range) bookmark that recomputes its metrics
     * live from these entries — never a container that owns them. Schema
     * realigned to the social media content plan: creator per row, richer
     * schedule/theme/flow text fields, and the three Y/N tracking flags
     * (Posted, Standards Met — Feed, Standards Met — Stories).
     */
    public function up(): void
    {
        Schema::dropIfExists('kpi_content_entries');

        Schema::create('kpi_content_entries', function (Blueprint $table) {
            $table->id();
            $table->string('creator_name');
            $table->date('entry_date');
            $table->string('activity_type')->nullable();
            $table->string('feed_post_schedule')->nullable();
            $table->string('story_theme')->nullable();
            $table->text('story_flow')->nullable();
            $table->enum('feed_posted', ['Y', 'N'])->default('N');
            $table->enum('standards_feed', ['Y', 'N', 'NA'])->default('NA');
            $table->enum('standards_stories', ['Y', 'N', 'NA'])->default('NA');
            $table->string('issues')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamps();

            $table->unique(['creator_name', 'entry_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kpi_content_entries');

        Schema::create('kpi_content_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('report_id')->constrained('kpi_content_reports')->onDelete('cascade');
            $table->date('entry_date');
            $table->string('activity_type')->nullable();
            $table->boolean('feed_scheduled')->default(false);
            $table->boolean('stories_scheduled')->default(false);
            $table->boolean('feed_posted')->default(false);
            $table->boolean('stories_posted')->default(false);
            $table->enum('standards_feed', ['Y', 'N', 'NA'])->default('NA');
            $table->enum('standards_stories', ['Y', 'N', 'NA'])->default('NA');
            $table->string('issues')->nullable();
            $table->timestamps();
        });
    }
};
