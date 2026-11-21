<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('kpi_chat_evaluations', function (Blueprint $table) {
            if (! Schema::hasColumn('kpi_chat_evaluations', 'agent_ids')) {
                $table->json('agent_ids')->nullable()->after('agent_id');
            }
            if (! Schema::hasColumn('kpi_chat_evaluations', 'agent_names')) {
                $table->json('agent_names')->nullable()->after('agent_ids');
            }
            if (! Schema::hasColumn('kpi_chat_evaluations', 'all_agents')) {
                $table->boolean('all_agents')->default(false)->after('agent_names');
            }
        });

        if (! Schema::hasTable('kpi_chat_evaluation_agents')) {
            Schema::create('kpi_chat_evaluation_agents', function (Blueprint $table) {
                $table->id();
                $table->foreignId('kpi_chat_evaluation_id')
                    ->constrained('kpi_chat_evaluations')
                    ->cascadeOnDelete();
                $table->foreignId('user_id')
                    ->constrained('users')
                    ->cascadeOnDelete();
                $table->timestamps();

                $table->unique(['kpi_chat_evaluation_id', 'user_id'], 'kpi_chat_eval_agents_unique');
                $table->index(['user_id', 'kpi_chat_evaluation_id'], 'kpi_chat_eval_agents_user_idx');
            });
        }

        // Backfill all existing single-agent evaluations with zero data loss.
        $now = now();
        $existingRows = DB::table('kpi_chat_evaluations')
            ->select('id', 'agent_id')
            ->whereNotNull('agent_id')
            ->get();

        if ($existingRows->isNotEmpty()) {
            $userNames = DB::table('users')
                ->whereIn('id', $existingRows->pluck('agent_id')->unique()->all())
                ->pluck('name', 'id');

            foreach ($existingRows as $row) {
                $agentId = (int) $row->agent_id;
                $agentName = $userNames->get($agentId);

                DB::table('kpi_chat_evaluation_agents')->insertOrIgnore([
                    'kpi_chat_evaluation_id' => $row->id,
                    'user_id' => $agentId,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);

                DB::table('kpi_chat_evaluations')
                    ->where('id', $row->id)
                    ->update([
                        'agent_ids' => json_encode([$agentId]),
                        'agent_names' => json_encode($agentName ? [$agentName] : []),
                        'all_agents' => false,
                    ]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('kpi_chat_evaluation_agents');

        Schema::table('kpi_chat_evaluations', function (Blueprint $table) {
            $dropColumns = [];
            foreach (['all_agents', 'agent_names', 'agent_ids'] as $column) {
                if (Schema::hasColumn('kpi_chat_evaluations', $column)) {
                    $dropColumns[] = $column;
                }
            }
            if (! empty($dropColumns)) {
                $table->dropColumn($dropColumns);
            }
        });
    }
};
