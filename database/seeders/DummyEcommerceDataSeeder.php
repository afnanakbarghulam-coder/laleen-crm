<?php

namespace Database\Seeders;

use App\Models\EcommerceCustomer;
use App\Models\EcommerceProduct;
use App\Models\EcommerceSale;
use Carbon\Carbon;
use Database\Factories\EcommerceSaleFactory;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DummyEcommerceDataSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function () {
            // Ensure the 3 core products exist first
            $products = collect(EcommerceSaleFactory::DEFAULT_PRODUCTS)->map(function ($meta, $name) {
                return EcommerceProduct::firstOrCreate(
                    ['name' => $name],
                    [
                        'sku' => $meta['sku'],
                        'unit_size' => '250ml',
                        'stock_pakistan' => 500,
                        'stock_qatar' => 300,
                        'sold_pakistan' => 0,
                        'sold_qatar' => 0,
                        'selling_price' => $meta['price'],
                    ]
                );
            })->values();

            $createdCustomers = collect();

            // -----------------------------------------------------------------
            // Group A: Active Whales (5 customers)
            // 4-6 sales each; most recent sale within the last 15 days -> Green "Active" + Top 20% Whale
            // -----------------------------------------------------------------
            $groupACustomers = EcommerceCustomer::factory()->count(5)->create();
            foreach ($groupACustomers as $customer) {
                $salesCount = random_int(4, 6);

                for ($i = 0; $i < $salesCount; $i++) {
                    // i === 0 is the most recent sale (within the last 1-14 days)
                    $saleDate = $i === 0
                        ? now()->subDays(random_int(1, 14))->subHours(random_int(1, 12))
                        : now()->subDays(random_int(16, 90))->subHours(random_int(1, 12));

                    $product = $products->random();
                    $totalAmount = (float) random_int(6500, 15000);
                    $qty = random_int(1, 3);

                    EcommerceSale::factory()->create([
                        'ecommerce_product_id' => $product->id,
                        'ecommerce_customer_id' => $customer->id,
                        'customer_name' => $customer->name,
                        'customer_phone' => $customer->phone,
                        'quantity' => $qty,
                        'unit_price' => round($totalAmount / $qty, 2),
                        'total_price' => $totalAmount,
                        'channel' => EcommerceSaleFactory::REVENUE_CHANNELS[array_rand(EcommerceSaleFactory::REVENUE_CHANNELS)],
                        'created_at' => $saleDate,
                        'updated_at' => $saleDate,
                    ]);
                }

                $createdCustomers->push($customer);
            }

            // -----------------------------------------------------------------
            // Group B: Slipping Repeat Buyers (10 customers)
            // 2-3 sales each; most recent sale exactly 38 days ago -> Yellow "Slipping"
            // Spread across 'Salon (Old Airport)' and 'Salon (Wakrah)'
            // -----------------------------------------------------------------
            $salonChannels = ['Salon (Old Airport)', 'Salon (Wakrah)'];
            $groupBCustomers = EcommerceCustomer::factory()->count(10)->create();

            foreach ($groupBCustomers as $idx => $customer) {
                $salesCount = random_int(2, 3);
                $primarySalonChannel = $salonChannels[$idx % count($salonChannels)];

                for ($i = 0; $i < $salesCount; $i++) {
                    // i === 0 is the most recent sale: forced to exactly 38 days ago
                    $saleDate = $i === 0
                        ? now()->subDays(38)->startOfDay()->addHours(14)
                        : now()->subDays(random_int(50, 110))->subHours(random_int(1, 12));

                    $product = $products->random();
                    $totalAmount = (float) random_int(1800, 6200);
                    $qty = random_int(1, 2);

                    EcommerceSale::factory()->create([
                        'ecommerce_product_id' => $product->id,
                        'ecommerce_customer_id' => $customer->id,
                        'customer_name' => $customer->name,
                        'customer_phone' => $customer->phone,
                        'quantity' => $qty,
                        'unit_price' => round($totalAmount / $qty, 2),
                        'total_price' => $totalAmount,
                        'channel' => $i === 0 ? $primarySalonChannel : $salonChannels[array_rand($salonChannels)],
                        'created_at' => $saleDate,
                        'updated_at' => $saleDate,
                    ]);
                }

                $createdCustomers->push($customer);
            }

            // -----------------------------------------------------------------
            // Group C: Churned One-Time Buyers (15 customers)
            // Exactly 1 sale each; sale created_at forced to 60+ days ago -> Red "Churned"
            // Spread across 'Shopify (Pakistan)' and 'Organic (Qatar)'
            // -----------------------------------------------------------------
            $churnChannels = ['Shopify (Pakistan)', 'Organic (Qatar)'];
            $groupCCustomers = EcommerceCustomer::factory()->count(15)->create();

            foreach ($groupCCustomers as $idx => $customer) {
                $saleDate = now()->subDays(random_int(60, 120))->subHours(random_int(1, 12));
                $channel = $churnChannels[$idx % count($churnChannels)];
                $product = $products->random();
                $totalAmount = (float) random_int(800, 4500);

                EcommerceSale::factory()->create([
                    'ecommerce_product_id' => $product->id,
                    'ecommerce_customer_id' => $customer->id,
                    'customer_name' => $customer->name,
                    'customer_phone' => $customer->phone,
                    'quantity' => 1,
                    'unit_price' => $totalAmount,
                    'total_price' => $totalAmount,
                    'channel' => $channel,
                    'created_at' => $saleDate,
                    'updated_at' => $saleDate,
                ]);

                $createdCustomers->push($customer);
            }

            // -----------------------------------------------------------------
            // Recalculate and sync total_spend, total_orders, and last_purchase_at
            // for every seeded customer from their actual sales records.
            // -----------------------------------------------------------------
            foreach ($createdCustomers as $customer) {
                $customerSales = EcommerceSale::where('ecommerce_customer_id', $customer->id)->get();

                $totalSpend = (float) $customerSales->sum('total_price');
                $totalOrders = $customerSales->count();
                $lastPurchaseAt = $customerSales->max('created_at');

                $customer->update([
                    'total_spend' => round($totalSpend, 2),
                    'total_orders' => $totalOrders,
                    'last_purchase_at' => $lastPurchaseAt ? Carbon::parse($lastPurchaseAt) : null,
                ]);
            }
        });
    }
}
