<?php

namespace App\Http\Controllers\Ecommerce;

use App\Http\Controllers\Controller;
use App\Models\EcommerceCustomer;
use App\Models\EcommerceLifecycleFlow;
use App\Models\EcommerceLifecycleLog;
use App\Models\EcommerceSale;
use App\Models\EcommerceSeoArticle;
use App\Services\EcommerceRetentionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EcommerceCustomerController extends Controller
{
    public const FILTERABLE_CHANNELS = EcommerceSale::REVENUE_CHANNELS;

    /** Customer Lifecycle Pipeline stage windows (days since purchase). */
    public const CROSS_SELL_START_DAYS = 6;
    public const CROSS_SELL_END_DAYS = 8;
    public const REFILL_WINDOW_START_DAYS = 25;
    public const REFILL_WINDOW_END_DAYS = 35;
    public const WIN_BACK_START_DAYS = 60;
    public const WIN_BACK_END_DAYS = 70;

    public function index(Request $request)
    {
        $region = EcommerceSale::normalizeRegion($request->query('region', 'pk'));
        $currency = EcommerceSale::currencyForRegion($region);
        $regionChannels = EcommerceSale::revenueChannelsForRegion($region);

        $search = trim((string) $request->input('search', ''));
        $channel = trim((string) $request->input('channel', 'all'));
        $segment = trim((string) $request->input('segment', $request->input('tier', 'all')));

        $query = EcommerceCustomer::forRegion($region)
            ->with(['sales' => function ($q) use ($regionChannels) {
                $q->with('product')
                    ->whereIn('channel', $regionChannels)
                    ->orderByDesc('created_at')
                    ->orderByDesc('id');
            }])
            ->orderByDesc('total_spend')
            ->orderByDesc('last_purchase_at');

        if ($search !== '') {
            $normalizedPhone = EcommerceCustomer::normalizePhone($search, $region === 'qa' ? '974' : '92');
            $query->where(function ($q) use ($search, $normalizedPhone) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%");
                if ($normalizedPhone !== '') {
                    $q->orWhere('phone', 'like', "%{$normalizedPhone}%");
                }
            });
        }

        $isSpecificChannel = $channel !== '' && $channel !== 'all';

        if ($isSpecificChannel) {
            $query->whereHas('sales', function ($q) use ($channel) {
                $q->where('channel', $channel);
            });
        }

        if ($segment !== '' && $segment !== 'all') {
            if (in_array($segment, ['whales', 'top_20', 'Top 20% LTV', 'VIP Whales (Top 20% LTV)'], true)) {
                $totalCustomerCount = EcommerceCustomer::forRegion($region)->count();
                if ($totalCustomerCount > 0) {
                    $top20Limit = max(1, (int) ceil($totalCustomerCount * 0.20));
                    $whaleIds = EcommerceCustomer::forRegion($region)
                        ->orderByDesc('total_spend')
                        ->orderByDesc('total_orders')
                        ->limit($top20Limit)
                        ->pluck('id');
                    $query->whereIn('id', $whaleIds);
                }
            } elseif (in_array($segment, ['repeat', 'vip', 'Repeat Buyers (2+ Orders)'], true)) {
                $query->where('total_orders', '>=', 2);
            } elseif (in_array($segment, ['one_time', 'single', 'One-Time Buyers', 'One-Time Buyers (Needs Reactivation)'], true)) {
                $query->where('total_orders', '=', 1);
            } elseif (in_array($segment, ['at_risk', 'At-Risk (Last purchase 30-45 days ago)'], true)) {
                $riskStart = now()->subDays(45)->startOfDay();
                $riskEnd = now()->subDays(30)->endOfDay();
                $query->whereBetween('last_purchase_at', [$riskStart, $riskEnd]);
            }
        }

        $customers = $query->get();

        if ($isSpecificChannel) {
            $totalCustomers = EcommerceCustomer::forRegion($region)
                ->whereHas('sales', function ($q) use ($channel) {
                    $q->realizedRevenue()->where('channel', $channel);
                })->count();

            $totalRevenue = (float) EcommerceSale::realizedRevenue()
                ->where('channel', $channel)
                ->sum('total_price');
            $activeCurrency = EcommerceSale::currencyForChannel($channel);
        } else {
            $totalCustomers = EcommerceCustomer::forRegion($region)->count();
            $totalRevenue = (float) EcommerceSale::realizedRevenue()
                ->whereIn('channel', $regionChannels)
                ->sum('total_price');
            $activeCurrency = $currency;
        }

        $regionUpper = strtoupper($region);
        /** @var EcommerceRetentionService $retentionService */
        $retentionService = app(EcommerceRetentionService::class);
        $lifecycleFlows = $retentionService->ensureDefaultFlows($regionUpper);

        $lifecycleClassifications = [];
        foreach ($customers as $c) {
            $lifecycleClassifications[$c->id] = $retentionService->classifyCustomerLifecycle($c);
        }

        $seoArticles = EcommerceSeoArticle::where('region', $regionUpper)
            ->orderByDesc('id')
            ->get();

        $lifecycleLogs = EcommerceLifecycleLog::where('region', $regionUpper)
            ->orderByDesc('sent_at')
            ->orderByDesc('id')
            ->limit(50)
            ->get();

        return view('ecommerce.customers.index', [
            'region' => $region,
            'regionUpper' => $regionUpper,
            'currency' => $activeCurrency,
            'customers' => $customers,
            'search' => $search,
            'channel' => $channel,
            'filterableChannels' => $regionChannels,
            'segment' => $segment,
            'totalCustomers' => $totalCustomers,
            'totalRevenue' => $totalRevenue,
            'totalCrossChannelLtv' => $totalRevenue,
            'lifecyclePipeline' => $this->lifecyclePipeline($region),
            'lifecycleFlows' => $lifecycleFlows,
            'lifecycleClassifications' => $lifecycleClassifications,
            'seoArticles' => $seoArticles,
            'lifecycleLogs' => $lifecycleLogs,
            'logs' => $lifecycleLogs,
        ]);
    }

    /**
     * Toggle an automated Email + WhatsApp lifecycle flow on/off.
     */
    public function toggleFlow(Request $request, ?EcommerceLifecycleFlow $flow = null)
    {
        /** @var EcommerceRetentionService $retentionService */
        $retentionService = app(EcommerceRetentionService::class);
        $flowId = $flow?->id ?: $request->input('flow_id');
        $updated = $retentionService->toggleFlow($flowId);

        $region = strtolower($request->input('region', $updated->region));

        return redirect()
            ->route('ecommerce.customers.index', ['region' => $region])
            ->with('success', "Lifecycle flow '{$updated->name}' is now " . ($updated->is_enabled ? 'Enabled' : 'Paused') . '.');
    }

    /**
     * Run all due automated Email + WhatsApp lifecycle flows for the active region.
     */
    public function runDueFlows(Request $request)
    {
        $region = strtoupper((string) $request->input('region', 'PK'));
        /** @var EcommerceRetentionService $retentionService */
        $retentionService = app(EcommerceRetentionService::class);
        $logs = $retentionService->runDueLifecycleFlows($region);

        return redirect()
            ->route('ecommerce.customers.index', ['region' => strtolower($region)])
            ->with('success', "Dispatched {$logs->count()} lifecycle Email & WhatsApp message(s) for {$region}.");
    }

    /**
     * 1-Click Send Single Customer Refill / Follow-Up via Email or WhatsApp.
     */
    public function sendLifecyclePing(Request $request, ?EcommerceCustomer $customer = null)
    {
        $customerId = $customer?->id ?: (int) $request->input('customer_id');
        $channel = (string) $request->input('channel', 'whatsapp');
        $flowKey = (string) $request->input('flow_key', EcommerceRetentionService::FLOW_REFILL_30D_REORDER);
        $region = (string) $request->input('region', 'pk');

        /** @var EcommerceRetentionService $retentionService */
        $retentionService = app(EcommerceRetentionService::class);
        $log = $retentionService->sendCustomerLifecyclePing($customerId, $channel, $flowKey, $region);

        return redirect()
            ->route('ecommerce.customers.index', ['region' => strtolower($log->region)])
            ->with('success', "Sent {$log->channel} refill message to {$log->customer_name}.");
    }

    /**
     * 1-Click Send SEO Blog Article as an Email Newsletter to all customers in the region.
     */
    public function sendBlogNewsletter(Request $request)
    {
        $region = strtoupper((string) $request->input('region', 'PK'));
        $articleId = (int) $request->input('article_id');

        if (!$articleId) {
            $firstArticle = EcommerceSeoArticle::where('region', $region)->first();
            $articleId = (int) ($firstArticle?->id ?? 0);
        }

        /** @var EcommerceRetentionService $retentionService */
        $retentionService = app(EcommerceRetentionService::class);
        $logs = $retentionService->sendBlogNewsletterBroadcast($articleId, $region);

        return redirect()
            ->route('ecommerce.customers.index', ['region' => strtolower($region)])
            ->with('success', "Sent SEO Blog Newsletter to {$logs->count()} subscriber(s) in {$region}.");
    }

    /**
     * Mark a customer as contacted in the retention pipeline.
     * POST /ecommerce/hub/customers/{customer}/mark-contacted
     */
    public function markContacted(Request $request, EcommerceCustomer $customer)
    {
        $stage = trim((string) $request->input('stage', 'manual'));
        $customer->update([
            'last_retention_contacted_at' => now(),
            'last_retention_stage' => $stage !== '' ? $stage : 'manual',
        ]);

        return back()->with('success', ($customer->name ?: 'Customer') . ' marked as contacted.');
    }

    /**
     * 3-Stage Automated Customer Lifecycle Pipeline (LTV Expansion).
     */
    private function lifecyclePipeline(string $region = 'pk'): array
    {
        return [
            'day7' => [
                'key' => 'day7',
                'title' => 'Day 7 · Cross-Sell',
                'window_label' => 'Day ' . self::CROSS_SELL_START_DAYS . '–' . self::CROSS_SELL_END_DAYS,
                'subtitle' => 'Realized revenue sales 6–8 days ago. Hook with a 20% bundle-completion cross-sell.',
                'cta_label' => 'Send 20% Cross-Sell',
                'badge_class' => 'ec-badge-bundle',
                'items' => $this->buildLifecycleStage(
                    self::CROSS_SELL_START_DAYS,
                    self::CROSS_SELL_END_DAYS,
                    false,
                    'cross_sell',
                    $region
                ),
            ],
            'day30' => [
                'key' => 'day30',
                'title' => 'Day 30 · Refill',
                'window_label' => 'Day ' . self::REFILL_WINDOW_START_DAYS . '–' . self::REFILL_WINDOW_END_DAYS,
                'subtitle' => 'Realized revenue sales 25–35 days ago. Remind customers right before their bottle runs dry.',
                'cta_label' => 'Send Refill Nudge',
                'badge_class' => 'ec-badge-injection',
                'items' => $this->buildLifecycleStage(
                    self::REFILL_WINDOW_START_DAYS,
                    self::REFILL_WINDOW_END_DAYS,
                    true,
                    'refill',
                    $region
                ),
            ],
            'day60' => [
                'key' => 'day60',
                'title' => 'Day 60 · Win-Back',
                'window_label' => 'Day ' . self::WIN_BACK_START_DAYS . '–' . self::WIN_BACK_END_DAYS,
                'subtitle' => 'Realized revenue sales 60–70 days ago with no subsequent orders. High-urgency, risk-free Grand Slam kit offer.',
                'cta_label' => 'Send Grand Slam Offer',
                'badge_class' => 'ec-badge-expense-paid',
                'items' => $this->buildLifecycleStage(
                    self::WIN_BACK_START_DAYS,
                    self::WIN_BACK_END_DAYS,
                    true,
                    'win_back',
                    $region
                ),
            ],
        ];
    }

    private function buildLifecycleStage(
        int $startDaysAgo,
        int $endDaysAgo,
        bool $excludeSubsequentOrders,
        string $stageType,
        string $region = 'pk'
    ) {
        $windowStart = now()->subDays($endDaysAgo)->startOfDay();
        $windowEnd = now()->subDays($startDaysAgo)->endOfDay();

        $dueSales = EcommerceSale::with(['product', 'customer'])
            ->realizedRevenue()
            ->forRegion($region)
            ->whereBetween('created_at', [$windowStart, $windowEnd])
            ->orderByDesc('created_at')
            ->get();

        if ($dueSales->isEmpty()) {
            return collect();
        }

        $normalizePhone = fn ($phone) => EcommerceCustomer::normalizePhone($phone);
        $normalizeName = fn ($name) => mb_strtolower(trim((string) $name));

        $repurchasedPhones = collect();
        $repurchasedNames = collect();

        if ($excludeSubsequentOrders) {
            $subsequentSales = EcommerceSale::realizedRevenue()
                ->forRegion($region)
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

                return 'sale:' . $sale->resolvedOrderGroupKey();
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
                /** @var EcommerceSale $latest */
                $latest = $group->first();
                $customer = $latest->customer;
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
                    'customer_id' => $customer?->id,
                    'contacted_recently' => $customer ? $customer->wasRecentlyContacted(7) : false,
                    'last_contacted_at' => $customer?->last_retention_contacted_at,
                    'last_retention_stage' => $customer?->last_retention_stage,
                    'phone' => $phone,
                    'display_phone' => $latest->customer_phone ?: 'No phone logged',
                    'customer_name' => $customerName,
                    'last_purchase_at' => $latest->created_at,
                    'days_since' => $daysSince,
                    'products' => $products,
                    'channel' => $latest->channel,
                    'currency' => EcommerceSale::currencyForChannel($latest->channel),
                    'order_count' => EcommerceSale::countDistinctOrders($group),
                    'total_spent' => (float) $group->sum('total_price'),
                    'wa_text' => $waText,
                    'wa_link' => $waLink,
                    'stage_type' => $stageType,
                ];
            })
            ->sortByDesc('days_since')
            ->values();
    }

    public function searchSuggestions(Request $request): JsonResponse
    {
        $q = trim((string) $request->query('q', ''));
        $region = EcommerceSale::normalizeRegion($request->query('region', session('ecommerce_region', 'pk')));

        if ($q === '') {
            return response()->json([]);
        }

        $normalizedPhone = EcommerceCustomer::normalizePhone($q, $region === 'qa' ? '974' : '92');

        $matches = EcommerceCustomer::forRegion($region)
            ->where(function ($query) use ($q, $normalizedPhone) {
                $query->where('name', 'like', "%{$q}%")
                    ->orWhere('phone', 'like', "%{$q}%");
                if ($normalizedPhone !== '') {
                    $query->orWhere('phone', 'like', "%{$normalizedPhone}%");
                }
            })
            ->orderByDesc('total_spend')
            ->limit(5)
            ->get(['id', 'name', 'phone', 'email', 'total_spend', 'total_orders']);

        return response()->json($matches);
    }

    public function show(EcommerceCustomer $customer)
    {
        $customer->load(['sales' => function ($q) {
            $q->with(['product', 'bundle'])->orderByDesc('created_at')->orderByDesc('id');
        }]);

        $channelsShopped = $customer->sales->pluck('channel')->filter()->unique()->values();
        $realizedSales = $customer->sales->filter(fn (EcommerceSale $s) => $s->isRealizedRevenue());

        $productBreakdown = $realizedSales
            ->groupBy(fn ($sale) => $sale->product->name ?? 'Deleted Product')
            ->map(function ($group, $productName) {
                return [
                    'product_name' => $productName,
                    'sku' => $group->first()?->product?->sku,
                    'total_units' => (int) $group->sum('quantity'),
                    'orders_count' => EcommerceSale::countDistinctOrders($group),
                    'total_spent' => (float) $group->sum('total_price'),
                    'last_bought_at' => $group->max('created_at'),
                ];
            })
            ->sortByDesc('total_spent')
            ->values();

        return view('ecommerce.customers.show', [
            'customer' => $customer,
            'channelsShopped' => $channelsShopped,
            'productBreakdown' => $productBreakdown,
        ]);
    }
}
