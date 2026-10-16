<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('ecommerce_monthly_ads')) {
            return;
        }

        Schema::create('ecommerce_monthly_ads', function (Blueprint $table) {
            $table->id();
            $table->string('ad_name');
            $table->string('person_responsible')->nullable();
            $table->text('offer')->nullable();
            $table->decimal('total_budget', 12, 2)->nullable();
            $table->text('pre_filled_message')->nullable();
            $table->date('live_date')->nullable();

            // String, not a DB enum: this codebase already had to drop and
            // recreate ecommerce_expenses.category because an enum turned
            // adding a value into a schema migration. Allowed values live on
            // EcommerceMonthlyAd::PHASES and in request validation.
            $table->string('phase')->default('Planning');

            $table->date('raw_data_deadline')->nullable();
            $table->date('final_creative_deadline')->nullable();
            $table->string('creative_asset_link', 2048)->nullable();

            // 4-point creative QC: a tick alone is too easy to rubber-stamp,
            // so each one is paired with a written justification.
            $table->boolean('chk_hook')->default(false);
            $table->text('txt_hook')->nullable();
            $table->boolean('chk_value')->default(false);
            $table->text('txt_value')->nullable();
            $table->boolean('chk_risk')->default(false);
            $table->text('txt_risk')->nullable();
            $table->boolean('chk_cta')->default(false);
            $table->text('txt_cta')->nullable();

            // Testing -> Scaling is gated on real CPL beating target CPL.
            $table->decimal('test_spend', 12, 2)->nullable();
            $table->integer('leads_generated')->nullable();
            $table->decimal('target_cpl', 10, 2)->nullable();
            $table->string('scaling_status')->nullable();

            $table->timestamps();

            $table->index('phase');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ecommerce_monthly_ads');
    }
};
