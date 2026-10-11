<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kpi_staff_evaluations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('staff_id')->constrained('staff')->cascadeOnDelete();
            $table->foreignId('evaluator_id')->nullable()->constrained('users')->nullOnDelete();
            $table->date('evaluation_date');
            $table->unsignedTinyInteger('score_cleanliness');
            $table->unsignedTinyInteger('score_dress_code');
            $table->unsignedTinyInteger('score_attitude');
            $table->unsignedTinyInteger('score_sales');
            $table->unsignedTinyInteger('score_teamwork');
            $table->unsignedTinyInteger('score_communication');
            $table->decimal('overall_average', 3, 2);
            $table->text('leader_notes')->nullable();
            $table->timestamps();

            $table->index(['staff_id', 'evaluation_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kpi_staff_evaluations');
    }
};
