<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Convert all Ecommerce & AI tables to utf8mb4_unicode_ci on MySQL / MariaDB
     * so 4-byte UTF-8 emojis (💬, ✨, 👋, 🎉, ⚡, 📦) never trigger SQLSTATE[HY000]: 1366.
     */
    public function up(): void
    {
        if (!in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            return;
        }

        $tables = [
            'ecommerce_whatsapp_messages',
            'ecommerce_whatsapp_threads',
            'ecommerce_customers',
            'ecommerce_sales',
            'ecommerce_abandoned_checkouts',
            'ecommerce_ai_settings',
            'ecommerce_monthly_ads',
            'ecommerce_content_calendar',
            'ecommerce_products',
            'ecommerce_bundles',
            'ecommerce_bundle_items',
            'ecommerce_kpis',
            'ecommerce_expenses',
            'ecommerce_raw_materials',
            'ecommerce_pricing_models',
        ];

        foreach ($tables as $table) {
            if (Schema::hasTable($table)) {
                try {
                    DB::statement("ALTER TABLE `{$table}` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
                } catch (\Throwable $e) {
                    // Continue converting remaining tables if a specific table cannot be altered
                }
            }
        }
    }

    public function down(): void
    {
        // Keep utf8mb4_unicode_ci on rollback for safety
    }
};
