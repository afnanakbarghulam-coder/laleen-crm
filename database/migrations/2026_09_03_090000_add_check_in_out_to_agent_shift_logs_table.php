<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The active shift window is now the exact check-in -> check-out times
     * an admin/agent records, replacing the old fixed 07:00-14:30/14:30-22:00
     * clock assumption. Nullable at the DB level (SQLite can't ADD COLUMN
     * NOT NULL without a default) — required via validation in
     * AgentShiftLogController instead.
     */
    public function up(): void
    {
        Schema::table('agent_shift_logs', function (Blueprint $table) {
            $table->time('check_in_time')->nullable()->after('shift');
            $table->time('check_out_time')->nullable()->after('check_in_time');
        });
    }

    public function down(): void
    {
        Schema::table('agent_shift_logs', function (Blueprint $table) {
            $table->dropColumn(['check_in_time', 'check_out_time']);
        });
    }
};
