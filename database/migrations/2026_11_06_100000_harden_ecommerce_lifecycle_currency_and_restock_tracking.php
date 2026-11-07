<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ecommerce_sales', function (Blueprint $table) {
            if (!Schema::hasColumn('ecommerce_sales', 'shopify_order_id')) {
                $table->string('shopify_order_id')->nullable()->after('ecommerce_customer_id')->index();
            }
            if (!Schema::hasColumn('ecommerce_sales', 'shopify_line_item_id')) {
                $table->string('shopify_line_item_id')->nullable()->after('shopify_order_id')->index();
            }
            if (!Schema::hasColumn('ecommerce_sales', 'order_group_id')) {
                $table->string('order_group_id')->nullable()->after('reference_id')->index();
            }
            if (!Schema::hasColumn('ecommerce_sales', 'order_status')) {
                $table->string('order_status')->default('delivered')->after('channel')->index();
            }
            if (!Schema::hasColumn('ecommerce_sales', 'payment_status')) {
                $table->string('payment_status')->default('paid')->after('order_status')->index();
            }
            if (!Schema::hasColumn('ecommerce_sales', 'stock_deducted')) {
                $table->boolean('stock_deducted')->default(true)->after('payment_status');
            }
        });

        // Backfill existing ecommerce_sales rows
        $sales = DB::table('ecommerce_sales')->get(['id', 'reference_id', 'ecommerce_bundle_id', 'created_at', 'order_group_id']);
        foreach ($sales as $sale) {
            $groupId = $sale->order_group_id;
            if (empty($groupId)) {
                if (!empty($sale->reference_id)) {
                    $groupId = 'REF-' . trim((string) $sale->reference_id);
                } elseif (!empty($sale->ecommerce_bundle_id)) {
                    $groupId = 'BUNDLE-' . $sale->ecommerce_bundle_id . '-' . ($sale->created_at ?? $sale->id);
                } else {
                    $groupId = 'LEGACY-' . $sale->id;
                }
            }

            DB::table('ecommerce_sales')
                ->where('id', $sale->id)
                ->update([
                    'order_group_id' => $groupId,
                    'order_status' => 'delivered',
                    'payment_status' => 'paid',
                    'stock_deducted' => true,
                ]);
        }

        Schema::table('ecommerce_customers', function (Blueprint $table) {
            if (!Schema::hasColumn('ecommerce_customers', 'last_retention_contacted_at')) {
                $table->timestamp('last_retention_contacted_at')->nullable()->after('last_purchase_at');
            }
            if (!Schema::hasColumn('ecommerce_customers', 'last_retention_stage')) {
                $table->string('last_retention_stage')->nullable()->after('last_retention_contacted_at');
            }
        });

        Schema::table('ecommerce_expenses', function (Blueprint $table) {
            if (!Schema::hasColumn('ecommerce_expenses', 'currency')) {
                $table->string('currency', 10)->default('PKR')->after('amount')->index();
            }
            if (!Schema::hasColumn('ecommerce_expenses', 'includes_raw_material_restock')) {
                $table->boolean('includes_raw_material_restock')->default(false)->after('receipt_path');
            }
            if (!Schema::hasColumn('ecommerce_expenses', 'ecommerce_raw_material_id')) {
                $table->unsignedBigInteger('ecommerce_raw_material_id')->nullable()->after('includes_raw_material_restock')->index();
            }
            if (!Schema::hasColumn('ecommerce_expenses', 'quantity_received')) {
                $table->decimal('quantity_received', 12, 2)->nullable()->after('ecommerce_raw_material_id');
            }
        });

        Schema::table('ecommerce_kpis', function (Blueprint $table) {
            if (!Schema::hasColumn('ecommerce_kpis', 'currency')) {
                try {
                    $table->dropUnique(['date']);
                } catch (\Throwable $e) {
                    // Ignore if index name differs or doesn't exist
                }
                $table->string('currency', 10)->default('PKR')->after('date')->index();
                $table->unique(['date', 'currency']);
            }
        });

        Schema::table('partner_transactions', function (Blueprint $table) {
            if (!Schema::hasColumn('partner_transactions', 'currency')) {
                $table->string('currency', 10)->default('PKR')->after('amount')->index();
            }
        });
    }

    public function down(): void
    {
        Schema::table('partner_transactions', function (Blueprint $table) {
            if (Schema::hasColumn('partner_transactions', 'currency')) {
                $table->dropColumn('currency');
            }
        });

        Schema::table('ecommerce_kpis', function (Blueprint $table) {
            if (Schema::hasColumn('ecommerce_kpis', 'currency')) {
                try {
                    $table->dropUnique(['date', 'currency']);
                } catch (\Throwable $e) {
                }
                $table->dropColumn('currency');
                try {
                    $table->unique('date');
                } catch (\Throwable $e) {
                }
            }
        });

        Schema::table('ecommerce_expenses', function (Blueprint $table) {
            $cols = array_filter(
                ['currency', 'includes_raw_material_restock', 'ecommerce_raw_material_id', 'quantity_received'],
                fn ($col) => Schema::hasColumn('ecommerce_expenses', $col)
            );
            if (!empty($cols)) {
                $table->dropColumn($cols);
            }
        });

        Schema::table('ecommerce_customers', function (Blueprint $table) {
            $cols = array_filter(
                ['last_retention_contacted_at', 'last_retention_stage'],
                fn ($col) => Schema::hasColumn('ecommerce_customers', $col)
            );
            if (!empty($cols)) {
                $table->dropColumn($cols);
            }
        });

        Schema::table('ecommerce_sales', function (Blueprint $table) {
            $cols = array_filter(
                ['shopify_order_id', 'shopify_line_item_id', 'order_group_id', 'order_status', 'payment_status', 'stock_deducted'],
                fn ($col) => Schema::hasColumn('ecommerce_sales', $col)
            );
            if (!empty($cols)) {
                $table->dropColumn($cols);
            }
        });
    }
};
