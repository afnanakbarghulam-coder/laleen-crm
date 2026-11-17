<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('ad_metric_import_logs')) {
            Schema::create('ad_metric_import_logs', function (Blueprint $table) {
                $table->id();
                $table->string('workspace', 30)->default('salon')->index();
                $table->string('region', 10)->default('qa')->index();
                $table->string('branch', 30)->nullable()->index();
                $table->date('date_from')->index();
                $table->date('date_to')->index();
                $table->string('account_currency', 10)->default('USD');
                $table->decimal('fx_rate_to_local', 12, 4)->default(3.6500);
                $table->string('local_currency', 10)->default('QAR');
                $table->json('screenshot_paths');
                $table->json('raw_extracted_json')->nullable();
                $table->json('total_results_json')->nullable();
                $table->json('verification_json')->nullable();
                $table->string('ai_provider', 40)->nullable();
                $table->unsignedInteger('campaigns_count')->default(0);
                $table->string('status', 20)->default('previewed')->index();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('ad_campaign_metrics')) {
            Schema::create('ad_campaign_metrics', function (Blueprint $table) {
                $table->id();
                $table->string('workspace', 30)->default('salon')->index();
                $table->string('region', 10)->default('qa')->index();
                $table->string('branch', 30)->nullable()->index();
                $table->string('campaign_name', 255)->index();
                $table->string('normalized_campaign_key', 255)->index();
                $table->string('ad_category', 120)->nullable()->index();
                $table->boolean('link_crm_revenue')->default(false);
                $table->date('date_from')->index();
                $table->date('date_to')->index();
                $table->string('account_currency', 10)->default('USD');
                $table->decimal('fx_rate_to_local', 12, 4)->default(3.6500);
                $table->string('local_currency', 10)->default('QAR');

                // All 25 Core Marketing Metrics + Meta Secondary Variants
                // Strictly nullable (NEVER default to 0) so multi-screenshot horizontal merges preserve earlier data
                $table->decimal('cpm', 14, 4)->nullable();
                $table->decimal('cpc', 14, 4)->nullable();
                $table->decimal('cpc_all', 14, 4)->nullable();
                $table->decimal('ctr', 10, 4)->nullable();
                $table->decimal('ctr_all', 10, 4)->nullable();
                $table->decimal('cpa', 14, 4)->nullable();
                $table->decimal('cpl', 14, 4)->nullable();
                $table->decimal('roas', 12, 4)->nullable();
                $table->decimal('roi', 12, 4)->nullable();
                $table->decimal('frequency', 10, 4)->nullable();
                $table->unsignedBigInteger('reach')->nullable();
                $table->unsignedBigInteger('impressions')->nullable();
                $table->decimal('cvr', 10, 4)->nullable();
                $table->decimal('aov', 14, 4)->nullable();
                $table->unsignedBigInteger('atc')->nullable();
                $table->unsignedBigInteger('ic')->nullable();
                $table->unsignedBigInteger('purchases')->nullable();
                $table->unsignedBigInteger('results_count')->nullable();
                $table->unsignedBigInteger('leads_count')->nullable();
                $table->decimal('cost_per_purchase', 14, 4)->nullable();
                $table->unsignedBigInteger('link_clicks')->nullable();
                $table->unsignedBigInteger('clicks_all')->nullable();
                $table->unsignedBigInteger('shop_clicks')->nullable();
                $table->unsignedBigInteger('landing_page_views')->nullable();
                $table->decimal('cost_per_lpv', 14, 4)->nullable();
                $table->decimal('engagement_rate', 10, 4)->nullable();
                $table->unsignedBigInteger('video_views')->nullable();
                $table->unsignedBigInteger('thruplays')->nullable();
                $table->decimal('cost_per_result', 14, 4)->nullable();
                $table->decimal('amount_spent', 14, 4)->nullable();
                $table->decimal('amount_spent_local', 14, 4)->nullable();
                $table->decimal('amount_spent_qar', 14, 4)->nullable();
                $table->decimal('revenue', 14, 4)->nullable();
                $table->decimal('revenue_local', 14, 4)->nullable();
                $table->decimal('revenue_qar', 14, 4)->nullable();

                $table->foreignId('last_import_log_id')->nullable()->constrained('ad_metric_import_logs')->nullOnDelete();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();

                $table->unique(
                    ['workspace', 'region', 'normalized_campaign_key', 'date_from', 'date_to'],
                    'ad_campaign_metrics_unique_period'
                );
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('ad_campaign_metrics');
        Schema::dropIfExists('ad_metric_import_logs');
    }
};
