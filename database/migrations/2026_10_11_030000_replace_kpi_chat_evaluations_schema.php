<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Replaces the free-text "coordinator_name" chat evaluation schema with
     * a structured agent/evaluator one for the new 15-question weighted
     * framework. The old table only ever held placeholder seed rows
     * ("Coordinator A/B/C"), so this drops and recreates rather than
     * attempting a lossy column migration.
     */
    public function up(): void
    {
        Schema::dropIfExists('kpi_chat_evaluations');

        Schema::create('kpi_chat_evaluations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agent_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('evaluator_id')->nullable()->constrained('users')->nullOnDelete();
            $table->date('evaluation_date');
            $table->json('answers');
            $table->unsignedSmallInteger('total_score');
            $table->decimal('percentage', 5, 2);
            $table->timestamps();

            $table->index(['agent_id', 'evaluation_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kpi_chat_evaluations');

        Schema::create('kpi_chat_evaluations', function (Blueprint $table) {
            $table->id();
            $table->date('eval_date');
            $table->string('coordinator_name');
            $table->unsignedInteger('chats_reviewed')->default(0);
            $table->json('answers');
            $table->foreignId('created_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamps();

            $table->index(['coordinator_name', 'eval_date']);
        });
    }
};
