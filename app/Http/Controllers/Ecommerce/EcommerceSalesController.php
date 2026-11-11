<?php

namespace App\Http\Controllers\Ecommerce;

use App\Http\Controllers\Controller;
use App\Models\EcommerceBundle;
use App\Models\EcommerceBundleItem;
use App\Models\EcommerceCustomer;
use App\Models\EcommercePricingModel;
use App\Models\EcommerceProduct;
use App\Models\EcommerceSale;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use RuntimeException;

class EcommerceSalesController extends Controller
{
    public const CHANNELS = EcommerceSale::CHANNELS;
    public const REVENUE_CHANNELS = EcommerceSale::REVENUE_CHANNELS;
    public const NON_REVENUE_CHANNELS = EcommerceSale::NON_REVENUE_CHANNELS;
    public const BACKBAR_CHANNELS = EcommerceSale::BACKBAR_CHANNELS;
    public const SALON_RETAIL_CHANNELS = EcommerceSale::SALON_RETAIL_CHANNELS;

    public function index(Request $request)
    {
        $region = EcommerceSale::normalizeRegion($request->query('region', 'pk'));
        $currency = EcommerceSale::currencyForRegion($region);
        $regionChannels = EcommerceSale::channelsForRegion($region);

        $selectedChannels = [];
        if ($request->has('channels') && is_array($request->channels) && count($request->channels) > 0) {
            $selectedChannels = array_values(array_intersect($request->channels, $regionChannels));
        }

        $salesQuery = EcommerceSale::with(['product', 'bundle'])
            ->whereIn('channel', !empty($selectedChannels) ? $selectedChannels : $regionChannels)
            ->orderByDesc('created_at')
            ->orderByDesc('id');

        if ($request->filled('start_date')) {
            $salesQuery->whereDate('created_at', '>=', $request->start_date);
        }

        if ($request->filled('end_date')) {
            $salesQuery->whereDate('created_at', '<=', $request->end_date);
        }

        $sales = $salesQuery->get();
        $products = EcommerceProduct::orderBy('name')->get();
        $salePricesByProduct = EcommercePricingModel::pluck('selling_price', 'ecommerce_product_id');

        $realizedSales = $sales->filter(fn (EcommerceSale $s) => $s->isRealizedRevenue());
        $pendingCodSales = $sales->filter(fn (EcommerceSale $s) => $s->isPendingCod());
        $nonRevenueActiveSales = $sales->filter(
            fn (EcommerceSale $s) => in_array($s->channel, EcommerceSale::NON_REVENUE_CHANNELS, true)
                && !in_array($s->order_status, EcommerceSale::INACTIVE_ORDER_STATUSES, true)
        );

        $totalRevenue = (float) $realizedSales->sum('total_price');
        $totalOrders = EcommerceSale::countDistinctOrders($realizedSales);
        $pendingCodRevenue = (float) $pendingCodSales->sum('total_price');
        $pendingCodOrders = EcommerceSale::countDistinctOrders($pendingCodSales);
        $totalItemsSoldRevenue = (int) $realizedSales->sum('quantity');
        $totalItemsUsedDamaged = (int) $nonRevenueActiveSales->sum('quantity');

        $chartFrom = $request->filled('start_date') ? Carbon::parse($request->start_date)->startOfDay() : now()->startOfMonth();
        $chartTo = $request->filled('end_date') ? Carbon::parse($request->end_date)->startOfDay() : now()->startOfDay();
        if ($chartFrom->gt($chartTo)) {
            [$chartFrom, $chartTo] = [$chartTo->copy(), $chartFrom->copy()];
        }

        $weekly = $chartFrom->diffInDays($chartTo) > 62;
        $bucketKey = fn ($date) => $weekly ? $date->format('o-W') : $date->format('Y-m-d');
        $bucketLabel = fn ($date) => $weekly ? 'Wk ' . $date->format('W M') : $date->format('d M');

        $grouped = $realizedSales->groupBy(fn ($sale) => $bucketKey($sale->created_at));

        $chartLabels = [];
        $chartRevenue = [];
        $chartUnits = [];
        $seenBuckets = [];
        $cursor = $chartFrom->copy();
        while ($cursor->lte($chartTo)) {
            $key = $bucketKey($cursor);
            if (!in_array($key, $seenBuckets, true)) {
                $seenBuckets[] = $key;
                $group = $grouped->get($key, collect());
                $chartLabels[] = $bucketLabel($cursor);
                $chartRevenue[] = round((float) $group->sum('total_price'), 2);
                $chartUnits[] = (int) $group->sum('quantity');
            }
            $cursor->addDay();
        }

        $totalChannelsCount = count($regionChannels);
        $selectedChannelsCount = count($selectedChannels);
        $regionLabel = $region === 'qa' ? 'Qatar (QAR)' : 'Pakistan (PKR)';

        if ($selectedChannelsCount === 0 || $selectedChannelsCount === $totalChannelsCount) {
            $filterText = "All {$regionLabel} channels";
        } elseif ($selectedChannelsCount > 2) {
            $filterText = 'Filtered: ' . $selectedChannelsCount . ' channels';
        } else {
            $filterText = 'Filtered: ' . implode(', ', $selectedChannels);
        }

        if ($request->filled('start_date') || $request->filled('end_date')) {
            if ($request->filled('start_date') && $request->filled('end_date')) {
                $dateRangeText = 'From ' . Carbon::parse($request->start_date)->format('M d, Y')
                    . ' to ' . Carbon::parse($request->end_date)->format('M d, Y');
            } elseif ($request->filled('start_date')) {
                $dateRangeText = 'From ' . Carbon::parse($request->start_date)->format('M d, Y');
            } else {
                $dateRangeText = 'Up to ' . Carbon::parse($request->end_date)->format('M d, Y');
            }
            $filterText .= ' | ' . $dateRangeText;
        } else {
            $filterText .= ' | All time';
        }

        $bundles = EcommerceBundle::with(['items.product.pricingModel'])->orderBy('name')->get();

        return view('ecommerce.sales', [
            'region' => $region,
            'currency' => $currency,
            'regionChannels' => $regionChannels,
            'sales' => $sales,
            'products' => $products,
            'salePricesByProduct' => $salePricesByProduct,
            'totalRevenue' => $totalRevenue,
            'totalOrders' => $totalOrders,
            'pendingCodRevenue' => $pendingCodRevenue,
            'pendingCodOrders' => $pendingCodOrders,
            'totalItemsSoldRevenue' => $totalItemsSoldRevenue,
            'totalItemsUsedDamaged' => $totalItemsUsedDamaged,
            'filterText' => $filterText,
            'selectedChannels' => $selectedChannels,
            'chartLabels' => $chartLabels,
            'chartRevenue' => $chartRevenue,
            'chartUnits' => $chartUnits,
            'bundles' => $bundles,
            'bundlesForJs' => $this->bundlesForJs($bundles),
        ]);
    }

