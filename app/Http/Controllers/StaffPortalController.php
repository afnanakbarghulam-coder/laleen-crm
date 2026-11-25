<?php

namespace App\Http\Controllers;

use App\Models\ShiftPattern;
use App\Models\Staff;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;

class StaffPortalController extends Controller
{
    private const ALLOWED_TABS = ['overview', 'contracts', 'documents', 'leaves'];

    private const DAY_NAMES = [
        0 => 'Sunday',
        1 => 'Monday',
        2 => 'Tuesday',
        3 => 'Wednesday',
        4 => 'Thursday',
        5 => 'Friday',
        6 => 'Saturday',
    ];

    private const DAY_SHORT = [
        0 => 'Sun',
        1 => 'Mon',
        2 => 'Tue',
        3 => 'Wed',
        4 => 'Thu',
        5 => 'Fri',
        6 => 'Sat',
    ];

    /**
     * Render the Staff Self-Service Portal (`/my-profile`).
     * Strictly scoped to `auth()->user()` and `auth()->user()->staff` — never
     * reads any `staff_id` or `user_id` parameter from the request.
     */
    public function index(Request $request)
    {
        /** @var User $user */
        $user = $request->user();

        /** @var ?Staff $staff */
        $staff = $user->staff()
            ->with([
                'services' => fn ($q) => $q->with('category')->orderBy('name'),
                'shiftPatterns.blocks',
                'timeOffs' => fn ($q) => $q->orderBy('start_date'),
            ])
            ->first();

        $rawTab = strtolower(trim((string) $request->query('tab', 'overview')));
        $activeTab = in_array($rawTab, self::ALLOWED_TABS, true) ? $rawTab : 'overview';

        $nowQatar = Carbon::now('Asia/Qatar');
        $todayQatar = $nowQatar->copy()->startOfDay();
        $windowEndQatar = $todayQatar->copy()->addDays(29);

        $isSalonStaff = $user->isSalonStaff();
        $isAgent = strtolower(trim((string) $user->role)) === 'agent';

        // ---- Identity & Status ----
        $displayName = $staff?->name ?: $user->name;
        $roleBadge = $isSalonStaff
            ? 'Salon Staff'
            : ($isAgent ? 'Chat Agent' : $user->roleBadgeLabel());

        $jobSubtitle = $isSalonStaff
            ? 'Beauty Specialist'
            : ($isAgent
                ? 'Customer Care & Bookings'
                : ($user->isAdmin() ? 'Salon Management' : $user->roleBadgeLabel()));

        $todayTimeOff = $staff
            ? $staff->timeOffs->first(fn ($t) => $t->coversDate($todayQatar))
            : null;

        if ($todayTimeOff) {
            $statusLabel = $this->formatTimeOffReason($todayTimeOff->reason);
            $statusTone = $todayTimeOff->reason === 'sick' ? 'danger' : 'amber';
        } elseif ($staff && in_array(strtolower((string) $staff->availability_status), ['sick', 'on-leave'], true)) {
            $statusLabel = strtolower((string) $staff->availability_status) === 'sick' ? 'Sick' : 'On Leave';
            $statusTone = strtolower((string) $staff->availability_status) === 'sick' ? 'danger' : 'amber';
        } else {
            $statusLabel = 'Active';
            $statusTone = 'emerald';
        }

        $employmentTypeLabel = $staff && filled($staff->employment_type)
            ? ucwords(str_replace(['_', '-'], ' ', (string) $staff->employment_type))
            : null;

        $avatarPath = $staff?->profile_picture ?: $user->profile_photo;
        $initials = collect(preg_split('/\s+/', trim($displayName)))
            ->filter()
            ->take(2)
            ->map(fn ($p) => mb_strtoupper(mb_substr($p, 0, 1)))
            ->implode('') ?: 'L';

        // ---- Branch Location & Shift Schedule ----
        $branchInfo = $this->resolveBranchInfo($user, $staff);
        $activePattern = $staff?->activePatternFor($todayQatar);
        $shiftSchedule = $this->buildWeeklyShiftSummary($staff, $activePattern);

        // ---- Skills / Agent Channels ----
        $skillsPayload = $this->buildSkillsPayload($user, $staff);

        // ---- Off Days (Weekly Fixed + Upcoming 30 Days in Asia/Qatar) ----
        $offDaysPayload = $this->buildOffDaysPayload($staff, $activePattern, $todayQatar, $windowEndQatar);

        return view('portal.my-profile', [
            'user' => $user,
            'staff' => $staff,
            'activeTab' => $activeTab,
            'isSalonStaff' => $isSalonStaff,
            'isAgent' => $isAgent,
            'profile' => [
                'name' => $displayName,
                'initials' => $initials,
                'avatar' => $avatarPath ? asset(str_replace('\\', '/', $avatarPath)) : null,
                'role_badge' => $roleBadge,
                'job_subtitle' => $jobSubtitle,
                'status_label' => $statusLabel,
                'status_tone' => $statusTone,
                'employment_type' => $employmentTypeLabel,
                'start_date' => $staff?->start_date ? $staff->start_date->format('d M Y') : null,
                'email' => $user->email,
                'phone' => $staff?->phone,
            ],
            'branchInfo' => $branchInfo,
            'shiftSchedule' => $shiftSchedule,
            'skillsPayload' => $skillsPayload,
            'offDaysPayload' => $offDaysPayload,
            'todayQatarLabel' => $nowQatar->format('D, d M Y'),
        ]);
    }

