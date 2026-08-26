<?php

namespace App\Http\Controllers\Kpi;

use App\Http\Controllers\Controller;
use App\Models\KpiAdsConversionReport;
use Illuminate\Http\Request;

class AdsConversionController extends Controller
{
    public function index()
    {
        $reports = KpiAdsConversionReport::orderByDesc('date_to')->orderByDesc('id')->paginate(15);

        return view('kpi.ads.index', compact('reports'));
    }

    public function create()
    {
        return view('kpi.ads.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'date_from' => 'required|date',
            'date_to' => 'required|date|after_or_equal:date_from',
            'categories' => 'required|array|min:1',
            'categories.*.name' => 'required|string|max:100',
            'categories.*.leads' => 'required|integer|min:0',
            'categories.*.bookings' => 'required|integer|min:0',
            'categories.*.avg_ticket' => 'required|numeric|min:0',
            'old_airport_bookings' => 'nullable|integer|min:0',
            'old_airport_revenue' => 'nullable|numeric|min:0',
            'wakrah_bookings' => 'nullable|integer|min:0',
            'wakrah_revenue' => 'nullable|numeric|min:0',
        ]);

        $report = KpiAdsConversionReport::create([
            'date_from' => $validated['date_from'],
            'date_to' => $validated['date_to'],
            'categories' => array_values($validated['categories']),
            'old_airport_bookings' => $validated['old_airport_bookings'] ?? 0,
            'old_airport_revenue' => $validated['old_airport_revenue'] ?? 0,
            'wakrah_bookings' => $validated['wakrah_bookings'] ?? 0,
            'wakrah_revenue' => $validated['wakrah_revenue'] ?? 0,
            'created_by' => auth()->id(),
        ]);

        return redirect()->route('kpi.ads.show', $report)->with('success', 'Ads conversion report saved.');
    }

    public function show(KpiAdsConversionReport $report)
    {
        return view('kpi.ads.show', compact('report'));
    }

    public function destroy(KpiAdsConversionReport $report)
    {
        $report->delete();

        return redirect()->route('kpi.ads.index')->with('success', 'Report deleted.');
    }
}
