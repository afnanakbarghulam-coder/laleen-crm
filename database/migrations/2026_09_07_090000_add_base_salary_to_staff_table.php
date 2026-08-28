<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The fixed base-pay component of Net Salary (Base Salary + Overtime Pay
     * - Deductions). Kept separate from the existing hourly_wage, which now
     * serves as the default overtime rate when a staff_overtime_entries row
     * doesn't specify its own rate override.
     */
    public function up(): void
    {
        Schema::table('staff', function (Blueprint $table) {
            $table->decimal('base_salary', 10, 2)->nullable()->after('hourly_wage');
        });
    }

    public function down(): void
    {
        Schema::table('staff', function (Blueprint $table) {
            $table->dropColumn('base_salary');
        });
    }
};
