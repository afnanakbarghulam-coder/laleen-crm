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
        Schema::create('monthly_ads', function (Blueprint $table) {
            $table->id();
            $table->string('ad_name');
            $table->string('person_responsible');
            $table->text('pre_filled_message')->nullable();
            $table->string('performance_target')->nullable();
            $table->date('shoot_date')->nullable();
            $table->string('team')->nullable();
            $table->date('raw_clips_date')->nullable();
            $table->date('editing_deadline')->nullable();
            $table->string('approved_by')->nullable();
            $table->date('live_date')->nullable();
            $table->decimal('total_budget', 10, 2)->nullable();
            $table->enum('phase', ['Planning', 'Production', 'Testing', 'Scaling', 'Killed'])->default('Planning');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('monthly_ads');
    }
};
