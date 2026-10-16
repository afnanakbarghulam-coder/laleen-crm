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
            $table->string('target_branch')->default('All Branches');
            $table->decimal('offer_price', 10, 2)->nullable();

            $table->string('creative_asset_link_2')->nullable();
            $table->string('creative_asset_link_3')->nullable();

            $table->date('testing_started_at')->nullable();
            $table->string('winning_creative')->nullable();
            $table->decimal('cost_per_message', 8, 2)->nullable();
            $table->string('sales_verdict')->nullable();
            $table->text('testing_notes')->nullable();
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
