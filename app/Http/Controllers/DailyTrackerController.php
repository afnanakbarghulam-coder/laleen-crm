<?php

namespace App\Http\Controllers;

use App\Models\DailyTracker;
use App\Models\User;
use Illuminate\Http\Request;

class DailyTrackerController extends Controller
{
    public function index(Request $request)
    {
        $query = DailyTracker::with('agent');

        // Filters
        if ($request->filled('date')) {
            $query->where('date', $request->date);
        }

        if ($request->filled('shift')) {
            $query->where('shift', $request->shift);
        }

        if ($request->filled('agent_id')) {
            $query->where('agent_id', $request->agent_id);
        }

        $trackers = $query->orderBy('date', 'desc')->paginate(20);
        $agents = User::where('role', 'agent')->get();

        return view('daily-tracker.index', compact('trackers', 'agents'));
    }

    public function create()
    {
        $agents = User::where('role', 'agent')->get();
        return view('daily-tracker.form', compact('agents'));
    }

    public function store(Request $request)
    {
        $validated = $this->validateData($request);
        DailyTracker::create($validated);

        return redirect()->route('daily-tracker.index')->with('success', 'Daily record saved!');
    }

    public function edit(DailyTracker $daily)
    {
        $agents = User::where('role', 'agent')->get();
        return view('daily-tracker.form', compact('daily', 'agents'));
    }

    public function update(Request $request, DailyTracker $daily)
    {
        $validated = $this->validateData($request);
        $daily->update($validated);

        return redirect()->route('daily-tracker.index')->with('success', 'Daily record updated!');
    }

    public function destroy(DailyTracker $daily)
    {
        $daily->delete();
        return redirect()->route('daily-tracker.index')->with('success', 'Record deleted!');
    }

    private function validateData(Request $request)
    {
        return $request->validate([
            'date' => 'required|date',
            'shift' => 'required|in:morning,night',
            'agent_id' => 'required|exists:users,id',
            'check_in' => 'nullable',
            'check_out' => 'nullable',
            'sent_reminders' => 'required|in:yes,no,na',
            'asked_feedbacks' => 'required|in:yes,no,na',
            'updated_no_shows' => 'required|in:yes,no,na',
            'excel_reviewed' => 'required|in:yes,no,na',
            'checked_bookings_vs_sales' => 'required|in:yes,no,na',
            'corrections_done' => 'required|in:yes,no,na',
            'leads_received' => 'nullable|integer',
            'bookings_done' => 'nullable|integer',
            'notes' => 'nullable|string',
        ]);
    }

    public function show(DailyTracker $daily)
    {
        $daily->load('agent');
        return response()->json($daily);
    }

}
