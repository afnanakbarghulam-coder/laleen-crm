<?php

namespace App\Http\Controllers;

use App\Models\StaffBlock;
use Illuminate\Http\Request;

class StaffBlockController extends Controller
{
    public function store(Request $request)
    {
        $request->validate([
            'staff_id' => 'required|exists:staff,id',
            'date' => 'required|date',
            'start_time' => 'required|date_format:H:i',
            'end_time' => 'required|date_format:H:i|after:start_time',
            'reason' => 'nullable|string|max:255',
        ]);

        StaffBlock::create([
            'staff_id' => $request->staff_id,
            'date' => $request->date,
            'start_time' => $request->start_time,
            'end_time' => $request->end_time,
            'reason' => $request->reason,
            'created_by' => auth()->id(),
        ]);

        if ($request->wantsJson()) {
            return response()->json(['success' => true]);
        }

        return back()->with('success', 'Time blocked successfully.');
    }

    public function destroy(StaffBlock $staffBlock)
    {
        $staffBlock->delete();

        if (request()->wantsJson()) {
            return response()->json(['success' => true]);
        }

        return back()->with('success', 'Block removed.');
    }
}
