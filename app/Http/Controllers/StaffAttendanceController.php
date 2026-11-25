<?php

namespace App\Http\Controllers;

use App\Models\Staff;
use App\Models\StaffAttendance;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

class StaffAttendanceController extends Controller
{
    /**
     * Upsert a daily attendance record (`present`, `absent`, `late`, `off`)
     * for any team member — works identically for physical-branch and Remote staff.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'staff_id' => ['required', 'integer', 'exists:staff,id'],
            'attendance_date' => ['required', 'date'],
            'status' => ['required', Rule::in(array_keys(StaffAttendance::STATUSES))],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $staff = Staff::findOrFail((int) $validated['staff_id']);
        $dateStr = Carbon::parse($validated['attendance_date'], 'Asia/Qatar')->toDateString();

        $record = StaffAttendance::updateOrCreate(
            [
                'staff_id' => $staff->id,
                'attendance_date' => $dateStr,
            ],
            [
                'status' => $validated['status'],
                'notes' => isset($validated['notes']) && trim((string) $validated['notes']) !== ''
                    ? trim((string) $validated['notes'])
                    : null,
                'recorded_by' => $request->user()?->id,
            ]
        );

        $message = "Marked {$staff->name} as {$record->statusLabel()} for {$dateStr}.";

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => $message,
                'attendance' => [
                    'id' => $record->id,
                    'staff_id' => $record->staff_id,
                    'staff_name' => $staff->name,
                    'location_label' => $staff->location_label,
                    'is_remote' => $staff->isRemote(),
                    'attendance_date' => $dateStr,
                    'status' => $record->status,
                    'status_label' => $record->statusLabel(),
                    'notes' => $record->notes,
                ],
            ]);
        }

        return redirect()->route('staffs.index', [
            'tab' => 'attendance',
            'att_date' => $dateStr,
            'att_branch' => $request->input('att_branch', ''),
        ])->with('success', $message);
    }
}
