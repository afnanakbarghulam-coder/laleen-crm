<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('monthly_ads', function (Blueprint $table) {
            if (!Schema::hasColumn('monthly_ads', 'target_branch')) {
                $table->string('target_branch')->default('All Branches');
            }
            if (!Schema::hasColumn('monthly_ads', 'offer_price')) {
                $table->decimal('offer_price', 10, 2)->nullable();
            }
            if (!Schema::hasColumn('monthly_ads', 'creative_asset_link_2')) {
                $table->string('creative_asset_link_2')->nullable();
            }
            if (!Schema::hasColumn('monthly_ads', 'creative_asset_link_3')) {
                $table->string('creative_asset_link_3')->nullable();
            }
            if (!Schema::hasColumn('monthly_ads', 'testing_started_at')) {
                $table->date('testing_started_at')->nullable();
            }
            if (!Schema::hasColumn('monthly_ads', 'winning_creative')) {
                $table->string('winning_creative')->nullable();
            }
            if (!Schema::hasColumn('monthly_ads', 'cost_per_message')) {
                $table->decimal('cost_per_message', 8, 2)->nullable();
            }
            if (!Schema::hasColumn('monthly_ads', 'sales_verdict')) {
                $table->string('sales_verdict')->nullable();
            }
            if (!Schema::hasColumn('monthly_ads', 'testing_notes')) {
                $table->text('testing_notes')->nullable();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('monthly_ads', function (Blueprint $table) {
            $table->dropColumn([
                'target_branch',
                'offer_price',
                'creative_asset_link_2',
                'creative_asset_link_3',
                'testing_started_at',
                'winning_creative',
                'cost_per_message',
                'sales_verdict',
                'testing_notes',
            ]);
        });
    }
};
