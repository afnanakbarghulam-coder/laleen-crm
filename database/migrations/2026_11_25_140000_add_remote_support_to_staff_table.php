<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('staff')) {
            return;
        }

        // 1. Widen `staff.branch` from ENUM('old_airport','wakrah','both') NOT NULL
        //    to nullable VARCHAR(32) so Remote staff cleanly store branch = NULL
        //    without adding any fake "Remote" salon branch.
        Schema::table('staff', function (Blueprint $table) {
            $table->string('branch', 32)->nullable()->default('both')->change();
        });

        // 2. Add explicit `is_remote` boolean flag (default false for in-salon staff).
        if (!Schema::hasColumn('staff', 'is_remote')) {
            Schema::table('staff', function (Blueprint $table) {
                $table->boolean('is_remote')->default(false)->after('branch');
            });
        }

        // 3. Safe non-destructive backfill:
        //    a) Any existing staff record linked to a Chat Agent (users.role = 'agent')
        //       is updated from physical salon branch (e.g. 'old_airport') to Remote
        //       (is_remote = true, branch = null, bookable = false).
        if (Schema::hasTable('users')) {
            $agentUserIds = DB::table('users')
                ->whereRaw("LOWER(TRIM(COALESCE(role, ''))) = 'agent'")
                ->pluck('id')
                ->all();

            if (!empty($agentUserIds)) {
                DB::table('staff')
                    ->whereIn('user_id', $agentUserIds)
                    ->update([
                        'branch' => null,
                        'is_remote' => true,
                        'bookable' => false,
                    ]);
            }
        }

        //    b) Normalize any staff row with NULL or 'remote' branch to consistent Remote flags.
        DB::table('staff')
            ->where(function ($q) {
                $q->whereNull('branch')
                    ->orWhereRaw("LOWER(TRIM(COALESCE(branch, ''))) = ''")
                    ->orWhereRaw("LOWER(TRIM(COALESCE(branch, ''))) = 'remote'");
            })
            ->update([
                'branch' => null,
                'is_remote' => true,
                'bookable' => false,
            ]);
    }

    public function down(): void
    {
        if (!Schema::hasTable('staff')) {
            return;
        }

        if (Schema::hasColumn('staff', 'is_remote')) {
            Schema::table('staff', function (Blueprint $table) {
                $table->dropColumn('is_remote');
            });
        }
    }
};
