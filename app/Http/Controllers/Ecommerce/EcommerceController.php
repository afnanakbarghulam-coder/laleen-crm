<?php

namespace App\Http\Controllers\Ecommerce;

use App\Http\Controllers\Controller;
use App\Models\EcommerceBundle;
use App\Models\EcommerceContentCalendar;
use App\Models\EcommerceExpense;
use App\Models\EcommerceKpi;
use App\Models\EcommerceProduct;
use App\Models\EcommerceSale;
use App\Models\KpiMonthlyAd;
use App\Models\PartnerTransaction;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class EcommerceController extends Controller
{
    /** Trailing window and threshold mirrored from EcommerceStockController. */
    private const VELOCITY_WINDOW_DAYS = 30;
    private const REORDER_THRESHOLD_DAYS = 21;

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
            'adsInFlight' => KpiMonthlyAd::whereIn('phase', ['Planning', 'Production', 'Testing', 'Scaling'])->count(),
            'bundleCount' => EcommerceBundle::count(),
            'reorderCount' => $this->reorderCount(),
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

    /** Sub-hub: bundles, refill queue and velocity alerts. */
    public function operations()
    {
        return view('ecommerce.operations', [
            'bundleCount' => EcommerceBundle::count(),
            'reorderCount' => $this->reorderCount(),
            'refillDueCount' => $this->refillDueCount(),
        ]);
    }

    /**
     * Marketing hub — hosts the Weekly Content Factory and links out to the
     * Monthly Ads Plan Kanban board.
     */
    public function marketing()
    {
        $calendar = EcommerceContentCalendar::all()
            ->sortBy([
                fn ($a, $b) => $a->dayIndex() <=> $b->dayIndex(),
                fn ($a, $b) => $a->id <=> $b->id,
            ])
            ->values();

        $calendarByDay = collect(EcommerceContentCalendar::DAYS_OF_WEEK)
            ->mapWithKeys(fn ($day) => [$day => $calendar->where('day_of_week', $day)->values()]);

        $statusCounts = collect(EcommerceContentCalendar::STATUSES)
            ->mapWithKeys(fn ($status) => [$status => $calendar->where('status', $status)->count()]);

        $adsByPhase = collect(KpiMonthlyAd::BOARD_PHASES)
            ->mapWithKeys(fn ($phase) => [$phase => KpiMonthlyAd::where('phase', $phase)->count()]);

        return view('ecommerce.marketing.index', [
            'calendar' => $calendar,
            'calendarByDay' => $calendarByDay,
            'statusCounts' => $statusCounts,
            'adsByPhase' => $adsByPhase,
            'staff' => User::orderBy('name')->get(['id', 'name']),
            'days' => EcommerceContentCalendar::DAYS_OF_WEEK,
            'contentTypes' => EcommerceContentCalendar::CONTENT_TYPES,
            'statuses' => EcommerceContentCalendar::STATUSES,
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

    /** SKUs whose cover drops to the reorder threshold at current velocity. */
    private function reorderCount(): int
    {
        $unitsByProduct = EcommerceSale::where('created_at', '>=', now()->subDays(self::VELOCITY_WINDOW_DAYS))
            ->groupBy('ecommerce_product_id')
            ->selectRaw('ecommerce_product_id, SUM(quantity) as units')
            ->pluck('units', 'ecommerce_product_id');

        return EcommerceProduct::all()->filter(function (EcommerceProduct $product) use ($unitsByProduct) {
            $velocity = (float) ($unitsByProduct[$product->id] ?? 0) / self::VELOCITY_WINDOW_DAYS;

            if ($velocity <= 0) {
                return false;
            }

            $stock = (float) $product->stock_pakistan + (float) $product->stock_qatar;

            return ($stock / $velocity) <= self::REORDER_THRESHOLD_DAYS;
        })->count();
    }

    /** Distinct customers sitting in the 25-35 day refill window. */
    private function refillDueCount(): int
    {
        $windowStart = now()->subDays(35)->startOfDay();
        $windowEnd = now()->subDays(25)->endOfDay();
        $normalize = fn ($phone) => preg_replace('/\D+/', '', (string) $phone);

        $due = EcommerceSale::whereNotNull('customer_phone')
            ->where('customer_phone', '!=', '')
            ->whereNotIn('channel', EcommerceSalesController::NON_REVENUE_CHANNELS)
            ->whereBetween('created_at', [$windowStart, $windowEnd])
            ->pluck('customer_phone')
            ->map($normalize)
            ->filter()
            ->unique();

        if ($due->isEmpty()) {
            return 0;
        }

        $repurchased = EcommerceSale::whereNotNull('customer_phone')
            ->whereNotIn('channel', EcommerceSalesController::NON_REVENUE_CHANNELS)
            ->where('created_at', '>', $windowEnd)
            ->pluck('customer_phone')
            ->map($normalize)
            ->filter()
            ->unique();

        return $due->diff($repurchased)->count();
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

    // ---- Weekly content calendar --------------------------------------------

    public function storeContent(Request $request)
    {
        EcommerceContentCalendar::create($this->validatedContent($request));

        return back()->with('success', 'Content slot added.');
    }

    public function updateContent(Request $request, EcommerceContentCalendar $content)
    {
        $content->update($this->validatedContent($request));

        return back()->with('success', 'Content slot updated.');
    }

    /**
     * Inline status / assignee change from the board — only ever touches the
     * two fields the dropdowns own, so a quick status flip can never blank
     * out the script concept.
     */
    public function updateContentStatus(Request $request, EcommerceContentCalendar $content)
    {
        $validated = $request->validate([
            'status' => ['nullable', Rule::in(EcommerceContentCalendar::STATUSES)],
            'person_responsible' => 'nullable|string|max:255',
        ]);

        $content->update(array_filter(
            $validated,
            fn ($value, $key) => $request->has($key),
            ARRAY_FILTER_USE_BOTH
        ));

        return back()->with('success', 'Content slot updated.');
    }

    public function destroyContent(EcommerceContentCalendar $content)
    {
        $content->delete();

        return back()->with('success', 'Content slot removed.');
    }

    /** Seeds an empty week with one slot per day on its default pillar. */
    public function seedContentWeek()
    {
        if (EcommerceContentCalendar::exists()) {
            return back()->withErrors(['error' => 'The content calendar already has rows — seeding is only for an empty board.']);
        }

        foreach (EcommerceContentCalendar::DAYS_OF_WEEK as $day) {
            EcommerceContentCalendar::create([
                'day_of_week' => $day,
                'content_type' => EcommerceContentCalendar::DEFAULT_PILLAR_BY_DAY[$day] ?? null,
                'status' => 'Scripted',
            ]);
        }

        return back()->with('success', 'Seeded a full Monday–Sunday content week.');
    }

    private function validatedContent(Request $request): array
    {
        return $request->validate([
            'day_of_week' => ['required', Rule::in(EcommerceContentCalendar::DAYS_OF_WEEK)],
            'content_type' => ['nullable', Rule::in(EcommerceContentCalendar::CONTENT_TYPES)],
            'objective' => 'nullable|string|max:255',
            'script_concept' => 'nullable|string|max:5000',
            'person_responsible' => 'nullable|string|max:255',
            'status' => ['required', Rule::in(EcommerceContentCalendar::STATUSES)],
        ]);
    }
}
