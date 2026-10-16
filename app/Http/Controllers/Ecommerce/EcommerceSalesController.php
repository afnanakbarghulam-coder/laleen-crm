<?php

namespace App\Http\Controllers\Ecommerce;

use App\Http\Controllers\Controller;
use App\Models\EcommerceBundle;
use App\Models\EcommerceBundleItem;
use App\Models\EcommercePricingModel;
use App\Models\EcommerceProduct;
use App\Models\EcommerceSale;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use RuntimeException;

class EcommerceSalesController extends Controller
{
    public const CHANNELS = [
        'Shopify (Pakistan)',
        'Organic (Pakistan)',
        'Salon (Old Airport)',
        'Salon (Wakrah)',
        'Organic (Qatar)',
        'Backbar Use (Qatar)',
        'Damage/Expiry (Pakistan)',
        'Damage/Expiry (Qatar)',
    ];

    public const NON_REVENUE_CHANNELS = [
        'Backbar Use (Qatar)',
        'Damage/Expiry (Pakistan)',
        'Damage/Expiry (Qatar)',
    ];

    /** Product consumed at the wash basin — the cost side of the salon funnel. */
    public const BACKBAR_CHANNELS = ['Backbar Use (Qatar)'];

    /** Front-desk retail sold inside a salon — the revenue side of that funnel. */
    public const SALON_RETAIL_CHANNELS = ['Salon (Old Airport)', 'Salon (Wakrah)'];

    /** Customer Lifecycle Pipeline stage windows (days since purchase). */
    public const CROSS_SELL_START_DAYS = 6;
    public const CROSS_SELL_END_DAYS = 8;
    public const REFILL_WINDOW_START_DAYS = 25;
    public const REFILL_WINDOW_END_DAYS = 35;
    public const WIN_BACK_START_DAYS = 60;
    public const WIN_BACK_END_DAYS = 70;

    public function index(Request $request)
    {
        $selectedChannels = [];
        if ($request->has('channels') && is_array($request->channels) && count($request->channels) > 0) {
            $selectedChannels = array_values(array_intersect($request->channels, self::CHANNELS));
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
        $totalItemsSoldRevenue = $sales->whereNotIn('channel', self::NON_REVENUE_CHANNELS)->sum('quantity');
        $totalItemsUsedDamaged = $sales->whereIn('channel', self::NON_REVENUE_CHANNELS)->sum('quantity');

        $chartFrom = $request->filled('start_date') ? Carbon::parse($request->start_date)->startOfDay() : now()->startOfMonth();
        $chartTo = $request->filled('end_date') ? Carbon::parse($request->end_date)->startOfDay() : now()->startOfDay();
        if ($chartFrom->gt($chartTo)) {
            [$chartFrom, $chartTo] = [$chartTo->copy(), $chartFrom->copy()];
        }

        // Falls back to weekly buckets once the range is too wide for a legible daily x-axis,
        // mirroring the Bookings Trend chart so both modules bucket consistently.
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
                $chartUnits[] = (int) $group->whereNotIn('channel', self::NON_REVENUE_CHANNELS)->sum('quantity');
            }
            $cursor->addDay();
        }

