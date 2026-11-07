<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('ecommerce_abandoned_checkouts')) {
            Schema::create('ecommerce_abandoned_checkouts', function (Blueprint $table) {
                $table->id();
                $table->string('shopify_checkout_id')->unique()->index();
                $table->string('shopify_checkout_token')->nullable()->index();
                $table->foreignId('ecommerce_customer_id')
                    ->nullable()
                    ->constrained('ecommerce_customers')
                    ->nullOnDelete();
                $table->string('customer_name')->nullable();
                $table->string('phone')->nullable()->index();
                $table->string('email')->nullable();
                $table->string('region', 10)->default('PK')->index();
                $table->string('currency', 10)->default('PKR');
                $table->decimal('subtotal_price', 12, 2)->default(0);
                $table->text('abandoned_checkout_url')->nullable();
                $table->json('line_items_json')->nullable();
                $table->string('status', 30)->default('abandoned')->index();
                $table->timestamp('abandoned_at')->nullable();
                $table->timestamp('outreach_sent_at')->nullable();
                $table->timestamp('recovered_at')->nullable();
                $table->string('recovered_order_group_id')->nullable();
                $table->timestamps();
            });
        }

        if (Schema::hasTable('ecommerce_ai_settings')) {
            Schema::table('ecommerce_ai_settings', function (Blueprint $table) {
                if (!Schema::hasColumn('ecommerce_ai_settings', 'abandoned_checkout_enabled')) {
                    $table->boolean('abandoned_checkout_enabled')->default(true)->after('day30_refill_enabled');
                }
                if (!Schema::hasColumn('ecommerce_ai_settings', 'abandoned_checkout_delay_minutes')) {
                    $table->integer('abandoned_checkout_delay_minutes')->default(15)->after('abandoned_checkout_enabled');
                }
            });
        }

        if (Schema::hasTable('ecommerce_monthly_ads')) {
            Schema::table('ecommerce_monthly_ads', function (Blueprint $table) {
                if (!Schema::hasColumn('ecommerce_monthly_ads', 'framework')) {
                    $table->string('framework')->nullable()->after('ad_name');
                }
                if (!Schema::hasColumn('ecommerce_monthly_ads', 'hook_text')) {
                    $table->text('hook_text')->nullable()->after('txt_cta');
                }
                if (!Schema::hasColumn('ecommerce_monthly_ads', 'spoken_script')) {
                    $table->text('spoken_script')->nullable()->after('hook_text');
                }
                if (!Schema::hasColumn('ecommerce_monthly_ads', 'b_roll_shot_list')) {
                    $table->text('b_roll_shot_list')->nullable()->after('spoken_script');
                }
                if (!Schema::hasColumn('ecommerce_monthly_ads', 'google_flow_prompt')) {
                    $table->text('google_flow_prompt')->nullable()->after('b_roll_shot_list');
                }
                if (!Schema::hasColumn('ecommerce_monthly_ads', 'ecommerce_product_id')) {
                    $table->unsignedBigInteger('ecommerce_product_id')->nullable()->after('google_flow_prompt');
                }
                if (!Schema::hasColumn('ecommerce_monthly_ads', 'region')) {
                    $table->string('region', 10)->default('PK')->after('ecommerce_product_id');
                }
            });
        }

        if (Schema::hasTable('ecommerce_content_calendar')) {
            Schema::table('ecommerce_content_calendar', function (Blueprint $table) {
                if (!Schema::hasColumn('ecommerce_content_calendar', 'caption_copy')) {
                    $table->text('caption_copy')->nullable()->after('script_concept');
                }
                if (!Schema::hasColumn('ecommerce_content_calendar', 'visual_direction')) {
                    $table->text('visual_direction')->nullable()->after('caption_copy');
                }
                if (!Schema::hasColumn('ecommerce_content_calendar', 'ecommerce_product_id')) {
                    $table->unsignedBigInteger('ecommerce_product_id')->nullable()->after('visual_direction');
                }
                if (!Schema::hasColumn('ecommerce_content_calendar', 'region')) {
                    $table->string('region', 10)->default('PK')->after('ecommerce_product_id');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('ecommerce_content_calendar')) {
            Schema::table('ecommerce_content_calendar', function (Blueprint $table) {
                $cols = array_filter(
                    ['caption_copy', 'visual_direction', 'ecommerce_product_id', 'region'],
                    fn ($col) => Schema::hasColumn('ecommerce_content_calendar', $col)
                );
                if (!empty($cols)) {
                    $table->dropColumn($cols);
                }
            });
        }

        if (Schema::hasTable('ecommerce_monthly_ads')) {
            Schema::table('ecommerce_monthly_ads', function (Blueprint $table) {
                $cols = array_filter(
                    ['framework', 'hook_text', 'spoken_script', 'b_roll_shot_list', 'google_flow_prompt', 'ecommerce_product_id', 'region'],
                    fn ($col) => Schema::hasColumn('ecommerce_monthly_ads', $col)
                );
                if (!empty($cols)) {
                    $table->dropColumn($cols);
                }
            });
        }

        if (Schema::hasTable('ecommerce_ai_settings')) {
            Schema::table('ecommerce_ai_settings', function (Blueprint $table) {
                $cols = array_filter(
                    ['abandoned_checkout_enabled', 'abandoned_checkout_delay_minutes'],
                    fn ($col) => Schema::hasColumn('ecommerce_ai_settings', $col)
                );
                if (!empty($cols)) {
                    $table->dropColumn($cols);
                }
            });
        }

        Schema::dropIfExists('ecommerce_abandoned_checkouts');
    }
};
