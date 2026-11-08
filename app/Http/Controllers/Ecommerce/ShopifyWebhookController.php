<?php

namespace App\Http\Controllers\Ecommerce;

use App\Http\Controllers\Controller;
use App\Models\EcommerceAbandonedCheckout;
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
    public const QATAR_SHOPIFY_CHANNEL = 'Organic (Qatar)';

    /**
     * Verify X-Shopify-Hmac-Sha256 header when SHOPIFY_WEBHOOK_SECRET is configured.
     * In production environment, an empty secret is strictly rejected with HTTP 401.
     */
    private function verifyHmac(Request $request): ?JsonResponse
    {
        $secret = trim((string) config('services.shopify.webhook_secret', ''));

        if (app()->environment('production') && $secret === '') {
            return response()->json(['error' => 'Unauthorized: Shopify webhook secret is required in production.'], 401);
        }

        if ($secret !== '') {
            $headerHmac = (string) $request->header('X-Shopify-Hmac-Sha256', '');
            $calculatedHmac = base64_encode(hash_hmac('sha256', $request->getContent(), $secret, true));

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

        if (
            $shopifyOrderId === null
            && $referenceId !== null
            && EcommerceSale::where('reference_id', $referenceId)
                ->whereIn('channel', [self::SHOPIFY_CHANNEL, self::QATAR_SHOPIFY_CHANNEL])
                ->exists()
        ) {
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

        $currency = strtoupper(trim((string) ($payload['currency'] ?? 'PKR')));
        $countryCode = strtoupper(trim((string) (
            data_get($payload, 'shipping_address.country_code')
            ?? data_get($payload, 'billing_address.country_code')
            ?? ''
        )));
        $rawDigits = preg_replace('/\D+/', '', (string) $rawPhone);
        $isQatarOrder = ($currency === 'QAR')
            || ($countryCode === 'QA')
            || str_starts_with($rawDigits, '974')
            || str_starts_with(trim((string) $rawPhone), '+974');

        $normalizedPhone = EcommerceCustomer::normalizePhone($rawPhone, $isQatarOrder ? '974' : '92');
        $isQatarOrder = $isQatarOrder
            || str_starts_with($normalizedPhone, '974')
            || str_starts_with($normalizedPhone, '+974');

        $orderChannel = $isQatarOrder ? self::QATAR_SHOPIFY_CHANNEL : self::SHOPIFY_CHANNEL;
        $customerRegion = $isQatarOrder ? 'QA' : 'PK';

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
            $taxAmount,
            $isQatarOrder,
            $orderChannel,
            $customerRegion
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
                        'region' => $customerRegion,
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
                if (empty($customer->region)) {
                    $customer->region = $customerRegion;
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
                    'channel' => $orderChannel,
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
                    if ($isQatarOrder) {
                        $product->stock_qatar = max(0.0, (float) $product->stock_qatar - $quantity);
                        $product->sold_qatar = (float) $product->sold_qatar + $quantity;
                    } else {
                        $product->stock_pakistan = max(0.0, (float) $product->stock_pakistan - $quantity);
                        $product->sold_pakistan = (float) $product->sold_pakistan + $quantity;
                    }
                    $product->save();
                }

                $salesLogged++;
                $isFirstLine = false;
            }

            if ($customer) {
                $customer->recalculateLtv($customerRegion);
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
                $isQatarOrder = str_starts_with(preg_replace('/\D+/', '', (string) $rawPhone), '974')
                    || strtoupper((string) ($payload['currency'] ?? '')) === 'QAR';
                $orderCurrency = $isQatarOrder ? 'QAR' : 'PKR';
                $checkoutToken = isset($payload['checkout_token'])
                    ? (string) $payload['checkout_token']
                    : (isset($payload['checkout_id']) ? (string) $payload['checkout_id'] : null);

                $aiAgent->attributeAbandonedCheckoutRecovery(
                    $result['customer'] ?? null,
                    $orderGroupId,
                    (float) ($result['order_revenue'] ?? 0),
                    $orderCurrency,
                    $checkoutToken,
                    (string) $rawPhone
                );

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
     * Handle Shopify checkouts/create and checkouts/update webhooks for Playbook 4 (15-Min Abandoned Cart Rescue).
     * POST /api/webhooks/shopify/checkouts-create
     * POST /api/webhooks/shopify/checkouts-update
     */
    public function handleCheckoutWebhook(Request $request): JsonResponse
    {
        if ($unauthorized = $this->verifyHmac($request)) {
            return $unauthorized;
        }

        $payload = $request->all();

        $checkoutId = isset($payload['id']) && $payload['id'] !== ''
            ? (string) $payload['id']
            : (isset($payload['token']) && $payload['token'] !== '' ? (string) $payload['token'] : null);

        if (!$checkoutId) {
            return response()->json(['error' => 'Missing checkout id'], 422);
        }

        $checkoutToken = isset($payload['token']) && $payload['token'] !== ''
            ? (string) $payload['token']
            : (isset($payload['cart_token']) && $payload['cart_token'] !== '' ? (string) $payload['cart_token'] : $checkoutId);

        $currency = strtoupper(trim((string) ($payload['currency'] ?? $payload['presentment_currency'] ?? 'PKR')));
        $rawPhone = (string) (
            $payload['phone']
            ?? data_get($payload, 'shipping_address.phone')
            ?? data_get($payload, 'billing_address.phone')
            ?? data_get($payload, 'customer.phone')
            ?? data_get($payload, 'customer.default_address.phone')
            ?? ''
        );

        $cleanDigits = preg_replace('/\D+/', '', $rawPhone);
        $isQatar = $currency === 'QAR'
            || str_starts_with($cleanDigits, '974')
            || strtoupper((string) data_get($payload, 'shipping_address.country_code', '')) === 'QA';

        $region = $isQatar ? 'QA' : 'PK';
        $resolvedCurrency = $isQatar ? 'QAR' : ($currency ?: 'PKR');
        $defaultCountryCode = $isQatar ? '974' : '92';
        $normalizedPhone = EcommerceCustomer::normalizePhone($rawPhone, $defaultCountryCode);

        $firstName = trim((string) (
            data_get($payload, 'shipping_address.first_name')
            ?? data_get($payload, 'billing_address.first_name')
            ?? data_get($payload, 'customer.first_name')
            ?? ''
        ));
        $lastName = trim((string) (
            data_get($payload, 'shipping_address.last_name')
            ?? data_get($payload, 'billing_address.last_name')
            ?? data_get($payload, 'customer.last_name')
            ?? ''
        ));
        $customerName = trim("{$firstName} {$lastName}");
        if ($customerName === '') {
            $customerName = trim((string) (
                $payload['customer_name']
                ?? data_get($payload, 'shipping_address.name')
                ?? data_get($payload, 'customer.name')
                ?? 'Valued Guest'
            ));
        }

        $email = $payload['email']
            ?? $payload['contact_email']
            ?? data_get($payload, 'customer.email')
            ?? null;

        $customer = null;
        if ($normalizedPhone !== '') {
            $customer = EcommerceCustomer::firstOrCreate(
                ['phone' => $normalizedPhone],
                [
                    'name' => $customerName ?: 'Valued Guest',
                    'email' => $email,
                    'total_spend' => 0,
                    'total_orders' => 0,
                ]
            );
            if ($customerName !== '' && $customerName !== 'Valued Guest' && (empty($customer->name) || $customer->name === 'Valued Guest')) {
                $customer->name = $customerName;
                $customer->save();
            }
        }

        $rawLineItems = is_array($payload['line_items'] ?? null) ? $payload['line_items'] : [];
        $parsedLineItems = [];
        $calculatedSubtotal = 0.0;

        foreach ($rawLineItems as $item) {
            if (!is_array($item)) {
                continue;
            }
            $sku = trim((string) ($item['sku'] ?? ''));
            $title = trim((string) ($item['title'] ?? $item['name'] ?? 'Product'));
            $qty = max(1, (int) ($item['quantity'] ?? 1));
            $price = (float) ($item['price'] ?? 0);

            $matchedProduct = null;
            if ($sku !== '') {
                $matchedProduct = EcommerceProduct::where('sku', $sku)->first();
            }
            if (!$matchedProduct && $title !== '') {
                $matchedProduct = EcommerceProduct::whereRaw('LOWER(name) = ?', [strtolower($title)])
                    ->orWhere('name', 'like', '%' . $title . '%')
                    ->first();
            }

            if ($price <= 0 && $matchedProduct) {
                $price = $matchedProduct->resolveBasePrice($region);
            }
            $calculatedSubtotal += round($price * $qty, 2);

            $parsedLineItems[] = [
                'title' => $matchedProduct?->name ?: $title,
                'quantity' => $qty,
                'price' => $price,
                'ecommerce_product_id' => $matchedProduct?->id,
            ];
        }

        $subtotalPrice = isset($payload['subtotal_price']) && (float) $payload['subtotal_price'] > 0
            ? (float) $payload['subtotal_price']
            : (isset($payload['total_price']) && (float) $payload['total_price'] > 0 ? (float) $payload['total_price'] : $calculatedSubtotal);

        $abandonedUrl = trim((string) ($payload['abandoned_checkout_url'] ?? ''));
        if ($abandonedUrl === '') {
            $firstMatchedProductId = $parsedLineItems[0]['ecommerce_product_id'] ?? null;
            $firstProduct = $firstMatchedProductId ? EcommerceProduct::find($firstMatchedProductId) : EcommerceProduct::orderBy('id')->first();
            $abandonedUrl = $firstProduct
                ? $firstProduct->getShopifyCheckoutUrl()
                : 'https://laleenrituals.com/checkout';
        }

        $existingCheckout = EcommerceAbandonedCheckout::where('shopify_checkout_id', $checkoutId)->first();
        $preserveStatus = ($existingCheckout && in_array($existingCheckout->status, ['outreach_sent', 'recovered'], true))
            ? $existingCheckout->status
            : 'abandoned';

        $abandonedAt = !empty($payload['abandoned_at'])
            ? \Carbon\Carbon::parse($payload['abandoned_at'])
            : ($existingCheckout?->abandoned_at ?? now());

        $checkout = EcommerceAbandonedCheckout::updateOrCreate(
            ['shopify_checkout_id' => $checkoutId],
            [
                'shopify_checkout_token' => $checkoutToken,
                'ecommerce_customer_id' => $customer?->id ?? $existingCheckout?->ecommerce_customer_id,
                'customer_name' => $customerName ?: $existingCheckout?->customer_name,
                'phone' => $normalizedPhone !== '' ? $normalizedPhone : $existingCheckout?->phone,
                'email' => $email ?: $existingCheckout?->email,
                'region' => $region,
                'currency' => $resolvedCurrency,
                'subtotal_price' => round($subtotalPrice, 2),
                'abandoned_checkout_url' => $abandonedUrl,
                'line_items_json' => $parsedLineItems,
                'status' => $preserveStatus,
                'abandoned_at' => $abandonedAt,
            ]
        );

        return response()->json([
            'status' => 'ok',
            'abandoned_checkout_id' => $checkout->id,
            'shopify_checkout_id' => $checkout->shopify_checkout_id,
            'region' => $checkout->region,
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
