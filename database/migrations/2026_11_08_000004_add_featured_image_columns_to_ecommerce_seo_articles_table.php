<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('ecommerce_seo_articles')) {
            Schema::table('ecommerce_seo_articles', function (Blueprint $table) {
                if (!Schema::hasColumn('ecommerce_seo_articles', 'featured_image_url')) {
                    $table->string('featured_image_url')->nullable()->after('image_filename');
                }
                if (!Schema::hasColumn('ecommerce_seo_articles', 'featured_image_path')) {
                    $table->string('featured_image_path')->nullable()->after('featured_image_url');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('ecommerce_seo_articles')) {
            Schema::table('ecommerce_seo_articles', function (Blueprint $table) {
                $cols = array_filter(
                    ['featured_image_url', 'featured_image_path'],
                    fn (string $c) => Schema::hasColumn('ecommerce_seo_articles', $c)
                );
                if (!empty($cols)) {
                    $table->dropColumn($cols);
                }
            });
        }
    }
};
