<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('ecommerce_ai_settings')) {
            Schema::create('ecommerce_ai_settings', function (Blueprint $table) {
                $table->id();
                $table->string('mode')->default('copilot');
                $table->boolean('cod_confirmation_enabled')->default(true);
                $table->boolean('pre_shipment_upsell_enabled')->default(true);
                $table->boolean('day7_cross_sell_enabled')->default(true);
                $table->decimal('upsell_discount_percent', 5, 2)->default(20.00);
                $table->string('whatsapp_driver')->default('simulator');
                $table->string('outbound_webhook_url')->nullable();
                $table->string('agent_persona_name')->default('Zara');
                $table->timestamps();
            });

            DB::table('ecommerce_ai_settings')->insert([
                'mode' => 'copilot',
                'cod_confirmation_enabled' => true,
                'pre_shipment_upsell_enabled' => true,
                'day7_cross_sell_enabled' => true,
                'upsell_discount_percent' => 20.00,
                'whatsapp_driver' => 'simulator',
                'outbound_webhook_url' => null,
                'agent_persona_name' => 'Zara',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        if (!Schema::hasTable('ecommerce_whatsapp_threads')) {
            Schema::create('ecommerce_whatsapp_threads', function (Blueprint $table) {
                $table->id();
                $table->foreignId('ecommerce_customer_id')
                    ->constrained('ecommerce_customers')
                    ->cascadeOnDelete();
                $table->string('order_group_id')->nullable()->index();
                $table->string('region', 10)->default('PK')->index();
                $table->string('playbook', 40)->default('cod_upsell')->index();
                $table->string('state', 50)->default('idle')->index();
                $table->foreignId('offered_product_id')
                    ->nullable()
                    ->constrained('ecommerce_products')
                    ->nullOnDelete();
                $table->foreignId('offered_bundle_id')
                    ->nullable()
                    ->constrained('ecommerce_bundles')
                    ->nullOnDelete();
                $table->decimal('offered_addon_price', 12, 2)->nullable();
                $table->decimal('upsell_revenue_generated', 12, 2)->default(0);
                $table->integer('unread_count')->default(0);
                $table->timestamp('last_message_at')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('ecommerce_whatsapp_messages')) {
            Schema::create('ecommerce_whatsapp_messages', function (Blueprint $table) {
                $table->id();
                $table->foreignId('thread_id')
                    ->constrained('ecommerce_whatsapp_threads')
                    ->cascadeOnDelete();
                $table->string('direction', 20);
                $table->string('sender_type', 30);
                $table->string('delivery_status', 30)->default('sent');
                $table->text('message_body');
                $table->json('ai_metadata')->nullable();
                $table->string('external_message_id')->nullable()->index();
                $table->timestamps();
            });
        }

        if (Schema::hasTable('ecommerce_sales')) {
            Schema::table('ecommerce_sales', function (Blueprint $table) {
                if (!Schema::hasColumn('ecommerce_sales', 'is_ai_upsell')) {
                    $table->boolean('is_ai_upsell')->default(false)->after('stock_deducted');
                }
                if (!Schema::hasColumn('ecommerce_sales', 'cod_confirmed_at')) {
                    $table->timestamp('cod_confirmed_at')->nullable()->after('is_ai_upsell');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('ecommerce_sales')) {
            Schema::table('ecommerce_sales', function (Blueprint $table) {
                if (Schema::hasColumn('ecommerce_sales', 'cod_confirmed_at')) {
                    $table->dropColumn('cod_confirmed_at');
                }
                if (Schema::hasColumn('ecommerce_sales', 'is_ai_upsell')) {
                    $table->dropColumn('is_ai_upsell');
                }
            });
        }

        Schema::dropIfExists('ecommerce_whatsapp_messages');
        Schema::dropIfExists('ecommerce_whatsapp_threads');
        Schema::dropIfExists('ecommerce_ai_settings');
    }
};