    /**
     * Allow the authenticated user to securely update their own password.
     * Strictly scoped to `$request->user()` and requires current password verification.
     */
    public function updatePassword(Request $request)
    {
        /** @var User $user */
        $user = $request->user();

        $validated = $request->validateWithBag('portalPassword', [
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'string', 'min:8', 'max:128', 'confirmed'],
        ]);

        $user->forceFill([
            'password' => Hash::make($validated['password']),
        ])->save();

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'Your password has been updated.',
            ]);
        }

        return redirect()
            ->route('portal.profile', ['tab' => 'overview'])
            ->with('success', 'Your password has been updated.');
    }

    private function resolveBranchInfo(User $user, ?Staff $staff): array
    {
        $isAgent = strtolower(trim((string) $user->role)) === 'agent';
        $rawBranch = strtolower(trim((string) ($staff?->branch ?? '')));

        if ($isAgent && (!$staff || !$staff->bookable || $rawBranch === '')) {
            return [
                'label' => 'Remote / HQ',
                'subtitle' => 'Digital Customer Care & Central Bookings',
                'is_remote' => true,
            ];
        }

        return match ($rawBranch) {
            'old_airport' => [
                'label' => 'Old Airport',
                'subtitle' => 'Laleen Beauty Salon — Old Airport Branch',
                'is_remote' => false,
            ],
            'wakrah', 'al_wakrah' => [
                'label' => 'Al Wakrah',
                'subtitle' => 'Laleen Beauty Salon — Al Wakrah Branch',
                'is_remote' => false,
            ],
            'both' => [
                'label' => 'Old Airport & Al Wakrah',
                'subtitle' => 'Assigned across both Laleen branches',
                'is_remote' => false,
            ],
            default => [
                'label' => 'Remote / HQ',
                'subtitle' => $user->isAdmin() ? 'Central Operations & HQ' : 'Laleen Central Team',
                'is_remote' => true,
            ],
        };
    }

    private function buildWeeklyShiftSummary(?Staff $staff, ?ShiftPattern $activePattern): array
    {
        if (!$staff) {
            return [
                'has_schedule' => false,
                'rows' => [],
                'weekly_hours' => 0.0,
            ];
        }

        if ($activePattern && $activePattern->blocks->isNotEmpty()) {
            $weeklyMinutes = 0;
            $dayEntries = [];

            for ($dow = 0; $dow <= 6; $dow++) {
                $blocks = $activePattern->blocks
                    ->where('day_of_week', $dow)
                    ->sortBy('start_time')
                    ->values();

                if ($blocks->isEmpty()) {
                    $dayEntries[$dow] = null;
                    continue;
                }

                $labels = [];
                foreach ($blocks as $block) {
                    $weeklyMinutes += $block->durationMinutes();
                    $labels[] = $this->formatTimeRange($block->start_time, $block->end_time);
                }
                $dayEntries[$dow] = implode(' · ', $labels);
            }

            // Group consecutive days with identical shift hours into compact readable rows
            $groupedRows = [];
            $dow = 0;
            while ($dow <= 6) {
                $hours = $dayEntries[$dow];
                if ($hours === null) {
                    $dow++;
                    continue;
                }
                $startDow = $dow;
                while ($dow + 1 <= 6 && $dayEntries[$dow + 1] === $hours) {
                    $dow++;
                }
                $dayLabel = $startDow === $dow
                    ? self::DAY_SHORT[$startDow]
                    : self::DAY_SHORT[$startDow] . ' – ' . self::DAY_SHORT[$dow];

                $groupedRows[] = [
                    'days' => $dayLabel,
                    'hours' => $hours,
                ];
                $dow++;
            }

            return [
                'has_schedule' => !empty($groupedRows),
                'rows' => $groupedRows,
                'weekly_hours' => round($weeklyMinutes / 60, 1),
            ];
        }

        // Fallback to legacy working_hours JSON if no ShiftPattern exists
        $wh = is_array($staff->working_hours) ? $staff->working_hours : [];
        if (!empty($wh['start']) && !empty($wh['end'])) {
            return [
                'has_schedule' => true,
                'rows' => [[
                    'days' => 'Standard Shift',
                    'hours' => $this->formatTimeRange((string) $wh['start'], (string) $wh['end']),
                ]],
                'weekly_hours' => 0.0,
            ];
        }

        return [
            'has_schedule' => false,
            'rows' => [],
            'weekly_hours' => 0.0,
        ];
    }

    private function buildSkillsPayload(User $user, ?Staff $staff): array
    {
        $isAgent = strtolower(trim((string) $user->role)) === 'agent';

        if ($isAgent) {
            $channels = [
                ['name' => 'WhatsApp Business', 'meta' => 'Client Inquiries & Bookings'],
                ['name' => 'Instagram & Meta DMs', 'meta' => 'Ad Lead Conversion'],
                ['name' => 'Enhanced Calendar', 'meta' => 'Appointment Scheduling'],
                ['name' => 'Lead Follow-Up Pipeline', 'meta' => 'Daily Follow-Ups'],
            ];

            $serviceSkills = $staff
                ? $staff->services->map(fn ($s) => [
                    'name' => $s->name,
                    'duration' => (int) ($s->duration ?: 30),
                ])->values()->all()
                : [];

            return [
                'mode' => 'agent',
                'title' => 'Channels & Role Scope',
                'items' => $channels,
                'extra_services' => $serviceSkills,
            ];
        }

        $serviceSkills = [];
        if ($staff && $staff->services->isNotEmpty()) {
            $serviceSkills = $staff->services->map(fn ($s) => [
                'name' => $s->name,
                'duration' => (int) ($s->duration ?: 30),
                'category' => $s->category?->name,
            ])->values()->all();
        } elseif ($staff && is_array($staff->skills) && !empty($staff->skills)) {
            $serviceSkills = collect($staff->skills)
                ->filter(fn ($v) => is_string($v) && trim($v) !== '')
                ->map(fn ($name) => ['name' => trim($name), 'duration' => null, 'category' => null])
                ->values()
                ->all();
        }

        if ($user->isSalonStaff() || ($staff && $staff->bookable) || !empty($serviceSkills)) {
            return [
                'mode' => 'salon_staff',
                'title' => 'Assigned Service Skills',
                'items' => $serviceSkills,
                'extra_services' => [],
            ];
        }

        return [
            'mode' => 'management',
            'title' => 'Role Scope',
            'items' => [
                ['name' => $user->roleBadgeLabel(), 'meta' => 'Operational Access'],
                ['name' => 'Team & Branch Oversight', 'meta' => 'Laleen Ops'],
            ],
            'extra_services' => [],
        ];
    }

    private function buildOffDaysPayload(
        ?Staff $staff,
        ?ShiftPattern $activePattern,
        Carbon $todayQatar,
        Carbon $windowEndQatar
    ): array {
        if (!$staff) {
            return [
                'weekly_off_days' => [],
                'scheduled_time_offs' => [],
                'upcoming_weekly_dates' => [],
            ];
        }

        // 1. Fixed weekly off day(s) from active ShiftPattern (or fallback to staff.weekly_off)
        $weeklyOffDayNumbers = [];
        if ($activePattern && $activePattern->blocks->isNotEmpty()) {
            $workingDays = $activePattern->blocks->pluck('day_of_week')->map(fn ($d) => (int) $d)->unique()->all();
            for ($dow = 0; $dow <= 6; $dow++) {
                if (!in_array($dow, $workingDays, true)) {
                    $weeklyOffDayNumbers[] = $dow;
                }
            }
            $weeklyOffLabels = array_map(fn (int $d) => self::DAY_NAMES[$d], $weeklyOffDayNumbers);
        } else {
            $legacyOff = is_array($staff->weekly_off) ? $staff->weekly_off : [];
            $weeklyOffLabels = collect($legacyOff)
                ->filter(fn ($d) => is_string($d) && trim($d) !== '')
                ->map(fn ($d) => ucfirst(strtolower(trim($d))))
                ->unique()
                ->values()
                ->all();
            $nameToNum = array_flip(self::DAY_NAMES);
            foreach ($weeklyOffLabels as $lbl) {
                if (isset($nameToNum[$lbl])) {
                    $weeklyOffDayNumbers[] = $nameToNum[$lbl];
                }
            }
        }

        // 2. Scheduled time-off entries from Shifts & Ops Actions overlapping the next 30 days (Asia/Qatar)
        $timeOffs = $staff->timeOffs
            ->filter(function ($t) use ($todayQatar, $windowEndQatar) {
                $start = $t->start_date->copy()->startOfDay();
                $end = $t->end_date->copy()->startOfDay();

                return $end->gte($todayQatar) && $start->lte($windowEndQatar);
            })
            ->sortBy('start_date')
            ->values();

        $scheduledTimeOffs = $timeOffs->map(function ($t) {
            $start = $t->start_date->copy()->startOfDay();
            $end = $t->end_date->copy()->startOfDay();
            $days = $start->diffInDays($end) + 1;
            $dateRange = $start->equalTo($end)
                ? $start->format('D, d M Y')
                : $start->format('d M') . ' – ' . $end->format('d M Y');

            return [
                'date_range' => $dateRange,
                'days' => $days,
                'reason_label' => $this->formatTimeOffReason($t->reason),
                'reason' => (string) $t->reason,
                'notes' => $t->notes,
            ];
        })->all();

        // 3. Upcoming weekly off dates across the next 30 days (excluding dates already covered by scheduled time off)
        $upcomingWeeklyDates = [];
        for ($i = 0; $i < 30; $i++) {
            $cursor = $todayQatar->copy()->addDays($i);
            $coveredByTimeOff = $timeOffs->contains(fn ($t) => $t->coversDate($cursor));
            if ($coveredByTimeOff) {
                continue;
            }

            $dayPattern = $staff->activePatternFor($cursor);
            $isOffDay = false;
            if ($dayPattern && $dayPattern->blocks->isNotEmpty()) {
                $isOffDay = $dayPattern->blocks->where('day_of_week', $cursor->dayOfWeek)->isEmpty();
            } elseif (!empty($weeklyOffDayNumbers)) {
                $isOffDay = in_array($cursor->dayOfWeek, $weeklyOffDayNumbers, true);
            }

            if ($isOffDay) {
                $upcomingWeeklyDates[] = [
                    'date' => $cursor->toDateString(),
                    'label' => $cursor->format('D, d M'),
                    'is_today' => $i === 0,
                ];
            }
        }

        return [
            'weekly_off_days' => $weeklyOffLabels,
            'scheduled_time_offs' => $scheduledTimeOffs,
            'upcoming_weekly_dates' => $upcomingWeeklyDates,
        ];
    }

    private function formatTimeRange(string $start, string $end): string
    {
        $fmt = static function (string $t): string {
            $norm = strlen(trim($t)) === 5 ? trim($t) . ':00' : trim($t);
            try {
                return Carbon::createFromFormat('H:i:s', $norm)->format('g:i A');
            } catch (\Throwable) {
                return $t;
            }
        };

        return $fmt($start) . ' – ' . $fmt($end);
    }

    private function formatTimeOffReason(?string $reason): string
    {
        return match (strtolower(trim((string) $reason))) {
            'sick' => 'Sick Leave',
            'unpaid' => 'Unpaid Leave',
            'annual', 'vacation', 'leave', 'on-leave' => 'On Leave',
            'other' => 'Scheduled Time Off',
            default => 'Time Off',
        };
    }
}
