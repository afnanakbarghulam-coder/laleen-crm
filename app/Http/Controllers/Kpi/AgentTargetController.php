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

        $selectedMonth = $request->query('month');
        $logsFrom = $request->date('logs_from');
        $logsTo = $request->date('logs_to');

        if (!$logsFrom && !$logsTo && is_string($selectedMonth) && preg_match('/^\d{4}-\d{2}$/', $selectedMonth)) {
            $qatarNow = now(AgentShiftLog::SHIFT_TIMEZONE);
            $monthStart = \Illuminate\Support\Carbon::createFromFormat('Y-m-d', $selectedMonth . '-01', AgentShiftLog::SHIFT_TIMEZONE)->startOfMonth();
            $logsFrom = $monthStart->copy();
            $logsTo = ($selectedMonth === $qatarNow->format('Y-m')) ? $qatarNow->copy() : $monthStart->copy()->endOfMonth();
        }

        $shiftLogs = AgentShiftLog::with('agent')
            ->when($logsFrom, fn ($q) => $q->whereDate('date', '>=', $logsFrom))
            ->when($logsTo, fn ($q) => $q->whereDate('date', '<=', $logsTo))
            ->orderByDesc('date')
            ->orderByDesc('id')
            ->paginate(20, ['*'], 'logs_page')
            ->appends(array_filter([
                'month' => is_string($selectedMonth) ? $selectedMonth : null,
                'logs_from' => $logsFrom?->format('Y-m-d'),
                'logs_to' => $logsTo?->format('Y-m-d'),
                'tab' => 'shifts',
            ]));

        $agents = User::where('role', 'agent')->orderBy('name')->get();
        $agentScorecards = KpiAgentTargetReport::liveAgentScorecards(is_string($selectedMonth) ? $selectedMonth : null);

        return view('kpi.agents.index', compact(
            'reports', 'shiftLogs', 'logsFrom', 'logsTo', 'agents', 'agentScorecards', 'activeTab'
        ));
    }

    public function store(Request $request)
    {
        abort_unless($request->user()?->isAdmin(), 403);

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

    public function destroy(Request $request, KpiAgentTargetReport $report)
    {
        abort_unless($request->user()?->isAdmin(), 403);

        $report->delete();

        return redirect()->route('kpi.agents.index', ['tab' => 'reports'])->with('success', 'Report deleted.');
    }
}
