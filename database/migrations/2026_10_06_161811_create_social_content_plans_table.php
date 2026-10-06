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
        Schema::create('social_content_plans', function (Blueprint $table) {
            $table->id();
            $table->date('week_start_date');
            $table->string('day_of_week');
            $table->text('reel_content')->nullable();
            $table->text('stories_content')->nullable();
            $table->timestamps();

            $table->unique(['week_start_date', 'day_of_week']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('social_content_plans');
    }
};
