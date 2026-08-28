<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tracks which logged-in account physically created the booking, as
     * distinct from booking_agent_id (who the booking is credited to). Agent
     * target reports attribute a booking to an agent's shift only when this
     * account IS that agent — a manager/admin creating a booking on an
     * agent's behalf must not count toward the agent's target.
     */
    public function up(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->foreignId('created_by')->nullable()->after('booking_agent_id')->constrained('users')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('created_by');
        });
    }
};
