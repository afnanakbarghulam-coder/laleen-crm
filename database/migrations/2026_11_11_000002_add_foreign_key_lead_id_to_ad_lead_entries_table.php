<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('ad_lead_entries') || !Schema::hasColumn('ad_lead_entries', 'lead_id')) {
            return;
        }

        if (Schema::hasTable('leads')) {
            DB::table('ad_lead_entries')
                ->whereNotNull('lead_id')
                ->whereNotIn('lead_id', DB::table('leads')->select('id'))
                ->update(['lead_id' => null]);
        }

        $hasForeignKey = collect(Schema::getForeignKeys('ad_lead_entries'))
            ->contains(fn (array $fk) => in_array('lead_id', $fk['columns'] ?? [], true));

        if (!$hasForeignKey && Schema::hasTable('leads')) {
            Schema::table('ad_lead_entries', function (Blueprint $table) {
                $table->foreign('lead_id', 'ad_lead_entries_lead_id_foreign')
                    ->references('id')
                    ->on('leads')
                    ->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('ad_lead_entries') || !Schema::hasColumn('ad_lead_entries', 'lead_id')) {
            return;
        }

        $foreignKey = collect(Schema::getForeignKeys('ad_lead_entries'))
            ->first(fn (array $fk) => in_array('lead_id', $fk['columns'] ?? [], true));

        if ($foreignKey) {
            Schema::table('ad_lead_entries', function (Blueprint $table) use ($foreignKey) {
                if (!empty($foreignKey['name'])) {
                    $table->dropForeign($foreignKey['name']);
                } else {
                    $table->dropForeign(['lead_id']);
                }
            });
        }
    }
};
