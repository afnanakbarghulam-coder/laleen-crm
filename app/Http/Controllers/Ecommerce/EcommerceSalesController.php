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
        $selectedChannels = [];
        if ($request->has('channels') && is_array($request->channels) && count($request->channels) > 0) {
            $selectedChannels = array_values(array_intersect($request->channels, EcommerceSale::CHANNELS));
        }

        $salesQuery = EcommerceSale::with('product')->orderByDesc('created_at');

        if (!empty($selectedChannels)) {
            $salesQuery->whereIn('channel', $selectedChannels);
        }

        if ($request->filled('start_date')) {
            $salesQuery->whereDate('created_at', '>=', $request->start_date);
        }

        if ($request->filled('end_date')) {
            $salesQuery->whereDate('created_at', '<=', $request->end_date);
        }

        $sales = $salesQuery->get();
        $products = EcommerceProduct::orderBy('name')->get();
        $salePricesByProduct = EcommercePricingModel::pluck('selling_price', 'ecommerce_product_id');

        $totalRevenue = $sales->sum('total_price');
        $totalItemsSoldRevenue = $sales->whereNotIn('channel', EcommerceSale::NON_REVENUE_CHANNELS)->sum('quantity');
        $totalItemsUsedDamaged = $sales->whereIn('channel', EcommerceSale::NON_REVENUE_CHANNELS)->sum('quantity');

        $chartFrom = $request->filled('start_date') ? Carbon::parse($request->start_date)->startOfDay() : now()->startOfMonth();
        $chartTo = $request->filled('end_date') ? Carbon::parse($request->end_date)->startOfDay() : now()->startOfDay();
        if ($chartFrom->gt($chartTo)) {
            [$chartFrom, $chartTo] = [$chartTo->copy(), $chartFrom->copy()];
        }

        $weekly = $chartFrom->diffInDays($chartTo) > 62;
        $bucketKey = fn ($date) => $weekly ? $date->format('o-W') : $date->format('Y-m-d');
        $bucketLabel = fn ($date) => $weekly ? 'Wk ' . $date->format('W M') : $date->format('d M');

        $grouped = $sales->groupBy(fn ($sale) => $bucketKey($sale->created_at));

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
                $chartUnits[] = (int) $group->whereNotIn('channel', EcommerceSale::NON_REVENUE_CHANNELS)->sum('quantity');
            }
            $cursor->addDay();
        }

        $totalChannelsCount = count(EcommerceSale::CHANNELS);
        $selectedChannelsCount = count($selectedChannels);

        if ($selectedChannelsCount === 0 || $selectedChannelsCount === $totalChannelsCount) {
            $filterText = 'Across all channels';
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
            'sales' => $sales,
            'products' => $products,
            'salePricesByProduct' => $salePricesByProduct,
            'totalRevenue' => $totalRevenue,
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
     * GET /api/ecommerce/customers/search?phone=...
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
     * Combine country code and raw phone digits into a single normalized phone string.
     * Returns '' if no subscriber digits were provided.
     */
    private function combineAndNormalizePhone(?string $countryCode, ?string $rawPhoneDigits): string
    {
        $digitsOnly = EcommerceCustomer::normalizePhone($rawPhoneDigits);
        if ($digitsOnly === '') {
            return '';
        }

        $codeDigits = EcommerceCustomer::normalizePhone($countryCode);
        if ($codeDigits !== '') {
            if (str_starts_with($digitsOnly, '00' . $codeDigits)) {
                $digitsOnly = substr($digitsOnly, 2);
                return $digitsOnly;
            }
            if (str_starts_with($digitsOnly, $codeDigits) && strlen($digitsOnly) > strlen($codeDigits) + 6) {
                return $digitsOnly;
            }
            $digitsOnly = ltrim($digitsOnly, '0');
            return $codeDigits . $digitsOnly;
        }

        return $digitsOnly;
    }

    /**
     * Resolve customer by ecommerce_customer_id first; otherwise combine country code
     * and digits, normalize phone, and firstOrCreate the EcommerceCustomer.
     * Updates name if empty and increments LTV / order count. Must be called inside
     * a DB transaction and ONLY for revenue channels.
     */
    private function resolveAndIncrementCustomer(
        ?int $customerId,
        ?string $normalizedPhone,
        ?string $customerName,
        float $orderRevenue
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
        }

        $customer->total_spend = round((float) $customer->total_spend + $orderRevenue, 2);
        $customer->total_orders = (int) $customer->total_orders + 1;
        $customer->last_purchase_at = now();
        $customer->save();

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

        $isPakistan = str_contains($validated['channel'], 'Pakistan');
        $source = $isPakistan ? 'pakistan' : 'qatar';
        $stockField = "stock_{$source}";
        $soldField = "sold_{$source}";
        $locationName = $isPakistan ? 'Pakistan' : 'Qatar';

        $product = EcommerceProduct::findOrFail($validated['ecommerce_product_id']);

        $pricingModel = EcommercePricingModel::where('ecommerce_product_id', $product->id)->first();
        $unitCogs = $pricingModel?->bomUnitCost() ?? 0.0;
        $unitLiquidCost = (float) ($pricingModel->liquid_cost ?? 0);
        $unitBottleCost = (float) ($pricingModel->bottle_cost ?? 0);
        $unitLabelCost = (float) ($pricingModel->label_cost ?? 0);
        $unitPumpCost = (float) ($pricingModel->pump_cost ?? 0);
        $unitOuterBoxCost = (float) ($pricingModel->box_cost ?? 0);

        $customerId = !empty($validated['ecommerce_customer_id']) ? (int) $validated['ecommerce_customer_id'] : null;
        $normalizedPhone = $this->combineAndNormalizePhone(
            $validated['customer_country_code'] ?? '+974',
            $validated['customer_phone'] ?? null
        );

        try {
            DB::transaction(function () use (
                $validated,
                $isNonRevenue,
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

                // Fix LTV Corruption Bug: Non-revenue events (Damage, Backbar) must NEVER
                // touch the EcommerceCustomer table, even if a phone number is present.
                $customer = null;
                if (!in_array($validated['channel'], self::NON_REVENUE_CHANNELS, true)) {
                    $customer = $this->resolveAndIncrementCustomer(
                        $customerId,
                        $normalizedPhone,
                        $validated['customer_name'] ?? null,
                        $totalPrice
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
                    'customer_name' => $finalCustomerName,
                    'customer_phone' => $finalCustomerPhone,
                    'quantity' => $quantity,
                    'channel' => $validated['channel'],
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
            });
        } catch (RuntimeException $e) {
            return back()->withErrors(['error' => $e->getMessage()]);
        }

        return back()->with('success', 'Sale logged and stock updated.');
    }

    private function storeBundleSale(array $validated)
    {
        $bundleQuantity = (int) $validated['quantity'];
        $channel = $validated['channel'];
        $isNonRevenue = in_array($channel, EcommerceSale::NON_REVENUE_CHANNELS, true);

        $isPakistan = str_contains($channel, 'Pakistan');
        $stockField = $isPakistan ? 'stock_pakistan' : 'stock_qatar';
        $soldField = $isPakistan ? 'sold_pakistan' : 'sold_qatar';
        $locationName = $isPakistan ? 'Pakistan' : 'Qatar';

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
        $normalizedPhone = $this->combineAndNormalizePhone(
            $validated['customer_country_code'] ?? '+974',
            $validated['customer_phone'] ?? null
        );
        $totalBundleRevenue = (float) array_sum($allocation);

        try {
            DB::transaction(function () use (
                $validated,
                $isNonRevenue,
                $customerId,
                $normalizedPhone,
                $totalBundleRevenue,
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

                // Fix LTV Corruption Bug: Only resolve/increment customer for revenue channels
                $customer = null;
                if (!in_array($validated['channel'], self::NON_REVENUE_CHANNELS, true)) {
                    $customer = $this->resolveAndIncrementCustomer(
                        $customerId,
                        $normalizedPhone,
                        $validated['customer_name'] ?? null,
                        $totalBundleRevenue
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

                    EcommerceSale::create([
                        'ecommerce_product_id' => $product->id,
                        'ecommerce_bundle_id' => $bundle->id,
                        'ecommerce_customer_id' => $customer?->id,
                        'reference_id' => $validated['reference_id'] ?? null,
                        'customer_name' => $finalCustomerName,
                        'customer_phone' => $finalCustomerPhone,
                        'quantity' => $unitsSold,
                        'channel' => $validated['channel'],
                        'reason' => trim(($validated['reason'] ?? '') . ' [Bundle: ' . $bundle->name . ']'),
                        'unit_price' => $unitsSold > 0 ? round($lineRevenue / $unitsSold, 2) : 0,
                        'total_price' => $lineRevenue,
                        'unit_cogs' => $pricingModel?->bomUnitCost() ?? 0.0,
                        'unit_liquid_cost' => (float) ($pricingModel->liquid_cost ?? 0),
                        'unit_bottle_cost' => (float) ($pricingModel->bottle_cost ?? 0),
                        'unit_label_cost' => (float) ($pricingModel->label_cost ?? 0),
                        'unit_pump_cost' => (float) ($pricingModel->pump_cost ?? 0),
                        'unit_outer_box_cost' => (float) ($pricingModel->box_cost ?? 0),
                        'shipping_cost' => $isFirstLine ? $shippingCost : 0,
                        'tax_amount' => $isFirstLine ? $taxAmount : 0,
                        'meta_ad_allocation' => $isFirstLine ? $metaAdAllocation : 0,
                    ]);

                    $product->{$stockField} -= $unitsSold;
                    $product->{$soldField} += $unitsSold;
                    $product->save();

                    $isFirstLine = false;
                }
            });
        } catch (RuntimeException $e) {
            return back()->withErrors(['error' => $e->getMessage()]);
        }

        return back()->with('success', 'Bundle sale logged — ' . $bundle->items->count() . ' component line(s) recorded and stock updated.');
    }

    public function destroy(EcommerceSale $sale)
    {
        DB::transaction(function () use ($sale) {
            $isPakistan = str_contains($sale->channel, 'Pakistan');
            $source = $isPakistan ? 'pakistan' : 'qatar';
            $stockField = "stock_{$source}";
            $soldField = "sold_{$source}";

            $product = EcommerceProduct::lockForUpdate()->findOrFail($sale->ecommerce_product_id);
            $product->{$stockField} += $sale->quantity;
            $product->{$soldField} -= $sale->quantity;
            $product->save();

            $customerId = $sale->ecommerce_customer_id;

            $sale->delete();

            // Patch 3: Recalculate the customer's exact LTV from their remaining revenue sales,
            // grouping by reference_id (Shopify) or ecommerce_bundle_id (kits) so deleting
            // a single bundle component row never over-decrements total_orders.
            if ($customerId) {
                $customer = EcommerceCustomer::lockForUpdate()->find($customerId);
                if ($customer) {
                    $remainingRevenueSales = EcommerceSale::where('ecommerce_customer_id', $customer->id)
                        ->whereNotIn('channel', EcommerceSale::NON_REVENUE_CHANNELS)
                        ->get(['id', 'reference_id', 'ecommerce_bundle_id', 'created_at', 'total_price']);

                    $customer->total_spend = round((float) $remainingRevenueSales->sum('total_price'), 2);
                    $customer->total_orders = $remainingRevenueSales
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

                    $latestRemainingSaleAt = $remainingRevenueSales->max('created_at');
                    $customer->last_purchase_at = $latestRemainingSaleAt ? Carbon::parse($latestRemainingSaleAt) : null;
                    $customer->save();
                }
            }
        });

        return back()->with('success', 'Sale deleted, inventory restored, and customer LTV updated.');
    }
}
