<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Agent Target Reports are now fully derived from agent_shift_logs +
     * appointments for their date range (see KpiAgentTargetReport::shiftStats())
     * — these manually-entered columns are no longer written to or read from.
     */
    public function up(): void
    {
        Schema::table('kpi_agent_target_reports', function (Blueprint $table) {
            $table->dropColumn([
                'morning_bookings',
                'morning_target',
                'evening_bookings',
                'evening_target',
                'prev_morning_pct',
                'prev_evening_pct',
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('kpi_agent_target_reports', function (Blueprint $table) {
            $table->unsignedInteger('morning_bookings')->default(0);
            $table->unsignedInteger('morning_target')->default(0);
            $table->unsignedInteger('evening_bookings')->default(0);
            $table->unsignedInteger('evening_target')->default(0);
            $table->decimal('prev_morning_pct', 5, 2)->nullable();
            $table->decimal('prev_evening_pct', 5, 2)->nullable();
        });
    }
};
