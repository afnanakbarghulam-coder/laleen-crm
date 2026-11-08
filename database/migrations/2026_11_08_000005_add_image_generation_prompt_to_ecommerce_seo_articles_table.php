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
                if (!Schema::hasColumn('ecommerce_seo_articles', 'image_generation_prompt')) {
                    $table->text('image_generation_prompt')->nullable()->after('featured_image_path');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('ecommerce_seo_articles')) {
            Schema::table('ecommerce_seo_articles', function (Blueprint $table) {
                if (Schema::hasColumn('ecommerce_seo_articles', 'image_generation_prompt')) {
                    $table->dropColumn('image_generation_prompt');
                }
            });
        }
    }
};
