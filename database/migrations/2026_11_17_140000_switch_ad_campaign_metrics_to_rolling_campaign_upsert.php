<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('ad_metric_import_logs')) {
            Schema::table('ad_metric_import_logs', function (Blueprint $table) {
                $table->date('date_from')->nullable()->change();
                $table->date('date_to')->nullable()->change();
            });
        }

        if (!Schema::hasTable('ad_campaign_metrics')) {
            return;
        }

        // 1. Deduplicate any existing multi-period rows for the same (workspace, normalized_campaign_key),
        // keeping the latest updated row while backfilling any non-null columns from older rows.
        $duplicates = DB::table('ad_campaign_metrics')
            ->select('workspace', 'normalized_campaign_key', DB::raw('COUNT(*) as cnt'))
            ->groupBy('workspace', 'normalized_campaign_key')
            ->havingRaw('COUNT(*) > 1')
            ->get();

        $metricCols = [
            'cpm', 'cpc', 'cpc_all', 'ctr', 'ctr_all', 'cpa', 'cpl', 'roas', 'roi',
            'frequency', 'reach', 'impressions', 'cvr', 'aov', 'atc', 'ic', 'purchases',
            'results_count', 'leads_count', 'cost_per_purchase', 'link_clicks', 'clicks_all',
            'shop_clicks', 'landing_page_views', 'cost_per_lpv', 'engagement_rate',
            'video_views', 'thruplays', 'cost_per_result', 'amount_spent',
            'amount_spent_local', 'amount_spent_qar', 'revenue', 'revenue_local', 'revenue_qar',
            'branch', 'ad_category', 'date_from', 'date_to',
        ];

        foreach ($duplicates as $dup) {
            $rows = DB::table('ad_campaign_metrics')
                ->where('workspace', $dup->workspace)
                ->where('normalized_campaign_key', $dup->normalized_campaign_key)
                ->orderByDesc('updated_at')
                ->orderByDesc('id')
                ->get();

            if ($rows->count() <= 1) {
                continue;
            }

            $winner = $rows->first();
            $patch = [];

            foreach ($rows->slice(1) as $older) {
                foreach ($metricCols as $col) {
                    if (($winner->{$col} ?? null) === null && !array_key_exists($col, $patch) && ($older->{$col} ?? null) !== null) {
                        $patch[$col] = $older->{$col};
                    }
                }
            }

            if ($patch !== []) {
                DB::table('ad_campaign_metrics')->where('id', $winner->id)->update($patch);
            }

            $loserIds = $rows->slice(1)->pluck('id')->all();
            DB::table('ad_campaign_metrics')->whereIn('id', $loserIds)->delete();
        }

        // 2. Make date_from / date_to nullable (for screenshots where the header date pill is cropped)
        // and replace the period-scoped unique index with a rolling (workspace, normalized_campaign_key) unique index.
        $existingIndexes = collect(Schema::getIndexes('ad_campaign_metrics'))->pluck('name')->all();

        Schema::table('ad_campaign_metrics', function (Blueprint $table) use ($existingIndexes) {
            $table->date('date_from')->nullable()->change();
            $table->date('date_to')->nullable()->change();

            if (in_array('ad_campaign_metrics_unique_period', $existingIndexes, true)) {
                $table->dropUnique('ad_campaign_metrics_unique_period');
            }

            if (!in_array('ad_campaign_metrics_workspace_campaign_unique', $existingIndexes, true)) {
                $table->unique(
                    ['workspace', 'normalized_campaign_key'],
                    'ad_campaign_metrics_workspace_campaign_unique'
                );
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('ad_campaign_metrics')) {
            return;
        }

        $existingIndexes = collect(Schema::getIndexes('ad_campaign_metrics'))->pluck('name')->all();

        Schema::table('ad_campaign_metrics', function (Blueprint $table) use ($existingIndexes) {
            if (in_array('ad_campaign_metrics_workspace_campaign_unique', $existingIndexes, true)) {
                $table->dropUnique('ad_campaign_metrics_workspace_campaign_unique');
            }
            if (!in_array('ad_campaign_metrics_unique_period', $existingIndexes, true)) {
                $table->unique(
                    ['workspace', 'region', 'normalized_campaign_key', 'date_from', 'date_to'],
                    'ad_campaign_metrics_unique_period'
                );
            }
        });
    }
};
