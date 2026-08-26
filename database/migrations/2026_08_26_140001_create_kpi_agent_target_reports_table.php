<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kpi_agent_target_reports', function (Blueprint $table) {
            $table->id();
            $table->date('date_from');
            $table->date('date_to');
            $table->unsignedInteger('morning_bookings')->default(0);
            $table->unsignedInteger('morning_target')->default(0);
            $table->unsignedInteger('evening_bookings')->default(0);
            $table->unsignedInteger('evening_target')->default(0);
            $table->decimal('prev_morning_pct', 5, 2)->nullable();
            $table->decimal('prev_evening_pct', 5, 2)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kpi_agent_target_reports');
    }
};