        $totalChannelsCount = count(self::CHANNELS);
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
        $lifecyclePipeline = $this->lifecyclePipeline();

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
            'lifecyclePipeline' => $lifecyclePipeline,
            'refillQueue' => $lifecyclePipeline['day30']['items'],
            'refillWindowStartDays' => self::REFILL_WINDOW_START_DAYS,
            'refillWindowEndDays' => self::REFILL_WINDOW_END_DAYS,
            'backbarConversion' => $this->backbarConversion(),
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
     * 3-Stage Automated Customer Lifecycle Pipeline (LTV Expansion):
     * - Day 7 (Cross-Sell): 6-8 days ago, 20% bundle-completion hook.
     * - Day 30 (Refill): 25-35 days ago, refill reminder hook.
     * - Day 60 (Win-Back): 60-70 days ago with no subsequent orders, high-urgency risk-free Grand Slam kit hook.
     */
    private function lifecyclePipeline(): array
    {
        return [
            'day7' => [
                'key' => 'day7',
                'title' => 'Day 7 · Cross-Sell',
                'window_label' => 'Day ' . self::CROSS_SELL_START_DAYS . '–' . self::CROSS_SELL_END_DAYS,
                'subtitle' => 'Revenue channel sales 6–8 days ago. Hook with a 20% bundle-completion cross-sell.',
                'cta_label' => 'Send 20% Cross-Sell',
                'badge_class' => 'ec-badge-bundle',
                'items' => $this->buildLifecycleStage(
                    self::CROSS_SELL_START_DAYS,
                    self::CROSS_SELL_END_DAYS,
                    false,
                    'cross_sell'
                ),
            ],
            'day30' => [
                'key' => 'day30',
                'title' => 'Day 30 · Refill',
                'window_label' => 'Day ' . self::REFILL_WINDOW_START_DAYS . '–' . self::REFILL_WINDOW_END_DAYS,
                'subtitle' => 'Revenue channel sales 25–35 days ago. Remind customers right before their bottle runs dry.',
                'cta_label' => 'Send Refill Nudge',
                'badge_class' => 'ec-badge-injection',
                'items' => $this->buildLifecycleStage(
                    self::REFILL_WINDOW_START_DAYS,
                    self::REFILL_WINDOW_END_DAYS,
                    true,
                    'refill'
                ),
            ],
            'day60' => [
                'key' => 'day60',
                'title' => 'Day 60 · Win-Back',
                'window_label' => 'Day ' . self::WIN_BACK_START_DAYS . '–' . self::WIN_BACK_END_DAYS,
                'subtitle' => 'Revenue channel sales 60–70 days ago with no subsequent orders. High-urgency, risk-free Grand Slam kit offer.',
                'cta_label' => 'Send Grand Slam Offer',
                'badge_class' => 'ec-badge-expense-paid',
                'items' => $this->buildLifecycleStage(
                    self::WIN_BACK_START_DAYS,
                    self::WIN_BACK_END_DAYS,
                    true,
                    'win_back'
                ),
            ],
        ];
    }

