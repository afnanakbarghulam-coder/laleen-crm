<?php

namespace App\Http\Controllers\Ecommerce;

use App\Http\Controllers\Controller;
use App\Models\EcommerceContentCalendar;
use App\Models\EcommerceKpi;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class EcommerceController extends Controller
{
    public function index(Request $request)
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

        $metrics = EcommerceKpi::summarize($kpiRows);

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

        return view('ecommerce.index', [
            'metrics' => $metrics,
            'kpiRows' => $kpiRows,
            'calendar' => $calendar,
            'calendarByDay' => $calendarByDay,
            'statusCounts' => $statusCounts,
            'staff' => User::orderBy('name')->get(['id', 'name']),
            'days' => EcommerceContentCalendar::DAYS_OF_WEEK,
            'contentTypes' => EcommerceContentCalendar::CONTENT_TYPES,
            'statuses' => EcommerceContentCalendar::STATUSES,
            'defaultPillarByDay' => EcommerceContentCalendar::DEFAULT_PILLAR_BY_DAY,
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
