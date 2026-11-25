<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Models\Staff;
use App\Models\StaffLeave;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class StaffLeaveController extends Controller
{
    /**
     * Self-service Leave Request submission from `/my-profile?tab=leaves`.
     * Strictly scoped to `auth()->user()->staff` and blocked (HTTP 403) for all Remote / non-eligible roles.
     */
    public function storePortalRequest(Request $request)
    {
        $user = $request->user();
        abort_unless($user && $user->isLeaveEligible(), 403, 'Leave requests are only available for Salon Managers and Salon Staff.');

        $staff = $user->staff()->first();
        if (!$staff) {
            return $this->errorResponse($request, 'Your account is not linked to a Staff HR Profile yet. Please contact management.', 422);
        }

        $validated = $request->validate([
            'leave_type' => ['required', Rule::in([StaffLeave::TYPE_ANNUAL, StaffLeave::TYPE_SICK])],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'reason' => ['nullable', 'string', 'max:1000'],
            'medical_certificate' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:10240'],
        ]);

        $startStr = Carbon::parse($validated['start_date'], 'Asia/Qatar')->toDateString();
        $endStr = Carbon::parse($validated['end_date'], 'Asia/Qatar')->toDateString();

        if ($this->hasOverlap($staff->id, $startStr, $endStr)) {
            return $this->errorResponse(
                $request,
                'You already have a pending or approved leave overlapping those dates.',
                422,
                'start_date'
            );
        }

        $certPath = null;
        if ($validated['leave_type'] === StaffLeave::TYPE_SICK && $request->hasFile('medical_certificate')) {
            $file = $request->file('medical_certificate');
            $ext = strtolower($file->getClientOriginalExtension() ?: $file->extension() ?: 'bin');
            $certPath = Storage::disk('local')->putFileAs(
                'private/staff-leaves/' . $staff->id,
                $file,
                Str::uuid()->toString() . '.' . $ext
            );
        }

        $totalDays = StaffLeave::inclusiveDays($startStr, $endStr);

        $leave = StaffLeave::create([
            'staff_id' => $staff->id,
            'leave_type' => $validated['leave_type'],
            'start_date' => $startStr,
            'end_date' => $endStr,
            'total_days' => $totalDays,
            'reason' => isset($validated['reason']) && trim((string) $validated['reason']) !== ''
                ? trim((string) $validated['reason'])
                : null,
            'medical_certificate_path' => $certPath,
            'status' => StaffLeave::STATUS_PENDING,
        ]);

        $message = "{$leave->typeLabel()} request submitted ({$totalDays} " . ($totalDays === 1 ? 'day' : 'days') . ').';

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => $message,
                'leave' => $this->serializeLeave($leave),
            ], 201);
        }

        return redirect()->route('portal.profile', ['tab' => 'leaves'])->with('success', $message);
    }

    /**
     * Allow a leave-eligible employee to cancel their own pending leave request.
     */
    public function cancelPortalRequest(Request $request, StaffLeave $leave)
    {
        $user = $request->user();
        abort_unless($user && ($user->isAdmin() || $user->isLeaveEligible()), 403, 'Leave requests are only available for Salon Managers and Salon Staff.');

        Gate::authorize('cancel', $leave);

        if ($leave->status !== StaffLeave::STATUS_PENDING && !$user->isAdmin()) {
            return $this->errorResponse($request, 'Only pending leave requests can be cancelled.', 422);
        }

        $leave->update([
            'status' => StaffLeave::STATUS_CANCELLED,
        ]);

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'Leave request cancelled.',
                'leave' => $this->serializeLeave($leave->fresh()),
            ]);
        }

        return redirect()->route('portal.profile', ['tab' => 'leaves'])->with('success', 'Leave request cancelled.');
    }

    /**
     * Policy-protected download/preview of a leave medical certificate.
     * ONLY Admin or the exact employee owning the leave record may access; all others receive 403.
     */
    public function certificate(Request $request, StaffLeave $leave)
    {
        Gate::authorize('viewCertificate', $leave);

        if (!$leave->medical_certificate_path || !Storage::disk('local')->exists($leave->medical_certificate_path)) {
            abort(404, 'Medical certificate not found.');
        }

        $ext = pathinfo($leave->medical_certificate_path, PATHINFO_EXTENSION) ?: 'pdf';
        $filename = 'medical-certificate-leave-' . $leave->id . '.' . $ext;
        $mime = Storage::disk('local')->mimeType($leave->medical_certificate_path) ?: 'application/octet-stream';
        $headers = [
            'Content-Type' => $mime,
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store, max-age=0',
        ];

        if ($request->boolean('inline')) {
            return Storage::disk('local')->response($leave->medical_certificate_path, $filename, $headers, 'inline');
        }

        return Storage::disk('local')->download($leave->medical_certificate_path, $filename, $headers);
    }

    /**
     * Admin-only: detect existing non-cancelled Enhanced Calendar bookings assigned to this
     * staff member during the leave's [start_date, end_date] window.
     */
    public function conflicts(Request $request, StaffLeave $leave)
    {
        Gate::authorize('review', StaffLeave::class);

        $conflicts = self::findBookingConflicts(
            (int) $leave->staff_id,
            $leave->start_date->toDateString(),
            $leave->end_date->toDateString()
        );

        return response()->json([
            'success' => true,
            'leave_id' => $leave->id,
            'conflict_count' => count($conflicts),
            'conflicts' => $conflicts,
        ]);
    }

    /**
     * Admin-only: approve, reject, or revoke/cancel a leave request.
     */
    public function review(Request $request, StaffLeave $leave)
    {
        Gate::authorize('review', StaffLeave::class);

        $validated = $request->validate([
            'action' => ['required', Rule::in(['approve', 'reject', 'cancel'])],
            'admin_notes' => ['nullable', 'string', 'max:500'],
        ]);

        $newStatus = match ($validated['action']) {
            'approve' => StaffLeave::STATUS_APPROVED,
            'reject' => StaffLeave::STATUS_REJECTED,
            'cancel' => StaffLeave::STATUS_CANCELLED,
        };

        $conflicts = [];
        if ($newStatus === StaffLeave::STATUS_APPROVED) {
            if ($this->hasOverlap($leave->staff_id, $leave->start_date->toDateString(), $leave->end_date->toDateString(), $leave->id)) {
                return $this->errorResponse($request, 'This staff member already has another active leave overlapping those dates.', 422);
            }

            $conflicts = self::findBookingConflicts(
                (int) $leave->staff_id,
                $leave->start_date->toDateString(),
                $leave->end_date->toDateString()
            );
        }

        $leave->update([
            'status' => $newStatus,
            'reviewed_by' => $request->user()?->id,
            'reviewed_at' => now(),
            'admin_notes' => isset($validated['admin_notes']) && trim((string) $validated['admin_notes']) !== ''
                ? trim((string) $validated['admin_notes'])
                : $leave->admin_notes,
        ]);

        $verb = match ($newStatus) {
            StaffLeave::STATUS_APPROVED => 'approved',
            StaffLeave::STATUS_REJECTED => 'rejected',
            default => 'cancelled',
        };
        $staffName = $leave->staff?->name ?? 'Staff member';
        $message = "{$leave->typeLabel()} for {$staffName} {$verb}.";

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => $message,
                'leave' => $this->serializeLeave($leave->fresh(['staff', 'reviewer'])),
                'conflicts' => $conflicts,
            ]);
        }

        return redirect()->route('staffs.index', ['tab' => 'leaves'])->with('success', $message);
    }

    /**
     * Admin-only: manually log an already-approved Annual or Sick Leave for any leave-eligible employee.
     */
    public function storeAdminManual(Request $request)
    {
        Gate::authorize('review', StaffLeave::class);

        $validated = $request->validate([
            'staff_id' => ['required', 'integer', 'exists:staff,id'],
            'leave_type' => ['required', Rule::in([StaffLeave::TYPE_ANNUAL, StaffLeave::TYPE_SICK])],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'reason' => ['nullable', 'string', 'max:1000'],
            'admin_notes' => ['nullable', 'string', 'max:500'],
            'medical_certificate' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:10240'],
        ]);

        $staff = Staff::with('user')->findOrFail((int) $validated['staff_id']);
        if (!$staff->isLeaveEligible()) {
            return $this->errorResponse(
                $request,
                "{$staff->name} is a Remote / non-branch team member and is not eligible for Salon Annual/Sick Leave.",
                422,
                'staff_id'
            );
        }

        $startStr = Carbon::parse($validated['start_date'], 'Asia/Qatar')->toDateString();
        $endStr = Carbon::parse($validated['end_date'], 'Asia/Qatar')->toDateString();

        if ($this->hasOverlap($staff->id, $startStr, $endStr)) {
            return $this->errorResponse($request, "{$staff->name} already has a pending or approved leave overlapping those dates.", 422, 'start_date');
        }

        $certPath = null;
        if ($validated['leave_type'] === StaffLeave::TYPE_SICK && $request->hasFile('medical_certificate')) {
            $file = $request->file('medical_certificate');
            $ext = strtolower($file->getClientOriginalExtension() ?: $file->extension() ?: 'bin');
            $certPath = Storage::disk('local')->putFileAs(
                'private/staff-leaves/' . $staff->id,
                $file,
                Str::uuid()->toString() . '.' . $ext
            );
        }

        $totalDays = StaffLeave::inclusiveDays($startStr, $endStr);
        $conflicts = self::findBookingConflicts($staff->id, $startStr, $endStr);

        $leave = StaffLeave::create([
            'staff_id' => $staff->id,
            'leave_type' => $validated['leave_type'],
            'start_date' => $startStr,
            'end_date' => $endStr,
            'total_days' => $totalDays,
            'reason' => isset($validated['reason']) && trim((string) $validated['reason']) !== ''
                ? trim((string) $validated['reason'])
                : null,
            'medical_certificate_path' => $certPath,
            'status' => StaffLeave::STATUS_APPROVED,
            'reviewed_by' => $request->user()?->id,
            'reviewed_at' => now(),
            'admin_notes' => isset($validated['admin_notes']) && trim((string) $validated['admin_notes']) !== ''
                ? trim((string) $validated['admin_notes'])
                : 'Logged directly by management.',
        ]);

        $message = "Approved {$leave->typeLabel()} logged for {$staff->name} ({$totalDays} " . ($totalDays === 1 ? 'day' : 'days') . ').';

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => $message,
                'leave' => $this->serializeLeave($leave->load(['staff', 'reviewer'])),
                'conflicts' => $conflicts,
            ], 201);
        }

        return redirect()->route('staffs.index', ['tab' => 'leaves'])->with('success', $message);
    }

    /**
     * Find all active (non-cancelled, non-no-show) Enhanced Calendar appointments assigned to
     * $staffId overlapping [$startDate, $endDate] (inclusive).
     *
     * @return list<array{id: int, customer_name: string, date: string, time: string, branch: string, status: string}>
     */
    public static function findBookingConflicts(int $staffId, string $startDate, string $endDate): array
    {
        $rangeStart = Carbon::parse($startDate, 'Asia/Qatar')->startOfDay();
        $rangeEnd = Carbon::parse($endDate, 'Asia/Qatar')->endOfDay();

        $appointments = Appointment::query()
            ->whereNotIn('status', ['cancelled', 'no_show'])
            ->where(function ($q) use ($staffId) {
                $q->where('staff_id', $staffId)
                    ->orWhereHas('appointmentServices', fn ($sq) => $sq->where('staff_id', $staffId));
            })
            ->where(function ($q) use ($startDate, $endDate, $rangeStart, $rangeEnd) {
                $q->whereBetween('booking_date', [$startDate, $endDate])
                    ->orWhereBetween('appointment_datetime', [$rangeStart, $rangeEnd]);
            })
            ->orderBy('appointment_datetime')
            ->get(['id', 'customer_name', 'booking_date', 'start_time', 'appointment_datetime', 'branch', 'status']);

        return $appointments->map(function (Appointment $appt) {
            $dateLabel = $appt->booking_date
                ? Carbon::parse($appt->booking_date)->format('D, d M Y')
                : ($appt->appointment_datetime ? Carbon::parse($appt->appointment_datetime)->format('D, d M Y') : '—');
            $timeLabel = $appt->start_time
                ? Carbon::parse($appt->start_time)->format('g:i A')
                : ($appt->appointment_datetime ? Carbon::parse($appt->appointment_datetime)->format('g:i A') : 'Unscheduled');

            return [
                'id' => (int) $appt->id,
                'customer_name' => (string) ($appt->customer_name ?: 'Client'),
                'date' => $dateLabel,
                'time' => $timeLabel,
                'branch' => $appt->branch === 'wakrah' ? 'Al Wakrah' : 'Old Airport',
                'status' => (string) $appt->status,
            ];
        })->values()->all();
    }

    private function hasOverlap(int $staffId, string $startDate, string $endDate, ?int $excludeLeaveId = null): bool
    {
        return StaffLeave::query()
            ->where('staff_id', $staffId)
            ->activeRequest()
            ->when($excludeLeaveId !== null, fn ($q) => $q->where('id', '!=', $excludeLeaveId))
            ->whereDate('start_date', '<=', $endDate)
            ->whereDate('end_date', '>=', $startDate)
            ->exists();
    }

    private function serializeLeave(StaffLeave $leave): array
    {
        return [
            'id' => $leave->id,
            'staff_id' => $leave->staff_id,
            'staff_name' => $leave->staff?->name,
            'leave_type' => $leave->leave_type,
            'type_label' => $leave->typeLabel(),
            'start_date' => $leave->start_date->toDateString(),
            'end_date' => $leave->end_date->toDateString(),
            'total_days' => (int) $leave->total_days,
            'reason' => $leave->reason,
            'status' => $leave->status,
            'status_label' => $leave->statusLabel(),
            'admin_notes' => $leave->admin_notes,
            'has_certificate' => !empty($leave->medical_certificate_path),
            'certificate_url' => !empty($leave->medical_certificate_path)
                ? route('staff-leaves.certificate', ['leave' => $leave->id, 'inline' => 1])
                : null,
        ];
    }

    private function errorResponse(Request $request, string $message, int $status = 422, string $field = 'leave')
    {
        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'success' => false,
                'message' => $message,
                'errors' => [$field => [$message]],
            ], $status);
        }

        return redirect()->back()->withErrors([$field => $message])->withInput();
    }
}
