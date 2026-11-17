<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('appointment_services')) {
            return;
        }

        $hasStaffId = Schema::hasColumn('appointment_services', 'staff_id');
        $hasSortOrder = Schema::hasColumn('appointment_services', 'sort_order');
        $hasIsParallel = Schema::hasColumn('appointment_services', 'is_parallel');
        $hasStartTime = Schema::hasColumn('appointment_services', 'start_time');
        $hasSliceEndAt = Schema::hasColumn('appointment_services', 'slice_end_at');

        if (!$hasStaffId || !$hasSortOrder || !$hasIsParallel || !$hasSliceEndAt) {
            Schema::table('appointment_services', function (Blueprint $table) use (
                $hasStaffId,
                $hasSortOrder,
                $hasIsParallel,
                $hasStartTime,
                $hasSliceEndAt
            ) {
                if (!$hasStaffId) {
                    $table->foreignId('staff_id')->nullable()->constrained('staff')->nullOnDelete();
                }

                if (!$hasSortOrder) {
                    $col = $table->unsignedSmallInteger('sort_order')->default(0);
                    if ($hasStaffId) {
                        $col->after('staff_id');
                    }
                }

                if (!$hasIsParallel) {
                    $col = $table->boolean('is_parallel')->default(false);
                    if ($hasSortOrder) {
                        $col->after('sort_order');
                    } elseif ($hasStaffId) {
                        $col->after('staff_id');
                    }
                }

                if (!$hasSliceEndAt) {
                    $col = $table->dateTime('slice_end_at')->nullable();
                    if ($hasStartTime) {
                        $col->after('start_time');
                    }
                }
            });
        }

        // Portable non-destructive backfill for MySQL 8.4 & SQLite
        if (Schema::hasColumn('appointment_services', 'slice_end_at') && Schema::hasColumn('appointment_services', 'start_time')) {
            $hasIsPending = Schema::hasColumn('appointment_services', 'is_pending');

            $query = DB::table('appointment_services')
                ->whereNotNull('start_time')
                ->whereNull('slice_end_at');

            if ($hasIsPending) {
                $query->where('is_pending', false);
            }

            $query->orderBy('id')
                ->chunkById(200, function ($rows) {
                    foreach ($rows as $row) {
                        try {
                            $start = Carbon::parse($row->start_time);
                            $duration = max(1, (int) ($row->duration ?: 30));
                            DB::table('appointment_services')
                                ->where('id', $row->id)
                                ->update([
                                    'slice_end_at' => $start->copy()->addMinutes($duration)->format('Y-m-d H:i:s'),
                                ]);
                        } catch (\Throwable) {
                            // Ignore malformed legacy timestamps safely
                        }
                    }
                });
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('appointment_services')) {
            return;
        }

        $columnsToDrop = array_values(array_filter(
            ['sort_order', 'is_parallel', 'slice_end_at'],
            static fn (string $col): bool => Schema::hasColumn('appointment_services', $col)
        ));

        if (!empty($columnsToDrop)) {
            Schema::table('appointment_services', function (Blueprint $table) use ($columnsToDrop) {
                $table->dropColumn($columnsToDrop);
            });
        }
    }
};
