<?php

namespace App\Http\Controllers\Ecommerce;

use App\Http\Controllers\Controller;
use App\Models\EcommerceContentCalendar;
use App\Models\EcommerceCustomer;
use App\Models\EcommerceExpense;
use App\Models\EcommerceKpi;
use App\Models\EcommerceMonthlyAd;
use App\Models\EcommerceSale;
use App\Models\PartnerTransaction;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class EcommerceController extends Controller
{
    /**
     * Top-level 2x2 workstation card menu. Supports ?region=pk (PKR) vs ?region=qa (QAR).
     */
    public function hub(Request $request)
    {
        $region = EcommerceSale::normalizeRegion($request->query('region', 'pk'));
        $currency = EcommerceSale::currencyForRegion($region);
        $regionChannels = EcommerceSale::channelsForRegion($region);

        $capitalPool = (float) PartnerTransaction::where('type', 'injection')
            ->where('currency', $currency)
            ->sum('amount');
        $partnerFundedExpenses = (float) EcommerceExpense::where('funding_source', 'partner_ledger')
            ->where('currency', $currency)
            ->sum('amount');

        $contentSlots = EcommerceContentCalendar::count() ?: 7;
        $contentLive = EcommerceContentCalendar::where(function ($q) {
            $q->where('is_reel_posted', true)
                ->orWhere('are_stories_posted', true)
                ->orWhere('status', 'Live on Shopify/Meta');
        })->count();
        $inFlightAdsCount = EcommerceMonthlyAd::forRegion($region)
            ->whereIn('phase', EcommerceMonthlyAd::BOARD_PHASES)
            ->count();

        $totalCustomersCount = EcommerceCustomer::forRegion($region)->count();

        $revenueSales = EcommerceSale::realizedRevenue()
            ->whereIn('channel', $regionChannels)
            ->get(['id', 'order_group_id', 'reference_id', 'ecommerce_bundle_id', 'created_at', 'total_price']);

        $totalSalesCount = EcommerceSale::countDistinctOrders($revenueSales);
        $totalSalesRevenue = (float) $revenueSales->sum('total_price');
        $aov = $totalSalesCount > 0 ? ($totalSalesRevenue / $totalSalesCount) : 0.0;

        if ($aov <= 0) {
            $kpiRevenue = (float) EcommerceKpi::where('currency', $currency)->sum('total_revenue');
            $kpiOrders = (int) EcommerceKpi::where('currency', $currency)->sum('total_orders');
            $aov = $kpiOrders > 0 ? ($kpiRevenue / $kpiOrders) : 0.0;
        }

        return view('ecommerce.hub', [
            'region' => $region,
            'currency' => $currency,
            'runway' => $capitalPool - $partnerFundedExpenses,
            'totalCustomersCount' => $totalCustomersCount,
            'totalSalesCount' => $totalSalesCount,
            'contentSlots' => $contentSlots,
            'contentLive' => $contentLive,
            'liveSlotsCount' => $contentLive,
            'adsInFlight' => $inFlightAdsCount,
            'inFlightAdsCount' => $inFlightAdsCount,
            'aov' => $aov,
        ]);
    }

    public function financials()
    {
        return redirect()->route('ecommerce.partners.index');
    }

    public function marketing(Request $request)
    {
        $region = EcommerceSale::normalizeRegion($request->query('region', 'pk'));
        $contentLive = EcommerceContentCalendar::where(function ($q) {
            $q->where('is_reel_posted', true)
                ->orWhere('are_stories_posted', true)
                ->orWhere('status', 'Live on Shopify/Meta');
        })->count();

        return view('ecommerce.marketing.hub', [
            'region' => $region,
            'adsInFlight' => EcommerceMonthlyAd::forRegion($region)->whereIn('phase', EcommerceMonthlyAd::BOARD_PHASES)->count(),
            'adsScaling' => EcommerceMonthlyAd::forRegion($region)->where('phase', 'Scaling')->count(),
            'contentSlots' => EcommerceContentCalendar::count(),
            'contentLive' => $contentLive,
        ]);
    }

    /**
     * Performance & KPIs — strictly separated by region/currency (?region=pk [PKR] vs ?region=qa [QAR]).
     */
    public function dashboard(Request $request)
    {
        $region = EcommerceSale::normalizeRegion($request->query('region', 'pk'));
        $currency = EcommerceSale::currencyForRegion($region);
        $regionChannels = EcommerceSale::channelsForRegion($region);

        $from = $request->filled('start_date')
            ? Carbon::parse($request->start_date)->startOfDay()
            : now()->startOfMonth();
        $to = $request->filled('end_date')
            ? Carbon::parse($request->end_date)->startOfDay()
            : now()->startOfDay();

        if ($from->gt($to)) {
            [$from, $to] = [$to->copy(), $from->copy()];
        }

        $rangeStart = $from->copy()->startOfDay();
        $rangeEnd = $to->copy()->endOfDay();

        $kpiRows = EcommerceKpi::where('currency', $currency)
            ->whereBetween('date', [$rangeStart, $rangeEnd])
            ->orderByDesc('date')
            ->get();

        // 1. Fetch all sales in range for this region, separating Realized vs Pending COD
        $allSalesInRange = EcommerceSale::whereIn('channel', $regionChannels)
            ->whereBetween('created_at', [$rangeStart, $rangeEnd])
            ->get();

        $realizedSalesInRange = $allSalesInRange->filter(fn (EcommerceSale $s) => $s->isRealizedRevenue());
        $pendingCodSalesInRange = $allSalesInRange->filter(fn (EcommerceSale $s) => $s->isPendingCod());

        $totalRevenue = (float) $realizedSalesInRange->sum('total_price');
        $totalOrders = EcommerceSale::countDistinctOrders($realizedSalesInRange);
        $aov = $totalOrders > 0 ? ($totalRevenue / $totalOrders) : 0.0;

        $pendingCodRevenue = (float) $pendingCodSalesInRange->sum('total_price');
        $pendingCodOrders = EcommerceSale::countDistinctOrders($pendingCodSalesInRange);

        // 2. Historical Cohort Calculation for New vs Repeat Customers in the selected date range & region:
        // Group realized sales up to $rangeEnd by customer identifier (customer_id or normalized phone)
        // and count distinct order_group_id up to $rangeEnd.
        [$newCustomers, $repeatCustomers] = $this->computeCohortCustomerCounts(
            $regionChannels,
            $rangeStart,
            $rangeEnd
        );

        $totalCustomersInRange = $newCustomers + $repeatCustomers;
        $repeatRate = $totalCustomersInRange > 0
            ? ($repeatCustomers / $totalCustomersInRange) * 100
            : 0.0;

        // 3. Calculate ROAS & Cost Per New Customer using logged Ad Spend + dynamic Realized Revenue/Orders
        $totalAdSpend = (float) $kpiRows->sum('total_ad_spend');
        $roas = $totalAdSpend > 0 ? ($totalRevenue / $totalAdSpend) : null;

        $acquisitionDenominator = $newCustomers > 0 ? $newCustomers : $totalOrders;
        $cac = ($totalAdSpend > 0 && $acquisitionDenominator > 0)
            ? ($totalAdSpend / $acquisitionDenominator)
            : null;

        // Enrich daily ad spend rows with live per-day realized revenue & order counts
        $salesByDate = $realizedSalesInRange->groupBy(fn (EcommerceSale $s) => $s->created_at->toDateString());
        foreach ($kpiRows as $row) {
            $dateKey = $row->date->toDateString();
            $daySales = $salesByDate->get($dateKey, collect());
            if ($daySales->isNotEmpty() || (float) $row->total_revenue == 0.0) {
                $row->total_revenue = (float) $daySales->sum('total_price');
                $row->total_orders = EcommerceSale::countDistinctOrders($daySales);
            }
        }

        $metrics = [
            'has_data' => $kpiRows->isNotEmpty() || $totalOrders > 0 || $totalCustomersInRange > 0,
            'has_ad_spend' => $kpiRows->isNotEmpty(),
            'day_count' => $kpiRows->count(),
            'total_revenue' => $totalRevenue,
            'pending_cod_revenue' => $pendingCodRevenue,
            'pending_cod_orders' => $pendingCodOrders,
            'total_ad_spend' => $totalAdSpend,
            'total_orders' => $totalOrders,
            'new_customers' => $newCustomers,
            'repeat_customers' => $repeatCustomers,

            'mer' => $roas,
            'cac' => $cac,
            'aov' => $aov,
            'repeat_rate' => $repeatRate,

            'mer_healthy' => $roas === null ? null : $roas >= EcommerceKpi::MER_TARGET,
            'cac_healthy' => ($cac === null || $aov <= 0) ? null : $cac < $aov,
            'aov_healthy' => null,
            'repeat_rate_healthy' => $repeatRate >= EcommerceKpi::REPEAT_RATE_TARGET,
        ];

        return view('ecommerce.dashboard', [
            'region' => $region,
            'currency' => $currency,
            'metrics' => $metrics,
            'kpiRows' => $kpiRows,
            'merTarget' => EcommerceKpi::MER_TARGET,
            'repeatRateTarget' => EcommerceKpi::REPEAT_RATE_TARGET,
            'startDate' => $request->input('start_date'),
            'endDate' => $request->input('end_date'),
            'filterText' => ($request->filled('start_date') || $request->filled('end_date'))
                ? 'From ' . $from->format('M d, Y') . ' to ' . $to->format('M d, Y')
                : 'This month to date',
        ]);
    }

    /**
     * Compute [$newCustomers, $repeatCustomers] active during [$rangeStart, $rangeEnd]
     * based on their cumulative realized order count (COUNT DISTINCT order_group_id) up to $rangeEnd.
     */
    private function computeCohortCustomerCounts(array $regionChannels, Carbon $rangeStart, Carbon $rangeEnd): array
    {
        $salesUpToEnd = EcommerceSale::realizedRevenue()
            ->whereIn('channel', $regionChannels)
            ->where('created_at', '<=', $rangeEnd)
            ->get(['id', 'ecommerce_customer_id', 'customer_phone', 'order_group_id', 'reference_id', 'ecommerce_bundle_id', 'created_at']);

        if ($salesUpToEnd->isEmpty()) {
            return [0, 0];
        }

        $groupedByCustomer = $salesUpToEnd->groupBy(function (EcommerceSale $sale) {
            if ($sale->ecommerce_customer_id) {
                return 'cid:' . $sale->ecommerce_customer_id;
            }
            $phone = EcommerceCustomer::normalizePhone($sale->customer_phone);
            if ($phone !== '') {
                return 'phone:' . $phone;
            }

            return 'order:' . $sale->resolvedOrderGroupKey();
        });

        $newCustomers = 0;
        $repeatCustomers = 0;

        foreach ($groupedByCustomer as $customerSales) {
            $activeInRange = $customerSales->contains(
                fn (EcommerceSale $s) => $s->created_at && $s->created_at->betweenIncluded($rangeStart, $rangeEnd)
            );

            if (!$activeInRange) {
                continue;
            }

            $ordersUpToPeriodEnd = EcommerceSale::countDistinctOrders($customerSales);
            if ($ordersUpToPeriodEnd >= 2) {
                $repeatCustomers++;
            } else {
                $newCustomers++;
            }
        }

        return [$newCustomers, $repeatCustomers];
    }

    public function storeKpi(Request $request)
    {
        $validated = $this->validatedKpi($request);
        $currency = strtoupper((string) ($validated['currency'] ?? 'PKR'));
        $region = $currency === 'QAR' ? 'qa' : 'pk';
        $regionChannels = EcommerceSale::channelsForRegion($region);

        $date = Carbon::parse($validated['date'])->startOfDay();
        $dayEnd = $date->copy()->endOfDay();

        $daySales = EcommerceSale::realizedRevenue()
            ->whereIn('channel', $regionChannels)
            ->whereBetween('created_at', [$date, $dayEnd])
            ->get();

        $dayRevenue = (float) $daySales->sum('total_price');
        $dayOrders = EcommerceSale::countDistinctOrders($daySales);

        [$newCustomers, $repeatCustomers] = $this->computeCohortCustomerCounts($regionChannels, $date, $dayEnd);

        EcommerceKpi::updateOrCreate(
            ['date' => $date, 'currency' => $currency],
            [
                'date' => $date,
                'currency' => $currency,
                'total_ad_spend' => $validated['total_ad_spend'],
                'total_revenue' => $dayRevenue,
                'total_orders' => $dayOrders,
                'new_customers' => $newCustomers,
                'repeat_customers' => $repeatCustomers,
            ]
        );

        return back()->with('success', "Daily {$currency} ad spend saved.");
    }

    public function destroyKpi(EcommerceKpi $kpi)
    {
        $kpi->delete();

        return back()->with('success', 'Ad spend entry deleted.');
    }

    private function validatedKpi(Request $request): array
    {
        return $request->validate([
            'date' => 'required|date',
            'currency' => ['nullable', Rule::in(['PKR', 'QAR'])],
            'total_ad_spend' => 'required|numeric|min:0',
        ]);
    }
}
