<?php

namespace App\Http\Controllers\Kpi;

use App\Http\Controllers\Controller;
use App\Models\KpiAgentTargetReport;
use Illuminate\Http\Request;

class AgentTargetController extends Controller
{
    public function index()
    {
        $reports = KpiAgentTargetReport::orderByDesc('date_to')->orderByDesc('id')->paginate(15);

        return view('kpi.agents.index', compact('reports'));
    }

    public function create()
    {
        return view('kpi.agents.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'date_from' => 'required|date',
            'date_to' => 'required|date|after_or_equal:date_from',
            'morning_bookings' => 'required|integer|min:0',
            'morning_target' => 'required|integer|min:0',
            'evening_bookings' => 'required|integer|min:0',
            'evening_target' => 'required|integer|min:0',
            'prev_morning_pct' => 'nullable|numeric|min:0',
            'prev_evening_pct' => 'nullable|numeric|min:0',
        ]);

        $report = KpiAgentTargetReport::create($validated + ['created_by' => auth()->id()]);

        return redirect()->route('kpi.agents.show', $report)->with('success', 'Agents target report saved.');
    }

    public function show(KpiAgentTargetReport $report)
    {
        return view('kpi.agents.show', compact('report'));
    }

    public function destroy(KpiAgentTargetReport $report)
    {
        $report->delete();

        return redirect()->route('kpi.agents.index')->with('success', 'Report deleted.');
    }
}
