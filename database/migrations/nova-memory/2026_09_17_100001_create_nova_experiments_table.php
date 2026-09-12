<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Nova's experiment memory (Stage 6) - a time-bounded trial with a
 * measurable outcome the owner explicitly approved, as opposed to a
 * standing nova_decisions row. Same isolated 'nova_memory' connection and
 * same NOVA READ-ONLY INTEGRATION RULE as every other Nova memory table
 * (app/NovaAI/README.md). Unspecified parameters (started_at, ends_at,
 * target_metric, success_criteria, review_date) are stored as NULL, never
 * an invented value - see App\NovaAI\Services\NovaDecisionExtractor.
 */
return new class extends Migration
{
    protected $connection = 'nova_memory';

    public function up(): void
    {
        Schema::connection('nova_memory')->create('nova_experiments', function (Blueprint $table) {
            $table->id();
            $table->string('title', 150);
            $table->string('description', 1000)->nullable();
            $table->string('category', 32);
            $table->enum('status', ['active', 'completed', 'cancelled', 'reversed'])->default('active');
            $table->date('started_at')->nullable();
            $table->date('ends_at')->nullable();
            $table->string('target_metric', 200)->nullable();
            $table->string('success_criteria', 300)->nullable();
            $table->date('review_date')->nullable();
            $table->string('result_summary', 500)->nullable();
            $table->unsignedBigInteger('decided_by_user_id');
            $table->string('source_message', 2000)->nullable();
            $table->timestamps();

            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::connection('nova_memory')->dropIfExists('nova_experiments');
    }
};
