<?php

namespace App\Http\Controllers\Kpi;

use App\Http\Controllers\Controller;
use App\Models\KpiContentReport;
use Illuminate\Http\Request;

class ContentKpiController extends Controller
{
    public function index()
    {
        $reports = KpiContentReport::with('entries')->orderByDesc('date_to')->orderByDesc('id')->paginate(15);

        return view('kpi.content.index', compact('reports'));
    }

    public function create()
    {
        return view('kpi.content.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'creator_name' => 'required|string|max:100',
            'date_from' => 'required|date',
            'date_to' => 'required|date|after_or_equal:date_from',
            'entries' => 'required|array|min:1',
            'entries.*.entry_date' => 'required|date',
            'entries.*.activity_type' => 'nullable|string|max:100',
            'entries.*.feed_scheduled' => 'nullable|boolean',
            'entries.*.stories_scheduled' => 'nullable|boolean',
            'entries.*.feed_posted' => 'nullable|boolean',
            'entries.*.stories_posted' => 'nullable|boolean',
            'entries.*.standards_feed' => 'required|in:Y,N,NA',
            'entries.*.standards_stories' => 'required|in:Y,N,NA',
            'entries.*.issues' => 'nullable|string|max:255',
        ]);

        $report = KpiContentReport::create([
            'creator_name' => $validated['creator_name'],
            'date_from' => $validated['date_from'],
            'date_to' => $validated['date_to'],
            'created_by' => auth()->id(),
        ]);

        foreach ($validated['entries'] as $entry) {
            $report->entries()->create([
                'entry_date' => $entry['entry_date'],
                'activity_type' => $entry['activity_type'] ?? null,
                'feed_scheduled' => (bool) ($entry['feed_scheduled'] ?? false),
                'stories_scheduled' => (bool) ($entry['stories_scheduled'] ?? false),
                'feed_posted' => (bool) ($entry['feed_posted'] ?? false),
                'stories_posted' => (bool) ($entry['stories_posted'] ?? false),
                'standards_feed' => $entry['standards_feed'],
                'standards_stories' => $entry['standards_stories'],
                'issues' => $entry['issues'] ?? null,
            ]);
        }

        return redirect()->route('kpi.content.show', $report)->with('success', 'Content KPI report saved.');
    }

    public function show(KpiContentReport $report)
    {
        $report->load('entries');

        return view('kpi.content.show', compact('report'));
    }

    public function destroy(KpiContentReport $report)
    {
        $report->delete();

        return redirect()->route('kpi.content.index')->with('success', 'Report deleted.');
    }
}
