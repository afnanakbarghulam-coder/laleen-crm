<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public const DEFAULT_KNOWLEDGE_BASE = <<<'TEXT'
- Hunza Apricot Oil: Cold-pressed organic apricot kernel oil rich in Vitamin E & oleic acid; lightweight, non-greasy, absorbs deeply into the scalp to reduce hairfall, nourish dry roots, and boost shine. Usage: Warm 4–6 drops between palms, massage into scalp for 5 minutes before wash (or leave overnight 2–3x a week).
- Sulfate-Free Botanical Shampoo: Gentle botanical formula (0% sulfates, parabens, or harsh silicones); safe for keratin/color-treated hair. Clears scalp buildup without stripping natural oils so treatment oils absorb 2x deeper.
- Hair Serum & Hair Mask: Frizz control, heat/humidity protection, and deep weekly cuticle repair.
- Delivery Timelines: Pakistan: 3–5 working days via courier (Cash on Delivery). Qatar: Same-day or next-day delivery in Doha/Al Wakrah/Old Airport.
TEXT;

    public function up(): void
    {
        if (Schema::hasTable('ecommerce_ai_settings')) {
            Schema::table('ecommerce_ai_settings', function (Blueprint $table) {
                if (!Schema::hasColumn('ecommerce_ai_settings', 'shopify_store_domain')) {
                    $table->string('shopify_store_domain')->nullable()->default('laleenrituals.com')->after('agent_persona_name');
                }
                if (!Schema::hasColumn('ecommerce_ai_settings', 'day7_discount_code')) {
                    $table->string('day7_discount_code')->default('COMPLETE20')->after('shopify_store_domain');
                }
                if (!Schema::hasColumn('ecommerce_ai_settings', 'day30_discount_code')) {
                    $table->string('day30_discount_code')->default('VIPREFILL15')->after('day7_discount_code');
                }
                if (!Schema::hasColumn('ecommerce_ai_settings', 'day30_refill_enabled')) {
                    $table->boolean('day30_refill_enabled')->default(true)->after('day7_cross_sell_enabled');
                }
                if (!Schema::hasColumn('ecommerce_ai_settings', 'brand_knowledge_base')) {
                    $table->text('brand_knowledge_base')->nullable()->after('day30_discount_code');
                }
            });

            DB::table('ecommerce_ai_settings')
                ->whereNull('brand_knowledge_base')
                ->orWhere('brand_knowledge_base', '')
                ->update([
                    'shopify_store_domain' => DB::raw("COALESCE(shopify_store_domain, 'laleenrituals.com')"),
                    'day7_discount_code' => DB::raw("COALESCE(day7_discount_code, 'COMPLETE20')"),
                    'day30_discount_code' => DB::raw("COALESCE(day30_discount_code, 'VIPREFILL15')"),
                    'brand_knowledge_base' => self::DEFAULT_KNOWLEDGE_BASE,
                ]);
        }

        if (Schema::hasTable('ecommerce_products')) {
            Schema::table('ecommerce_products', function (Blueprint $table) {
                if (!Schema::hasColumn('ecommerce_products', 'shopify_url')) {
                    $table->string('shopify_url')->nullable()->after('sku');
                }
                if (!Schema::hasColumn('ecommerce_products', 'shopify_variant_id')) {
                    $table->string('shopify_variant_id')->nullable()->after('shopify_url');
                }
            });
        }

        if (Schema::hasTable('ecommerce_whatsapp_threads')) {
            Schema::table('ecommerce_whatsapp_threads', function (Blueprint $table) {
                if (!Schema::hasColumn('ecommerce_whatsapp_threads', 'nudge_sent_at')) {
                    $table->timestamp('nudge_sent_at')->nullable()->after('last_message_at');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('ecommerce_whatsapp_threads') && Schema::hasColumn('ecommerce_whatsapp_threads', 'nudge_sent_at')) {
            Schema::table('ecommerce_whatsapp_threads', function (Blueprint $table) {
                $table->dropColumn('nudge_sent_at');
            });
        }

        if (Schema::hasTable('ecommerce_products')) {
            Schema::table('ecommerce_products', function (Blueprint $table) {
                if (Schema::hasColumn('ecommerce_products', 'shopify_variant_id')) {
                    $table->dropColumn('shopify_variant_id');
                }
                if (Schema::hasColumn('ecommerce_products', 'shopify_url')) {
                    $table->dropColumn('shopify_url');
                }
            });
        }

        if (Schema::hasTable('ecommerce_ai_settings')) {
            Schema::table('ecommerce_ai_settings', function (Blueprint $table) {
                foreach (['brand_knowledge_base', 'day30_refill_enabled', 'day30_discount_code', 'day7_discount_code', 'shopify_store_domain'] as $col) {
                    if (Schema::hasColumn('ecommerce_ai_settings', $col)) {
                        $table->dropColumn($col);
                    }
                }
            });
        }
    }
};
