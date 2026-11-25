<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('staff_leaves')) {
            Schema::table('staff_leaves', function (Blueprint $table) {
                if (!Schema::hasColumn('staff_leaves', 'submitted_within_window')) {
                    $table->boolean('submitted_within_window')->default(true)->after('medical_certificate_path');
                }
                if (!Schema::hasColumn('staff_leaves', 'deduction_applied')) {
                    $table->boolean('deduction_applied')->default(false)->after('submitted_within_window');
                }
                if (!Schema::hasColumn('staff_leaves', 'deduction_id')) {
                    $table->foreignId('deduction_id')
                        ->nullable()
                        ->after('deduction_applied')
                        ->constrained('staff_deductions')
                        ->nullOnDelete();
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('staff_leaves')) {
            Schema::table('staff_leaves', function (Blueprint $table) {
                if (Schema::hasColumn('staff_leaves', 'deduction_id')) {
                    $table->dropConstrainedForeignId('deduction_id');
                }
                if (Schema::hasColumn('staff_leaves', 'deduction_applied')) {
                    $table->dropColumn('deduction_applied');
                }
                if (Schema::hasColumn('staff_leaves', 'submitted_within_window')) {
                    $table->dropColumn('submitted_within_window');
                }
            });
        }
    }
};
