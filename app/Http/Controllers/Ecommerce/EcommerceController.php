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

class EcommerceController extends Controller
{
    /**
     * Top-level 2x2 workstation card menu. Each card carries a live headline
     * figure so the hub reports operational state at a glance.
     */
    public function hub()
    {
        $capitalPool = (float) PartnerTransaction::where('type', 'injection')->sum('amount');
        $partnerFundedExpenses = (float) EcommerceExpense::where('funding_source', 'partner_ledger')->sum('amount');

        $contentSlots = EcommerceContentCalendar::count() ?: 7;
        $contentLive = EcommerceContentCalendar::where('status', 'Live on Shopify/Meta')->count();
        $inFlightAdsCount = EcommerceMonthlyAd::whereIn('phase', EcommerceMonthlyAd::BOARD_PHASES)->count();

        $totalCustomersCount = EcommerceCustomer::count();

        $revenueSales = EcommerceSale::whereNotIn('channel', EcommerceSale::NON_REVENUE_CHANNELS)
            ->get(['id', 'reference_id', 'ecommerce_bundle_id', 'created_at', 'total_price']);

        $totalSalesCount = $revenueSales
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

        $totalSalesRevenue = (float) $revenueSales->sum('total_price');
        $aov = $totalSalesCount > 0 ? ($totalSalesRevenue / $totalSalesCount) : 0.0;

        if ($aov <= 0) {
            $kpiRevenue = (float) EcommerceKpi::sum('total_revenue');
            $kpiOrders = (int) EcommerceKpi::sum('total_orders');
            $aov = $kpiOrders > 0 ? ($kpiRevenue / $kpiOrders) : 0.0;
        }

        return view('ecommerce.hub', [
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

    public function marketing()
    {
        return view('ecommerce.marketing.hub', [
            'adsInFlight' => EcommerceMonthlyAd::whereIn('phase', EcommerceMonthlyAd::BOARD_PHASES)->count(),
            'adsScaling' => EcommerceMonthlyAd::where('phase', 'Scaling')->count(),
            'contentSlots' => EcommerceContentCalendar::count(),
            'contentLive' => EcommerceContentCalendar::where('status', 'Live on Shopify/Meta')->count(),
        ]);
    }

    /** Performance & KPIs — automated sales/customer metrics + daily ad spend ROAS/CAC. */
    public function dashboard(Request $request)
    {
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

        $kpiRows = EcommerceKpi::whereBetween('date', [$rangeStart, $rangeEnd])
            ->orderByDesc('date')
            ->get();

        // 1. Dynamically calculate Revenue, Orders, and AOV from ecommerce_sales
        $salesInRange = EcommerceSale::whereNotIn('channel', EcommerceSale::NON_REVENUE_CHANNELS)
            ->whereBetween('created_at', [$rangeStart, $rangeEnd])
            ->get();

        $totalRevenue = (float) $salesInRange->sum('total_price');

        $groupOrders = fn ($sales) => $sales->groupBy(function (EcommerceSale $s) {
            if (!empty($s->reference_id)) {
                return 'ref:' . $s->reference_id;
            }
            if (!empty($s->ecommerce_bundle_id)) {
                return 'bundle:' . $s->ecommerce_bundle_id . ':' . $s->created_at;
            }
            return 'id:' . $s->id;
        });

        $totalOrders = $groupOrders($salesInRange)->count();
        $aov = $totalOrders > 0 ? ($totalRevenue / $totalOrders) : 0.0;

        // 2. Dynamically calculate Repeat Customer Rate & New Customers from ecommerce_customers
        $customersInRange = EcommerceCustomer::where(function ($q) use ($rangeStart, $rangeEnd) {
            $q->whereBetween('last_purchase_at', [$rangeStart, $rangeEnd])
                ->orWhereBetween('created_at', [$rangeStart, $rangeEnd])
                ->orWhereHas('sales', function ($sq) use ($rangeStart, $rangeEnd) {
                    $sq->whereNotIn('channel', EcommerceSale::NON_REVENUE_CHANNELS)
                        ->whereBetween('created_at', [$rangeStart, $rangeEnd]);
                });
        })->get();

        $totalCustomersInRange = $customersInRange->count();
        $repeatCustomers = $customersInRange->where('total_orders', '>=', 2)->count();
        $newCustomers = $customersInRange->where('total_orders', '<=', 1)->count();
        $repeatRate = $totalCustomersInRange > 0
            ? ($repeatCustomers / $totalCustomersInRange) * 100
            : 0.0;

        // 3. Calculate ROAS & Cost Per New Customer using logged Ad Spend + dynamic Revenue/Orders
        $totalAdSpend = (float) $kpiRows->sum('total_ad_spend');
        $roas = $totalAdSpend > 0 ? ($totalRevenue / $totalAdSpend) : null;

        $acquisitionDenominator = $newCustomers > 0 ? $newCustomers : $totalOrders;
        $cac = ($totalAdSpend > 0 && $acquisitionDenominator > 0)
            ? ($totalAdSpend / $acquisitionDenominator)
            : null;

        // Enrich daily ad spend rows with live per-day revenue & order counts
        $salesByDate = $salesInRange->groupBy(fn (EcommerceSale $s) => $s->created_at->toDateString());
        foreach ($kpiRows as $row) {
            $dateKey = $row->date->toDateString();
            $daySales = $salesByDate->get($dateKey, collect());
            if ($daySales->isNotEmpty() || (float) $row->total_revenue == 0.0) {
                $row->total_revenue = (float) $daySales->sum('total_price');
                $row->total_orders = $groupOrders($daySales)->count();
            }
        }

        $metrics = [
            'has_data' => $kpiRows->isNotEmpty() || $totalOrders > 0 || $totalCustomersInRange > 0,
            'has_ad_spend' => $kpiRows->isNotEmpty(),
            'day_count' => $kpiRows->count(),
            'total_revenue' => $totalRevenue,
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

    public function storeKpi(Request $request)
    {
        $validated = $this->validatedKpi($request);
        $date = Carbon::parse($validated['date'])->startOfDay();
        $dayEnd = $date->copy()->endOfDay();

        $daySales = EcommerceSale::whereNotIn('channel', EcommerceSale::NON_REVENUE_CHANNELS)
            ->whereBetween('created_at', [$date, $dayEnd])
            ->get();

        $dayRevenue = (float) $daySales->sum('total_price');
        $dayOrders = $daySales->groupBy(function (EcommerceSale $s) {
            if (!empty($s->reference_id)) {
                return 'ref:' . $s->reference_id;
            }
            if (!empty($s->ecommerce_bundle_id)) {
                return 'bundle:' . $s->ecommerce_bundle_id . ':' . $s->created_at;
            }
            return 'id:' . $s->id;
        })->count();

        $dayCustomers = EcommerceCustomer::where(function ($q) use ($date, $dayEnd) {
            $q->whereBetween('last_purchase_at', [$date, $dayEnd])
                ->orWhereBetween('created_at', [$date, $dayEnd])
                ->orWhereHas('sales', function ($sq) use ($date, $dayEnd) {
                    $sq->whereNotIn('channel', EcommerceSale::NON_REVENUE_CHANNELS)
                        ->whereBetween('created_at', [$date, $dayEnd]);
                });
        })->get();

        EcommerceKpi::updateOrCreate(
            ['date' => $date],
            [
                'date' => $date,
                'total_ad_spend' => $validated['total_ad_spend'],
                'total_revenue' => $dayRevenue,
                'total_orders' => $dayOrders,
                'new_customers' => $dayCustomers->where('total_orders', '<=', 1)->count(),
                'repeat_customers' => $dayCustomers->where('total_orders', '>=', 2)->count(),
            ]
        );

        return back()->with('success', 'Daily ad spend saved.');
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
            'total_ad_spend' => 'required|numeric|min:0',
        ]);
    }
}
