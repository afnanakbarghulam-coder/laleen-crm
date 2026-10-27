<?php

namespace App\Http\Controllers\Kpi;

use App\Http\Controllers\Controller;
use App\Models\AdLeadEntry;
use App\Models\KpiAdsConversionReport;
use Illuminate\Http\Request;

class AdsConversionController extends Controller
{
    public function index(Request $request)
    {
        $activeTab = in_array($request->query('tab'), ['analytics', 'reports'], true)
            ? 'analytics'
            : 'leads';

        $entriesFrom = $request->date('entries_from');
        $entriesTo = $request->date('entries_to');

        $adLeadEntries = AdLeadEntry::query()
            ->when($entriesFrom, fn ($q) => $q->whereDate('date', '>=', $entriesFrom))
            ->when($entriesTo, fn ($q) => $q->whereDate('date', '<=', $entriesTo))
            ->orderByDesc('date')
            ->orderByDesc('id')
            ->paginate(20, ['*'], 'entries_page')
            ->appends(array_filter([
                'entries_from' => $entriesFrom?->format('Y-m-d'),
                'entries_to' => $entriesTo?->format('Y-m-d'),
                'tab' => 'leads',
            ]));

        $categories = AdLeadEntry::availableCategories();
        $branches = AdLeadEntry::BRANCHES;

        // Ads Analytics: a live, non-persisted breakdown for a date range —
        // no "report" needs to be generated/saved to see it. Defaults to the
        // full span of logged leads when no range is picked.
        $earliestLogged = AdLeadEntry::min('date');
        $latestLogged = AdLeadEntry::max('date');

        $analyticsFrom = $request->date('analytics_from')
            ?? ($earliestLogged ? \Carbon\Carbon::parse($earliestLogged) : now());
        $analyticsTo = $request->date('analytics_to')
            ?? ($latestLogged ? \Carbon\Carbon::parse($latestLogged) : now());

        $analyticsReport = new KpiAdsConversionReport([
            'date_from' => $analyticsFrom,
            'date_to' => $analyticsTo,
        ]);

        $reports = KpiAdsConversionReport::orderByDesc('date_to')->orderByDesc('id')
            ->paginate(15, ['*'], 'reports_page')
            ->appends(array_filter([
                'analytics_from' => $request->filled('analytics_from') ? $analyticsFrom->format('Y-m-d') : null,
                'analytics_to' => $request->filled('analytics_to') ? $analyticsTo->format('Y-m-d') : null,
                'tab' => 'analytics',
            ]));

        return view('kpi.ads.index', compact(
            'reports', 'adLeadEntries', 'categories', 'branches', 'entriesFrom', 'entriesTo',
            'analyticsReport', 'analyticsFrom', 'analyticsTo', 'activeTab'
        ));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'date_from' => 'required|date',
            'date_to' => 'required|date|after_or_equal:date_from',
        ]);

        KpiAdsConversionReport::create([
            'date_from' => $validated['date_from'],
            'date_to' => $validated['date_to'],
            'created_by' => auth()->id(),
        ]);

        return redirect()->route('kpi.ads.index', ['tab' => 'analytics'])->with('success', 'Ads conversion report generated from the lead log.');
    }

    public function show(KpiAdsConversionReport $report)
    {
        return view('kpi.ads.show', compact('report'));
    }

    public function destroy(KpiAdsConversionReport $report)
    {
        $report->delete();

        return redirect()->route('kpi.ads.index', ['tab' => 'analytics'])->with('success', 'Report deleted.');
    }
}
