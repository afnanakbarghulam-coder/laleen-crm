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
     * Verify X-Shopify-Hmac-Sha256 header when SHOPIFY_WEBHOOK_SECRET is configured.
     */
    private function verifyHmac(Request $request): ?JsonResponse
    {
        $secret = config('services.shopify.webhook_secret');

        if ($secret !== null && trim((string) $secret) !== '') {
            $headerHmac = (string) $request->header('X-Shopify-Hmac-Sha256', '');
            $calculatedHmac = base64_encode(hash_hmac('sha256', $request->getContent(), (string) $secret, true));

            if ($headerHmac === '' || !hash_equals($calculatedHmac, $headerHmac)) {
                return response()->json(['error' => 'Unauthorized: Invalid Shopify HMAC signature.'], 401);
            }
        }

        return null;
    }

    /**
     * Resolve status pair [order_status, payment_status, should_deduct_stock] from a Shopify order payload.
     */
    private function resolveStatusesFromPayload(array $payload): array
    {
        $financialStatus = strtolower((string) ($payload['financial_status'] ?? ''));
        $fulfillmentStatus = strtolower((string) ($payload['fulfillment_status'] ?? ''));
        $isCancelled = !empty($payload['cancelled_at']) || in_array($financialStatus, ['voided', 'refunded'], true);

        if ($isCancelled) {
            return ['cancelled', 'refunded', false];
        }

        if ($financialStatus === 'paid') {
            $orderStatus = $fulfillmentStatus === 'fulfilled' ? 'delivered' : 'pending';

            return [$orderStatus, 'paid', true];
        }

        // Default COD / unpaid flow: if fulfilled in Shopify (or delivered), mark delivered/shipped
        $orderStatus = $fulfillmentStatus === 'fulfilled' ? 'shipped' : 'pending';

        return [$orderStatus, 'unpaid_cod', true];
    }

    /**
     * Receive and process a Shopify order creation webhook.
     * POST /api/webhooks/shopify/orders
     */
    public function storeOrder(Request $request): JsonResponse
    {
        if ($unauthorized = $this->verifyHmac($request)) {
            return $unauthorized;
        }

        $payload = $request->all();

        // Ignore Shopify test orders so they never pollute inventory or revenue
        if (!empty($payload['test']) && $payload['test'] === true) {
            return response()->json([
                'status' => 'ignored_test_order',
                'customer_id' => null,
                'sales_logged' => 0,
            ], 200);
        }

        $shopifyOrderId = isset($payload['id']) && $payload['id'] !== '' ? (string) $payload['id'] : null;
        $referenceId = $payload['name']
            ?? (isset($payload['order_number']) ? 'Shopify #' . $payload['order_number'] : null)
            ?? ($shopifyOrderId ? 'Shopify #' . $shopifyOrderId : null);

        $orderGroupId = $shopifyOrderId
            ? 'SHOPIFY-' . $shopifyOrderId
            : ('SHOPIFY-REF-' . ($referenceId ?: uniqid()));

        // Idempotency guard: if this Shopify order was already processed, return 200 immediately
        if ($shopifyOrderId !== null && EcommerceSale::where('shopify_order_id', $shopifyOrderId)->exists()) {
            return response()->json([
                'status' => 'duplicate_ignored',
                'shopify_order_id' => $shopifyOrderId,
                'sales_logged' => 0,
            ], 200);
        }

        if ($shopifyOrderId === null && $referenceId !== null && EcommerceSale::where('reference_id', $referenceId)->where('channel', self::SHOPIFY_CHANNEL)->exists()) {
            return response()->json([
                'status' => 'duplicate_ignored',
                'reference_id' => $referenceId,
                'sales_logged' => 0,
            ], 200);
        }

        [$orderStatus, $paymentStatus, $shouldDeductStock] = $this->resolveStatusesFromPayload($payload);

        $rawPhone = $payload['phone']
            ?? data_get($payload, 'customer.phone')
            ?? data_get($payload, 'shipping_address.phone')
            ?? data_get($payload, 'billing_address.phone')
            ?? data_get($payload, 'customer.default_address.phone');

        $normalizedPhone = EcommerceCustomer::normalizePhone($rawPhone, '92');

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

        $shippingCost = (float) (
            data_get($payload, 'total_shipping_price_set.shop_money.amount')
            ?? collect($payload['shipping_lines'] ?? [])->sum('price')
            ?? 0
        );
        $taxAmount = (float) ($payload['total_tax'] ?? 0);

        $result = DB::transaction(function () use (
            $shopifyOrderId,
            $orderGroupId,
            $orderStatus,
            $paymentStatus,
            $shouldDeductStock,
            $normalizedPhone,
            $customerName,
            $email,
            $lineItems,
            $referenceId,
            $shippingCost,
            $taxAmount
        ) {
            // Double-check idempotency inside transaction
            if ($shopifyOrderId !== null && EcommerceSale::where('shopify_order_id', $shopifyOrderId)->lockForUpdate()->exists()) {
                return [
                    'customer_id' => null,
                    'sales_logged' => 0,
                    'duplicate' => true,
                ];
            }

            $matchedItems = [];
            foreach ($lineItems as $item) {
                $sku = trim((string) ($item['sku'] ?? ''));
                $quantity = max(1, (int) ($item['quantity'] ?? 1));

                $product = null;
                if ($sku !== '') {
                    $product = EcommerceProduct::lockForUpdate()
                        ->where('sku', $sku)
                        ->first();
                }

                if (!$product && !empty($item['title'])) {
                    $product = EcommerceProduct::lockForUpdate()
                        ->where('name', trim((string) $item['title']))
                        ->first();
                }

                if (!$product) {
                    continue;
                }

                $matchedItems[] = [
                    'item' => $item,
                    'product' => $product,
                    'quantity' => $quantity,
                ];
            }

            // If 0 line items match an EcommerceProduct, do NOT create/increment customer orders or spend
            if (empty($matchedItems)) {
                return [
                    'customer_id' => null,
                    'sales_logged' => 0,
                ];
            }

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
                $customer->save();
            }

            $salesLogged = 0;
            $orderRevenue = 0.0;
            $isFirstLine = true;

            foreach ($matchedItems as $matched) {
                $item = $matched['item'];
                /** @var EcommerceProduct $product */
                $product = $matched['product'];
                $quantity = $matched['quantity'];
                $lineItemId = isset($item['id']) && $item['id'] !== '' ? (string) $item['id'] : null;

                $unitPrice = isset($item['price'])
                    ? (float) $item['price']
                    : (float) ($product->selling_price ?? 0);
                $lineDiscount = (float) ($item['total_discount'] ?? 0);
                $lineTotal = max(0.0, ($quantity * $unitPrice) - $lineDiscount);
                $effectiveUnitPrice = $quantity > 0 ? round($lineTotal / $quantity, 2) : $unitPrice;
                $orderRevenue += $lineTotal;

                $pricingModel = EcommercePricingModel::where('ecommerce_product_id', $product->id)->first();
                $unitCogs = $pricingModel?->bomUnitCost() ?? 0.0;

                EcommerceSale::create([
                    'ecommerce_product_id' => $product->id,
                    'ecommerce_customer_id' => $customer?->id,
                    'shopify_order_id' => $shopifyOrderId,
                    'shopify_line_item_id' => $lineItemId,
                    'reference_id' => $referenceId,
                    'order_group_id' => $orderGroupId,
                    'customer_name' => $customerName ?? $customer?->name,
                    'customer_phone' => $normalizedPhone !== '' ? $normalizedPhone : null,
                    'quantity' => $quantity,
                    'channel' => self::SHOPIFY_CHANNEL,
                    'order_status' => $orderStatus,
                    'payment_status' => $paymentStatus,
                    'stock_deducted' => $shouldDeductStock,
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

                if ($shouldDeductStock) {
                    $product->stock_pakistan = max(0.0, (float) $product->stock_pakistan - $quantity);
                    $product->sold_pakistan = (float) $product->sold_pakistan + $quantity;
                    $product->save();
                }

                $salesLogged++;
                $isFirstLine = false;
            }

            if ($customer) {
                $customer->recalculateLtv();
            }

            return [
                'customer_id' => $customer?->id,
                'customer' => $customer,
                'sales_logged' => $salesLogged,
                'order_revenue' => $orderRevenue,
            ];
        });

        if (empty($result['duplicate']) && ($result['sales_logged'] ?? 0) > 0) {
            try {
                /** @var \App\Services\Ecommerce\EcommerceAiSalesAgentService $aiAgent */
                $aiAgent = app(\App\Services\Ecommerce\EcommerceAiSalesAgentService::class);
                $aiAgent->attributeRetentionThreadConversion(
                    $result['customer'] ?? null,
                    $orderGroupId,
                    (float) ($result['order_revenue'] ?? 0)
                );

                if ($paymentStatus === 'unpaid_cod') {
                    $aiAgent->initiateCodConfirmation($orderGroupId);
                }
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning('AI COD confirmation / retention attribution failed on Shopify webhook.', [
                    'order_group_id' => $orderGroupId,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return response()->json([
            'status' => !empty($result['duplicate']) ? 'duplicate_ignored' : 'ok',
            'customer_id' => $result['customer_id'],
            'sales_logged' => $result['sales_logged'],
        ], 200);
    }

    /**
     * Handle Shopify orders/updated webhook.
     * POST /api/webhooks/shopify/orders-updated
     */
    public function updateOrder(Request $request): JsonResponse
    {
        if ($unauthorized = $this->verifyHmac($request)) {
            return $unauthorized;
        }

        $payload = $request->all();
        if (!empty($payload['test']) && $payload['test'] === true) {
            return response()->json(['status' => 'ignored_test_order'], 200);
        }

        $shopifyOrderId = isset($payload['id']) && $payload['id'] !== '' ? (string) $payload['id'] : null;
        if (!$shopifyOrderId) {
            return response()->json(['status' => 'missing_order_id'], 200);
        }

        $existingSale = EcommerceSale::where('shopify_order_id', $shopifyOrderId)->first();
        if (!$existingSale) {
            return $this->storeOrder($request);
        }

        [$orderStatus, $paymentStatus] = $this->resolveStatusesFromPayload($payload);

        // If the order was already delivered & paid and Shopify sends an update that is still active, keep delivered
        if ($existingSale->order_status === 'delivered' && !in_array($orderStatus, EcommerceSale::INACTIVE_ORDER_STATUSES, true)) {
            $orderStatus = 'delivered';
            $paymentStatus = 'paid';
        }

        $updatedCount = EcommerceSale::transitionOrderGroup(
            $existingSale->order_group_id ?: ('SHOPIFY-' . $shopifyOrderId),
            $orderStatus,
            $paymentStatus
        );

        return response()->json([
            'status' => 'ok',
            'shopify_order_id' => $shopifyOrderId,
            'order_status' => $orderStatus,
            'payment_status' => $paymentStatus,
            'rows_updated' => $updatedCount,
        ], 200);
    }

    /**
     * Handle Shopify orders/cancelled webhook.
     * POST /api/webhooks/shopify/orders-cancelled
     */
    public function cancelOrder(Request $request): JsonResponse
    {
        if ($unauthorized = $this->verifyHmac($request)) {
            return $unauthorized;
        }

        $payload = $request->all();
        if (!empty($payload['test']) && $payload['test'] === true) {
            return response()->json(['status' => 'ignored_test_order'], 200);
        }

        $shopifyOrderId = isset($payload['id']) && $payload['id'] !== '' ? (string) $payload['id'] : null;
        if (!$shopifyOrderId) {
            return response()->json(['status' => 'missing_order_id'], 200);
        }

        $existingSale = EcommerceSale::where('shopify_order_id', $shopifyOrderId)->first();
        if (!$existingSale) {
            return response()->json(['status' => 'order_not_found', 'rows_updated' => 0], 200);
        }

        $updatedCount = EcommerceSale::transitionOrderGroup(
            $existingSale->order_group_id ?: ('SHOPIFY-' . $shopifyOrderId),
            'cancelled',
            'refunded'
        );

        return response()->json([
            'status' => 'ok',
            'shopify_order_id' => $shopifyOrderId,
            'order_status' => 'cancelled',
            'payment_status' => 'refunded',
            'rows_updated' => $updatedCount,
        ], 200);
    }

    /**
     * Handle Shopify orders/fulfilled webhook.
     * POST /api/webhooks/shopify/orders-fulfilled
     */
    public function fulfillOrder(Request $request): JsonResponse
    {
        if ($unauthorized = $this->verifyHmac($request)) {
            return $unauthorized;
        }

        $payload = $request->all();
        if (!empty($payload['test']) && $payload['test'] === true) {
            return response()->json(['status' => 'ignored_test_order'], 200);
        }

        $shopifyOrderId = isset($payload['id']) && $payload['id'] !== '' ? (string) $payload['id'] : null;
        if (!$shopifyOrderId) {
            return response()->json(['status' => 'missing_order_id'], 200);
        }

        $existingSale = EcommerceSale::where('shopify_order_id', $shopifyOrderId)->first();
        if (!$existingSale) {
            return $this->storeOrder($request);
        }

        $updatedCount = EcommerceSale::transitionOrderGroup(
            $existingSale->order_group_id ?: ('SHOPIFY-' . $shopifyOrderId),
            'delivered',
            'paid'
        );

        return response()->json([
            'status' => 'ok',
            'shopify_order_id' => $shopifyOrderId,
            'order_status' => 'delivered',
            'payment_status' => 'paid',
            'rows_updated' => $updatedCount,
        ], 200);
    }
}