    /** Shape the bundle catalogue for the sales modal's Vanilla JS pricing. */
    private function bundlesForJs($bundles): array
    {
        return $bundles->mapWithKeys(fn (EcommerceBundle $bundle) => [$bundle->id => [
            'name' => $bundle->name,
            'bundle_price' => (float) $bundle->bundle_price,
            'standalone_value' => $bundle->standaloneValue(),
            'savings' => $bundle->savingsVersusStandalone(),
            'items' => $bundle->items->map(fn (EcommerceBundleItem $item) => [
                'product_name' => $item->product->name ?? 'Deleted product',
                'quantity' => (int) $item->quantity,
            ])->values()->all(),
        ]])->all();
    }

    /**
     * Lightweight Smart Search endpoint for the Front-Desk Sales Modal.
     * Protected under auth + module:ecommerce in routes/web.php:
     * GET /ecommerce/hub/sales/customer-search?phone=...
     */
    public function searchCustomer(Request $request): JsonResponse
    {
        $normalizedPhone = EcommerceCustomer::normalizePhone($request->query('phone'));

        if ($normalizedPhone === '' || strlen($normalizedPhone) < 5) {
            return response()->json(['found' => false, 'customer' => null]);
        }

        $customer = EcommerceCustomer::where('phone', $normalizedPhone)->first();

        if (!$customer) {
            return response()->json(['found' => false, 'customer' => null]);
        }

        return response()->json([
            'found' => true,
            'customer' => [
                'id' => $customer->id,
                'name' => $customer->name,
                'phone' => $customer->phone,
                'email' => $customer->email,
                'total_spend' => (float) $customer->total_spend,
                'total_orders' => (int) $customer->total_orders,
                'last_purchase_at' => $customer->last_purchase_at?->toIso8601String(),
            ],
        ]);
    }

