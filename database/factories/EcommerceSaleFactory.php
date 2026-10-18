<?php

namespace Database\Factories;

use App\Models\EcommerceProduct;
use App\Models\EcommerceSale;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\EcommerceSale>
 */
class EcommerceSaleFactory extends Factory
{
    protected $model = EcommerceSale::class;

    public const REVENUE_CHANNELS = [
        'Shopify (Pakistan)',
        'Organic (Pakistan)',
        'Salon (Old Airport)',
        'Salon (Wakrah)',
        'Organic (Qatar)',
    ];

    public const DEFAULT_PRODUCTS = [
        'Apricot Oil' => ['sku' => 'LAL-APR-OIL', 'price' => 2800],
        'Hair Recovery Kit' => ['sku' => 'LAL-REC-KIT', 'price' => 6500],
        'Shampoo' => ['sku' => 'LAL-SHM-250', 'price' => 2200],
    ];

    public function definition(): array
    {
        $productName = $this->faker->randomElement(array_keys(self::DEFAULT_PRODUCTS));
        $meta = self::DEFAULT_PRODUCTS[$productName];

        $product = EcommerceProduct::firstOrCreate(
            ['name' => $productName],
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

        $quantity = $this->faker->numberBetween(1, 3);
        $totalAmount = (float) $this->faker->numberBetween(800, 15000);
        $unitPrice = round($totalAmount / $quantity, 2);

        return [
            'ecommerce_product_id' => $product->id,
            'ecommerce_bundle_id' => null,
            'ecommerce_customer_id' => null,
            'reference_id' => 'ORD-' . $this->faker->unique()->numerify('#####'),
            'customer_name' => $this->faker->name(),
            'customer_phone' => null,
            'quantity' => $quantity,
            'channel' => $this->faker->randomElement(self::REVENUE_CHANNELS),
            'reason' => 'Seeded Order',
            'unit_price' => $unitPrice,
            'total_price' => $totalAmount,
            'unit_cogs' => round($unitPrice * 0.28, 2),
            'unit_liquid_cost' => round($unitPrice * 0.12, 2),
            'unit_bottle_cost' => round($unitPrice * 0.06, 2),
            'unit_label_cost' => round($unitPrice * 0.03, 2),
            'unit_pump_cost' => round($unitPrice * 0.03, 2),
            'unit_outer_box_cost' => round($unitPrice * 0.04, 2),
            'shipping_cost' => 250.00,
            'tax_amount' => 0.00,
            'meta_ad_allocation' => 0.00,
        ];
    }
}
