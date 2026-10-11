<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('branch_evaluations', function (Blueprint $table) {
            $table->id();
            $table->enum('branch', ['wakrah', 'old_airport']);
            $table->enum('manager', ['Rhea', 'Ahana']);
            $table->date('evaluation_date');
            $table->json('answers');
            $table->unsignedSmallInteger('total_score');
            $table->decimal('percentage', 5, 2);
            $table->string('grade');
            $table->text('action_plan')->nullable();
            $table->timestamps();

            $table->index(['branch', 'evaluation_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('branch_evaluations');
    }
};
