<?php

use Carbon\Carbon;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('ops_action_items')) {
            return;
        }

        if (! Schema::hasColumn('ops_action_items', 'due_at')) {
            Schema::table('ops_action_items', function (Blueprint $table) {
                $table->timestamp('due_at')->nullable()->after('ball_assigned_at');
            });
        }

        DB::table('ops_action_items')
            ->whereNull('due_at')
            ->orderBy('id')
            ->chunkById(200, function ($rows) {
                foreach ($rows as $row) {
                    $slaHours = (int) ($row->sla_hours ?: 48);
                    $baseTime = $row->ball_assigned_at ?? $row->created_at ?? now();
                    $computedDueAt = Carbon::parse($baseTime)->addHours($slaHours);

                    DB::table('ops_action_items')
                        ->where('id', $row->id)
                        ->update(['due_at' => $computedDueAt]);
                }
            });
    }

    public function down(): void
    {
        // Safe additive backfill migration; column is part of core ops_action_items schema.
    }
};
