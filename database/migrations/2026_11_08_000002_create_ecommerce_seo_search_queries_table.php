<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('ecommerce_seo_search_queries')) {
            Schema::create('ecommerce_seo_search_queries', function (Blueprint $table) {
                $table->id();
                $table->string('region', 10)->default('PK')->index();
                $table->string('business_unit', 30)->default('rituals_ecommerce')->index();
                $table->string('search_query')->index();
                $table->integer('impressions')->default(0);
                $table->integer('clicks')->default(0);
                $table->decimal('ctr_percent', 6, 2)->default(0);
                $table->decimal('avg_position', 6, 1)->default(0);
                $table->string('target_url')->nullable();
                $table->string('opportunity_type', 30)->default('quick_win')->index();
                $table->foreignId('linked_article_id')
                    ->nullable()
                    ->constrained('ecommerce_seo_articles')
                    ->nullOnDelete();
                $table->timestamp('last_synced_at')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('ecommerce_seo_search_queries');
    }
};
