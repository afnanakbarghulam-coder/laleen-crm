<?php

namespace App\Http\Controllers\Ecommerce;

use App\Http\Controllers\Controller;
use App\Models\EcommerceContentCalendar;
use App\Models\EcommerceExpense;
use App\Models\EcommerceKpi;
use App\Models\EcommerceMonthlyAd;
use App\Models\PartnerTransaction;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class EcommerceController extends Controller
{
    /**
     * Top-level card menu. Each card carries one live headline figure so the
     * hub reports state rather than just being a link list.
     */
    public function hub()
    {
        $capitalPool = (float) PartnerTransaction::where('type', 'injection')->sum('amount');
        $partnerFundedExpenses = (float) EcommerceExpense::where('funding_source', 'partner_ledger')->sum('amount');

        $contentSlots = EcommerceContentCalendar::count();
        $contentLive = EcommerceContentCalendar::where('status', 'Live on Shopify/Meta')->count();

        return view('ecommerce.hub', [
            'runway' => $capitalPool - $partnerFundedExpenses,
            'contentSlots' => $contentSlots,
            'contentLive' => $contentLive,
            'adsInFlight' => EcommerceMonthlyAd::whereIn('phase', EcommerceMonthlyAd::BOARD_PHASES)->count(),
        ]);
    }

    /**
     * Financials & Inventory has no landing menu of its own — the section IS
     * the tabbed report set, so this drops straight into Partner Ledger.
     * Kept as a redirect rather than deleted so existing /ecommerce/financials
     * links and bookmarks keep resolving.
     */
    public function financials()
    {
        return redirect()->route('ecommerce.partners.index');
    }

    /** Marketing hub — two cards: the ads plan and the content calendar. */
    public function marketing()
    {
        return view('ecommerce.marketing.hub', [
            'adsInFlight' => EcommerceMonthlyAd::whereIn('phase', EcommerceMonthlyAd::BOARD_PHASES)->count(),
            'adsScaling' => EcommerceMonthlyAd::where('phase', 'Scaling')->count(),
            'contentSlots' => EcommerceContentCalendar::count(),
            'contentLive' => EcommerceContentCalendar::where('status', 'Live on Shopify/Meta')->count(),
        ]);
    }

    /** Ecommerce KPIs — blended acquisition economics. */
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

        // Carbon day boundaries, not 'Y-m-d' strings: stored dates carry a
        // 00:00:00 time component, so a bare end-date string would compare
        // lexically BELOW the final day's value and silently drop it.
        $kpiRows = EcommerceKpi::whereBetween('date', [$from->copy()->startOfDay(), $to->copy()->endOfDay()])
            ->orderByDesc('date')
            ->get();

        return view('ecommerce.dashboard', [
            'metrics' => EcommerceKpi::summarize($kpiRows),
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


    // ---- Daily KPI snapshots -------------------------------------------------

    public function storeKpi(Request $request)
    {
        $validated = $this->validatedKpi($request);

        // The 'date' cast writes a time component ('2026-10-14 00:00:00'), so
        // looking the row up by the bare 'Y-m-d' string never matches and the
        // unique index throws instead of updating. Normalising both sides to
        // a Carbon day boundary keeps the binding format identical.
        $date = Carbon::parse($validated['date'])->startOfDay();
        $validated['date'] = $date;

        // A re-submitted day is an update, not a second row — otherwise
        // revenue and ad spend would double-count.
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
