<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('staff_attendances')) {
            return;
        }

        Schema::table('staff_attendances', function (Blueprint $table) {
            if (!Schema::hasColumn('staff_attendances', 'scheduled_start_time')) {
                $table->string('scheduled_start_time', 16)->nullable()->after('status');
            }
            if (!Schema::hasColumn('staff_attendances', 'scheduled_end_time')) {
                $table->string('scheduled_end_time', 16)->nullable()->after('scheduled_start_time');
            }
            if (!Schema::hasColumn('staff_attendances', 'checked_in_at')) {
                $table->timestamp('checked_in_at')->nullable()->after('scheduled_end_time');
            }
            if (!Schema::hasColumn('staff_attendances', 'checked_out_at')) {
                $table->timestamp('checked_out_at')->nullable()->after('checked_in_at');
            }
            if (!Schema::hasColumn('staff_attendances', 'late_minutes')) {
                $table->unsignedInteger('late_minutes')->default(0)->after('checked_out_at');
            }
            if (!Schema::hasColumn('staff_attendances', 'checked_in_by')) {
                $table->foreignId('checked_in_by')->nullable()->after('late_minutes')->constrained('users')->nullOnDelete();
            }
            if (!Schema::hasColumn('staff_attendances', 'is_locked')) {
                $table->boolean('is_locked')->default(false)->after('checked_in_by');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('staff_attendances')) {
            return;
        }

        Schema::table('staff_attendances', function (Blueprint $table) {
            if (Schema::hasColumn('staff_attendances', 'checked_in_by')) {
                $table->dropConstrainedForeignId('checked_in_by');
            }
            $dropCols = array_values(array_filter([
                'scheduled_start_time',
                'scheduled_end_time',
                'checked_in_at',
                'checked_out_at',
                'late_minutes',
                'is_locked',
            ], fn (string $col) => Schema::hasColumn('staff_attendances', $col)));

            if (!empty($dropCols)) {
                $table->dropColumn($dropCols);
            }
        });
    }
};
