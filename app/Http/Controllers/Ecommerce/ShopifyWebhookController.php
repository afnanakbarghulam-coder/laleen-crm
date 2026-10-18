<?php

namespace App\Http\Controllers\Ecommerce;

use App\Http\Controllers\Controller;
use App\Models\EcommerceCustomer;
use App\Models\EcommercePricingModel;
use App\Models\EcommerceProduct;
use App\Models\EcommerceSale;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ShopifyWebhookController extends Controller
{
    public const SHOPIFY_CHANNEL = 'Shopify (Pakistan)';

    /**
     * Receive and process a Shopify order creation webhook.
     * POST /api/webhooks/shopify/orders
     */
    public function storeOrder(Request $request): JsonResponse
    {
        $payload = $request->all();

        $rawPhone = $payload['phone']
            ?? data_get($payload, 'customer.phone')
            ?? data_get($payload, 'shipping_address.phone')
            ?? data_get($payload, 'billing_address.phone')
            ?? data_get($payload, 'customer.default_address.phone');

        $normalizedPhone = EcommerceCustomer::normalizePhone($rawPhone);

        $firstName = trim((string) (
            data_get($payload, 'customer.first_name')
            ?? data_get($payload, 'shipping_address.first_name')
            ?? data_get($payload, 'billing_address.first_name')
            ?? ''
        ));
        $lastName = trim((string) (
            data_get($payload, 'customer.last_name')
            ?? data_get($payload, 'shipping_address.last_name')
            ?? data_get($payload, 'billing_address.last_name')
            ?? ''
        ));

        $customerName = trim($firstName . ' ' . $lastName);
        if ($customerName === '') {
            $customerName = trim((string) (
                data_get($payload, 'customer.name')
                ?? data_get($payload, 'shipping_address.name')
                ?? data_get($payload, 'billing_address.name')
                ?? ''
            ));
        }
        $customerName = $customerName !== '' ? $customerName : null;

        $email = $payload['email']
            ?? $payload['contact_email']
            ?? data_get($payload, 'customer.email')
            ?? null;

        $lineItems = is_array($payload['line_items'] ?? null) ? $payload['line_items'] : [];
        $payloadTotalPrice = isset($payload['total_price']) ? (float) $payload['total_price'] : null;

        $referenceId = $payload['name']
            ?? (isset($payload['order_number']) ? 'Shopify #' . $payload['order_number'] : null)
            ?? (isset($payload['id']) ? 'Shopify #' . $payload['id'] : null);

        $shippingCost = (float) (
            data_get($payload, 'total_shipping_price_set.shop_money.amount')
            ?? collect($payload['shipping_lines'] ?? [])->sum('price')
            ?? 0
        );
        $taxAmount = (float) ($payload['total_tax'] ?? 0);

        $result = DB::transaction(function () use (
            $normalizedPhone,
            $customerName,
            $email,
            $lineItems,
            $payloadTotalPrice,
            $referenceId,
            $shippingCost,
            $taxAmount
        ) {
            $customer = null;

            if ($normalizedPhone !== '') {
                $customer = EcommerceCustomer::lockForUpdate()->firstOrCreate(
                    ['phone' => $normalizedPhone],
                    [
                        'name' => $customerName,
                        'email' => $email,
                        'total_spend' => 0,
                        'total_orders' => 0,
                    ]
                );

                if (empty($customer->name) && $customerName !== null) {
                    $customer->name = $customerName;
                }
                if (empty($customer->email) && $email !== null) {
                    $customer->email = $email;
                }
            }

            $salesLogged = 0;
            $loggedLinesRevenue = 0.0;
            $isFirstLine = true;

            foreach ($lineItems as $item) {
                $sku = trim((string) ($item['sku'] ?? ''));
                $quantity = max(1, (int) ($item['quantity'] ?? 1));

                $product = null;
                if ($sku !== '') {
                    $product = EcommerceProduct::lockForUpdate()
                        ->where('sku', $sku)
                        ->first();
                }

                // Fallback match by title/name if SKU wasn't set or didn't match
                if (!$product && !empty($item['title'])) {
                    $product = EcommerceProduct::lockForUpdate()
                        ->where('name', trim((string) $item['title']))
                        ->first();
                }

                if (!$product) {
                    continue;
                }

                $unitPrice = isset($item['price'])
                    ? (float) $item['price']
                    : (float) ($product->selling_price ?? 0);
                $lineDiscount = (float) ($item['total_discount'] ?? 0);
                $lineTotal = max(0.0, ($quantity * $unitPrice) - $lineDiscount);
                $effectiveUnitPrice = $quantity > 0 ? round($lineTotal / $quantity, 2) : $unitPrice;

                $pricingModel = EcommercePricingModel::where('ecommerce_product_id', $product->id)->first();
                $unitCogs = $pricingModel?->bomUnitCost() ?? 0.0;

                EcommerceSale::create([
                    'ecommerce_product_id' => $product->id,
                    'ecommerce_customer_id' => $customer?->id,
                    'reference_id' => $referenceId,
                    'customer_name' => $customerName ?? $customer?->name,
                    'customer_phone' => $normalizedPhone !== '' ? $normalizedPhone : null,
                    'quantity' => $quantity,
                    'channel' => self::SHOPIFY_CHANNEL,
                    'reason' => 'Shopify Webhook Automated Order',
                    'unit_price' => $effectiveUnitPrice,
                    'total_price' => $lineTotal,
                    'unit_cogs' => $unitCogs,
                    'unit_liquid_cost' => (float) ($pricingModel->liquid_cost ?? 0),
                    'unit_bottle_cost' => (float) ($pricingModel->bottle_cost ?? 0),
                    'unit_label_cost' => (float) ($pricingModel->label_cost ?? 0),
                    'unit_pump_cost' => (float) ($pricingModel->pump_cost ?? 0),
                    'unit_outer_box_cost' => (float) ($pricingModel->box_cost ?? 0),
                    'shipping_cost' => $isFirstLine ? $shippingCost : 0,
                    'tax_amount' => $isFirstLine ? $taxAmount : 0,
                    'meta_ad_allocation' => 0,
                ]);

                $product->stock_pakistan = (float) $product->stock_pakistan - $quantity;
                $product->sold_pakistan = (float) $product->sold_pakistan + $quantity;
                $product->save();

                $loggedLinesRevenue += $lineTotal;
                $salesLogged++;
                $isFirstLine = false;
            }

            if ($customer) {
                $orderSpend = $payloadTotalPrice !== null && $payloadTotalPrice > 0
                    ? $payloadTotalPrice
                    : $loggedLinesRevenue;

                $customer->total_spend = round((float) $customer->total_spend + $orderSpend, 2);
                $customer->total_orders = (int) $customer->total_orders + 1;
                $customer->last_purchase_at = now();
                $customer->save();
            }

            return [
                'customer_id' => $customer?->id,
                'sales_logged' => $salesLogged,
            ];
        });

        return response()->json([
            'status' => 'ok',
            'customer_id' => $result['customer_id'],
            'sales_logged' => $result['sales_logged'],
        ], 200);
    }
}
