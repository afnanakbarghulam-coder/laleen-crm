<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const NON_REVENUE_CHANNELS = [
        'Backbar Use (Qatar)',
        'Damage/Expiry (Pakistan)',
        'Damage/Expiry (Qatar)',
    ];

    public function up(): void
    {
        Schema::table('ecommerce_sales', function (Blueprint $table) {
            $table->foreignId('ecommerce_customer_id')
                ->nullable()
                ->after('ecommerce_bundle_id')
                ->constrained('ecommerce_customers')
                ->nullOnDelete();
        });

        // Data Backfill: group all existing ecommerce_sales by customer_phone (stripping non-digits),
        // create an EcommerceCustomer record for each unique phone number, calculate historical
        // total_spend and total_orders, and update ecommerce_customer_id on their past sales rows.
        $sales = DB::table('ecommerce_sales')
            ->whereNotNull('customer_phone')
            ->where('customer_phone', '!=', '')
            ->orderBy('created_at')
            ->orderBy('id')
            ->get();

        if ($sales->isEmpty()) {
            return;
        }

        $groupedByPhone = [];
        foreach ($sales as $sale) {
            $normalizedPhone = preg_replace('/\D+/', '', (string) $sale->customer_phone);
            if ($normalizedPhone === '') {
                continue;
            }
            $groupedByPhone[$normalizedPhone][] = $sale;
        }

        $now = now();

        DB::transaction(function () use ($groupedByPhone, $now) {
            foreach ($groupedByPhone as $phone => $customerSales) {
                $customerSalesCollection = collect($customerSales);

                // Pick the latest non-empty customer name
                $latestWithName = $customerSalesCollection
                    ->filter(fn ($s) => trim((string) $s->customer_name) !== '')
                    ->last();
                $name = $latestWithName ? trim((string) $latestWithName->customer_name) : null;

                // Revenue-channel sales contribute to spend and order count
                $revenueSales = $customerSalesCollection->reject(
                    fn ($s) => in_array($s->channel, self::NON_REVENUE_CHANNELS, true)
                );

                $totalSpend = (float) $revenueSales->sum('total_price');

                // Group bundle / multi-line orders that share a reference_id or bundle timestamp,
                // or count each distinct order event.
                $totalOrders = $revenueSales
                    ->groupBy(function ($s) {
                        if (!empty($s->reference_id)) {
                            return 'ref:' . $s->reference_id;
                        }
                        if (!empty($s->ecommerce_bundle_id)) {
                            return 'bundle:' . $s->ecommerce_bundle_id . ':' . $s->created_at;
                        }
                        return 'sale:' . $s->id;
                    })
                    ->count();

                // Fallback if customer only had non-revenue entries with a phone
                if ($totalOrders === 0 && $customerSalesCollection->isNotEmpty()) {
                    $totalOrders = $customerSalesCollection->count();
                }

                $lastPurchaseAt = $customerSalesCollection->max('created_at');

                $customerId = DB::table('ecommerce_customers')->insertGetId([
                    'name' => $name,
                    'phone' => $phone,
                    'email' => null,
                    'total_spend' => round($totalSpend, 2),
                    'total_orders' => $totalOrders,
                    'last_purchase_at' => $lastPurchaseAt,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);

                $saleIds = $customerSalesCollection->pluck('id')->all();

                DB::table('ecommerce_sales')
                    ->whereIn('id', $saleIds)
                    ->update([
                        'ecommerce_customer_id' => $customerId,
                        'customer_phone' => $phone,
                    ]);
            }
        });
    }

    public function down(): void
    {
        Schema::table('ecommerce_sales', function (Blueprint $table) {
            $table->dropConstrainedForeignId('ecommerce_customer_id');
        });
    }
};