    /**
     * Resolve customer by ecommerce_customer_id first; otherwise normalize phone and firstOrCreate.
     * Must be called inside a DB transaction and ONLY for revenue channels.
     */
    private function resolveCustomer(
        ?int $customerId,
        ?string $normalizedPhone,
        ?string $customerName
    ): ?EcommerceCustomer {
        $cleanName = trim((string) $customerName) !== '' ? trim((string) $customerName) : null;
        $customer = null;

        if ($customerId) {
            $customer = EcommerceCustomer::lockForUpdate()->find($customerId);
        }

        if (!$customer) {
            if ($normalizedPhone === null || $normalizedPhone === '') {
                return null;
            }

            $customer = EcommerceCustomer::lockForUpdate()->firstOrCreate(
                ['phone' => $normalizedPhone],
                [
                    'name' => $cleanName,
                    'total_spend' => 0,
                    'total_orders' => 0,
                ]
            );
        }

        if (empty($customer->name) && $cleanName !== null) {
            $customer->name = $cleanName;
            $customer->save();
        }

        return $customer;
    }

    public function store(Request $request)
    {
        $isBundleSale = $request->input('sale_mode') === 'bundle';

        $validated = $request->validate([
            'sale_mode' => ['nullable', Rule::in(['product', 'bundle'])],
            'ecommerce_product_id' => [
                Rule::requiredIf(fn () => !$isBundleSale),
                'nullable',
                'exists:ecommerce_products,id',
            ],
            'ecommerce_bundle_id' => [
                Rule::requiredIf(fn () => $isBundleSale),
                'nullable',
                'exists:ecommerce_bundles,id',
            ],
            'ecommerce_customer_id' => 'nullable|integer|exists:ecommerce_customers,id',
            'reference_id' => 'nullable|string|max:255',
            'customer_name' => 'nullable|string|max:255',
            'customer_country_code' => 'nullable|string|max:10',
            'customer_phone' => 'nullable|string|max:40',
            'quantity' => 'required|integer|min:1',
            'channel' => 'required|in:' . implode(',', EcommerceSale::CHANNELS),
            'order_status' => ['nullable', Rule::in(['pending', 'shipped', 'delivered'])],
            'payment_status' => ['nullable', Rule::in(['unpaid_cod', 'paid'])],
            'reason' => 'nullable|string|max:255',
            'unit_price' => [
                Rule::requiredIf(fn () => !$isBundleSale && !in_array($request->input('channel'), EcommerceSale::NON_REVENUE_CHANNELS, true)),
                'nullable',
                'numeric',
                'min:0',
            ],
            'shipping_cost' => 'nullable|numeric|min:0',
            'tax_amount' => 'nullable|numeric|min:0',
            'meta_ad_allocation' => 'nullable|numeric|min:0',
        ]);

        if ($isBundleSale) {
            return $this->storeBundleSale($validated);
        }

        $quantity = (int) $validated['quantity'];
        $shippingCost = (float) ($validated['shipping_cost'] ?? 0);
        $taxAmount = (float) ($validated['tax_amount'] ?? 0);
        $metaAdAllocation = (float) ($validated['meta_ad_allocation'] ?? 0);
        $isNonRevenue = in_array($validated['channel'], EcommerceSale::NON_REVENUE_CHANNELS, true);
        $unitPrice = $isNonRevenue ? 0.0 : (float) ($validated['unit_price'] ?? 0);
        $totalPrice = $quantity * $unitPrice;

        $orderStatus = $validated['order_status'] ?? 'delivered';
        $paymentStatus = ($orderStatus === 'delivered' || $isNonRevenue)
            ? 'paid'
            : ($validated['payment_status'] ?? 'paid');

        $isPakistan = str_contains($validated['channel'], 'Pakistan');
        $source = $isPakistan ? 'pakistan' : 'qatar';
        $stockField = "stock_{$source}";
        $soldField = "sold_{$source}";
        $locationName = $isPakistan ? 'Pakistan' : 'Qatar';
        $defaultCountryCode = $validated['customer_country_code'] ?? ($isPakistan ? '+92' : '+974');

        $product = EcommerceProduct::findOrFail($validated['ecommerce_product_id']);

        $orderRegion = $isPakistan ? 'pk' : 'qa';
        $pricingModel = EcommercePricingModel::where('ecommerce_product_id', $product->id)->first();
        $resolvedCosts = $pricingModel
            ? $pricingModel->componentCostsForRegion($orderRegion)
            : [
                'unit_cogs' => 0.0,
                'unit_liquid_cost' => 0.0,
                'unit_bottle_cost' => 0.0,
                'unit_label_cost' => 0.0,
                'unit_pump_cost' => 0.0,
                'unit_outer_box_cost' => 0.0,
            ];
        $unitCogs = $resolvedCosts['unit_cogs'];
        $unitLiquidCost = $resolvedCosts['unit_liquid_cost'];
        $unitBottleCost = $resolvedCosts['unit_bottle_cost'];
        $unitLabelCost = $resolvedCosts['unit_label_cost'];
        $unitPumpCost = $resolvedCosts['unit_pump_cost'];
        $unitOuterBoxCost = $resolvedCosts['unit_outer_box_cost'];

        $customerId = !empty($validated['ecommerce_customer_id']) ? (int) $validated['ecommerce_customer_id'] : null;
        $normalizedPhone = EcommerceCustomer::normalizePhone(
            $validated['customer_phone'] ?? null,
            $defaultCountryCode
        );
        $orderGroupId = (string) Str::uuid();

        try {
            DB::transaction(function () use (
                $validated,
                $isNonRevenue,
                $orderGroupId,
                $orderStatus,
                $paymentStatus,
                $customerId,
                $normalizedPhone,
                $quantity,
                $unitPrice,
                $totalPrice,
                $unitCogs,
                $unitLiquidCost,
                $unitBottleCost,
                $unitLabelCost,
                $unitPumpCost,
                $unitOuterBoxCost,
                $shippingCost,
                $taxAmount,
                $metaAdAllocation,
                $stockField,
                $soldField,
                $locationName
            ) {
                $product = EcommerceProduct::lockForUpdate()->findOrFail($validated['ecommerce_product_id']);

                if ((float) $product->{$stockField} < $quantity) {
                    throw new RuntimeException("Insufficient stock in {$locationName} for this sale. Requested: " . number_format($quantity, 2) . ', Available: ' . number_format((float) $product->{$stockField}, 2) . '.');
                }

                $customer = null;
                if (!$isNonRevenue) {
                    $customer = $this->resolveCustomer(
                        $customerId,
                        $normalizedPhone,
                        $validated['customer_name'] ?? null
                    );
                }

                $finalCustomerName = $validated['customer_name'] ?? null;
                if ((empty($finalCustomerName) || trim((string) $finalCustomerName) === '') && $customer?->name) {
                    $finalCustomerName = $customer->name;
                }

                $finalCustomerPhone = null;
                if (!$isNonRevenue) {
                    $finalCustomerPhone = $normalizedPhone !== '' ? $normalizedPhone : $customer?->phone;
                }

                EcommerceSale::create([
                    'ecommerce_product_id' => $validated['ecommerce_product_id'],
                    'ecommerce_customer_id' => $customer?->id,
                    'reference_id' => $validated['reference_id'] ?? null,
                    'order_group_id' => $orderGroupId,
                    'customer_name' => $finalCustomerName,
                    'customer_phone' => $finalCustomerPhone,
                    'quantity' => $quantity,
                    'channel' => $validated['channel'],
                    'order_status' => $orderStatus,
                    'payment_status' => $paymentStatus,
                    'stock_deducted' => true,
                    'reason' => $validated['reason'] ?? null,
                    'unit_price' => $unitPrice,
                    'total_price' => $totalPrice,
                    'unit_cogs' => $unitCogs,
                    'unit_liquid_cost' => $unitLiquidCost,
                    'unit_bottle_cost' => $unitBottleCost,
                    'unit_label_cost' => $unitLabelCost,
                    'unit_pump_cost' => $unitPumpCost,
                    'unit_outer_box_cost' => $unitOuterBoxCost,
                    'shipping_cost' => $shippingCost,
                    'tax_amount' => $taxAmount,
                    'meta_ad_allocation' => $metaAdAllocation,
                ]);

                $product->{$stockField} -= $quantity;
                $product->{$soldField} += $quantity;
                $product->save();

                if ($customer) {
                    $customer->recalculateLtv();
                }

                return $customer;
            });
        } catch (RuntimeException $e) {
            return back()->withErrors(['error' => $e->getMessage()]);
        }

        if (!$isNonRevenue) {
            try {
                /** @var \App\Services\Ecommerce\EcommerceAiSalesAgentService $aiAgent */
                $aiAgent = app(\App\Services\Ecommerce\EcommerceAiSalesAgentService::class);
                $saleCustomer = EcommerceSale::where('order_group_id', $orderGroupId)->first()?->customer;
                $isQatarSale = in_array($validated['channel'], EcommerceSale::QATAR_CHANNELS, true);
                $saleCurrency = $isQatarSale ? 'QAR' : 'PKR';
                $aiAgent->attributeAbandonedCheckoutRecovery(
                    $saleCustomer,
                    $orderGroupId,
                    (float) $totalPrice,
                    $saleCurrency,
                    null,
                    $normalizedPhone
                );
                $aiAgent->attributeRetentionThreadConversion($saleCustomer, $orderGroupId, (float) $totalPrice);

                if ($paymentStatus === 'unpaid_cod') {
                    $aiAgent->initiateCodConfirmation($orderGroupId);
                }
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning('AI COD confirmation auto-trigger failed on manual sale.', [
                    'order_group_id' => $orderGroupId,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return back()->with('success', 'Sale logged and stock updated.');
    }

    private function storeBundleSale(array $validated)
    {
        $bundleQuantity = (int) $validated['quantity'];
        $channel = $validated['channel'];
        $isNonRevenue = in_array($channel, EcommerceSale::NON_REVENUE_CHANNELS, true);

        $orderStatus = $validated['order_status'] ?? 'delivered';
        $paymentStatus = ($orderStatus === 'delivered' || $isNonRevenue)
            ? 'paid'
            : ($validated['payment_status'] ?? 'paid');

        $isPakistan = str_contains($channel, 'Pakistan');
        $stockField = $isPakistan ? 'stock_pakistan' : 'stock_qatar';
        $soldField = $isPakistan ? 'sold_pakistan' : 'sold_qatar';
        $locationName = $isPakistan ? 'Pakistan' : 'Qatar';
        $defaultCountryCode = $validated['customer_country_code'] ?? ($isPakistan ? '+92' : '+974');

        $bundle = EcommerceBundle::with(['items.product.pricingModel'])->findOrFail($validated['ecommerce_bundle_id']);

        if ($bundle->items->isEmpty()) {
            return back()->withErrors(['error' => "Bundle \"{$bundle->name}\" has no products in it yet, so it cannot be sold."]);
        }

        $allocation = $isNonRevenue
            ? $bundle->items->mapWithKeys(fn ($item) => [$item->id => 0.0])->all()
            : $bundle->allocateRevenue($bundleQuantity);

        $shippingCost = (float) ($validated['shipping_cost'] ?? 0);
        $taxAmount = (float) ($validated['tax_amount'] ?? 0);
        $metaAdAllocation = (float) ($validated['meta_ad_allocation'] ?? 0);

        $customerId = !empty($validated['ecommerce_customer_id']) ? (int) $validated['ecommerce_customer_id'] : null;
        $normalizedPhone = EcommerceCustomer::normalizePhone(
            $validated['customer_phone'] ?? null,
            $defaultCountryCode
        );
        $orderGroupId = (string) Str::uuid();

        try {
            DB::transaction(function () use (
                $validated,
                $isNonRevenue,
                $orderGroupId,
                $orderStatus,
                $paymentStatus,
                $customerId,
                $normalizedPhone,
                $bundle,
                $bundleQuantity,
                $allocation,
                $stockField,
                $soldField,
                $locationName,
                $shippingCost,
                $taxAmount,
                $metaAdAllocation
            ) {
                $productIds = $bundle->items->pluck('ecommerce_product_id')->all();

                $lockedProducts = EcommerceProduct::whereIn('id', $productIds)
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->get()
                    ->keyBy('id');

                foreach ($bundle->items as $item) {
                    $product = $lockedProducts->get($item->ecommerce_product_id);
                    $unitsNeeded = (int) $item->quantity * $bundleQuantity;

                    if (!$product) {
                        throw new RuntimeException("A product in \"{$bundle->name}\" no longer exists. Edit the bundle before selling it.");
                    }

                    if ((float) $product->{$stockField} < $unitsNeeded) {
                        throw new RuntimeException("Insufficient stock in {$locationName} for {$product->name} (part of \"{$bundle->name}\"). Required: {$unitsNeeded}, Available: " . number_format((float) $product->{$stockField}, 2) . '.');
                    }
                }

                $customer = null;
                if (!$isNonRevenue) {
                    $customer = $this->resolveCustomer(
                        $customerId,
                        $normalizedPhone,
                        $validated['customer_name'] ?? null
                    );
                }

                $finalCustomerName = $validated['customer_name'] ?? null;
                if ((empty($finalCustomerName) || trim((string) $finalCustomerName) === '') && $customer?->name) {
                    $finalCustomerName = $customer->name;
                }

                $finalCustomerPhone = null;
                if (!$isNonRevenue) {
                    $finalCustomerPhone = $normalizedPhone !== '' ? $normalizedPhone : $customer?->phone;
                }

                $isFirstLine = true;

                foreach ($bundle->items as $item) {
                    $product = $lockedProducts->get($item->ecommerce_product_id);
                    $unitsSold = (int) $item->quantity * $bundleQuantity;
                    $lineRevenue = (float) ($allocation[$item->id] ?? 0);
                    $pricingModel = $product->pricingModel;
                    $bundleRegion = str_contains($validated['channel'], 'Pakistan') ? 'pk' : 'qa';
                    $resolvedBundleCosts = $pricingModel
                        ? $pricingModel->componentCostsForRegion($bundleRegion)
                        : [
                            'unit_cogs' => 0.0,
                            'unit_liquid_cost' => 0.0,
                            'unit_bottle_cost' => 0.0,
                            'unit_label_cost' => 0.0,
                            'unit_pump_cost' => 0.0,
                            'unit_outer_box_cost' => 0.0,
                        ];

                    EcommerceSale::create([
                        'ecommerce_product_id' => $product->id,
                        'ecommerce_bundle_id' => $bundle->id,
                        'ecommerce_customer_id' => $customer?->id,
                        'reference_id' => $validated['reference_id'] ?? null,
                        'order_group_id' => $orderGroupId,
                        'customer_name' => $finalCustomerName,
                        'customer_phone' => $finalCustomerPhone,
                        'quantity' => $unitsSold,
                        'channel' => $validated['channel'],
                        'order_status' => $orderStatus,
                        'payment_status' => $paymentStatus,
                        'stock_deducted' => true,
                        'reason' => trim(($validated['reason'] ?? '') . ' [Bundle: ' . $bundle->name . ']'),
                        'unit_price' => $unitsSold > 0 ? round($lineRevenue / $unitsSold, 2) : 0,
                        'total_price' => $lineRevenue,
                        'unit_cogs' => $resolvedBundleCosts['unit_cogs'],
                        'unit_liquid_cost' => $resolvedBundleCosts['unit_liquid_cost'],
                        'unit_bottle_cost' => $resolvedBundleCosts['unit_bottle_cost'],
                        'unit_label_cost' => $resolvedBundleCosts['unit_label_cost'],
                        'unit_pump_cost' => $resolvedBundleCosts['unit_pump_cost'],
                        'unit_outer_box_cost' => $resolvedBundleCosts['unit_outer_box_cost'],
                        'shipping_cost' => $isFirstLine ? $shippingCost : 0,
                        'tax_amount' => $isFirstLine ? $taxAmount : 0,
                        'meta_ad_allocation' => $isFirstLine ? $metaAdAllocation : 0,
                    ]);

                    $product->{$stockField} -= $unitsSold;
                    $product->{$soldField} += $unitsSold;
                    $product->save();

                    $isFirstLine = false;
                }

                if ($customer) {
                    $customer->recalculateLtv();
                }
            });
        } catch (RuntimeException $e) {
            return back()->withErrors(['error' => $e->getMessage()]);
        }

        if (!$isNonRevenue) {
            try {
                /** @var \App\Services\Ecommerce\EcommerceAiSalesAgentService $aiAgent */
                $aiAgent = app(\App\Services\Ecommerce\EcommerceAiSalesAgentService::class);
                $saleCustomer = EcommerceSale::where('order_group_id', $orderGroupId)->first()?->customer;
                $bundleTotalRevenue = array_sum($allocation);
                $isQatarBundle = in_array($channel, EcommerceSale::QATAR_CHANNELS, true);
                $bundleCurrency = $isQatarBundle ? 'QAR' : 'PKR';
                $aiAgent->attributeAbandonedCheckoutRecovery(
                    $saleCustomer,
                    $orderGroupId,
                    (float) $bundleTotalRevenue,
                    $bundleCurrency,
                    null,
                    $normalizedPhone
                );
                $aiAgent->attributeRetentionThreadConversion($saleCustomer, $orderGroupId, (float) $bundleTotalRevenue);

                if ($paymentStatus === 'unpaid_cod') {
                    $aiAgent->initiateCodConfirmation($orderGroupId);
                }
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning('AI COD confirmation auto-trigger failed on bundle sale.', [
                    'order_group_id' => $orderGroupId,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return back()->with('success', 'Bundle sale logged — ' . $bundle->items->count() . ' component line(s) recorded and stock updated.');
    }

    /**
     * Quick status update for an atomic order_group_id.
     * PATCH /ecommerce/hub/sales/{orderGroupId}/status
     */
    public function updateStatus(Request $request, string $orderGroupId)
    {
        $validated = $request->validate([
            'order_status' => ['required', Rule::in(EcommerceSale::ORDER_STATUSES)],
            'payment_status' => ['nullable', Rule::in(EcommerceSale::PAYMENT_STATUSES)],
        ]);

        try {
            $updated = EcommerceSale::transitionOrderGroup(
                $orderGroupId,
                $validated['order_status'],
                $validated['payment_status'] ?? null
            );

            if ($updated === 0) {
                return back()->withErrors(['error' => 'Order not found.']);
            }
        } catch (RuntimeException $e) {
            return back()->withErrors(['error' => $e->getMessage()]);
        }

        return back()->with('success', 'Order status updated to ' . strtoupper($validated['order_status']) . ' (' . $updated . ' line item(s) updated).');
    }

    /**
     * Delete all sibling rows sharing the same order_group_id atomically,
     * restoring stock for every row where stock_deducted === true and recalculating customer LTV.
     */
    public function destroy(EcommerceSale $sale)
    {
        DB::transaction(function () use ($sale) {
            $siblings = !empty($sale->order_group_id)
                ? EcommerceSale::lockForUpdate()->where('order_group_id', $sale->order_group_id)->get()
                : collect([$sale]);

            if ($siblings->isEmpty()) {
                $siblings = collect([$sale]);
            }

            $affectedCustomerIds = [];

            foreach ($siblings as $row) {
                if ($row->stock_deducted) {
                    $isPakistan = str_contains((string) $row->channel, 'Pakistan');
                    $source = $isPakistan ? 'pakistan' : 'qatar';
                    $stockField = "stock_{$source}";
                    $soldField = "sold_{$source}";

                    $product = EcommerceProduct::lockForUpdate()->find($row->ecommerce_product_id);
                    if ($product) {
                        $product->{$stockField} = (float) $product->{$stockField} + (int) $row->quantity;
                        $product->{$soldField} = max(0, (float) $product->{$soldField} - (int) $row->quantity);
                        $product->save();
                    }
                }

                if ($row->ecommerce_customer_id) {
                    $affectedCustomerIds[$row->ecommerce_customer_id] = $row->ecommerce_customer_id;
                }

                $row->delete();
            }

            foreach ($affectedCustomerIds as $customerId) {
                $customer = EcommerceCustomer::lockForUpdate()->find($customerId);
                $customer?->recalculateLtv();
            }
        });

        return back()->with('success', 'Order deleted, inventory restored, and customer LTV updated.');
    }
}
