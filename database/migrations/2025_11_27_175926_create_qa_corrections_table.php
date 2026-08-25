<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::create('qa_corrections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agent_id')->constrained('users')->onDelete('cascade');
            $table->string('customer_phone', 20);
            $table->enum('issue_type', [
                'wrong-info',
                'poor-follow-up',
                'bad-convincing',
                'rude-behaviour',
                'booking-error',
                'other'
            ])->default('other');
            $table->text('notes')->nullable();
            $table->enum('severity', ['low', 'medium', 'high'])->default('low');
            $table->enum('status', ['pending', 'done'])->default('pending');

            $table->foreignId('appointment_id')->nullable()->constrained()->onDelete('set null');
            $table->string('proof_file')->nullable();
            $table->timestamps();
        });
    }


    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('qa_corrections');
    }
};
