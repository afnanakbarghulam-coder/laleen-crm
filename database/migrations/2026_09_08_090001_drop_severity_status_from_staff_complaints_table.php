<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Severity/status modeled generic staff-discipline tracking, which the
     * Complaints & Feedback record (customer complaint + optional deduction)
     * has superseded. Nothing has been logged against these columns yet.
     */
    public function up(): void
    {
        Schema::table('staff_complaints', function (Blueprint $table) {
            $table->dropColumn(['severity', 'status']);
        });
    }

    public function down(): void
    {
        Schema::table('staff_complaints', function (Blueprint $table) {
            $table->enum('severity', ['Low', 'Medium', 'High'])->default('Low');
            $table->enum('status', ['Open', 'Resolved'])->default('Open');
        });
    }
};
