<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('ecommerce_seo_audits')) {
            Schema::create('ecommerce_seo_audits', function (Blueprint $table) {
                $table->id();
                $table->string('region', 10)->default('PK')->index();
                $table->string('business_line', 30)->default('rituals_ecommerce')->index();
                $table->string('target_type', 30)->default('product')->index();
                $table->foreignId('ecommerce_product_id')
                    ->nullable()
                    ->constrained('ecommerce_products')
                    ->nullOnDelete();
                $table->string('target_name');
                $table->string('target_url_slug')->index();
                $table->string('primary_keyword')->nullable();
                $table->json('secondary_entities')->nullable();
                $table->integer('seo_score')->default(0);
                $table->string('meta_title')->nullable();
                $table->text('meta_description')->nullable();
                $table->string('h1_heading')->nullable();
                $table->longText('optimized_body_html')->nullable();
                $table->json('faq_json')->nullable();
                $table->longText('json_ld_schema')->nullable();
                $table->json('audit_checklist_json')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('ecommerce_seo_articles')) {
            Schema::create('ecommerce_seo_articles', function (Blueprint $table) {
                $table->id();
                $table->string('region', 10)->default('PK')->index();
                $table->string('business_line', 30)->default('rituals_ecommerce')->index();
                $table->string('cluster_topic')->nullable();
                $table->string('search_intent', 30)->default('informational')->index();
                $table->string('title');
                $table->string('slug')->index();
                $table->string('target_keyword')->nullable();
                $table->string('internal_link_target_url')->nullable();
                $table->longText('article_markdown')->nullable();
                $table->string('meta_title')->nullable();
                $table->text('meta_description')->nullable();
                $table->longText('json_ld_schema')->nullable();
                $table->string('status', 30)->default('draft')->index();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('ecommerce_seo_articles');
        Schema::dropIfExists('ecommerce_seo_audits');
    }
};
