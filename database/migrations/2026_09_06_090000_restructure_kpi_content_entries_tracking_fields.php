<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Drops the free-text story_theme/story_flow fields and replaces the
     * old NA-based standards_feed/standards_stories enums with a
     * streamlined set of five plain Y/N tracking flags (stories_posted,
     * feed_posted, standards_stories, standards_feed, event). "Applicable
     * to this row" is no longer stored as a value (NA) — it's now derived
     * structurally from activity_type via
     * KpiContentEntry::FIELD_VISIBILITY, and the corresponding column is
     * simply hidden in the UI and excluded from report metrics for rows it
     * doesn't apply to.
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
            $table->enum('stories_posted', ['Y', 'N'])->default('N');
            $table->enum('feed_posted', ['Y', 'N'])->default('N');
            $table->enum('standards_stories', ['Y', 'N'])->default('N');
            $table->enum('standards_feed', ['Y', 'N'])->default('N');
            $table->enum('event', ['Y', 'N'])->default('N');
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
};
