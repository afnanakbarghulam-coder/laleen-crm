<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('staff_deductions')) {
            return;
        }

        Schema::table('staff_deductions', function (Blueprint $table) {
            if (Schema::hasColumn('staff_deductions', 'reason')) {
                $table->string('reason', 500)->change();
            }
        });
    }

    public function down(): void
    {
        // Keep reason column non-destructively widened to avoid truncating existing 256-500 char reasons.
    }
};
