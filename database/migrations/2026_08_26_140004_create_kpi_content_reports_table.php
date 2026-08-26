<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kpi_content_reports', function (Blueprint $table) {
            $table->id();
            $table->string('creator_name');
            $table->date('date_from');
            $table->date('date_to');
            $table->foreignId('created_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamps();
        });

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

    public function down(): void
    {
        Schema::dropIfExists('kpi_content_entries');
        Schema::dropIfExists('kpi_content_reports');
    }
};
