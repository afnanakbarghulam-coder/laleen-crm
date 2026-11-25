<?php

namespace App\Support;

use App\Models\Appointment;
use App\Models\AppointmentService;
use App\Models\Customer;
use App\Models\Service;
use App\Models\Staff;
use App\Models\StaffAttendance;
use App\Models\StaffBlock;
use App\Models\StaffLeave;
use App\Models\StaffTimeOff;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;

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
        if (!$staff || $staff->isRemote()) {
            return false;
        }

        $linkedUser = $staff->relationLoaded('user') ? $staff->user : ($staff->user_id ? $staff->user : null);
        if ($linkedUser && strtolower(trim((string) $linkedUser->role)) === 'agent') {
            return false;
        }

        if ($staff->branch === 'both') {
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
    public function staffOffInfo(
        Staff $staff,
        Carbon $date,
        ?int $duration = null,
        bool $enforceShiftHours = false,
        bool $allowOvertimeSpillover = true
    ): array {
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

        $dateStr = $date->copy()->timezone('Asia/Qatar')->toDateString();

        if (Schema::hasTable('staff_leaves')) {
            $approvedLeave = $staff->relationLoaded('leaves')
                ? $staff->leaves->first(fn (StaffLeave $l) => $l->status === StaffLeave::STATUS_APPROVED && $l->coversDate($date))
                : StaffLeave::query()
                    ->where('staff_id', $staff->id)
                    ->approved()
                    ->coveringDate($date)
                    ->first();

            if ($approvedLeave) {
                return [
                    'off' => true,
                    'kind' => 'on_leave',
                    'reason' => 'On Leave',
                ];
            }
        }

        if (Schema::hasTable('staff_attendances')) {
            $absentRecord = $staff->relationLoaded('attendances')
                ? $staff->attendances->first(fn (StaffAttendance $att) => $att->status === StaffAttendance::STATUS_ABSENT
                    && $att->attendance_date
                    && $att->attendance_date->toDateString() === $dateStr)
                : StaffAttendance::query()
                    ->where('staff_id', $staff->id)
                    ->whereDate('attendance_date', $dateStr)
                    ->where('status', StaffAttendance::STATUS_ABSENT)
                    ->first();

            if ($absentRecord) {
                return [
                    'off' => true,
                    'kind' => 'absent',
                    'reason' => 'Absent',
                ];
            }
        }

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
                $fitsBlock = $dayBlocks->contains(function ($block) use ($dateStr, $reqStart, $reqEnd, $allowOvertimeSpillover) {
                    $bStart = Carbon::parse($dateStr . ' ' . $block->start_time);
                    $bEnd = Carbon::parse($dateStr . ' ' . $block->end_time);
                    if ($bEnd->lte($bStart)) {
                        $bEnd->addDay();
                    }

                    if ($allowOvertimeSpillover) {
                        return $reqStart->gte($bStart) && $reqStart->lt($bEnd);
                    }

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
            $outside = $allowOvertimeSpillover
                ? ($reqStart->lt($wStart) || $reqStart->gte($wEnd))
                : ($reqStart->lt($wStart) || $reqEnd->gt($wEnd));
            if ($outside) {
                return ['off' => true, 'kind' => 'shift_hours', 'reason' => 'Outside working hours'];
            }
        }

        return ['off' => false, 'kind' => null, 'reason' => null];
    }

    /**
     * Evaluate a specialist's shift window for [$apptStart, $apptStart + $duration] using full Carbon datetimes
     * (safely handling late-running or midnight-crossing services).
     *
     * Returns:
     * - status: 'in_shift' | 'overtime' | 'off'
     * - kind: null | 'full_day' | 'shift_hours'
     * - reason: ?string
     * - is_overtime: bool
     * - overtime_minutes: int
     * - shift_end: ?string (HH:MM)
     * - shift_end_at: ?Carbon
     */
    public function evaluateShiftWindow(Staff $staff, Carbon $apptStart, int $duration, string $branch): array
    {
        $fullDayOff = $this->staffOffInfo($staff, $apptStart, $duration, false);
        if ($fullDayOff['off']) {
            return [
                'status' => 'off',
                'kind' => $fullDayOff['kind'] ?? 'full_day',
                'reason' => $fullDayOff['reason'] ?? 'Day Off',
                'is_overtime' => false,
                'overtime_minutes' => 0,
                'shift_end' => null,
                'shift_end_at' => null,
            ];
        }

        $apptEnd = $apptStart->copy()->addMinutes(max($duration, 1));
        $windows = $this->staffShiftWindowsForDate($staff, $apptStart);

        if (is_array($windows)) {
            if (empty($windows)) {
                return [
                    'status' => 'off',
                    'kind' => 'full_day',
                    'reason' => 'Off Shift',
                    'is_overtime' => false,
                    'overtime_minutes' => 0,
                    'shift_end' => null,
                    'shift_end_at' => null,
                ];
            }
            $effectiveWindows = $windows;
        } else {
            // Staff has no custom pattern / working_hours -> falls back to branch shift hours
            $branchHours = $this->branchWorkingHours($branch, $apptStart);
            [$bsh, $bsm] = array_map('intval', explode(':', $branchHours['start']));
            [$beh, $bem] = array_map('intval', explode(':', $branchHours['end']));
            $endMins = $beh * 60 + $bem;
            if ($endMins <= ($bsh * 60 + $bsm)) {
                $endMins += 1440;
            }
            $effectiveWindows = [[
                'start' => $branchHours['start'],
                'end' => $branchHours['end'],
                'start_minutes' => $bsh * 60 + $bsm,
                'end_minutes' => $endMins,
            ]];
        }

        $dayStart = $apptStart->copy()->startOfDay();
        $bestOvertime = null;

        foreach ($effectiveWindows as $w) {
            $wStart = $dayStart->copy()->addMinutes((int) $w['start_minutes']);
            $wEnd = $dayStart->copy()->addMinutes((int) $w['end_minutes']);
            if ($wEnd->lte($wStart)) {
                $wEnd->addDay();
            }

            // Tier 1: Strictly In Shift (shift_start <= appt_start AND appt_end <= shift_end)
            if ($apptStart->gte($wStart) && $apptEnd->lte($wEnd)) {
                return [
                    'status' => 'in_shift',
                    'kind' => null,
                    'reason' => null,
                    'is_overtime' => false,
                    'overtime_minutes' => 0,
                    'shift_end' => $w['end'],
                    'shift_end_at' => $wEnd,
                ];
            }

            // Tier 2: Overtime Spillover (shift_start <= appt_start < shift_end AND appt_end > shift_end)
            if ($apptStart->gte($wStart) && $apptStart->lt($wEnd) && $apptEnd->gt($wEnd)) {
                $otMinutes = max(1, (int) round($wEnd->diffInMinutes($apptEnd)));
                if ($bestOvertime === null || $otMinutes < $bestOvertime['overtime_minutes']) {
                    $bestOvertime = [
                        'status' => 'overtime',
                        'kind' => null,
                        'reason' => null,
                        'is_overtime' => true,
                        'overtime_minutes' => $otMinutes,
                        'shift_end' => $w['end'],
                        'shift_end_at' => $wEnd,
                    ];
                }
            }
        }

        if ($bestOvertime !== null) {
            return $bestOvertime;
        }

        return [
            'status' => 'off',
            'kind' => 'shift_hours',
            'reason' => 'Outside scheduled shift',
            'is_overtime' => false,
            'overtime_minutes' => 0,
            'shift_end' => null,
            'shift_end_at' => null,
        ];
    }

    /**
     * Recompute and persist `is_overtime` and `overtime_minutes` across an Appointment
     * and every active AppointmentService line item. Automatically clears overtime flags
     * when rescheduled/edited back inside normal shift hours or when unscheduled/cancelled.
     */
    public function syncAppointmentOvertime(Appointment $appointment): void
    {
        if (
            ! \Illuminate\Support\Facades\Schema::hasColumn('appointments', 'is_overtime')
            || ! \Illuminate\Support\Facades\Schema::hasColumn('appointment_services', 'is_overtime')
        ) {
            return;
        }

        $appointment->load([
            'staff.shiftPatterns.blocks',
            'staff.timeOffs',
            'appointmentServices.staff.shiftPatterns.blocks',
            'appointmentServices.staff.timeOffs',
        ]);

        $isInactiveOrUnscheduled = $appointment->start_time === null
            || in_array(strtolower((string) $appointment->status), ['cancelled', 'no_show'], true);

        $branch = (string) ($appointment->branch ?: 'old_airport');
        $activeLines = $appointment->appointmentServices->where('is_pending', false);
        $maxOvertimeMinutes = 0;

        foreach ($appointment->appointmentServices as $line) {
            $lineIsOt = false;
            $lineOtMins = 0;

            if (! $isInactiveOrUnscheduled && ! $line->is_pending) {
                $lineStaff = $line->staff ?: $appointment->staff;
                $lineStart = $line->start_time
                    ? Carbon::parse($line->start_time)
                    : ($appointment->appointment_datetime ? Carbon::parse($appointment->appointment_datetime) : null);
                $lineDuration = max(1, (int) ($line->duration ?: 30));

                if ($lineStaff && $lineStart) {
                    $eval = $this->evaluateShiftWindow($lineStaff, $lineStart, $lineDuration, $branch);
                    if ($eval['status'] === 'overtime') {
                        $lineIsOt = true;
                        $lineOtMins = (int) $eval['overtime_minutes'];
                        $maxOvertimeMinutes = max($maxOvertimeMinutes, $lineOtMins);
                    }
                }
            }

            if ((bool) $line->is_overtime !== $lineIsOt || (int) $line->overtime_minutes !== $lineOtMins) {
                $line->forceFill([
                    'is_overtime' => $lineIsOt,
                    'overtime_minutes' => $lineOtMins,
                ])->saveQuietly();
            }
        }

        // Bare holds with 0 active appointment_services lines (e.g. Decide in Salon / Combo TBD)
        if (! $isInactiveOrUnscheduled && $activeLines->isEmpty() && $appointment->staff && $appointment->appointment_datetime) {
            $holdStart = Carbon::parse($appointment->appointment_datetime);
            $holdDuration = (int) ($appointment->hold_duration ?: 60);
            $eval = $this->evaluateShiftWindow($appointment->staff, $holdStart, $holdDuration, $branch);
            if ($eval['status'] === 'overtime') {
                $maxOvertimeMinutes = max($maxOvertimeMinutes, (int) $eval['overtime_minutes']);
            }
        }

        $apptIsOt = $maxOvertimeMinutes > 0;
        if ((bool) $appointment->is_overtime !== $apptIsOt || (int) $appointment->overtime_minutes !== $maxOvertimeMinutes) {
            $appointment->forceFill([
                'is_overtime' => $apptIsOt,
                'overtime_minutes' => $maxOvertimeMinutes,
            ])->saveQuietly();
        }
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
     * Return concrete active shift windows on $date for $staff, or null when unrestricted beyond branch hours.
     *
     * @return array<int, array{start: string, end: string, start_minutes: int, end_minutes: int}>|null
     */
    public function staffShiftWindowsForDate(Staff $staff, Carbon $date): ?array
    {
        if (!$staff->relationLoaded('shiftPatterns')) {
            $staff->load('shiftPatterns.blocks');
        }

        $pattern = $staff->activePatternFor($date);
        if ($pattern) {
            if (!$pattern->relationLoaded('blocks')) {
                $pattern->load('blocks');
            }
            $dayBlocks = $pattern->blocks->where('day_of_week', $date->dayOfWeek)->sortBy('start_time')->values();
            if ($dayBlocks->isEmpty()) {
                return [];
            }

            return $dayBlocks->map(function ($b) {
                $s = substr((string) $b->start_time, 0, 5);
                $e = substr((string) $b->end_time, 0, 5);
                [$sh, $sm] = array_map('intval', explode(':', $s));
                [$eh, $em] = array_map('intval', explode(':', $e));
                $endMins = $eh * 60 + $em;
                if ($endMins <= ($sh * 60 + $sm)) {
                    $endMins += 1440;
                }

                return [
                    'start' => $s,
                    'end' => $e,
                    'start_minutes' => $sh * 60 + $sm,
                    'end_minutes' => $endMins,
                ];
            })->all();
        }

        if (is_array($staff->working_hours) && !empty($staff->working_hours['start']) && !empty($staff->working_hours['end'])) {
            $s = substr((string) $staff->working_hours['start'], 0, 5);
            $e = substr((string) $staff->working_hours['end'], 0, 5);
            [$sh, $sm] = array_map('intval', explode(':', $s));
            [$eh, $em] = array_map('intval', explode(':', $e));
            $endMins = $eh * 60 + $em;
            if ($endMins <= ($sh * 60 + $sm)) {
                $endMins += 1440;
            }

            return [[
                'start' => $s,
                'end' => $e,
                'start_minutes' => $sh * 60 + $sm,
                'end_minutes' => $endMins,
            ]];
        }

        return null;
    }

    /**
     * Deterministic slice timeline calculator from booking start time, sort_order, duration, and is_parallel.
     *
     * @param  array<int, array<string, mixed>>  $items
     * @return array{slices: array<int, array<string, mixed>>, min_start: ?Carbon, max_end: ?Carbon, span_minutes: int}
     */
    public static function computeSliceTimeline(?Carbon $bookingStart, array $items): array
    {
        if (empty($items)) {
            return [
                'slices' => [],
                'min_start' => $bookingStart ? $bookingStart->copy() : null,
                'max_end' => $bookingStart ? $bookingStart->copy() : null,
                'span_minutes' => 0,
            ];
        }

        $indexed = [];
        foreach (array_values($items) as $origIdx => $item) {
            $hasExplicitSort = array_key_exists('sort_order', $item)
                && $item['sort_order'] !== null
                && $item['sort_order'] !== '';
            $indexed[] = [
                '_orig_idx' => $origIdx,
                '_sort_key' => $hasExplicitSort ? (int) $item['sort_order'] : $origIdx,
                'item' => $item,
            ];
        }

        usort($indexed, static function (array $a, array $b): int {
            if ($a['_sort_key'] !== $b['_sort_key']) {
                return $a['_sort_key'] <=> $b['_sort_key'];
            }

            return $a['_orig_idx'] <=> $b['_orig_idx'];
        });

        $computed = [];
        $groupStartOffset = 0;
        $runningMaxEndOffset = 0;

        foreach ($indexed as $normalizedIdx => $wrapped) {
            $row = $wrapped['item'];
            $duration = max(1, (int) ($row['duration'] ?? 30));
            $isParallel = $normalizedIdx > 0 && !empty($row['is_parallel']);

            if ($normalizedIdx === 0) {
                $offsetStart = 0;
                $offsetEnd = $duration;
                $groupStartOffset = 0;
                $runningMaxEndOffset = $duration;
            } elseif ($isParallel) {
                $offsetStart = $groupStartOffset;
                $offsetEnd = $offsetStart + $duration;
                if ($offsetEnd > $runningMaxEndOffset) {
                    $runningMaxEndOffset = $offsetEnd;
                }
            } else {
                $offsetStart = $runningMaxEndOffset;
                $offsetEnd = $offsetStart + $duration;
                $groupStartOffset = $offsetStart;
                $runningMaxEndOffset = $offsetEnd;
            }

            $sliceStart = $bookingStart ? $bookingStart->copy()->addMinutes($offsetStart) : null;
            $sliceEnd = $bookingStart ? $bookingStart->copy()->addMinutes($offsetEnd) : null;

            $row['sort_order'] = $normalizedIdx;
            $row['is_parallel'] = $isParallel;
            $row['duration'] = $duration;
            $row['start'] = $sliceStart;
            $row['end'] = $sliceEnd;
            $row['offset_start_minutes'] = $offsetStart;
            $row['offset_end_minutes'] = $offsetEnd;

            $computed[] = $row;
        }

        return [
            'slices' => $computed,
            'min_start' => $bookingStart ? $bookingStart->copy() : null,
            'max_end' => $bookingStart ? $bookingStart->copy()->addMinutes($runningMaxEndOffset) : null,
            'span_minutes' => $runningMaxEndOffset,
        ];
    }

    /**
     * Check if at least 1 active staff member in $branch holds the skill for $serviceId.
     */
    public function branchHasMappedSkillForService(string $branch, int $serviceId): bool
    {
        $canonicalBranch = Customer::normalizeBranch($branch) ?? 'old_airport';
        $branchVariants = array_values(array_unique(array_merge(
            Customer::branchMatchValues($canonicalBranch),
            ['both']
        )));

        return Staff::active()
            ->whereIn('branch', $branchVariants)
            ->whereHas('services', fn ($q) => $q->where('services.id', $serviceId))
            ->exists();
    }

    /**
     * Return concrete service names from $serviceNames that $staffId is NOT trained for.
     * Placeholder names ("Decide in Salon", "Choose in Salon", "Services TBD in Salon") are skipped.
     * When $branch is provided and a catalog service has 0 skilled active staff mapped in that branch,
     * the graceful unmapped-skill safety fallback permits active branch staff so reception is never dead-ended.
     */
    public function unskilledServices(int $staffId, array $serviceNames, ?string $branch = null): array
    {
        $concrete = $this->filterConcreteServiceNames($serviceNames);
        if (empty($concrete)) {
            return [];
        }

        $catalogByLower = Service::whereIn('name', $concrete)
            ->get()
            ->keyBy(static fn (Service $s) => strtolower(trim((string) $s->name)));

        $qualified = Service::whereIn('name', $concrete)
            ->whereHas('staff', fn ($q) => $q->where('staff.id', $staffId))
            ->pluck('name')
            ->all();

        $qualifiedLower = array_map(static fn ($n) => strtolower(trim((string) $n)), $qualified);

        return array_values(array_filter(
            $concrete,
            function ($name) use ($qualifiedLower, $branch, $catalogByLower): bool {
                $lower = strtolower(trim((string) $name));
                if (in_array($lower, $qualifiedLower, true)) {
                    return false;
                }

                if ($branch !== null && $catalogByLower->has($lower)) {
                    /** @var Service $svcModel */
                    $svcModel = $catalogByLower->get($lower);
                    if (!$this->branchHasMappedSkillForService($branch, (int) $svcModel->id)) {
                        return false;
                    }
                }

                return true;
            }
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
        $windowStart = $newStart->copy()->subDay()->startOfDay();
        $windowEnd = $newEnd->copy()->endOfDay();

        // 1. Active per-service lines (appointment_services) across surrounding days (handles midnight spillover)
        $lineQuery = AppointmentService::query()
            ->where('staff_id', $staffId)
            ->where('is_pending', false)
            ->whereNotNull('start_time')
            ->whereBetween('start_time', [$windowStart, $windowEnd])
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
            ->whereBetween('appointment_datetime', [$windowStart, $windowEnd])
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

                // Allow overtime spillover past branch close as long as the service STARTS strictly within branch working hours
                if ($sliceStart->lt($startBranch) || $sliceStart->gte($endBranch)) {
                    $branchLabel = self::BRANCH_LABELS[$canonicalBranch] ?? $canonicalBranch;
                    return [
                        'reason' => self::REASON_OUTSIDE_HOURS,
                        'field' => 'appointment_datetime',
                        'message' => "{$prefix}: Appointment must start within {$branchLabel} working hours ({$startBranch->format('g:i A')} – {$endBranch->format('g:i A')}).",
                    ];
                }
            }
        }

        // 2. Per-slice staff checks (skipped whenever a slice's staff_id is null/unassigned)
        $seenSlicesByStaff = [];
        $normalizedSlices = array_values($slices);

        foreach ($normalizedSlices as $sliceIdx => $slice) {
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

            // 2a-ii. Intra-booking parallel / self-overlap guard on the same specialist
            if ($sliceIdx > 0 && !empty($slice['is_parallel'])) {
                $prevStaffId = !empty($normalizedSlices[$sliceIdx - 1]['staff_id'])
                    ? (int) $normalizedSlices[$sliceIdx - 1]['staff_id']
                    : null;
                if ($prevStaffId === $staffId) {
                    $windowSuffix = $sliceStart !== null
                        ? ' (' . $sliceStart->format('g:i A') . ' – ' . $sliceStart->copy()->addMinutes($duration)->format('g:i A') . ')'
                        : '';
                    return [
                        'reason' => self::REASON_STAFF_BUSY,
                        'field' => 'staff_id',
                        'message' => "{$prefix}: {$staff->name} cannot perform multiple services at the same time{$windowSuffix}.",
                    ];
                }
            }

            if (!$isUnscheduled && $sliceStart !== null) {
                $sliceEnd = $sliceStart->copy()->addMinutes($duration);
                foreach ($seenSlicesByStaff[$staffId] ?? [] as $earlierWindow) {
                    if ($sliceStart->lt($earlierWindow['end']) && $sliceEnd->gt($earlierWindow['start'])) {
                        return [
                            'reason' => self::REASON_STAFF_BUSY,
                            'field' => 'staff_id',
                            'message' => "{$prefix}: {$staff->name} cannot perform overlapping services at the same time ({$earlierWindow['start']->format('g:i A')} – {$earlierWindow['end']->format('g:i A')}).",
                        ];
                    }
                }
                $seenSlicesByStaff[$staffId][] = [
                    'start' => $sliceStart->copy(),
                    'end' => $sliceEnd->copy(),
                ];
            }

            // 2b. Staff Off / Shift Hours / Overtime Spillover / Blocked Slot (`staff_off`)
            // Overtime spillover (shift_start <= appt_start < shift_end AND appt_end > shift_end) is ALLOWED;
            // day off, before-shift-start, and at/after-shift-end remain strictly blocked.
            if (!$isUnscheduled && $sliceStart !== null) {
                $shiftEval = $this->evaluateShiftWindow($staff, $sliceStart, $duration, $canonicalBranch);
                if ($shiftEval['status'] === 'off') {
                    if ($shiftEval['kind'] === 'shift_hours') {
                        $sliceEnd = $sliceStart->copy()->addMinutes($duration);
                        return [
                            'reason' => self::REASON_STAFF_OFF,
                            'field' => 'staff_id',
                            'message' => "{$prefix}: {$staff->name} is unavailable / blocked from {$sliceStart->format('g:i A')} to {$sliceEnd->format('g:i A')}.",
                        ];
                    }

                    if ($shiftEval['kind'] === 'on_leave') {
                        return [
                            'reason' => self::REASON_STAFF_OFF,
                            'field' => 'staff_id',
                            'message' => "{$prefix}: {$staff->name} is on approved leave on {$effectiveMoment->format('d M Y')}.",
                        ];
                    }

                    if ($shiftEval['kind'] === 'absent') {
                        return [
                            'reason' => self::REASON_STAFF_OFF,
                            'field' => 'staff_id',
                            'message' => "{$prefix}: {$staff->name} is marked Absent on {$effectiveMoment->format('d M Y')}.",
                        ];
                    }

                    return [
                        'reason' => self::REASON_STAFF_OFF,
                        'field' => 'staff_id',
                        'message' => "{$prefix}: {$staff->name} is off duty on {$effectiveMoment->format('d M Y')}.",
                    ];
                }
            } else {
                $offInfo = $this->staffOffInfo($staff, $effectiveMoment, $duration, false);
                if ($offInfo['off']) {
                    $offMsg = match ($offInfo['kind'] ?? null) {
                        'on_leave' => "{$prefix}: {$staff->name} is on approved leave on {$effectiveMoment->format('d M Y')}.",
                        'absent' => "{$prefix}: {$staff->name} is marked Absent on {$effectiveMoment->format('d M Y')}.",
                        default => "{$prefix}: {$staff->name} is off duty on {$effectiveMoment->format('d M Y')}.",
                    };
                    return [
                        'reason' => self::REASON_STAFF_OFF,
                        'field' => 'staff_id',
                        'message' => $offMsg,
                    ];
                }
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
            $unskilled = $this->unskilledServices($staffId, $serviceNames, $canonicalBranch);
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