    /**
     * Queries revenue-channel sales in a specific [startDaysAgo, endDaysAgo] window,
     * optionally excluding customers with subsequent orders after the window closed,
     * and builds a stage-specific WhatsApp deep link for each customer.
     */
    private function buildLifecycleStage(
        int $startDaysAgo,
        int $endDaysAgo,
        bool $excludeSubsequentOrders,
        string $stageType
    ) {
        $windowStart = now()->subDays($endDaysAgo)->startOfDay();
        $windowEnd = now()->subDays($startDaysAgo)->endOfDay();

        $dueSales = EcommerceSale::with('product')
            ->whereNotIn('channel', self::NON_REVENUE_CHANNELS)
            ->whereBetween('created_at', [$windowStart, $windowEnd])
            ->orderByDesc('created_at')
            ->get();

        if ($dueSales->isEmpty()) {
            return collect();
        }

        $normalizePhone = fn ($phone) => preg_replace('/\D+/', '', (string) $phone);
        $normalizeName = fn ($name) => mb_strtolower(trim((string) $name));

        $repurchasedPhones = collect();
        $repurchasedNames = collect();

        if ($excludeSubsequentOrders) {
            $subsequentSales = EcommerceSale::whereNotIn('channel', self::NON_REVENUE_CHANNELS)
                ->where('created_at', '>', $windowEnd)
                ->get(['customer_phone', 'customer_name']);

            $repurchasedPhones = $subsequentSales->pluck('customer_phone')
                ->map($normalizePhone)
                ->filter()
                ->unique()
                ->flip();

            $repurchasedNames = $subsequentSales->pluck('customer_name')
                ->map($normalizeName)
                ->filter()
                ->unique()
                ->flip();
        }

        return $dueSales
            ->groupBy(function (EcommerceSale $sale) use ($normalizePhone, $normalizeName) {
                $phone = $normalizePhone($sale->customer_phone);
                if ($phone !== '') {
                    return 'phone:' . $phone;
                }
                $name = $normalizeName($sale->customer_name);
                if ($name !== '') {
                    return 'name:' . $name;
                }
                return 'sale:' . ($sale->reference_id ?: $sale->id);
            })
            ->reject(function ($group) use ($excludeSubsequentOrders, $normalizePhone, $normalizeName, $repurchasedPhones, $repurchasedNames) {
                if (!$excludeSubsequentOrders) {
                    return false;
                }
                $latest = $group->first();
                $phone = $normalizePhone($latest->customer_phone);
                if ($phone !== '' && $repurchasedPhones->has($phone)) {
                    return true;
                }
                $name = $normalizeName($latest->customer_name);
                if ($phone === '' && $name !== '' && $repurchasedNames->has($name)) {
                    return true;
                }
                return false;
            })
            ->map(function ($group) use ($normalizePhone, $stageType) {
                $latest = $group->first();
                $phone = $normalizePhone($latest->customer_phone);
                $customerName = trim((string) $latest->customer_name) !== '' ? trim((string) $latest->customer_name) : 'there';
                $daysSince = (int) $latest->created_at->startOfDay()->diffInDays(now()->startOfDay());
                $products = $group->map(fn (EcommerceSale $s) => $s->product->name ?? 'product')
                    ->unique()->values();
                $productsList = $products->implode(', ');

                $waText = match ($stageType) {
                    'cross_sell' => "Hi {$customerName}! How is your {$productsList} treating you so far? Since you grabbed it {$daysSince} days ago, here is an exclusive 20% OFF bundle-completion offer (code: COMPLETE20) to pair it with the rest of the Laleen routine. Want us to dispatch your companion kit today?",
                    'win_back' => "Hi {$customerName}! It has been {$daysSince} days since your last Laleen order ({$productsList}) and we want to win you back! For the next 24 hours, claim our full Grand Slam Hair Transformation Kit at an exclusive VIP price — 100% risk-free with our money-back guarantee. Reply YES to lock in your kit before this window closes!",
                    default => "Hi {$customerName}! It has been {$daysSince} days since your {$productsList} from Laleen. Most customers run out right about now — want us to send your fresh refill out today?",
                };

                $waLink = ($phone !== '' ? 'https://wa.me/' . $phone : 'https://wa.me/')
                    . '?text=' . rawurlencode($waText);

                return [
                    'phone' => $phone,
                    'display_phone' => $latest->customer_phone ?: 'No phone logged',
                    'customer_name' => $customerName,
                    'last_purchase_at' => $latest->created_at,
                    'days_since' => $daysSince,
                    'products' => $products,
                    'channel' => $latest->channel,
                    'order_count' => $group->count(),
                    'total_spent' => (float) $group->sum('total_price'),
                    'wa_text' => $waText,
                    'wa_link' => $waLink,
                ];
            })
            ->sortByDesc('days_since')
            ->values();
    }

