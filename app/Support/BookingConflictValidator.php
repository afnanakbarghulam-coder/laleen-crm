<?php

namespace App\Support;

use App\Models\Appointment;
use App\Models\AppointmentService;
use App\Models\Customer;
use App\Models\Service;
use App\Models\Staff;
use App\Models\StaffBlock;
use App\Models\StaffTimeOff;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class BookingConflictValidator
{
    public const REASON_STAFF_OFF = 'staff_off';
    public const REASON_MISSING_SKILL = 'missing_skill';
    public const REASON_STAFF_BUSY = 'staff_busy';
    public const REASON_OUTSIDE_HOURS = 'outside_branch_hours';
    public const REASON_CROSS_BRANCH = 'cross_branch';

    public const PLACEHOLDER_SERVICE_NAMES = [
        '',
        'decide in salon',
        'choose in salon',
        'services tbd in salon',
    ];

    public const BRANCH_LABELS = [
        'old_airport' => 'Old Airport',
        'wakrah' => 'Al Wakrah',
    ];

    public function branchWorkingHours(string $branch, Carbon $date): array
    {
        $canonical = Customer::normalizeBranch($branch) ?? 'old_airport';
        $isFriday = $date->isFriday();

        return match ($canonical) {
            'wakrah' => $isFriday ? ['start' => '12:00', 'end' => '22:00'] : ['start' => '11:00', 'end' => '21:00'],
            default => $isFriday ? ['start' => '12:00', 'end' => '22:00'] : ['start' => '10:00', 'end' => '22:00'],
        };
    }

    public function staffMatchesBranch(?Staff $staff, string $branch): bool
    {
        if (!$staff || empty($staff->branch) || $staff->branch === 'both') {
            return true;
        }

        $canonicalTarget = Customer::normalizeBranch($branch) ?? $branch;
        $staffBranch = Customer::normalizeBranch($staff->branch) ?? $staff->branch;

        return $staffBranch === $canonicalTarget;
    }

    /**
     * Determine whether a staff member is off on a given date/time and why.
     * Used by both Calendar column headers (`enforceShiftHours = false`) and
     * slot validation (`enforceShiftHours = true`).
     *
     * Returns:
     * - off: bool
     * - kind: null | 'full_day' | 'shift_hours'
     * - reason: ?string (display label for calendar UI, e.g. 'On Leave', 'Sick', 'Weekly Off')
     */
    public function staffOffInfo(Staff $staff, Carbon $date, ?int $duration = null, bool $enforceShiftHours = false): array
    {
        $rawAvail = strtolower(trim((string) ($staff->availability_status ?? 'present')));

        if ($rawAvail === 'sick') {
            if ($staff->off_from && $staff->off_to) {
                if ($date->copy()->startOfDay()->between(Carbon::parse($staff->off_from)->startOfDay(), Carbon::parse($staff->off_to)->endOfDay())) {
                    return ['off' => true, 'kind' => 'full_day', 'reason' => 'Sick'];
                }
            } else {
                return ['off' => true, 'kind' => 'full_day', 'reason' => 'Sick'];
            }
        }

        $onDutyStatuses = ['', 'present', 'available', 'active', 'on-duty', 'on_duty'];

        if ($staff->off_from && $staff->off_to) {
            if ($date->copy()->startOfDay()->between(Carbon::parse($staff->off_from)->startOfDay(), Carbon::parse($staff->off_to)->endOfDay())) {
                $label = in_array($rawAvail, ['on-leave', 'on_leave'], true) ? 'On Leave' : 'Unavailable';
                return ['off' => true, 'kind' => 'full_day', 'reason' => $label];
            }
        } elseif (!in_array($rawAvail, $onDutyStatuses, true)) {
            $label = match ($rawAvail) {
                'on-leave', 'on_leave' => 'On Leave',
                'absent' => 'Absent',
                'off', 'off-duty', 'off_duty' => 'Off Duty',
                default => ucwords(str_replace(['_', '-'], ' ', $rawAvail)),
            };
            return ['off' => true, 'kind' => 'full_day', 'reason' => $label];
        }

        $dateStr = $date->toDateString();
        $timeOff = $staff->relationLoaded('timeOffs')
            ? $staff->timeOffs->first(fn ($t) => $t->coversDate($date))
            : StaffTimeOff::where('staff_id', $staff->id)
                ->whereDate('start_date', '<=', $dateStr)
                ->whereDate('end_date', '>=', $dateStr)
                ->first();

        if ($timeOff) {
            $reasonLabel = match ($timeOff->reason) {
                'on-leave' => 'On Leave',
                'sick' => 'Sick',
                'unpaid' => 'Unpaid Leave',
                default => $timeOff->reason ?: 'Time Off',
            };
            return ['off' => true, 'kind' => 'full_day', 'reason' => $reasonLabel];
        }

        if (!empty($staff->weekly_off)) {
            $weeklyOff = is_array($staff->weekly_off) ? $staff->weekly_off : (json_decode((string) $staff->weekly_off, true) ?: []);
            $lowerWeekly = array_map(static fn ($d) => strtolower(trim((string) $d)), (array) $weeklyOff);
            if (in_array(strtolower($date->format('l')), $lowerWeekly, true) || in_array(strtolower($date->format('D')), $lowerWeekly, true)) {
                return ['off' => true, 'kind' => 'full_day', 'reason' => 'Weekly Off'];
            }
        }

        if (!$staff->relationLoaded('shiftPatterns')) {
            $staff->load('shiftPatterns.blocks');
        }

        $pattern = $staff->activePatternFor($date);
        if ($pattern) {
            if (!$pattern->relationLoaded('blocks')) {
                $pattern->load('blocks');
            }
            $dayBlocks = $pattern->blocks->where('day_of_week', $date->dayOfWeek)->sortBy('start_time');
            if ($dayBlocks->isEmpty()) {
                return ['off' => true, 'kind' => 'full_day', 'reason' => 'Off Shift'];
            }

            if ($enforceShiftHours) {
                $reqStart = $date->copy();
                $reqEnd = $date->copy()->addMinutes(max((int) ($duration ?? 30), 1));
                $fitsBlock = $dayBlocks->contains(function ($block) use ($dateStr, $reqStart, $reqEnd) {
                    $bStart = Carbon::parse($dateStr . ' ' . $block->start_time);
                    $bEnd = Carbon::parse($dateStr . ' ' . $block->end_time);
                    return $reqStart->gte($bStart) && $reqEnd->lte($bEnd);
                });

                if (!$fitsBlock) {
                    return ['off' => true, 'kind' => 'shift_hours', 'reason' => 'Outside scheduled shift'];
                }
            }
        } elseif ($enforceShiftHours && is_array($staff->working_hours) && !empty($staff->working_hours['start']) && !empty($staff->working_hours['end'])) {
            $reqStart = $date->copy();
            $reqEnd = $date->copy()->addMinutes(max((int) ($duration ?? 30), 1));
            $wStart = Carbon::parse($dateStr . ' ' . $staff->working_hours['start']);
            $wEnd = Carbon::parse($dateStr . ' ' . $staff->working_hours['end']);
            if ($wEnd->lte($wStart)) {
                $wEnd->addDay();
            }
            if ($reqStart->lt($wStart) || $reqEnd->gt($wEnd)) {
                return ['off' => true, 'kind' => 'shift_hours', 'reason' => 'Outside working hours'];
            }
        }

        return ['off' => false, 'kind' => null, 'reason' => null];
    }

    /**
     * Find an overlapping StaffBlock on $start->toDateString() for [$start, $start + $duration].
     */
    public function findOverlappingStaffBlock(int $staffId, Carbon $start, int $duration): ?array
    {
        $end = $start->copy()->addMinutes(max($duration, 1));
        $dateStr = $start->toDateString();

        $blocks = StaffBlock::where('staff_id', $staffId)
            ->whereDate('date', $dateStr)
            ->orderBy('start_time')
            ->get();

        foreach ($blocks as $block) {
            $blockStart = Carbon::parse($dateStr . ' ' . $block->start_time);
            $blockEnd = Carbon::parse($dateStr . ' ' . $block->end_time);

            if ($start->lt($blockEnd) && $end->gt($blockStart)) {
                return [
                    'block' => $block,
                    'start' => $blockStart,
                    'end' => $blockEnd,
                ];
            }
        }

        return null;
    }

    /**
     * Return concrete service names from $serviceNames that $staffId is NOT trained for.
     * Placeholder names ("Decide in Salon", "Choose in Salon", "Services TBD in Salon") are skipped.
     */
    public function unskilledServices(int $staffId, array $serviceNames): array
    {
        $concrete = $this->filterConcreteServiceNames($serviceNames);
        if (empty($concrete)) {
            return [];
        }

        $qualified = Service::whereIn('name', $concrete)
            ->whereHas('staff', fn ($q) => $q->where('staff.id', $staffId))
            ->pluck('name')
            ->all();

        $qualifiedLower = array_map(static fn ($n) => strtolower(trim((string) $n)), $qualified);

        return array_values(array_filter(
            $concrete,
            static fn ($name) => !in_array(strtolower(trim($name)), $qualifiedLower, true)
        ));
    }

    /**
     * Find other active staff at $branch who possess ALL of the specified $serviceNames skills.
     */
    public function eligibleBranchStaffNames(string $branch, array $serviceNames, ?int $excludeStaffId = null): array
    {
        $concrete = $this->filterConcreteServiceNames($serviceNames);
        if (empty($concrete)) {
            return [];
        }

        $serviceIds = Service::whereIn('name', $concrete)->pluck('id');
        if ($serviceIds->count() !== count($concrete)) {
            return [];
        }

        $canonicalBranch = Customer::normalizeBranch($branch) ?? 'old_airport';
        $branchVariants = array_values(array_unique(array_merge(
            Customer::branchMatchValues($canonicalBranch),
            ['both']
        )));

        return Staff::active()
            ->when($excludeStaffId, fn ($q) => $q->where('id', '!=', $excludeStaffId))
            ->whereIn('branch', $branchVariants)
            ->whereHas('services', function ($q) use ($serviceIds) {
                $q->whereIn('services.id', $serviceIds);
            }, '=', $serviceIds->count())
            ->orderBy('name')
            ->pluck('name')
            ->all();
    }

    /**
     * Find an overlapping active appointment (ignoring Cancelled and No Show) for $staffId.
     * Always excludes $ignoreAppointmentId and optional $ignoreAppointmentServiceId.
     *
     * Returns null when free, or ['start' => Carbon, 'end' => Carbon, 'appointment_id' => int].
     */
    public function findOverlappingAppointment(
        int $staffId,
        Carbon $newStart,
        int $newDuration,
        ?int $ignoreAppointmentId = null,
        ?int $ignoreAppointmentServiceId = null
    ): ?array {
        $newEnd = $newStart->copy()->addMinutes(max($newDuration, 1));
        $dateStr = $newStart->toDateString();

        // 1. Active per-service lines (appointment_services)
        $lineQuery = AppointmentService::query()
            ->where('staff_id', $staffId)
            ->where('is_pending', false)
            ->whereNotNull('start_time')
            ->whereDate('start_time', $dateStr)
            ->whereHas('appointment', function ($q) {
                $q->whereNotIn('status', ['cancelled', 'no_show'])
                    ->whereNotNull('start_time');
            })
            ->orderBy('start_time');

        if ($ignoreAppointmentId) {
            $lineQuery->where('appointment_id', '!=', $ignoreAppointmentId);
        }

        if ($ignoreAppointmentServiceId) {
            $lineQuery->where('id', '!=', $ignoreAppointmentServiceId);
        }

        foreach ($lineQuery->get() as $line) {
            $existingStart = Carbon::parse($line->start_time);
            $existingDuration = (int) ($line->duration ?: 30);
            $existingEnd = $existingStart->copy()->addMinutes($existingDuration);

            if ($newStart->lt($existingEnd) && $newEnd->gt($existingStart)) {
                return [
                    'appointment_id' => (int) $line->appointment_id,
                    'start' => $existingStart,
                    'end' => $existingEnd,
                ];
            }
        }

        // 2. Parent appointments with 0 active appointment_services rows (Combo "Choose in Salon" / "Decide in Salon" holds)
        $apptQuery = Appointment::where('staff_id', $staffId)
            ->whereNotIn('status', ['cancelled', 'no_show'])
            ->whereNotNull('start_time')
            ->whereDate('appointment_datetime', $dateStr)
            ->whereDoesntHave('appointmentServices', fn ($q) => $q->where('is_pending', false))
            ->with(['combo', 'clientPackages'])
            ->orderBy('appointment_datetime');

        if ($ignoreAppointmentId) {
            $apptQuery->where('id', '!=', $ignoreAppointmentId);
        }

        foreach ($apptQuery->get() as $appointment) {
            $existingStart = Carbon::parse($appointment->appointment_datetime);
            $rawSvc = strtolower(trim((string) $appointment->service_name));
            $isComboHold = ($appointment->combo_id || $appointment->display_combo_name)
                && in_array($rawSvc, self::PLACEHOLDER_SERVICE_NAMES, true);
            $isDecideHold = (bool) $appointment->decide_in_salon
                || (!$isComboHold && $rawSvc === 'decide in salon');

            if ($isComboHold) {
                $existingDuration = (int) ($appointment->hold_duration ?: ($appointment->combo?->effective_duration ?? 60));
            } elseif ($isDecideHold) {
                $existingDuration = $appointment->effective_hold_duration;
            } else {
                $existingDuration = $this->totalServiceDuration(explode(',', (string) $appointment->service_name));
            }
            $existingEnd = $existingStart->copy()->addMinutes($existingDuration);

            if ($newStart->lt($existingEnd) && $newEnd->gt($existingStart)) {
                return [
                    'appointment_id' => (int) $appointment->id,
                    'start' => $existingStart,
                    'end' => $existingEnd,
                ];
            }
        }

        return null;
    }

    /**
     * Boolean convenience check (includes StaffBlock + overlapping appointments) used by availableStaff().
     */
    public function staffHasAnyConflict(
        ?int $staffId,
        Carbon $newStart,
        int $newDuration,
        ?int $ignoreAppointmentId = null,
        ?int $ignoreAppointmentServiceId = null
    ): bool {
        if (!$staffId) {
            return false;
        }

        if ($this->findOverlappingStaffBlock($staffId, $newStart, $newDuration) !== null) {
            return true;
        }

        return $this->findOverlappingAppointment(
            $staffId,
            $newStart,
            $newDuration,
            $ignoreAppointmentId,
            $ignoreAppointmentServiceId
        ) !== null;
    }

    /**
     * Centralized validation across booking create, edit, reschedule, and drawer service updates.
     *
     * Each slice in $slices is:
     * [
     *   'staff_id' => ?int,
     *   'start' => ?Carbon,       // null when $isUnscheduled = true
     *   'duration' => int,
     *   'service_names' => array, // empty or placeholder when Decide/Choose in Salon
     * ]
     *
     * @param string $mode 'book' | 'reschedule'
     */
    public function validateBookingOperation(
        string $branch,
        ?Carbon $bookingDate,
        array $slices,
        string $mode = 'book',
        bool $isUnscheduled = false,
        ?int $ignoreAppointmentId = null,
        ?int $ignoreAppointmentServiceId = null
    ): ?array {
        $canonicalBranch = Customer::normalizeBranch($branch) ?? 'old_airport';
        $prefix = $mode === 'reschedule' ? 'Cannot reschedule' : 'Cannot book';
        $staffCache = [];

        $resolveStaff = function (?int $id) use (&$staffCache): ?Staff {
            if (!$id) {
                return null;
            }
            if (!array_key_exists($id, $staffCache)) {
                $staffCache[$id] = Staff::with(['shiftPatterns.blocks', 'timeOffs'])->find($id);
            }
            return $staffCache[$id];
        };

        // 1. Branch working-hours check (skipped when unscheduled)
        if (!$isUnscheduled) {
            foreach ($slices as $slice) {
                /** @var ?Carbon $sliceStart */
                $sliceStart = $slice['start'] ?? null;
                if (!$sliceStart) {
                    continue;
                }
                $duration = max((int) ($slice['duration'] ?? 30), 1);
                $sliceEnd = $sliceStart->copy()->addMinutes($duration);

                $workingHours = $this->branchWorkingHours($canonicalBranch, $sliceStart);
                $startBranch = Carbon::createFromFormat('H:i', $workingHours['start'])
                    ->setDate($sliceStart->year, $sliceStart->month, $sliceStart->day);
                $endBranch = Carbon::createFromFormat('H:i', $workingHours['end'])
                    ->setDate($sliceStart->year, $sliceStart->month, $sliceStart->day);

                if ($sliceStart->lt($startBranch) || $sliceStart->gt($endBranch) || $sliceEnd->gt($endBranch)) {
                    $branchLabel = self::BRANCH_LABELS[$canonicalBranch] ?? $canonicalBranch;
                    return [
                        'reason' => self::REASON_OUTSIDE_HOURS,
                        'field' => 'appointment_datetime',
                        'message' => "{$prefix}: Appointment must be within {$branchLabel} working hours ({$startBranch->format('g:i A')} – {$endBranch->format('g:i A')}).",
                    ];
                }
            }
        }

        // 2. Per-slice staff checks (skipped whenever a slice's staff_id is null/unassigned)
        foreach ($slices as $slice) {
            $staffId = !empty($slice['staff_id']) ? (int) $slice['staff_id'] : null;
            if (!$staffId) {
                continue;
            }

            $staff = $resolveStaff($staffId);
            if (!$staff) {
                continue;
            }

            // 2a. Branch membership check
            if (!$this->staffMatchesBranch($staff, $canonicalBranch)) {
                $branchLabel = self::BRANCH_LABELS[$canonicalBranch] ?? $canonicalBranch;
                return [
                    'reason' => self::REASON_CROSS_BRANCH,
                    'field' => 'staff_id',
                    'message' => "{$prefix}: {$staff->name} is not assigned to {$branchLabel}.",
                ];
            }

            /** @var ?Carbon $sliceStart */
            $sliceStart = $slice['start'] ?? null;
            $duration = max((int) ($slice['duration'] ?? 30), 1);
            $effectiveMoment = $sliceStart ?: ($bookingDate ? $bookingDate->copy()->startOfDay() : now()->startOfDay());

            // 2b. Staff Off / Shift Hours / Blocked Slot (`staff_off`)
            $offInfo = $this->staffOffInfo($staff, $effectiveMoment, $duration, !$isUnscheduled && $sliceStart !== null);
            if ($offInfo['off']) {
                if ($offInfo['kind'] === 'shift_hours' && $sliceStart !== null) {
                    $sliceEnd = $sliceStart->copy()->addMinutes($duration);
                    return [
                        'reason' => self::REASON_STAFF_OFF,
                        'field' => 'staff_id',
                        'message' => "{$prefix}: {$staff->name} is unavailable / blocked from {$sliceStart->format('g:i A')} to {$sliceEnd->format('g:i A')}.",
                    ];
                }

                return [
                    'reason' => self::REASON_STAFF_OFF,
                    'field' => 'staff_id',
                    'message' => "{$prefix}: {$staff->name} is off duty on {$effectiveMoment->format('d M Y')}.",
                ];
            }

            if (!$isUnscheduled && $sliceStart !== null) {
                $blockOverlap = $this->findOverlappingStaffBlock($staffId, $sliceStart, $duration);
                if ($blockOverlap !== null) {
                    return [
                        'reason' => self::REASON_STAFF_OFF,
                        'field' => 'staff_id',
                        'message' => "{$prefix}: {$staff->name} is unavailable / blocked from {$blockOverlap['start']->format('g:i A')} to {$blockOverlap['end']->format('g:i A')}.",
                    ];
                }
            }

            // 2c. Staff Service Skill Eligibility (`missing_skill`)
            // Automatically skipped when service_names is empty or placeholder (e.g. Combo "Choose Services in Salon")
            $serviceNames = (array) ($slice['service_names'] ?? []);
            $unskilled = $this->unskilledServices($staffId, $serviceNames);
            if (!empty($unskilled)) {
                $missingLabel = implode(', ', $unskilled);
                $eligibleNames = $this->eligibleBranchStaffNames($canonicalBranch, $unskilled, $staffId);
                $eligibleSuffix = !empty($eligibleNames)
                    ? ' Eligible staff: ' . implode(', ', $eligibleNames) . '.'
                    : '';

                return [
                    'reason' => self::REASON_MISSING_SKILL,
                    'field' => 'staff_id',
                    'message' => "{$prefix}: {$staff->name} does not have the required skill for {$missingLabel}.{$eligibleSuffix}",
                ];
            }

            // 2d. Overlapping Active Appointment (`staff_busy`) — skipped when unscheduled
            if (!$isUnscheduled && $sliceStart !== null) {
                $busyOverlap = $this->findOverlappingAppointment(
                    $staffId,
                    $sliceStart,
                    $duration,
                    $ignoreAppointmentId,
                    $ignoreAppointmentServiceId
                );

                if ($busyOverlap !== null) {
                    return [
                        'reason' => self::REASON_STAFF_BUSY,
                        'field' => 'staff_id',
                        'message' => "{$prefix}: {$staff->name} is already busy with another booking from {$busyOverlap['start']->format('g:i A')} to {$busyOverlap['end']->format('g:i A')}.",
                    ];
                }
            }
        }

        return null;
    }

    /**
     * Format a standardized HTTP 422 JSON response (or HTML redirect fallback for non-AJAX forms).
     */
    public function toResponse(Request $request, array $conflict, bool $alwaysJson = false): JsonResponse|RedirectResponse
    {
        $reason = $conflict['reason'] ?? 'conflict';
        $field = $conflict['field'] ?? 'staff_id';
        $message = $conflict['message'] ?? 'Unable to save booking due to a schedule conflict.';

        if ($alwaysJson || $request->expectsJson() || $request->ajax()) {
            return response()->json([
                'success' => false,
                'reason' => $reason,
                'code' => $reason,
                'message' => $message,
                'errors' => [
                    $field => [$message],
                    $reason => [$message],
                ],
            ], 422);
        }

        return redirect()->back()
            ->withInput()
            ->with('error', $message)
            ->withErrors([
                $field => $message,
                $reason => $message,
            ]);
    }

    public function filterConcreteServiceNames(array $serviceNames): array
    {
        $filtered = [];
        foreach ($serviceNames as $raw) {
            $clean = trim((string) $raw);
            if ($clean === '' || in_array(strtolower($clean), self::PLACEHOLDER_SERVICE_NAMES, true)) {
                continue;
            }
            if (!in_array($clean, $filtered, true)) {
                $filtered[] = $clean;
            }
        }

        return $filtered;
    }

    public function totalServiceDuration(array $serviceNames): int
    {
        $concrete = $this->filterConcreteServiceNames($serviceNames);
        if (empty($concrete)) {
            return 30;
        }

        return (int) (Service::whereIn('name', $concrete)->sum('duration') ?: 30);
    }
}
