<?php

namespace App\Http\Controllers\Kpi;

use App\Http\Controllers\Controller;
use App\Models\AgentShiftLog;
use App\Models\KpiAgentTargetReport;
use App\Models\User;
use Illuminate\Http\Request;

class AgentTargetController extends Controller
{
    public function index(Request $request)
    {
        $activeTab = in_array($request->query('tab'), ['shifts', 'reports'], true)
            ? $request->query('tab')
            : 'shifts';

        $reports = KpiAgentTargetReport::orderByDesc('date_to')->orderByDesc('id')
            ->paginate(15)
            ->appends(['tab' => 'reports']);

        $logsFrom = $request->date('logs_from');
        $logsTo = $request->date('logs_to');

        $shiftLogs = AgentShiftLog::with('agent')
            ->when($logsFrom, fn ($q) => $q->whereDate('date', '>=', $logsFrom))
            ->when($logsTo, fn ($q) => $q->whereDate('date', '<=', $logsTo))
            ->orderByDesc('date')
            ->orderByDesc('id')
            ->paginate(20, ['*'], 'logs_page')
            ->appends(array_filter([
                'logs_from' => $logsFrom?->format('Y-m-d'),
                'logs_to' => $logsTo?->format('Y-m-d'),
                'tab' => 'shifts',
            ]));

        $agents = User::where('role', 'agent')->orderBy('name')->get();

        return view('kpi.agents.index', compact(
            'reports', 'shiftLogs', 'logsFrom', 'logsTo', 'agents', 'activeTab'
        ));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'date_from' => 'required|date',
            'date_to' => 'required|date|after_or_equal:date_from',
        ]);

        $report = KpiAgentTargetReport::create([
            'date_from' => $validated['date_from'],
            'date_to' => $validated['date_to'],
            'created_by' => auth()->id(),
        ]);

        return redirect()->route('kpi.agents.show', $report)->with('success', 'Agents target report generated from shift logs.');
    }

    public function show(KpiAgentTargetReport $report)
    {
        return view('kpi.agents.show', compact('report'));
    }

    public function destroy(KpiAgentTargetReport $report)
    {
        $report->delete();

        return redirect()->route('kpi.agents.index', ['tab' => 'reports'])->with('success', 'Report deleted.');
    }
}
