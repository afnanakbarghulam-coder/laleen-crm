<?php

namespace App\Http\Controllers\Kpi;

use App\Http\Controllers\Controller;
use App\Models\KpiContentReport;
use Illuminate\Http\Request;

class ContentKpiController extends Controller
{
    public function index()
    {
        return view('kpi.content.index');
    }

    /**
     * Generate a report: just a saved (creator, date range) bookmark. All
     * scoring is computed live from the content calendar at render time —
     * see KpiContentReport::metrics()/entriesInRange().
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'creator_name' => 'required|string|max:100',
            'date_from' => 'required|date',
            'date_to' => 'required|date|after_or_equal:date_from',
        ]);

        $report = KpiContentReport::create([
            'creator_name' => $validated['creator_name'],
            'date_from' => $validated['date_from'],
            'date_to' => $validated['date_to'],
            'created_by' => auth()->id(),
        ]);

        return redirect()->route('kpi.content.show', $report)->with('success', 'Content KPI report generated from the calendar.');
    }

    public function show(KpiContentReport $report)
    {
        return view('kpi.content.show', compact('report'));
    }

    public function destroy(KpiContentReport $report)
    {
        $report->delete();

        return redirect()->route('kpi.content.index', ['tab' => 'reports'])->with('success', 'Report deleted.');
    }
}
