<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
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

    public function down(): void
    {
        Schema::dropIfExists('kpi_chat_evaluations');
    }
};
