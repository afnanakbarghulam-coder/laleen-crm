<?php

use App\Models\EcommerceSale;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Ensure `region` exists on `ecommerce_customers` (nullable, indexed)
        if (Schema::hasTable('ecommerce_customers') && !Schema::hasColumn('ecommerce_customers', 'region')) {
            Schema::table('ecommerce_customers', function (Blueprint $table) {
                $table->string('region', 10)->nullable()->after('email')->index();
            });
        }

        // 2. Defensively ensure `region` exists on all other regional ecommerce_* tables
        $regionalTablesWithDefaultPk = [
            'ecommerce_monthly_ads',
            'ecommerce_content_calendar',
            'ecommerce_abandoned_checkouts',
            'ecommerce_lifecycle_flows',
            'ecommerce_lifecycle_logs',
            'ecommerce_seo_articles',
            'ecommerce_seo_audits',
            'ecommerce_seo_search_queries',
            'ecommerce_whatsapp_threads',
        ];

        foreach ($regionalTablesWithDefaultPk as $tableName) {
            if (Schema::hasTable($tableName) && !Schema::hasColumn($tableName, 'region')) {
                Schema::table($tableName, function (Blueprint $table) {
                    $table->string('region', 10)->default('PK')->index();
                });
            }
        }

        // 3. Backfill existing `ecommerce_customers` rows ('QA' vs 'PK') using channel + phone fallback logic
        if (Schema::hasTable('ecommerce_customers') && Schema::hasColumn('ecommerce_customers', 'region')) {
            $hasSalesTable = Schema::hasTable('ecommerce_sales')
                && Schema::hasColumn('ecommerce_sales', 'ecommerce_customer_id')
                && Schema::hasColumn('ecommerce_sales', 'channel');

            $qaChannels = EcommerceSale::QATAR_CHANNELS;
            $pkChannels = EcommerceSale::PAKISTAN_CHANNELS;

            $untaggedCustomers = DB::table('ecommerce_customers')
                ->where(function ($q) {
                    $q->whereNull('region')->orWhere('region', '');
                })
                ->get(['id', 'phone']);

            foreach ($untaggedCustomers as $customer) {
                $resolvedRegion = null;

                if ($hasSalesTable) {
                    $channels = DB::table('ecommerce_sales')
                        ->where('ecommerce_customer_id', $customer->id)
                        ->orderByDesc('created_at')
                        ->orderByDesc('id')
                        ->pluck('channel')
                        ->all();

                    $hasQa = count(array_intersect($channels, $qaChannels)) > 0;
                    $hasPk = count(array_intersect($channels, $pkChannels)) > 0;

                    if ($hasQa && !$hasPk) {
                        $resolvedRegion = 'QA';
                    } elseif ($hasPk && !$hasQa) {
                        $resolvedRegion = 'PK';
                    } elseif ($hasQa && $hasPk) {
                        $latestChannel = $channels[0] ?? '';
                        $resolvedRegion = in_array($latestChannel, $qaChannels, true) ? 'QA' : 'PK';
                    }
                }

                if ($resolvedRegion === null) {
                    $cleanPhone = ltrim(trim((string) ($customer->phone ?? '')), '+');
                    if (str_starts_with($cleanPhone, '00974')) {
                        $cleanPhone = substr($cleanPhone, 2);
                    }
                    $resolvedRegion = str_starts_with($cleanPhone, '974') ? 'QA' : 'PK';
                }

                DB::table('ecommerce_customers')
                    ->where('id', $customer->id)
                    ->update(['region' => $resolvedRegion]);
            }
        }

        // 4. Backfill any untagged `ecommerce_monthly_ads` rows
        if (Schema::hasTable('ecommerce_monthly_ads') && Schema::hasColumn('ecommerce_monthly_ads', 'region')) {
            DB::table('ecommerce_monthly_ads')
                ->where(function ($q) {
                    $q->whereNull('region')->orWhere('region', '');
                })
                ->whereIn('target_branch', ['Old Airport', 'Wakrah'])
                ->update(['region' => 'QA']);

            DB::table('ecommerce_monthly_ads')
                ->where(function ($q) {
                    $q->whereNull('region')->orWhere('region', '');
                })
                ->update(['region' => 'PK']);
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('ecommerce_customers') && Schema::hasColumn('ecommerce_customers', 'region')) {
            Schema::table('ecommerce_customers', function (Blueprint $table) {
                $table->dropColumn('region');
            });
        }
    }
};
