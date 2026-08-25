<?php

namespace App\Http\Controllers;

use App\Models\DailyTarget;
use Illuminate\Http\Request;

class DailyTargetController extends Controller
{
    public function index()
    {
        $records = DailyTarget::orderBy('date', 'desc')->paginate(15);
        return view('daily-target.index', compact('records'));
    }

    public function create()
    {
        return view('daily-target.form', [
            'isEdit' => false,
            'action' => route('daily-target.store'),
            'data' => null
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'date' => 'required|date',
            'daily_target' => 'required|integer|min:0',
            'actual_bookings' => 'required|integer|min:0',
            'notes' => 'nullable|string'
        ]);

        $percentage = $request->daily_target > 0
            ? ($request->actual_bookings / $request->daily_target) * 100 : 0;

        DailyTarget::create([
            'date' => $request->date,
            'daily_target' => $request->daily_target,
            'actual_bookings' => $request->actual_bookings,
            'percentage_achieved' => number_format($percentage, 2),
            'notes' => $request->notes,
        ]);

        return redirect()->route('daily-target.index')->with('success', 'Record Added!');
    }

    public function edit(DailyTarget $daily_target)
    {
        return view('daily-target.form', [
            'isEdit' => true,
            'action' => route('daily-target.update', $daily_target->id),
            'data' => $daily_target
        ]);
    }

    public function update(Request $request, DailyTarget $daily_target)
    {
        $request->validate([
            'date' => 'required|date',
            'daily_target' => 'required|integer|min:0',
            'actual_bookings' => 'required|integer|min:0',
            'notes' => 'nullable|string'
        ]);

        $percentage = $request->daily_target > 0
            ? ($request->actual_bookings / $request->daily_target) * 100
            : 0;

        $daily_target->update([
            'date' => $request->date,
            'daily_target' => $request->daily_target,
            'actual_bookings' => $request->actual_bookings,
            'percentage_achieved' => number_format($percentage, 2),
            'notes' => $request->notes,
        ]);

        return redirect()->route('daily-target.index')->with('success', 'Record Updated!');
    }

    public function destroy(DailyTarget $daily_target)
    {
        $daily_target->delete();
        return redirect()->route('daily-target.index')->with('success', 'Record Deleted');
    }
}
