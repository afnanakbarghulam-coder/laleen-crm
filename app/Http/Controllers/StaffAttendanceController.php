<?php

namespace App\Http\Controllers;

use App\Models\Staff;
use App\Models\StaffAttendance;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class StaffAttendanceController extends Controller
{
    /**
     * Server-side tamper-proof Salon Check-In (`POST /staff-attendance/check-in`).
     * Always uses `Carbon::now('Asia/Qatar')` on the server (never trusts client clock),
     * snapshots the staff member's scheduled shift start/end, calculates exact late minutes,
     * and locks the record so non-admins cannot alter or reset it.
     */
    public function checkIn(Request $request)
    {
        /** @var User $actor */
        $actor = $request->user();

        $validated = $request->validate([
            'staff_id' => ['required', 'integer', 'exists:staff,id'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $staff = Staff::with(['user', 'shiftPatterns.blocks', 'timeOffs'])->findOrFail((int) $validated['staff_id']);
        $this->authorizeBranchScope($actor, $staff);

        $nowQatar = Carbon::now('Asia/Qatar');
        $todayStr = $nowQatar->toDateString();

        $existing = StaffAttendance::query()
            ->where('staff_id', $staff->id)
            ->whereDate('attendance_date', $todayStr)
            ->first();

        if ($existing && ($existing->is_locked || $existing->checked_in_at !== null)) {
            return $this->errorResponse(
                $request,
                'This check-in is already recorded and locked. Only an Admin can reset or edit a locked check-in.',
                403
            );
        }

        $shift = $staff->resolveShiftForDate($nowQatar);
        $scheduledStartTime = null;
        $scheduledEndTime = null;
        $status = StaffAttendance::STATUS_PRESENT;
        $lateMinutes = 0;

        $inputNotes = isset($validated['notes']) && trim((string) $validated['notes']) !== ''
            ? trim((string) $validated['notes'])
            : null;
        $finalNotes = $inputNotes ?? $existing?->notes;

        if ($shift['type'] === 'shift' && !empty($shift['start_time'])) {
            $scheduledStartTime = (string) $shift['start_time'];
            $scheduledEndTime = $shift['end_time'] ? (string) $shift['end_time'] : null;

            $scheduledStart = Carbon::parse($todayStr . ' ' . $scheduledStartTime, 'Asia/Qatar');
            if ($nowQatar->lte($scheduledStart)) {
                $status = StaffAttendance::STATUS_PRESENT;
                $lateMinutes = 0;
            } else {
                $status = StaffAttendance::STATUS_LATE;
                $diffSeconds = (int) $scheduledStart->diffInSeconds($nowQatar, false);
                $lateMinutes = max(1, (int) floor($diffSeconds / 60));
            }
        } elseif (in_array($shift['type'], ['off', 'on_leave'], true)) {
            $status = StaffAttendance::STATUS_PRESENT;
            $lateMinutes = 0;
            if (!$finalNotes) {
                $finalNotes = 'Off-day check-in';
            } elseif (!str_contains(strtolower($finalNotes), 'off-day check-in')) {
                $finalNotes = 'Off-day check-in — ' . $finalNotes;
            }
        }

        $record = DB::transaction(function () use (
            $staff,
            $todayStr,
            $status,
            $scheduledStartTime,
            $scheduledEndTime,
            $nowQatar,
            $lateMinutes,
            $actor,
            $finalNotes
        ) {
            return StaffAttendance::updateOrCreate(
                [
                    'staff_id' => $staff->id,
                    'attendance_date' => $todayStr,
                ],
                [
                    'status' => $status,
                    'scheduled_start_time' => $scheduledStartTime,
                    'scheduled_end_time' => $scheduledEndTime,
                    'checked_in_at' => $nowQatar,
                    'late_minutes' => $lateMinutes,
                    'checked_in_by' => $actor->id,
                    'recorded_by' => $actor->id,
                    'is_locked' => true,
                    'notes' => $finalNotes,
                ]
            );
        });

        $checkInClock = $record->checkedInQatarLabel();
        $message = $record->status === StaffAttendance::STATUS_LATE && $record->late_minutes > 0
            ? "{$staff->name} checked in at {$checkInClock} (Late " . StaffAttendance::formatLateDuration($record->late_minutes) . ').'
            : "{$staff->name} checked in on time at {$checkInClock}.";

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => $message,
                'attendance' => $this->serializeAttendance($record, $staff),
            ], 201);
        }

        return redirect()->route('staffs.index', [
            'tab' => 'attendance',
            'att_date' => $todayStr,
            'att_branch' => $request->input('att_branch', ''),
        ])->with('success', $message);
    }

    /**
     * Record server-side Check-Out (`POST /staff-attendance/check-out`) once for a checked-in row
     * without altering `checked_in_at`, `status`, or `late_minutes`.
     */
    public function checkOut(Request $request)
    {
        /** @var User $actor */
        $actor = $request->user();

        $validated = $request->validate([
            'staff_id' => ['required', 'integer', 'exists:staff,id'],
            'attendance_date' => ['nullable', 'date'],
        ]);

        $staff = Staff::with('user')->findOrFail((int) $validated['staff_id']);
        $this->authorizeBranchScope($actor, $staff);

        $nowQatar = Carbon::now('Asia/Qatar');
        $dateStr = !empty($validated['attendance_date'])
            ? Carbon::parse($validated['attendance_date'], 'Asia/Qatar')->toDateString()
            : $nowQatar->toDateString();

        $record = StaffAttendance::query()
            ->where('staff_id', $staff->id)
            ->whereDate('attendance_date', $dateStr)
            ->first();

        if (!$record || !$record->checked_in_at) {
            return $this->errorResponse($request, "{$staff->name} has not checked in yet for {$dateStr}.", 422);
        }

        if ($record->checked_out_at !== null && !$actor->isAdmin()) {
            return $this->errorResponse($request, 'Check-out has already been recorded and is locked.', 403);
        }

        $record->forceFill([
            'checked_out_at' => $nowQatar,
        ])->save();

        $outClock = $record->checkedOutQatarLabel();
        $message = "{$staff->name} checked out at {$outClock}.";

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => $message,
                'attendance' => $this->serializeAttendance($record->fresh(), $staff),
            ]);
        }

        return redirect()->route('staffs.index', [
            'tab' => 'attendance',
            'att_date' => $dateStr,
            'att_branch' => $request->input('att_branch', ''),
        ])->with('success', $message);
    }

    /**
     * Manual attendance status upsert (`present`, `absent`, `late`, `off`) for past dates,
     * remote staff, no-shows, or note updates. Strictly rejects non-admin attempts to
     * alter or overwrite a locked check-in record (HTTP 403).
     */
    public function store(Request $request)
    {
        /** @var User $actor */
        $actor = $request->user();

        $validated = $request->validate([
            'staff_id' => ['required', 'integer', 'exists:staff,id'],
            'attendance_date' => ['required', 'date'],
            'status' => ['required', Rule::in(array_keys(StaffAttendance::STATUSES))],
            'late_minutes' => ['nullable', 'integer', 'min:0', 'max:1440'],
            'notes' => ['nullable', 'string', 'max:500'],
            'notes_only' => ['nullable', 'boolean'],
        ]);

        $staff = Staff::with('user')->findOrFail((int) $validated['staff_id']);
        $this->authorizeBranchScope($actor, $staff);

        $dateStr = Carbon::parse($validated['attendance_date'], 'Asia/Qatar')->toDateString();

        $existing = StaffAttendance::query()
            ->where('staff_id', $staff->id)
            ->whereDate('attendance_date', $dateStr)
            ->first();

        $cleanNotes = isset($validated['notes']) && trim((string) $validated['notes']) !== ''
            ? trim((string) $validated['notes'])
            : null;

        // Anti-tamper lock enforcement: non-admins can NEVER alter status/timestamps of a locked check-in
        if ($existing && $existing->is_locked && !$actor->isAdmin()) {
            if ($request->boolean('notes_only')) {
                $existing->update(['notes' => $cleanNotes]);

                return response()->json([
                    'success' => true,
                    'message' => "Updated note for {$staff->name}.",
                    'attendance' => $this->serializeAttendance($existing->fresh(), $staff),
                ]);
            }

            return $this->errorResponse(
                $request,
                'This check-in is locked and cannot be altered by non-administrators.',
                403
            );
        }

        // If notes_only on an existing unlocked row, just update notes
        if ($existing && $request->boolean('notes_only')) {
            $existing->update(['notes' => $cleanNotes]);

            return response()->json([
                'success' => true,
                'message' => "Updated note for {$staff->name}.",
                'attendance' => $this->serializeAttendance($existing->fresh(), $staff),
            ]);
        }

        $lateMinutes = $validated['status'] === StaffAttendance::STATUS_LATE
            ? (int) ($validated['late_minutes'] ?? ($existing?->late_minutes ?? 0))
            : 0;

        $record = StaffAttendance::updateOrCreate(
            [
                'staff_id' => $staff->id,
                'attendance_date' => $dateStr,
            ],
            [
                'status' => $validated['status'],
                'late_minutes' => $lateMinutes,
                'notes' => $cleanNotes,
                'recorded_by' => $actor->id,
            ]
        );

        $message = "Marked {$staff->name} as {$record->statusLabel()} for {$dateStr}.";

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => $message,
                'attendance' => $this->serializeAttendance($record, $staff),
            ]);
        }

        return redirect()->route('staffs.index', [
            'tab' => 'attendance',
            'att_date' => $dateStr,
            'att_branch' => $request->input('att_branch', ''),
        ])->with('success', $message);
    }

    /**
     * Admin-only override to correct a check-in timestamp, status, late minutes, or notes.
     */
    public function adminOverride(Request $request, StaffAttendance $attendance)
    {
        /** @var User $actor */
        $actor = $request->user();
        abort_unless($actor && $actor->isAdmin(), 403, 'Only administrators can edit locked check-in records.');

        $validated = $request->validate([
            'status' => ['required', Rule::in(array_keys(StaffAttendance::STATUSES))],
            'checked_in_time' => ['nullable', 'date_format:H:i'],
            'checked_out_time' => ['nullable', 'date_format:H:i'],
            'late_minutes' => ['nullable', 'integer', 'min:0', 'max:1440'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $dateStr = $attendance->attendance_date->format('Y-m-d');
        $staff = $attendance->staff()->with(['shiftPatterns.blocks', 'timeOffs'])->first();

        $checkedInAt = $attendance->checked_in_at;
        if (array_key_exists('checked_in_time', $validated)) {
            $checkedInAt = !empty($validated['checked_in_time'])
                ? Carbon::parse($dateStr . ' ' . $validated['checked_in_time'] . ':00', 'Asia/Qatar')
                : null;
        }

        $checkedOutAt = $attendance->checked_out_at;
        if (array_key_exists('checked_out_time', $validated)) {
            $checkedOutAt = !empty($validated['checked_out_time'])
                ? Carbon::parse($dateStr . ' ' . $validated['checked_out_time'] . ':00', 'Asia/Qatar')
                : null;
        }

        $status = $validated['status'];
        $lateMinutes = isset($validated['late_minutes'])
            ? (int) $validated['late_minutes']
            : (int) ($attendance->late_minutes ?? 0);

        // If Admin supplied a new check-in time and left late_minutes blank, auto-recalculate vs scheduled shift
        if (!empty($validated['checked_in_time']) && $validated['late_minutes'] === null && $checkedInAt) {
            $schedStartStr = $attendance->scheduled_start_time;
            if (!$schedStartStr && $staff) {
                $resolved = $staff->resolveShiftForDate($dateStr);
                if ($resolved['type'] === 'shift' && !empty($resolved['start_time'])) {
                    $schedStartStr = (string) $resolved['start_time'];
                    $attendance->scheduled_start_time = $schedStartStr;
                    $attendance->scheduled_end_time = $resolved['end_time'];
                }
            }
            if ($schedStartStr) {
                $schedStart = Carbon::parse($dateStr . ' ' . $schedStartStr, 'Asia/Qatar');
                if ($checkedInAt->gt($schedStart)) {
                    $lateMinutes = max(1, (int) floor($schedStart->diffInSeconds($checkedInAt, false) / 60));
                    if ($status === StaffAttendance::STATUS_PRESENT) {
                        $status = StaffAttendance::STATUS_LATE;
                    }
                } else {
                    $lateMinutes = 0;
                    if ($status === StaffAttendance::STATUS_LATE) {
                        $status = StaffAttendance::STATUS_PRESENT;
                    }
                }
            }
        }

        if (in_array($status, [StaffAttendance::STATUS_PRESENT, StaffAttendance::STATUS_ABSENT, StaffAttendance::STATUS_OFF], true)) {
            $lateMinutes = 0;
        }

        $attendance->fill([
            'status' => $status,
            'checked_in_at' => $checkedInAt,
            'checked_out_at' => $checkedOutAt,
            'late_minutes' => $lateMinutes,
            'is_locked' => $checkedInAt !== null,
            'notes' => isset($validated['notes']) && trim((string) $validated['notes']) !== ''
                ? trim((string) $validated['notes'])
                : null,
            'recorded_by' => $actor->id,
        ])->save();

        $staffName = $staff?->name ?? 'Staff member';
        $message = "Updated attendance record for {$staffName} ({$dateStr}).";

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => $message,
                'attendance' => $staff ? $this->serializeAttendance($attendance->fresh(), $staff) : $attendance->fresh(),
            ]);
        }

        return redirect()->route('staffs.index', [
            'tab' => 'attendance',
            'att_date' => $dateStr,
        ])->with('success', $message);
    }

    /**
     * Admin-only reset: removes an erroneous check-in record so the staff member can punch in cleanly.
     */
    public function adminReset(Request $request, StaffAttendance $attendance)
    {
        /** @var User $actor */
        $actor = $request->user();
        abort_unless($actor && $actor->isAdmin(), 403, 'Only administrators can reset locked check-in records.');

        $staffName = $attendance->staff?->name ?? 'Staff member';
        $dateStr = $attendance->attendance_date->format('Y-m-d');
        $attendance->delete();

        $message = "Reset attendance check-in for {$staffName} on {$dateStr}.";

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => $message,
            ]);
        }

        return redirect()->route('staffs.index', [
            'tab' => 'attendance',
            'att_date' => $dateStr,
        ])->with('success', $message);
    }

    private function authorizeBranchScope(User $actor, Staff $targetStaff): void
    {
        $managedBranch = $actor->managedSalonBranch();
        if ($managedBranch === null) {
            return;
        }

        $belongsToBranch = !$targetStaff->isRemote()
            && in_array(strtolower(trim((string) $targetStaff->branch)), [$managedBranch, 'both'], true);

        abort_unless($belongsToBranch, 403, 'You can only manage attendance for your assigned salon branch.');
    }

    private function serializeAttendance(StaffAttendance $record, Staff $staff): array
    {
        $badge = $record->statusBadgePayload();

        return [
            'id' => $record->id,
            'staff_id' => $record->staff_id,
            'staff_name' => $staff->name,
            'location_label' => $staff->location_label,
            'is_remote' => $staff->isRemote(),
            'attendance_date' => $record->attendance_date->toDateString(),
            'status' => $record->status,
            'status_label' => $record->statusLabel(),
            'badge_label' => $badge['label'],
            'badge_tone' => $badge['tone'],
            'scheduled_start_time' => $record->scheduled_start_time,
            'scheduled_end_time' => $record->scheduled_end_time,
            'scheduled_shift_label' => $record->scheduledShiftLabel(),
            'checked_in_at' => $record->checked_in_at?->copy()->timezone('Asia/Qatar')->toIso8601String(),
            'checked_in_label' => $record->checkedInQatarLabel(),
            'checked_out_at' => $record->checked_out_at?->copy()->timezone('Asia/Qatar')->toIso8601String(),
            'checked_out_label' => $record->checkedOutQatarLabel(),
            'late_minutes' => (int) ($record->late_minutes ?? 0),
            'late_formatted' => StaffAttendance::formatLateDuration((int) ($record->late_minutes ?? 0)),
            'is_locked' => (bool) ($record->is_locked ?? false),
            'notes' => $record->notes,
        ];
    }

    private function errorResponse(Request $request, string $message, int $status = 422)
    {
        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'success' => false,
                'message' => $message,
            ], $status);
        }

        return redirect()->back()->withErrors(['attendance' => $message]);
    }
}
