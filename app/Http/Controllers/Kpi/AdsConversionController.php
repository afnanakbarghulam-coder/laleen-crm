<?php

namespace App\Http\Controllers\Kpi;

use App\Http\Controllers\Controller;
use App\Models\AdLeadEntry;
use App\Models\KpiAdsConversionReport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class AdsConversionController extends Controller
{
    public function index(Request $request)
    {
        $activeTab = in_array($request->query('tab'), ['analytics', 'reports'], true)
            ? 'analytics'
            : 'leads';

        $rawMonth = trim((string) $request->query('month', ''));
        if ($rawMonth !== '' && preg_match('/^\d{4}-\d{2}$/', $rawMonth)) {
            $monthStart = \Illuminate\Support\Carbon::createFromFormat('Y-m-d', $rawMonth . '-01')->startOfMonth()->startOfDay();
            $defaultFrom = $monthStart->copy();
            $defaultTo = $rawMonth === now()->format('Y-m')
                ? now()->copy()->endOfDay()
                : $monthStart->copy()->endOfMonth()->endOfDay();
        } else {
            $defaultFrom = now()->copy()->startOfMonth()->startOfDay();
            $defaultTo = now()->copy()->endOfDay();
        }

        $entriesFrom = $request->date('entries_from') ?? $defaultFrom->copy();
        $entriesTo = $request->date('entries_to') ?? $defaultTo->copy();

        $hasCustomerId = Schema::hasColumn('ad_lead_entries', 'customer_id');

        $adLeadEntries = AdLeadEntry::query()
            ->when($hasCustomerId, fn ($q) => $q->with('customer'))
            ->when($entriesFrom, fn ($q) => $q->whereDate('date', '>=', $entriesFrom))
            ->when($entriesTo, fn ($q) => $q->whereDate('date', '<=', $entriesTo))
            ->orderByDesc('date')
            ->orderByDesc('id')
            ->paginate(20, ['*'], 'entries_page')
            ->appends(array_filter([
                'entries_from' => $entriesFrom?->format('Y-m-d'),
                'entries_to' => $entriesTo?->format('Y-m-d'),
                'month' => $rawMonth !== '' ? $rawMonth : null,
                'tab' => 'leads',
            ]));

        $categories = AdLeadEntry::availableCategories();
        $branches = AdLeadEntry::BRANCHES;

        // Ads Analytics: defaults to current month MTD (or selected ?month) when no explicit range is passed.
        $analyticsFrom = $request->date('analytics_from') ?? $defaultFrom->copy();
        $analyticsTo = $request->date('analytics_to') ?? $defaultTo->copy();

        $analyticsReport = new KpiAdsConversionReport([
            'date_from' => $analyticsFrom,
            'date_to' => $analyticsTo,
        ]);

        $reports = KpiAdsConversionReport::orderByDesc('date_to')->orderByDesc('id')
            ->paginate(15, ['*'], 'reports_page')
            ->appends(array_filter([
                'analytics_from' => $request->filled('analytics_from') ? $analyticsFrom->format('Y-m-d') : null,
                'analytics_to' => $request->filled('analytics_to') ? $analyticsTo->format('Y-m-d') : null,
                'month' => $rawMonth !== '' ? $rawMonth : null,
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
