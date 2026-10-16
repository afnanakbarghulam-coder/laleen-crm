<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('monthly_ads', function (Blueprint $table) {
            if (!Schema::hasColumn('monthly_ads', 'plan_month')) {
                $table->string('plan_month', 7)->nullable()->index();
            }
            if (!Schema::hasColumn('monthly_ads', 'raw_data_status')) {
                $table->string('raw_data_status')->default('scheduled');
            }
            if (!Schema::hasColumn('monthly_ads', 'creative_1_name')) {
                $table->string('creative_1_name')->nullable();
            }
            if (!Schema::hasColumn('monthly_ads', 'creative_2_name')) {
                $table->string('creative_2_name')->nullable();
            }
            if (!Schema::hasColumn('monthly_ads', 'creative_3_name')) {
                $table->string('creative_3_name')->nullable();
            }
            if (!Schema::hasColumn('monthly_ads', 'production_started_at')) {
                $table->date('production_started_at')->nullable();
            }
            if (!Schema::hasColumn('monthly_ads', 'testing_ends_at')) {
                $table->date('testing_ends_at')->nullable();
            }
            if (!Schema::hasColumn('monthly_ads', 'verdict_at')) {
                $table->date('verdict_at')->nullable();
            }
        });

        // Backfill plan_month for existing rows from live_date (falling back to created_at).
        DB::table('monthly_ads')->orderBy('id')->get(['id', 'live_date', 'created_at'])->each(function ($row) {
            DB::table('monthly_ads')->where('id', $row->id)->update([
                'plan_month' => Carbon::parse($row->live_date ?? $row->created_at)->format('Y-m'),
            ]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('monthly_ads', function (Blueprint $table) {
            $table->dropColumn([
                'plan_month',
                'raw_data_status',
                'creative_1_name',
                'creative_2_name',
                'creative_3_name',
                'production_started_at',
                'testing_ends_at',
                'verdict_at',
            ]);
        });
    }
};
