<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('daily_trackers', function (Blueprint $table) {
            $table->id();
            $table->date('date');
            $table->enum('shift', ['morning', 'night']);
            $table->foreignId('agent_id')->constrained('users')->onDelete('cascade');
            $table->time('check_in')->nullable();
            $table->time('check_out')->nullable();
            $table->enum('sent_reminders', ['yes', 'no', 'na'])->default('na');
            $table->enum('asked_feedbacks', ['yes', 'no', 'na'])->default('na');
            $table->enum('updated_no_shows', ['yes', 'no', 'na'])->default('na');
            $table->enum('excel_reviewed', ['yes', 'no', 'na'])->default('na');
            $table->enum('checked_bookings_vs_sales', ['yes', 'no', 'na'])->default('na');
            $table->enum('corrections_done', ['yes', 'no', 'na'])->default('na');
            $table->integer('leads_received')->nullable();
            $table->integer('bookings_done')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('daily_trackers');
    }
};