    /**
     * Salon Backbar-to-Retail conversion: for every unit a stylist consumes at
     * the wash basin, how many units does the front desk actually sell?
     *
     * Note the data shape — backbar consumption is logged against a single
     * 'Backbar Use (Qatar)' channel with no branch, while retail is split per
     * branch. So the ratio is only meaningful per product (and salon-wide),
     * and the branch columns are a retail-side split, not a per-branch ratio.
     */
    private function backbarConversion(): array
    {
        $rows = EcommerceSale::with('product')
            ->whereIn('channel', array_merge(self::BACKBAR_CHANNELS, self::SALON_RETAIL_CHANNELS))
            ->get();

        $byProduct = $rows->groupBy('ecommerce_product_id')->map(function ($group) {
            $backbar = (int) $group->whereIn('channel', self::BACKBAR_CHANNELS)->sum('quantity');
            $retail = (int) $group->whereIn('channel', self::SALON_RETAIL_CHANNELS)->sum('quantity');

            return [
                'product' => $group->first()->product,
                'backbar_units' => $backbar,
                'retail_units' => $retail,
                'old_airport_units' => (int) $group->where('channel', 'Salon (Old Airport)')->sum('quantity'),
                'wakrah_units' => (int) $group->where('channel', 'Salon (Wakrah)')->sum('quantity'),
                'retail_revenue' => (float) $group->whereIn('channel', self::SALON_RETAIL_CHANNELS)->sum('total_price'),
                'ratio' => $backbar > 0 ? ($retail / $backbar) * 100 : null,
            ];
        })->sortByDesc('backbar_units')->values();

        $totalBackbar = (int) $rows->whereIn('channel', self::BACKBAR_CHANNELS)->sum('quantity');
        $totalRetail = (int) $rows->whereIn('channel', self::SALON_RETAIL_CHANNELS)->sum('quantity');

        return [
            'by_product' => $byProduct,
            'total_backbar_units' => $totalBackbar,
            'total_retail_units' => $totalRetail,
            'total_retail_revenue' => (float) $rows->whereIn('channel', self::SALON_RETAIL_CHANNELS)->sum('total_price'),
            'efficiency' => $totalBackbar > 0 ? ($totalRetail / $totalBackbar) * 100 : null,
        ];
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
            'reference_id' => 'nullable|string|max:255',
            'customer_name' => 'nullable|string|max:255',
            'customer_phone' => 'nullable|string|max:40',
            'quantity' => 'required|integer|min:1',
            'channel' => 'required|in:' . implode(',', self::CHANNELS),
            'reason' => 'nullable|string|max:255',
            // Revenue channels must carry a price. This was previously enforced
            // only by client-side JS, so a direct POST without it blew up on an
            // undefined array key instead of returning a validation error.
            // Bundle sales price themselves from the bundle record.
            'unit_price' => [
                Rule::requiredIf(fn () => !$isBundleSale && !in_array($request->input('channel'), self::NON_REVENUE_CHANNELS, true)),
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
        $isNonRevenue = in_array($validated['channel'], self::NON_REVENUE_CHANNELS, true);
        // Non-revenue channels are always free; revenue channels are guaranteed
        // a price by the rule above, but still default defensively.
        $unitPrice = $isNonRevenue ? 0.0 : (float) ($validated['unit_price'] ?? 0);
        $totalPrice = $quantity * $unitPrice;

        $isPakistan = str_contains($validated['channel'], 'Pakistan');
        $source = $isPakistan ? 'pakistan' : 'qatar';
        $stockField = "stock_{$source}";
        $soldField = "sold_{$source}";
        $locationName = $isPakistan ? 'Pakistan' : 'Qatar';

        $product = EcommerceProduct::findOrFail($validated['ecommerce_product_id']);

        // Snapshot the product's current BOM cost per unit — both the total
        // (Liquid + Bottle + Label + Box + Pump) and each component — onto
        // the sale itself, so Net Profit's itemized COGS breakdown and
        // margin figures for this transaction stay accurate even if the
        // Pricing & Profit model is edited later. No saved pricing model yet
        // means every component snapshots as PKR 0.
        $pricingModel = EcommercePricingModel::where('ecommerce_product_id', $product->id)->first();
        $unitCogs = $pricingModel?->bomUnitCost() ?? 0.0;
        $unitLiquidCost = (float) ($pricingModel->liquid_cost ?? 0);
        $unitBottleCost = (float) ($pricingModel->bottle_cost ?? 0);
        $unitLabelCost = (float) ($pricingModel->label_cost ?? 0);
        $unitPumpCost = (float) ($pricingModel->pump_cost ?? 0);
        $unitOuterBoxCost = (float) ($pricingModel->box_cost ?? 0);

        try {
            DB::transaction(function () use (
                $validated,
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
                // Lock the product first, then check stock against the locked
                // row. Checking before the lock let two concurrent sales both
                // clear the same balance and oversell into negative stock.
                $product = EcommerceProduct::lockForUpdate()->findOrFail($validated['ecommerce_product_id']);

                if ((float) $product->{$stockField} < $quantity) {
                    throw new RuntimeException("Insufficient stock in {$locationName} for this sale. Requested: " . number_format($quantity, 2) . ', Available: ' . number_format((float) $product->{$stockField}, 2) . '.');
                }

                EcommerceSale::create([
                    'ecommerce_product_id' => $validated['ecommerce_product_id'],
                    'reference_id' => $validated['reference_id'] ?? null,
                    'customer_name' => $validated['customer_name'] ?? null,
                    'customer_phone' => $validated['customer_phone'] ?? null,
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

    /**
     * A bundle sale is written as one EcommerceSale row PER CHILD PRODUCT.
     *
     * That keeps every downstream calculation — stock decrements, the
     * itemized COGS snapshot, Net Profit's rollup, channel filters — working
     * exactly as it does for a single-product sale, with no special-casing
     * anywhere else in the module. The rows are tagged with
     * ecommerce_bundle_id so the UI can group them back into one order.
     *
     * Revenue is the bundle price split across the children in proportion to
     * their standalone value; COGS is each child's own real BOM cost.
     */
    private function storeBundleSale(array $validated)
    {
        $bundleQuantity = (int) $validated['quantity'];
        $channel = $validated['channel'];
        $isNonRevenue = in_array($channel, self::NON_REVENUE_CHANNELS, true);

        $isPakistan = str_contains($channel, 'Pakistan');
        $stockField = $isPakistan ? 'stock_pakistan' : 'stock_qatar';
        $soldField = $isPakistan ? 'sold_pakistan' : 'sold_qatar';
        $locationName = $isPakistan ? 'Pakistan' : 'Qatar';

        $bundle = EcommerceBundle::with(['items.product.pricingModel'])->findOrFail($validated['ecommerce_bundle_id']);

        if ($bundle->items->isEmpty()) {
            return back()->withErrors(['error' => "Bundle \"{$bundle->name}\" has no products in it yet, so it cannot be sold."]);
        }

        // Non-revenue channels (backbar/damage) give the bundle away at zero.
        $allocation = $isNonRevenue
            ? $bundle->items->mapWithKeys(fn ($item) => [$item->id => 0.0])->all()
            : $bundle->allocateRevenue($bundleQuantity);

        // Order-level costs belong to the order, not each line — park them all
        // on the first row so Net Profit never counts them more than once.
        $shippingCost = (float) ($validated['shipping_cost'] ?? 0);
        $taxAmount = (float) ($validated['tax_amount'] ?? 0);
        $metaAdAllocation = (float) ($validated['meta_ad_allocation'] ?? 0);

        try {
            DB::transaction(function () use (
                $validated,
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

                // Lock every component up front (ordered by id to keep a
                // consistent lock sequence between concurrent bundle sales),
                // then validate the whole basket before writing anything.
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

                $isFirstLine = true;

                foreach ($bundle->items as $item) {
                    $product = $lockedProducts->get($item->ecommerce_product_id);
                    $unitsSold = (int) $item->quantity * $bundleQuantity;
                    $lineRevenue = (float) ($allocation[$item->id] ?? 0);

                    // Each child keeps its OWN real BOM snapshot — only the
                    // revenue is apportioned, never the cost.
                    $pricingModel = $product->pricingModel;

                    EcommerceSale::create([
                        'ecommerce_product_id' => $product->id,
                        'ecommerce_bundle_id' => $bundle->id,
                        'reference_id' => $validated['reference_id'] ?? null,
                        'customer_name' => $validated['customer_name'] ?? null,
                        'customer_phone' => $validated['customer_phone'] ?? null,
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

    public function storeBundle(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'bundle_price' => 'required|numeric|min:0',
            'items' => 'required|array|min:1',
            'items.*.ecommerce_product_id' => 'required|exists:ecommerce_products,id',
            'items.*.quantity' => 'required|integer|min:1',
        ]);

        // The unique(bundle_id, product_id) index would otherwise throw a raw
        // SQL error if the same product is picked on two rows.
        $productIds = collect($validated['items'])->pluck('ecommerce_product_id');
        if ($productIds->count() !== $productIds->unique()->count()) {
            return back()->withErrors(['error' => 'Each product can only appear once in a bundle — increase its quantity instead.']);
        }

        DB::transaction(function () use ($validated) {
            $bundle = EcommerceBundle::create([
                'name' => $validated['name'],
                'bundle_price' => $validated['bundle_price'],
            ]);

            foreach ($validated['items'] as $item) {
                $bundle->items()->create([
                    'ecommerce_product_id' => $item['ecommerce_product_id'],
                    'quantity' => $item['quantity'],
                ]);
            }
        });

        return back()->with('success', 'Bundle created.');
    }

    public function destroyBundle(EcommerceBundle $bundle)
    {
        if ($bundle->sales()->exists()) {
            return back()->withErrors(['error' => "Cannot delete \"{$bundle->name}\" because it has recorded sales. Deleting it would orphan historical revenue."]);
        }

        $bundle->delete();

        return back()->with('success', 'Bundle deleted.');
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

            $sale->delete();
        });

        return back()->with('success', 'Sale deleted and inventory restored.');
    }
}
