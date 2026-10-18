<?php

namespace App\Http\Controllers\Ecommerce;

use App\Http\Controllers\Controller;
use App\Models\EcommerceCustomer;
use App\Models\EcommerceSale;
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
        $search = trim((string) $request->input('search', ''));
        $channel = trim((string) $request->input('channel', 'all'));
        $segment = trim((string) $request->input('segment', $request->input('tier', 'all')));

        $query = EcommerceCustomer::with(['sales' => function ($q) {
            $q->with('product')->orderByDesc('created_at')->orderByDesc('id');
        }])
            ->orderByDesc('total_spend')
            ->orderByDesc('last_purchase_at');

        if ($search !== '') {
            $normalizedPhone = EcommerceCustomer::normalizePhone($search);
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
                $totalCustomerCount = EcommerceCustomer::count();
                if ($totalCustomerCount > 0) {
                    $top20Limit = max(1, (int) ceil($totalCustomerCount * 0.20));
                    $whaleIds = EcommerceCustomer::orderByDesc('total_spend')
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
            $totalCustomers = EcommerceCustomer::whereHas('sales', function ($q) use ($channel) {
                $q->where('channel', $channel);
            })->count();

            $totalRevenue = (float) EcommerceSale::where('channel', $channel)->sum('total_price');
        } else {
            $totalCustomers = EcommerceCustomer::count();
            $totalRevenue = (float) EcommerceCustomer::sum('total_spend');
        }

        return view('ecommerce.customers.index', [
            'customers' => $customers,
            'search' => $search,
            'channel' => $channel,
            'filterableChannels' => self::FILTERABLE_CHANNELS,
            'segment' => $segment,
            'totalCustomers' => $totalCustomers,
            'totalRevenue' => $totalRevenue,
            'totalCrossChannelLtv' => $totalRevenue,
            'lifecyclePipeline' => $this->lifecyclePipeline(),
        ]);
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

    private function buildLifecycleStage(
        int $startDaysAgo,
        int $endDaysAgo,
        bool $excludeSubsequentOrders,
        string $stageType
    ) {
        $windowStart = now()->subDays($endDaysAgo)->startOfDay();
        $windowEnd = now()->subDays($startDaysAgo)->endOfDay();

        $dueSales = EcommerceSale::with('product')
            ->whereNotIn('channel', EcommerceSale::NON_REVENUE_CHANNELS)
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
            $subsequentSales = EcommerceSale::whereNotIn('channel', EcommerceSale::NON_REVENUE_CHANNELS)
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

    public function searchSuggestions(Request $request): JsonResponse
    {
        $q = trim((string) $request->query('q', ''));

        if ($q === '') {
            return response()->json([]);
        }

        $normalizedPhone = EcommerceCustomer::normalizePhone($q);

        $matches = EcommerceCustomer::query()
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

        $productBreakdown = $customer->sales
            ->groupBy(fn ($sale) => $sale->product->name ?? 'Deleted Product')
            ->map(function ($group, $productName) {
                return [
                    'product_name' => $productName,
                    'sku' => $group->first()?->product?->sku,
                    'total_units' => (int) $group->sum('quantity'),
                    'orders_count' => $group->count(),
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
