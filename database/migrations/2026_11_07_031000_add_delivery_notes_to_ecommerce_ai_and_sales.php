<?php

use App\Models\EcommerceAiSetting;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('ecommerce_whatsapp_threads')) {
            Schema::table('ecommerce_whatsapp_threads', function (Blueprint $table) {
                if (!Schema::hasColumn('ecommerce_whatsapp_threads', 'delivery_notes')) {
                    $table->text('delivery_notes')->nullable()->after('nudge_sent_at');
                }
            });
        }

        if (Schema::hasTable('ecommerce_sales')) {
            Schema::table('ecommerce_sales', function (Blueprint $table) {
                if (!Schema::hasColumn('ecommerce_sales', 'delivery_notes')) {
                    $table->text('delivery_notes')->nullable()->after('reason');
                }
            });
        }

        if (Schema::hasTable('ecommerce_ai_settings') && Schema::hasColumn('ecommerce_ai_settings', 'brand_knowledge_base')) {
            DB::table('ecommerce_ai_settings')->update([
                'brand_knowledge_base' => EcommerceAiSetting::DEFAULT_KNOWLEDGE_BASE,
            ]);
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('ecommerce_whatsapp_threads') && Schema::hasColumn('ecommerce_whatsapp_threads', 'delivery_notes')) {
            Schema::table('ecommerce_whatsapp_threads', function (Blueprint $table) {
                $table->dropColumn('delivery_notes');
            });
        }

        if (Schema::hasTable('ecommerce_sales') && Schema::hasColumn('ecommerce_sales', 'delivery_notes')) {
            Schema::table('ecommerce_sales', function (Blueprint $table) {
                $table->dropColumn('delivery_notes');
            });
        }
    }
};
