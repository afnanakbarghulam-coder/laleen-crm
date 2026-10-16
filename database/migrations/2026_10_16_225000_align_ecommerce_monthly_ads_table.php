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
        Schema::table('ecommerce_monthly_ads', function (Blueprint $table) {
            if (!Schema::hasColumn('ecommerce_monthly_ads', 'plan_month')) {
                $table->string('plan_month', 7)->nullable()->index();
            }
            if (!Schema::hasColumn('ecommerce_monthly_ads', 'target_branch')) {
                $table->string('target_branch')->default('All Branches');
            }
            if (!Schema::hasColumn('ecommerce_monthly_ads', 'offer_price')) {
                $table->decimal('offer_price', 10, 2)->nullable();
            }
            if (!Schema::hasColumn('ecommerce_monthly_ads', 'raw_data_status')) {
                $table->string('raw_data_status')->default('scheduled');
            }
            if (!Schema::hasColumn('ecommerce_monthly_ads', 'creative_1_name')) {
                $table->string('creative_1_name')->nullable();
            }
            if (!Schema::hasColumn('ecommerce_monthly_ads', 'creative_2_name')) {
                $table->string('creative_2_name')->nullable();
            }
            if (!Schema::hasColumn('ecommerce_monthly_ads', 'creative_asset_link_2')) {
                $table->string('creative_asset_link_2', 2048)->nullable();
            }
            if (!Schema::hasColumn('ecommerce_monthly_ads', 'creative_3_name')) {
                $table->string('creative_3_name')->nullable();
            }
            if (!Schema::hasColumn('ecommerce_monthly_ads', 'creative_asset_link_3')) {
                $table->string('creative_asset_link_3', 2048)->nullable();
            }
            if (!Schema::hasColumn('ecommerce_monthly_ads', 'production_started_at')) {
                $table->date('production_started_at')->nullable();
            }
            if (!Schema::hasColumn('ecommerce_monthly_ads', 'testing_started_at')) {
                $table->date('testing_started_at')->nullable();
            }
            if (!Schema::hasColumn('ecommerce_monthly_ads', 'testing_ends_at')) {
                $table->date('testing_ends_at')->nullable();
            }
            if (!Schema::hasColumn('ecommerce_monthly_ads', 'verdict_at')) {
                $table->date('verdict_at')->nullable();
            }
            if (!Schema::hasColumn('ecommerce_monthly_ads', 'winning_creative')) {
                $table->string('winning_creative')->nullable();
            }
            if (!Schema::hasColumn('ecommerce_monthly_ads', 'cost_per_message')) {
                $table->decimal('cost_per_message', 8, 2)->nullable();
            }
            if (!Schema::hasColumn('ecommerce_monthly_ads', 'sales_verdict')) {
                $table->string('sales_verdict')->nullable();
            }
            if (!Schema::hasColumn('ecommerce_monthly_ads', 'testing_notes')) {
                $table->text('testing_notes')->nullable();
            }
        });

        // Backfill plan_month for any existing rows from live_date (falling back to created_at).
        DB::table('ecommerce_monthly_ads')->orderBy('id')->get(['id', 'live_date', 'created_at'])->each(function ($row) {
            DB::table('ecommerce_monthly_ads')->where('id', $row->id)->update([
                'plan_month' => Carbon::parse($row->live_date ?? $row->created_at)->format('Y-m'),
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('ecommerce_monthly_ads', function (Blueprint $table) {
            $table->dropColumn([
                'plan_month',
                'target_branch',
                'offer_price',
                'raw_data_status',
                'creative_1_name',
                'creative_2_name',
                'creative_asset_link_2',
                'creative_3_name',
                'creative_asset_link_3',
                'production_started_at',
                'testing_started_at',
                'testing_ends_at',
                'verdict_at',
                'winning_creative',
                'cost_per_message',
                'sales_verdict',
                'testing_notes',
            ]);
        });
    }
};
