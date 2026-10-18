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

    /** Performance & KPIs — blended acquisition economics + Salon Backbar Conversion. */
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

        $normalizePhone = fn ($phone) => EcommerceCustomer::normalizePhone($phone);

        $priorCustomerPhones = EcommerceSale::whereNotNull('customer_phone')
            ->where('customer_phone', '!=', '')
            ->whereNotIn('channel', EcommerceSale::NON_REVENUE_CHANNELS)
            ->where('created_at', '<', $rangeStart)
            ->pluck('customer_phone')
            ->map($normalizePhone)
            ->filter()
            ->unique()
            ->flip();

        $newCustomerSales = EcommerceSale::whereNotIn('channel', EcommerceSale::NON_REVENUE_CHANNELS)
            ->whereBetween('created_at', [$rangeStart, $rangeEnd])
            ->get()
            ->reject(function (EcommerceSale $sale) use ($normalizePhone, $priorCustomerPhones) {
                $phone = $normalizePhone($sale->customer_phone);
                return $phone !== '' && $priorCustomerPhones->has($phone);
            })
            ->values();

        return view('ecommerce.dashboard', [
            'metrics' => EcommerceKpi::summarize($kpiRows, $newCustomerSales),
            'kpiRows' => $kpiRows,
            'merTarget' => EcommerceKpi::MER_TARGET,
            'repeatRateTarget' => EcommerceKpi::REPEAT_RATE_TARGET,
            'startDate' => $request->input('start_date'),
            'endDate' => $request->input('end_date'),
            'filterText' => ($request->filled('start_date') || $request->filled('end_date'))
                ? 'From ' . $from->format('M d, Y') . ' to ' . $to->format('M d, Y')
                : 'This month to date',
            'backbarConversion' => $this->backbarConversion(),
        ]);
    }

    /**
     * Salon Backbar-to-Retail conversion: for every unit a stylist consumes at
     * the wash basin, how many units does the front desk actually sell?
     */
    private function backbarConversion(): array
    {
        $rows = EcommerceSale::with('product')
            ->whereIn('channel', array_merge(EcommerceSale::BACKBAR_CHANNELS, EcommerceSale::SALON_RETAIL_CHANNELS))
            ->get();

        $byProduct = $rows->groupBy('ecommerce_product_id')->map(function ($group) {
            $backbar = (int) $group->whereIn('channel', EcommerceSale::BACKBAR_CHANNELS)->sum('quantity');
            $retail = (int) $group->whereIn('channel', EcommerceSale::SALON_RETAIL_CHANNELS)->sum('quantity');

            return [
                'product' => $group->first()->product,
                'backbar_units' => $backbar,
                'retail_units' => $retail,
                'old_airport_units' => (int) $group->where('channel', 'Salon (Old Airport)')->sum('quantity'),
                'wakrah_units' => (int) $group->where('channel', 'Salon (Wakrah)')->sum('quantity'),
                'retail_revenue' => (float) $group->whereIn('channel', EcommerceSale::SALON_RETAIL_CHANNELS)->sum('total_price'),
                'ratio' => $backbar > 0 ? ($retail / $backbar) * 100 : null,
            ];
        })->sortByDesc('backbar_units')->values();

        $totalBackbar = (int) $rows->whereIn('channel', EcommerceSale::BACKBAR_CHANNELS)->sum('quantity');
        $totalRetail = (int) $rows->whereIn('channel', EcommerceSale::SALON_RETAIL_CHANNELS)->sum('quantity');

        return [
            'by_product' => $byProduct,
            'total_backbar_units' => $totalBackbar,
            'total_retail_units' => $totalRetail,
            'total_retail_revenue' => (float) $rows->whereIn('channel', EcommerceSale::SALON_RETAIL_CHANNELS)->sum('total_price'),
            'efficiency' => $totalBackbar > 0 ? ($totalRetail / $totalBackbar) * 100 : null,
        ];
    }

    public function storeKpi(Request $request)
    {
        $validated = $this->validatedKpi($request);
        $date = Carbon::parse($validated['date'])->startOfDay();
        $validated['date'] = $date;

        EcommerceKpi::updateOrCreate(['date' => $date], $validated);

        return back()->with('success', 'Daily KPI snapshot saved.');
    }

    public function destroyKpi(EcommerceKpi $kpi)
    {
        $kpi->delete();

        return back()->with('success', 'KPI snapshot deleted.');
    }

    private function validatedKpi(Request $request): array
    {
        return $request->validate([
            'date' => 'required|date',
            'total_revenue' => 'required|numeric|min:0',
            'total_ad_spend' => 'required|numeric|min:0',
            'total_orders' => 'required|integer|min:0',
            'new_customers' => 'required|integer|min:0',
            'repeat_customers' => 'required|integer|min:0',
        ]);
    }
}
