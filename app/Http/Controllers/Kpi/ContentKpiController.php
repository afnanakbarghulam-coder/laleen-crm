<?php

namespace App\Http\Controllers\Kpi;

use App\Http\Controllers\Controller;
use App\Models\KpiContentEntry;
use App\Models\KpiContentReport;
use Illuminate\Http\Request;

class ContentKpiController extends Controller
{
    public function index(Request $request)
    {
        $activeTab = in_array($request->query('tab'), ['calendar', 'reports'], true)
            ? $request->query('tab')
            : 'calendar';

        $calendarFrom = $request->date('calendar_from');
        $calendarTo = $request->date('calendar_to');
        $calendarCreator = $request->filled('calendar_creator') ? $request->calendar_creator : null;

        $entries = KpiContentEntry::query()
            ->when($calendarFrom, fn ($q) => $q->whereDate('entry_date', '>=', $calendarFrom))
            ->when($calendarTo, fn ($q) => $q->whereDate('entry_date', '<=', $calendarTo))
            ->when($calendarCreator, fn ($q) => $q->where('creator_name', $calendarCreator))
            ->orderByDesc('entry_date')
            ->orderByDesc('id')
            ->paginate(20, ['*'], 'entries_page')
            ->appends(array_filter([
                'calendar_from' => $calendarFrom?->format('Y-m-d'),
                'calendar_to' => $calendarTo?->format('Y-m-d'),
                'calendar_creator' => $calendarCreator,
                'tab' => 'calendar',
            ]));

        $creators = KpiContentEntry::select('creator_name')->distinct()->orderBy('creator_name')->pluck('creator_name');

        $reports = KpiContentReport::orderByDesc('date_to')->orderByDesc('id')
            ->paginate(15, ['*'], 'reports_page')
            ->appends(['tab' => 'reports']);

        return view('kpi.content.index', compact(
            'activeTab', 'entries', 'creators', 'calendarFrom', 'calendarTo', 'calendarCreator', 'reports'
        ));
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
