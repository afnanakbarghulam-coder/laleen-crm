<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('ecommerce_seo_audits')) {
            Schema::table('ecommerce_seo_audits', function (Blueprint $table) {
                if (!Schema::hasColumn('ecommerce_seo_audits', 'image_alt_text')) {
                    $table->string('image_alt_text')->nullable()->after('h1_heading');
                }
                if (!Schema::hasColumn('ecommerce_seo_audits', 'image_filename')) {
                    $table->string('image_filename')->nullable()->after('image_alt_text');
                }
                if (!Schema::hasColumn('ecommerce_seo_audits', 'shopify_sync_status')) {
                    $table->string('shopify_sync_status', 32)->default('draft')->after('audit_checklist_json');
                }
                if (!Schema::hasColumn('ecommerce_seo_audits', 'shopify_published_at')) {
                    $table->timestamp('shopify_published_at')->nullable()->after('shopify_sync_status');
                }
            });
        }

        if (Schema::hasTable('ecommerce_seo_articles')) {
            Schema::table('ecommerce_seo_articles', function (Blueprint $table) {
                if (!Schema::hasColumn('ecommerce_seo_articles', 'image_alt_text')) {
                    $table->string('image_alt_text')->nullable()->after('meta_description');
                }
                if (!Schema::hasColumn('ecommerce_seo_articles', 'image_filename')) {
                    $table->string('image_filename')->nullable()->after('image_alt_text');
                }
                if (!Schema::hasColumn('ecommerce_seo_articles', 'shopify_sync_status')) {
                    $table->string('shopify_sync_status', 32)->default('draft')->after('status');
                }
                if (!Schema::hasColumn('ecommerce_seo_articles', 'shopify_published_at')) {
                    $table->timestamp('shopify_published_at')->nullable()->after('shopify_sync_status');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('ecommerce_seo_audits')) {
            Schema::table('ecommerce_seo_audits', function (Blueprint $table) {
                $cols = array_filter(
                    ['image_alt_text', 'image_filename', 'shopify_sync_status', 'shopify_published_at'],
                    fn (string $c) => Schema::hasColumn('ecommerce_seo_audits', $c)
                );
                if (!empty($cols)) {
                    $table->dropColumn($cols);
                }
            });
        }

        if (Schema::hasTable('ecommerce_seo_articles')) {
            Schema::table('ecommerce_seo_articles', function (Blueprint $table) {
                $cols = array_filter(
                    ['image_alt_text', 'image_filename', 'shopify_sync_status', 'shopify_published_at'],
                    fn (string $c) => Schema::hasColumn('ecommerce_seo_articles', $c)
                );
                if (!empty($cols)) {
                    $table->dropColumn($cols);
                }
            });
        }
    }
};
