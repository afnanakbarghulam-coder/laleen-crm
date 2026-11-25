<?php

namespace App\Http\Controllers;

use App\Models\AdLeadEntry;
use App\Models\Appointment;
use App\Models\BranchEvaluation;
use App\Models\KpiChatEvaluation;
use App\Models\KpiStaffEvaluation;
use App\Models\Lead;
use App\Models\OpsActionItem;
use App\Models\ShiftPattern;
use App\Models\SocialContentPlan;
use App\Models\SocialLateReplyLog;
use App\Models\Staff;
use App\Models\StaffAttendance;
use App\Models\StaffComplaint;
use App\Models\StaffContract;
use App\Models\StaffDeduction;
use App\Models\StaffDocument;
use App\Models\StaffLeave;
use App\Models\User;
use App\Support\AgentBookingQuotaCalculator;
use App\Support\DashboardScorecardBuilder;
use App\Support\StaffContractPdfGenerator;
use App\Support\StaffSalesAnalytics;
use Carbon\CarbonPeriod;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class StaffPortalController extends Controller
{
    private const ALLOWED_TABS = ['overview', 'performance', 'contracts', 'documents', 'leaves'];

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
        $isLeaveEligible = $user->isLeaveEligible();

        if ($rawTab === 'leaves' && !$isLeaveEligible) {
            abort(403, 'Leave tracking is only available for branch-assigned Salon Managers and Salon Staff.');
        }

        $activeTab = in_array($rawTab, self::ALLOWED_TABS, true) ? $rawTab : 'overview';

        return view('portal.my-profile', $this->buildPortalViewData($user, $staff, $activeTab, false, $request));
    }

    /**
     * Admin-only read-only preview of a staff member's Staff Self-Service Portal view.
     * Strictly gated behind Admin authorization (`role:admin` + explicit `isAdmin()` check).
     */
    public function adminPreview(Request $request, Staff $staff)
    {
        abort_unless($request->user()?->isAdmin(), 403, 'Only administrators can preview staff portal profiles.');

        $staff->load([
            'user',
            'services' => fn ($q) => $q->with('category')->orderBy('name'),
            'shiftPatterns.blocks',
            'timeOffs' => fn ($q) => $q->orderBy('start_date'),
        ]);

        $targetUser = $staff->user;
        if (!$targetUser) {
            $targetUser = new User([
                'name' => $staff->name,
                'email' => $staff->email ?: 'No portal login assigned yet',
                'role' => ($staff->bookable && !$staff->isRemote()) ? 'salon_staff' : 'agent',
                'is_active' => true,
            ]);
        }

        $rawTab = strtolower(trim((string) $request->query('tab', 'overview')));
        $isLeaveEligible = $targetUser->exists
            ? $targetUser->isLeaveEligible()
            : $staff->isLeaveEligible();

        if ($rawTab === 'leaves' && !$isLeaveEligible) {
            abort(403, 'Leave tracking is only available for branch-assigned Salon Managers and Salon Staff.');
        }

        $activeTab = in_array($rawTab, self::ALLOWED_TABS, true) ? $rawTab : 'overview';

        return view('portal.my-profile', $this->buildPortalViewData($targetUser, $staff, $activeTab, true, $request));
    }

    /**
     * Policy-protected PDF download/preview of an employee's OWN StaffContract from `/my-profile?tab=contracts`.
     * ONLY Admin or the exact employee owning the contract may download it; all others get HTTP 403.
     */
    public function downloadOwnContractPdf(Request $request, StaffContract $contract, StaffContractPdfGenerator $pdfGenerator)
    {
        /** @var User $viewer */
        $viewer = $request->user();
        $ownStaffId = $viewer->staff()->value('id');

        $isOwner = ($contract->user_id !== null && (int) $contract->user_id === (int) $viewer->id)
            || ($ownStaffId !== null && $contract->staff_id !== null && (int) $contract->staff_id === (int) $ownStaffId);

        abort_unless($viewer->isAdmin() || $isOwner, 403, 'You are not authorized to access this contract.');

        $contract->loadMissing(['staff', 'user']);
        $pdfBinary = $pdfGenerator->render($contract);
        $slugName = Str::slug($contract->memberName() ?: 'staff');
        $filename = "Laleen-Contract-{$contract->contract_number}-{$slugName}.pdf";
        $disposition = $request->boolean('inline') ? 'inline' : 'attachment';

        return response($pdfBinary, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => $disposition . '; filename="' . $filename . '"',
            'Content-Length' => (string) strlen($pdfBinary),
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store, max-age=0',
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

    private function buildPortalViewData(
        User $user,
        ?Staff $staff,
        string $activeTab,
        bool $isAdminPreview,
        Request $request
    ): array {
        $nowQatar = Carbon::now('Asia/Qatar');
        $todayQatar = $nowQatar->copy()->startOfDay();
        $windowEndQatar = $todayQatar->copy()->addDays(29);

        $roleSlug = strtolower(trim((string) $user->role));
        $isSalonStaff = $user->isSalonStaff();
        $isAgent = $roleSlug === 'agent';
        $isOpsLead = $roleSlug === 'ops_lead';
        $isSalesCoordinator = $roleSlug === 'sales_coordinator';
        $isSalonManager = in_array($roleSlug, ['salon_manager', 'manager'], true);
        $isMarketingSpecialist = $roleSlug === 'marketing_specialist';
        $isSocialMediaEditor = $roleSlug === 'social_media_editor';
        $isLeaveEligible = $user->exists
            ? $user->isLeaveEligible()
            : ($staff ? $staff->isLeaveEligible() : false);

        // ---- Identity & Status ----
        $displayName = $staff?->name ?: $user->name;
        $roleBadge = User::roleLabel($roleSlug);

        $todayApprovedLeave = ($staff && Schema::hasTable('staff_leaves'))
            ? StaffLeave::where('staff_id', $staff->id)->approved()->coveringDate($todayQatar)->first()
            : null;

        $todayTimeOff = $staff
            ? $staff->timeOffs->first(fn ($t) => $t->coversDate($todayQatar))
            : null;

        if ($todayApprovedLeave) {
            $statusLabel = $todayApprovedLeave->leave_type === StaffLeave::TYPE_SICK ? 'Sick Leave' : 'On Leave';
            $statusTone = $todayApprovedLeave->leave_type === StaffLeave::TYPE_SICK ? 'danger' : 'amber';
        } elseif ($todayTimeOff) {
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

        // ---- Location & Shift Schedule ----
        $branchInfo = $this->resolveBranchInfo($user, $staff);
        $activePattern = $staff?->activePatternFor($todayQatar);
        $shiftSchedule = $this->buildWeeklyShiftSummary($staff, $activePattern);
        $branchInfo['compact_subtitle'] = $this->buildCompactLocationSubtitle($branchInfo, $shiftSchedule);

        // ---- Skills Card (Salon Staff ONLY) ----
        $skillsPayload = $isSalonStaff ? $this->buildSalonSkillsPayload($staff) : null;

        // ---- Off Days (Weekly Fixed + Next 30 Days incl. Approved Leaves & Time Off) ----
        $offDaysPayload = $this->buildOffDaysPayload($staff, $activePattern, $todayQatar, $windowEndQatar);

        // ---- Absentees Card (ALL roles — live from staff_attendances) ----
        $absenteesPayload = $this->buildAbsenteesPayload($staff, $nowQatar);

        // ---- Deductions & Penalties (ALL roles — Read-Only, zero client contact info) ----
        $deductionsPayload = $this->buildDeductionsPayload($staff, $nowQatar);

        // ---- Contracts Tab & Single-Source Own Monthly Salary (ALL roles — Read-Only, strictly own records) ----
        $contractsPayload = $this->buildContractsPayload($user, $staff);
        $salaryPayload = $this->buildSalaryPayload($user, $staff, $contractsPayload);

        // ---- Salon Manager / Salon Staff Detail Tables (scoped to Performance tab) ----
        $branchPendingComplaints = ($activeTab === 'performance' && $isSalonManager && $staff && !$staff->isRemote())
            ? $this->buildBranchPendingComplaints($staff)
            : null;

        // If a Salon Manager has no linked Staff record yet (e.g. legacy manager preview), resolve branch from user name
        if ($activeTab === 'performance' && $isSalonManager && $branchPendingComplaints === null) {
            $fallbackBranch = KpiStaffEvaluation::defaultBranchFor($user);
            if ($fallbackBranch) {
                $branchPendingComplaints = $this->buildBranchPendingComplaintsByBranch($fallbackBranch);
            }
        }

        // ---- Salon Staff ONLY: My Complaints (STRICT PRIVACY — zero client PII!) & Weekly Evaluations ----
        $myComplaints = ($activeTab === 'performance' && $isSalonStaff && $staff)
            ? $this->buildPrivacySafeStaffComplaints($staff)
            : null;

        $weeklyEvaluations = ($activeTab === 'performance' && $isSalonStaff && $staff)
            ? $this->buildStaffWeeklyEvaluations($staff)
            : null;

        // ---- Dedicated Performance Tab (KPIs-module style scorecards per role) ----
        $performancePayload = $activeTab === 'performance'
            ? $this->buildPerformancePayload(
                $user,
                $staff,
                $roleSlug,
                $nowQatar,
                $isAdminPreview,
                $request->user(),
                $branchPendingComplaints,
                $myComplaints,
                $weeklyEvaluations
            )
            : null;

        // ---- Documents Tab (ALL roles — Read-Only, strictly own private documents) ----
        $documentsPayload = $this->buildDocumentsPayload($staff, $todayQatar);

        // ---- Leaves Tab (Salon Manager & Salon Staff ONLY) ----
        $leaveTypeFilter = strtolower(trim((string) $request->query('leave_type', 'all')));
        if (!in_array($leaveTypeFilter, ['all', 'annual', 'sick'], true)) {
            $leaveTypeFilter = 'all';
        }
        $leavesPayload = $isLeaveEligible
            ? $this->buildLeavesPayload($staff, $nowQatar, $leaveTypeFilter)
            : null;

        return [
            'user' => $user,
            'staff' => $staff,
            'activeTab' => $activeTab,
            'roleSlug' => $roleSlug,
            'isSalonStaff' => $isSalonStaff,
            'isAgent' => $isAgent,
            'isOpsLead' => $isOpsLead,
            'isSalesCoordinator' => $isSalesCoordinator,
            'isSalonManager' => $isSalonManager,
            'isMarketingSpecialist' => $isMarketingSpecialist,
            'isSocialMediaEditor' => $isSocialMediaEditor,
            'isLeaveEligible' => $isLeaveEligible,
            'isAdminPreview' => $isAdminPreview,
            'portalTabRoute' => $isAdminPreview && $staff
                ? fn (string $tab, array $extra = []) => route('portal.admin-preview', array_merge(['staff' => $staff->id, 'tab' => $tab], $extra))
                : fn (string $tab, array $extra = []) => route('portal.profile', array_merge(['tab' => $tab], $extra)),
            'profile' => [
                'name' => $displayName,
                'initials' => $initials,
                'avatar_raw' => $avatarPath,
                'avatar' => $avatarPath ? asset(str_replace('\\', '/', $avatarPath)) : null,
                'role_badge' => $roleBadge,
                'status_label' => $statusLabel,
                'status_tone' => $statusTone,
                'employment_type' => $employmentTypeLabel,
                'start_date' => $staff?->start_date ? $staff->start_date->format('d M Y') : null,
                'email' => $user->email,
                'phone' => $staff?->phone,
            ],
            'branchInfo' => $branchInfo,
            'shiftSchedule' => $shiftSchedule,
            'salaryPayload' => $salaryPayload,
            'skillsPayload' => $skillsPayload,
            'offDaysPayload' => $offDaysPayload,
            'absenteesPayload' => $absenteesPayload,
            'performancePayload' => $performancePayload,
            'branchPendingComplaints' => $branchPendingComplaints,
            'myComplaints' => $myComplaints,
            'weeklyEvaluations' => $weeklyEvaluations,
            'deductionsPayload' => $deductionsPayload,
            'contractsPayload' => $contractsPayload,
            'documentsPayload' => $documentsPayload,
            'leavesPayload' => $leavesPayload,
            'todayQatarLabel' => $nowQatar->format('D, d M Y'),
        ];
    }

    private function resolveBranchInfo(User $user, ?Staff $staff): array
    {
        $role = strtolower(trim((string) $user->role));
        $isRemoteRole = User::isRemoteRole($role);

        if (($staff && $staff->isRemote()) || ($isRemoteRole && (!$staff || !$staff->bookable))) {
            $subtitle = match ($role) {
                'agent' => 'Digital Customer Care & Central Bookings',
                'sales_coordinator' => 'Remote Sales Coordination & Lead Pipeline',
                'ops_lead' => 'Remote Operations & SLA Oversight',
                'marketing_specialist' => 'Performance Ads & Growth Marketing',
                'social_media_editor' => 'Social Content & Reels Production',
                default => 'Laleen Remote Operations Team',
            };

            return [
                'label' => 'Remote',
                'subtitle' => $subtitle,
                'is_remote' => true,
            ];
        }

        $rawBranch = strtolower(trim((string) ($staff?->branch ?? '')));
        if ($rawBranch === '' && in_array($role, ['salon_manager', 'manager'], true)) {
            $rawBranch = strtolower(trim((string) (KpiStaffEvaluation::defaultBranchFor($user) ?? '')));
        }

        return match ($rawBranch) {
            'old_airport' => [
                'label' => 'Old Airport',
                'subtitle' => 'Old Airport Branch',
                'is_remote' => false,
            ],
            'wakrah', 'al_wakrah' => [
                'label' => 'Al Wakrah',
                'subtitle' => 'Al Wakrah Branch',
                'is_remote' => false,
            ],
            'both' => [
                'label' => 'Old Airport & Al Wakrah',
                'subtitle' => 'Both Salon Branches',
                'is_remote' => false,
            ],
            default => [
                'label' => 'Remote',
                'subtitle' => $user->isAdmin() ? 'Central Operations & HQ' : 'Remote Team',
                'is_remote' => true,
            ],
        };
    }

    private function buildCompactLocationSubtitle(array $branchInfo, array $shiftSchedule): string
    {
        if (!empty($shiftSchedule['has_schedule']) && !empty($shiftSchedule['rows'])) {
            $first = $shiftSchedule['rows'][0];
            $hoursTag = ($shiftSchedule['weekly_hours'] ?? 0) > 0
                ? ($shiftSchedule['weekly_hours'] . 'h/wk · ')
                : '';

            return $hoursTag . $first['days'] . ' ' . $first['hours'];
        }

        return (string) ($branchInfo['subtitle'] ?? ($branchInfo['is_remote'] ? 'Remote schedule' : 'Branch schedule'));
    }

    /**
     * Single source of truth for the employee's OWN monthly base salary.
     * Strictly scoped to the authenticated user / previewed staff member — never reads other staff data.
     *
     * Priority:
     *   1. Own active StaffContract (base_salary + currency)
     *   2. Own staff.base_salary (QAR, or currency from own latest contract if any)
     *   3. Own pending/draft StaffContract (base_salary + currency)
     *   4. Otherwise "Not set"
     */
    private function buildSalaryPayload(User $user, ?Staff $staff, array $contractsPayload): array
    {
        $activeContract = $contractsPayload['active_contract'] ?? null;

        if ($activeContract && ($activeContract['status'] ?? null) === StaffContract::STATUS_ACTIVE && (float) ($activeContract['base_salary'] ?? 0) > 0) {
            $amount = (float) $activeContract['base_salary'];
            $currency = (string) ($activeContract['currency'] ?: 'QAR');

            return [
                'has_salary' => true,
                'amount' => $amount,
                'currency' => $currency,
                'formatted' => $this->formatCurrencyAmount($amount, $currency),
                'source_label' => 'Active contract · Monthly base',
            ];
        }

        if ($staff && (float) ($staff->base_salary ?? 0) > 0) {
            $amount = (float) $staff->base_salary;
            $currency = ($activeContract && !empty($activeContract['currency']))
                ? (string) $activeContract['currency']
                : 'QAR';

            return [
                'has_salary' => true,
                'amount' => $amount,
                'currency' => $currency,
                'formatted' => $this->formatCurrencyAmount($amount, $currency),
                'source_label' => 'Payroll base salary',
            ];
        }

        if ($activeContract && (float) ($activeContract['base_salary'] ?? 0) > 0) {
            $amount = (float) $activeContract['base_salary'];
            $currency = (string) ($activeContract['currency'] ?: 'QAR');

            return [
                'has_salary' => true,
                'amount' => $amount,
                'currency' => $currency,
                'formatted' => $this->formatCurrencyAmount($amount, $currency),
                'source_label' => ($activeContract['status_label'] ?? 'Contract') . ' · Monthly base',
            ];
        }

        return [
            'has_salary' => false,
            'amount' => null,
            'currency' => 'QAR',
            'formatted' => 'Not set',
            'source_label' => 'No active salary record yet',
        ];
    }

    private function formatCurrencyAmount(float $amount, string $currency = 'QAR'): string
    {
        $cleanCurrency = strtoupper(trim($currency)) ?: 'QAR';
        $decimals = abs($amount - round($amount)) < 0.005 ? 0 : 2;

        return number_format($amount, $decimals) . ' ' . $cleanCurrency;
    }

    private function buildSalonSkillsPayload(?Staff $staff): array
    {
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

        return [
            'title' => 'Assigned Service Skills',
            'items' => $serviceSkills,
        ];
    }

    private function buildAbsenteesPayload(?Staff $staff, Carbon $nowQatar): array
    {
        if (!$staff || !Schema::hasTable('staff_attendances')) {
            return [
                'month_count' => 0,
                'ytd_count' => 0,
                'recent_dates' => [],
                'month_label' => $nowQatar->format('M Y'),
                'year_label' => (string) $nowQatar->year,
            ];
        }

        $yearStart = $nowQatar->copy()->startOfYear()->toDateString();
        $monthStart = $nowQatar->copy()->startOfMonth()->toDateString();
        $todayStr = $nowQatar->toDateString();

        $ytdAbsences = StaffAttendance::query()
            ->where('staff_id', $staff->id)
            ->where('status', StaffAttendance::STATUS_ABSENT)
            ->whereDate('attendance_date', '>=', $yearStart)
            ->whereDate('attendance_date', '<=', $todayStr)
            ->orderByDesc('attendance_date')
            ->get(['attendance_date', 'notes']);

        $monthCount = $ytdAbsences
            ->filter(fn (StaffAttendance $a) => $a->attendance_date->toDateString() >= $monthStart)
            ->count();

        return [
            'month_count' => $monthCount,
            'ytd_count' => $ytdAbsences->count(),
            'recent_dates' => $ytdAbsences->take(6)->map(fn (StaffAttendance $a) => [
                'date_label' => $a->attendance_date->format('D, d M Y'),
                'notes' => $a->notes,
            ])->values()->all(),
            'month_label' => $nowQatar->format('M Y'),
            'year_label' => (string) $nowQatar->year,
        ];
    }

    private function buildPerformancePayload(
        User $user,
        ?Staff $staff,
        string $roleSlug,
        Carbon $nowQatar,
        bool $isAdminPreview,
        ?User $viewer,
        ?array $branchPendingComplaints,
        ?array $myComplaints,
        ?array $weeklyEvaluations
    ): array {
        $monthStart = $nowQatar->copy()->startOfMonth()->startOfDay();
        $todayEnd = $nowQatar->copy()->endOfDay();
        $daysElapsed = max(1, (int) $nowQatar->day);
        $daysInMonth = max(1, (int) $nowQatar->daysInMonth);
        $periodLabel = sprintf('%s – %s (MTD)', $monthStart->format('M j'), $nowQatar->format('M j, Y'));
        $canOpenKpis = (!$isAdminPreview && $user->canView('kpis')) || ($isAdminPreview && ($viewer?->canView('kpis') ?? false));
        $canOpenOps = (!$isAdminPreview && $user->canView('staff')) || ($isAdminPreview && ($viewer?->canView('staff') ?? false));

        // 1. Chat Agent — Today's Daily Booking Target (X / 13) + MTD Booking Quota (X / Y)
        if ($roleSlug === 'agent') {
            $todayBounds = AgentBookingQuotaCalculator::qatarDayBounds($nowQatar);
            $dailyTarget = DashboardScorecardBuilder::AGENT_DAILY_BOOKING_QUOTA;
            $mtdTarget = max(1, $dailyTarget * $daysElapsed);
            $fullMoTarget = $dailyTarget * $daysInMonth;

            $todayBookings = 0;
            $mtdBookings = 0;
            if ($user->exists) {
                $deduped = AgentBookingQuotaCalculator::fetchForRange(
                    [$user->id],
                    $monthStart->toDateString(),
                    $nowQatar->toDateString()
                );
                $mtdBookings = $deduped->count();
                $todayBookings = $deduped
                    ->filter(fn (Appointment $appt) => AgentBookingQuotaCalculator::isCreatedWithinBounds($appt, $todayBounds))
                    ->count();
            }

            $todayPct = min(100.0, round(($todayBookings / $dailyTarget) * 100, 1));
            $mtdPct = min(100.0, round(($mtdBookings / $mtdTarget) * 100, 1));
            $todayPass = $todayBookings >= $dailyTarget;
            $mtdPass = $mtdBookings >= $mtdTarget;
            $leftToday = max(0, $dailyTarget - $todayBookings);
            $agentUrl = $canOpenKpis ? route('kpi.agents.index') : null;

            return [
                'heading' => 'Booking Targets & Quota',
                'period_label' => $periodLabel,
                'columns' => 2,
                'action_label' => $agentUrl ? 'Open Agent Scorecard' : null,
                'action_url' => $agentUrl,
                'cards' => [
                    [
                        'label' => "Today's Daily Booking Target",
                        'subnote' => AgentBookingQuotaCalculator::UI_TOOLTIP_COPY,
                        'tag' => $todayPass ? 'QUOTA MET' : 'TODAY',
                        'tag_tone' => $todayPass ? 'pass' : 'rose',
                        'tag_url' => $agentUrl,
                        'icon' => 'clock',
                        'orb_tone' => $todayPass ? 'emerald' : 'rose',
                        'hero_main' => (string) $todayBookings,
                        'hero_unit' => "/ {$dailyTarget}",
                        'delta_highlight' => number_format($todayPct, 1) . '%',
                        'delta_tone' => $todayPass ? 'emerald' : 'rose',
                        'delta_text' => 'of Daily Quota (' . ($todayPass ? 'Daily Quota Met!' : "{$leftToday} left today") . ')',
                        'bar_pct' => $todayPct,
                        'bar_tone' => $todayPass ? 'emerald' : 'rose-gold',
                        'scale_left' => '0%',
                        'scale_right' => '100%',
                    ],
                    [
                        'label' => 'MTD Booking Quota',
                        'subnote' => AgentBookingQuotaCalculator::UI_TOOLTIP_COPY,
                        'tag' => $mtdPass ? 'ON TARGET' : 'MTD PACE',
                        'tag_tone' => $mtdPass ? 'pass' : 'rose',
                        'tag_url' => $agentUrl,
                        'icon' => 'calendar-check',
                        'orb_tone' => $mtdPass ? 'emerald' : 'rose',
                        'hero_main' => (string) $mtdBookings,
                        'hero_unit' => "/ {$mtdTarget}",
                        'delta_highlight' => number_format($mtdPct, 1) . '%',
                        'delta_tone' => $mtdPass ? 'emerald' : 'rose',
                        'delta_text' => "of MTD Target (13/day × {$daysElapsed}d · {$fullMoTarget} Full Mo)",
                        'bar_pct' => $mtdPct,
                        'bar_tone' => $mtdPass ? 'emerald' : 'rose-gold',
                        'scale_left' => '0%',
                        'scale_right' => '100%',
                    ],
                ],
                'ops_items' => null,
                'branch_pending_complaints' => null,
                'weekly_evaluations' => null,
                'my_complaints' => null,
            ];
        }

        // 2. Sales Coordinator — 3 Scorecards: MTD Team Bookings, Lead Conversion Rate, Overdue Leads
        if ($roleSlug === 'sales_coordinator') {
            $chatAgentIds = User::where('role', 'agent')->pluck('id');
            $dailyTarget = DashboardScorecardBuilder::LADEN_DAILY_BOOKING_QUOTA;
            $mtdTarget = max(1, $dailyTarget * $daysElapsed);
            $fullMoTarget = $dailyTarget * $daysInMonth;
            $todayBounds = AgentBookingQuotaCalculator::qatarDayBounds($nowQatar);

            $mtdBookings = 0;
            $todayBookings = 0;
            if ($chatAgentIds->isNotEmpty()) {
                $deduped = AgentBookingQuotaCalculator::fetchForRange(
                    $chatAgentIds,
                    $monthStart->toDateString(),
                    $nowQatar->toDateString()
                );
                $mtdBookings = $deduped->count();
                $todayBookings = $deduped
                    ->filter(fn (Appointment $appt) => AgentBookingQuotaCalculator::isCreatedWithinBounds($appt, $todayBounds))
                    ->count();
            }
            $bookingsPct = min(100.0, round(($mtdBookings / $mtdTarget) * 100, 1));
            $bookingsPass = $mtdBookings >= $mtdTarget;

            $periodLeads = Schema::hasTable('leads')
                ? Lead::with('appointment:id,status')
                    ->whereBetween('created_at', [$monthStart, $todayEnd])
                    ->get(['id', 'appointment_id', 'needful_done'])
                : collect();
            $totalLeads = $periodLeads->count();
            $bookedLeads = $periodLeads->filter(fn (Lead $l) => $l->isBookedConversion())->count();
            $leadConvPct = $totalLeads > 0 ? round(($bookedLeads / $totalLeads) * 100, 1) : 0.0;
            $leadConvBarPct = min(100.0, round(($leadConvPct / DashboardScorecardBuilder::LEAD_CONVERSION_TARGET_PCT) * 100, 1));
            $leadConvPass = $leadConvPct >= DashboardScorecardBuilder::LEAD_CONVERSION_TARGET_PCT;

            $overdueLeadsCount = Schema::hasTable('leads')
                ? Lead::open()
                    ->whereNotNull('next_followup_date')
                    ->whereDate('next_followup_date', '<', $nowQatar->toDateString())
                    ->count()
                : 0;
            $overdueClear = $overdueLeadsCount === 0;
            $overdueBarPct = $overdueClear ? 100.0 : max(8.0, round(max(0.0, 80.0 - ($overdueLeadsCount * 10.0)), 1));

            $kpiHubUrl = $canOpenKpis ? route('kpi.hub') : null;
            $leadsUrl = (!$isAdminPreview && $user->canView('leads')) || ($isAdminPreview && ($viewer?->canView('leads') ?? false))
                ? route('leads.index')
                : null;

            return [
                'heading' => 'Sales Coordinator KPIs',
                'period_label' => $periodLabel,
                'columns' => 3,
                'action_label' => $kpiHubUrl ? 'Open Executive KPIs' : null,
                'action_url' => $kpiHubUrl,
                'cards' => [
                    [
                        'label' => 'MTD Team Bookings',
                        'subnote' => null,
                        'tag' => $bookingsPass ? 'ON TARGET' : 'TEAM QUOTA',
                        'tag_tone' => $bookingsPass ? 'pass' : 'champagne',
                        'tag_url' => $canOpenKpis ? route('kpi.agents.index') : null,
                        'icon' => 'calendar-check',
                        'orb_tone' => $bookingsPass ? 'emerald' : 'amber',
                        'hero_main' => (string) $mtdBookings,
                        'hero_unit' => "/ {$mtdTarget}",
                        'delta_highlight' => number_format($bookingsPct, 1) . '%',
                        'delta_tone' => $bookingsPass ? 'emerald' : 'champagne',
                        'delta_text' => "of MTD Target ({$todayBookings}/{$dailyTarget} Today · {$fullMoTarget} Full Mo)",
                        'bar_pct' => $bookingsPct,
                        'bar_tone' => $bookingsPass ? 'emerald' : 'champagne',
                        'scale_left' => '0%',
                        'scale_right' => '100%',
                    ],
                    [
                        'label' => 'Lead Conversion Rate',
                        'subnote' => null,
                        'tag' => $leadConvPass ? 'ON TARGET' : 'PIPELINE',
                        'tag_tone' => $leadConvPass ? 'pass' : 'rose',
                        'tag_url' => $leadsUrl,
                        'icon' => 'users',
                        'orb_tone' => $leadConvPass ? 'emerald' : 'rose',
                        'hero_main' => ((int) round($leadConvPct)) . '%',
                        'hero_unit' => null,
                        'delta_highlight' => '≥ 30% Target',
                        'delta_tone' => $leadConvPass ? 'emerald' : 'champagne',
                        'delta_text' => "({$bookedLeads}/{$totalLeads} leads booked MTD)",
                        'bar_pct' => $leadConvBarPct,
                        'bar_tone' => $leadConvPass ? 'emerald' : 'champagne',
                        'scale_left' => '0%',
                        'scale_right' => '30% Target',
                    ],
                    [
                        'label' => 'Overdue Leads',
                        'subnote' => null,
                        'tag' => $overdueClear ? 'CLEAR' : 'ACTION NEEDED',
                        'tag_tone' => $overdueClear ? 'pass' : 'ruby',
                        'tag_url' => $leadsUrl,
                        'icon' => 'alert',
                        'orb_tone' => $overdueClear ? 'emerald' : 'ruby',
                        'hero_main' => (string) $overdueLeadsCount,
                        'hero_unit' => null,
                        'delta_highlight' => $overdueClear ? '0 overdue' : "{$overdueLeadsCount} overdue",
                        'delta_tone' => $overdueClear ? 'emerald' : 'ruby',
                        'delta_text' => $overdueClear ? 'All follow-ups on schedule' : 'Target: 0 overdue follow-ups',
                        'bar_pct' => $overdueBarPct,
                        'bar_tone' => $overdueClear ? 'emerald' : 'ruby',
                        'scale_left' => '0 Overdue',
                        'scale_right' => $overdueClear ? '100% Clear' : 'Alert',
                    ],
                ],
                'ops_items' => null,
                'branch_pending_complaints' => null,
                'weekly_evaluations' => null,
                'my_complaints' => null,
            ];
        }

        // 3. Ops Lead — Pending Ops Tasks, Overdue / Priority Items, Completion Rate + Pending Ops Tracker table
        if ($roleSlug === 'ops_lead') {
            $currentMonday = $nowQatar->copy()->startOfWeek(Carbon::MONDAY)->startOfDay();
            OpsActionItem::$referenceWeekMonday = $currentMonday->toDateString();

            $openItems = Schema::hasTable('ops_action_items')
                ? OpsActionItem::query()
                    ->where('is_completed', false)
                    ->executionOrder()
                    ->get()
                : collect();

            $activeWeekItems = Schema::hasTable('ops_action_items')
                ? OpsActionItem::forWeek($currentMonday)->get()
                : collect();
            $periodItems = Schema::hasTable('ops_action_items')
                ? OpsActionItem::query()->whereBetween('created_at', [$monthStart, $todayEnd])->get()
                : collect();

            $trackedItems = $activeWeekItems->concat($periodItems)->concat($openItems)->unique('id')->values();
            $totalCount = $trackedItems->count();
            $completedCount = $trackedItems->where('is_completed', true)->count();
            $pendingCount = $openItems->count();
            $overdueCount = $openItems->filter(fn (OpsActionItem $i) => (bool) $i->is_overdue)->count();
            $urgentCount = $openItems->filter(fn (OpsActionItem $i) => $i->priority === 'urgent')->count();
            $overdueOrUrgentCount = $openItems
                ->filter(fn (OpsActionItem $i) => (bool) $i->is_overdue || $i->priority === 'urgent')
                ->count();

            $completionPct = $totalCount > 0 ? round(($completedCount / $totalCount) * 100, 1) : 100.0;
            $pendingClear = $pendingCount === 0;
            $priorityClear = $overdueOrUrgentCount === 0;
            $completionPass = $completionPct >= 85.0;
            $opsUrl = $canOpenOps ? route('staff.ops-tracker.index') : null;

            $itemList = $openItems->take(10)->map(fn (OpsActionItem $i) => [
                'id' => $i->id,
                'title' => $i->title,
                'scope' => $i->scope,
                'priority' => $i->priority,
                'is_overdue' => (bool) $i->is_overdue,
                'due_label' => $i->effectiveDueAt()->timezone('Asia/Qatar')->format('d M, g:i A'),
                'assignees' => implode(', ', $i->assignee_list),
            ])->values()->all();

            return [
                'heading' => 'Ops Tracker KPI',
                'period_label' => $periodLabel,
                'columns' => 3,
                'action_label' => $opsUrl ? 'Open Ops Tracker' : null,
                'action_url' => $opsUrl,
                'cards' => [
                    [
                        'label' => 'Pending Ops Tasks',
                        'subnote' => null,
                        'tag' => $pendingClear ? 'QUEUE CLEAR' : 'ACTIVE QUEUE',
                        'tag_tone' => $pendingClear ? 'pass' : 'champagne',
                        'tag_url' => $opsUrl,
                        'icon' => 'tasks',
                        'orb_tone' => $pendingClear ? 'emerald' : 'amber',
                        'hero_main' => (string) $pendingCount,
                        'hero_unit' => $totalCount > 0 ? "/ {$totalCount}" : null,
                        'delta_highlight' => $pendingClear ? '0 open' : "{$pendingCount} pending",
                        'delta_tone' => $pendingClear ? 'emerald' : 'champagne',
                        'delta_text' => $pendingClear ? 'All operational actions complete' : 'awaiting completion in Ops Tracker',
                        'bar_pct' => $totalCount > 0 ? min(100.0, round(($pendingCount / $totalCount) * 100, 1)) : 0.0,
                        'bar_tone' => $pendingClear ? 'emerald' : 'champagne',
                        'scale_left' => '0',
                        'scale_right' => "{$totalCount} Total",
                    ],
                    [
                        'label' => 'Overdue / Priority Items',
                        'subnote' => null,
                        'tag' => $priorityClear ? 'ON TRACK' : 'ALERT',
                        'tag_tone' => $priorityClear ? 'pass' : 'ruby',
                        'tag_url' => $opsUrl,
                        'icon' => 'alert',
                        'orb_tone' => $priorityClear ? 'emerald' : 'ruby',
                        'hero_main' => (string) $overdueOrUrgentCount,
                        'hero_unit' => null,
                        'delta_highlight' => "{$overdueCount} overdue · {$urgentCount} urgent",
                        'delta_tone' => $priorityClear ? 'emerald' : 'ruby',
                        'delta_text' => 'Target: 0 overdue SLA items',
                        'bar_pct' => $priorityClear ? 100.0 : max(10.0, 80.0 - ($overdueOrUrgentCount * 15.0)),
                        'bar_tone' => $priorityClear ? 'emerald' : 'ruby',
                        'scale_left' => '0 Overdue',
                        'scale_right' => $priorityClear ? '100% SLA' : 'Priority',
                    ],
                    [
                        'label' => 'Completion Rate',
                        'subnote' => null,
                        'tag' => $completionPass ? 'ON TARGET' : 'EXECUTION',
                        'tag_tone' => $completionPass ? 'pass' : 'rose',
                        'tag_url' => $opsUrl,
                        'icon' => 'check-circle',
                        'orb_tone' => $completionPass ? 'emerald' : 'rose',
                        'hero_main' => ((int) round($completionPct)) . '%',
                        'hero_unit' => null,
                        'delta_highlight' => "{$completedCount} / {$totalCount}",
                        'delta_tone' => $completionPass ? 'emerald' : 'rose',
                        'delta_text' => 'ops actions completed this period',
                        'bar_pct' => $completionPct,
                        'bar_tone' => $completionPass ? 'emerald' : 'rose-gold',
                        'scale_left' => '0%',
                        'scale_right' => '100%',
                    ],
                ],
                'ops_items' => $itemList,
                'branch_pending_complaints' => null,
                'weekly_evaluations' => null,
                'my_complaints' => null,
            ];
        }

        // 4. Salon Manager (and legacy Manager) — Branch Manager Scorecards + Branch Pending Complaints
        if (in_array($roleSlug, ['salon_manager', 'manager'], true)) {
            $assignedBranch = ($staff && in_array($staff->branch, ['old_airport', 'wakrah'], true))
                ? $staff->branch
                : (KpiStaffEvaluation::defaultBranchFor($user) ?? 'old_airport');
            $branchLabel = $assignedBranch === 'wakrah' ? 'Al Wakrah' : 'Old Airport';

            $analytics = new StaffSalesAnalytics($monthStart, $todayEnd);
            $upsellTotals = $analytics->totals($assignedBranch);
            $upsellActual = round((float) ($upsellTotals['team_total'] ?? 0.0), 0);
            $upsellTarget = round((float) ($upsellTotals['team_target'] ?? 0.0), 0);
            if ($upsellTarget <= 0) {
                $upsellTarget = max(1.0, round((StaffSalesAnalytics::DEFAULT_MONTHLY_TARGET / $daysInMonth) * $daysElapsed, 0));
                $upsellPct = min(100.0, round(($upsellActual / $upsellTarget) * 100, 1));
            } else {
                $upsellPct = min(100.0, round((float) ($upsellTotals['team_pct'] ?? 0.0), 1));
            }
            $upsellPass = $upsellPct >= 85.0;

            $allMondays = [];
            foreach (CarbonPeriod::create($monthStart->copy()->startOfDay(), $nowQatar->copy()->startOfDay()) as $day) {
                if ($day->isMonday()) {
                    $allMondays[] = $day->toDateString();
                }
            }
            $evalRows = Schema::hasTable('kpi_staff_evaluations')
                ? KpiStaffEvaluation::query()
                    ->where('branch', $assignedBranch)
                    ->whereDate('evaluation_date', '>=', $monthStart->toDateString())
                    ->whereDate('evaluation_date', '<=', $nowQatar->toDateString())
                    ->get(['staff_id', 'evaluation_date'])
                : collect();

            $expectedEvals = 0;
            $completedEvals = 0;
            foreach ($allMondays as $mondayStr) {
                $eligibleIds = Staff::salonEvaluatable($assignedBranch, $mondayStr)
                    ->pluck('id')
                    ->map(fn ($id) => (int) $id)
                    ->all();
                $expectedEvals += count($eligibleIds);
                if (!empty($eligibleIds)) {
                    $completedEvals += $evalRows
                        ->filter(function (KpiStaffEvaluation $e) use ($mondayStr, $eligibleIds): bool {
                            $evalMonday = Carbon::parse($e->evaluation_date, 'Asia/Qatar')
                                ->startOfWeek(Carbon::MONDAY)
                                ->toDateString();

                            return $evalMonday === $mondayStr && in_array((int) $e->staff_id, $eligibleIds, true);
                        })
                        ->pluck('staff_id')
                        ->map(fn ($id) => (int) $id)
                        ->unique()
                        ->count();
                }
            }
            $evalPct = $expectedEvals > 0 ? min(100.0, round(($completedEvals / $expectedEvals) * 100, 1)) : 0.0;
            $evalPass = $expectedEvals > 0 && $completedEvals >= $expectedEvals;

            $audits = Schema::hasTable('branch_evaluations')
                ? BranchEvaluation::query()
                    ->where('branch', $assignedBranch)
                    ->whereDate('evaluation_date', '>=', $monthStart->toDateString())
                    ->whereDate('evaluation_date', '<=', $nowQatar->toDateString())
                    ->get(['id', 'percentage'])
                : collect();
            $auditsCount = $audits->count();
            $auditAvgPct = $auditsCount > 0 ? round((float) $audits->avg('percentage'), 1) : 0.0;
            $auditAttainPct = $auditsCount > 0
                ? min(100.0, round(($auditAvgPct / DashboardScorecardBuilder::BRANCH_AUDIT_TARGET_PCT) * 100, 1))
                : 0.0;
            $auditPass = $auditsCount > 0 && $auditAvgPct >= DashboardScorecardBuilder::BRANCH_AUDIT_TARGET_PCT;

            $branchKpiUrl = $canOpenKpis ? route('kpi.staff-sales.index', ['branch' => $assignedBranch]) : null;
            $evalUrl = $canOpenKpis ? route('kpi.evaluations.index', ['branch' => $assignedBranch]) : null;
            $auditUrl = $canOpenKpis ? route('kpi.branch-evaluation.index', ['branch' => $assignedBranch]) : null;

            return [
                'heading' => "Branch Manager KPIs — {$branchLabel}",
                'period_label' => $periodLabel,
                'columns' => 3,
                'action_label' => $branchKpiUrl ? 'Open Branch KPIs' : null,
                'action_url' => $branchKpiUrl,
                'cards' => [
                    [
                        'label' => 'Chair Upsells',
                        'subnote' => null,
                        'tag' => strtoupper($branchLabel),
                        'tag_tone' => $upsellPass ? 'pass' : 'champagne',
                        'tag_url' => $branchKpiUrl,
                        'icon' => 'store',
                        'orb_tone' => $upsellPass ? 'emerald' : 'champagne',
                        'hero_main' => number_format($upsellActual, 0),
                        'hero_unit' => '/ ' . number_format($upsellTarget, 0) . ' QAR',
                        'delta_highlight' => number_format($upsellPct, 1) . '%',
                        'delta_tone' => $upsellPass ? 'emerald' : 'champagne',
                        'delta_text' => "of MTD {$branchLabel} upsell target",
                        'bar_pct' => $upsellPct,
                        'bar_tone' => $upsellPass ? 'emerald' : 'champagne',
                        'scale_left' => '0%',
                        'scale_right' => '100%',
                    ],
                    [
                        'label' => 'Monday Staff Evaluations',
                        'subnote' => null,
                        'tag' => $evalPass ? 'COMPLIANT' : 'EVALUATIONS',
                        'tag_tone' => $evalPass ? 'pass' : 'rose',
                        'tag_url' => $evalUrl,
                        'icon' => 'star',
                        'orb_tone' => $evalPass ? 'emerald' : 'rose',
                        'hero_main' => number_format($evalPct, 1) . '%',
                        'hero_unit' => null,
                        'delta_highlight' => "{$completedEvals}/{$expectedEvals} evaluated",
                        'delta_tone' => $evalPass ? 'emerald' : 'rose',
                        'delta_text' => count($allMondays) . ' Mon MTD · 100% Target',
                        'bar_pct' => $evalPct,
                        'bar_tone' => $evalPass ? 'emerald' : 'rose-gold',
                        'scale_left' => '0%',
                        'scale_right' => '100%',
                    ],
                    [
                        'label' => 'Branch Mystery Shopper',
                        'subnote' => null,
                        'tag' => $auditPass ? 'PASSING' : 'AUDIT SCORE',
                        'tag_tone' => $auditPass ? 'pass' : 'rose',
                        'tag_url' => $auditUrl,
                        'icon' => 'shield',
                        'orb_tone' => $auditPass ? 'emerald' : 'rose',
                        'hero_main' => number_format($auditAvgPct, 1) . '%',
                        'hero_unit' => null,
                        'delta_highlight' => '≥ 85.0% Target',
                        'delta_tone' => $auditPass ? 'emerald' : 'ruby',
                        'delta_text' => "({$auditsCount} branch audits MTD)",
                        'bar_pct' => $auditAttainPct,
                        'bar_tone' => $auditPass ? 'emerald' : 'rose-gold',
                        'scale_left' => '0%',
                        'scale_right' => '85% Target',
                    ],
                ],
                'ops_items' => null,
                'branch_pending_complaints' => $branchPendingComplaints,
                'weekly_evaluations' => null,
                'my_complaints' => null,
            ];
        }

        // 5. Salon Staff — 3 Summary Scorecards + Weekly Evaluations History + Privacy-Safe My Complaints
        if ($roleSlug === 'salon_staff') {
            $evals = $weeklyEvaluations ?? [];
            $complaints = $myComplaints ?? [];
            $evalCount = count($evals);
            $evalAvg = $evalCount > 0
                ? round(collect($evals)->avg(fn ($e) => (float) $e['score']), 2)
                : null;
            $evalAvgPct = $evalAvg !== null ? min(100.0, round(($evalAvg / 5.0) * 100, 1)) : 0.0;
            $evalPass = $evalAvg !== null && $evalAvg >= 4.0;
            $latestEvalDate = $evalCount > 0 ? $evals[0]['date_label'] : null;

            $complaintsCount = count($complaints);
            $unresolvedCount = collect($complaints)->where('status', 'Under Review')->count();
            $complaintsClean = $complaintsCount === 0;

            return [
                'heading' => 'Service Quality & Evaluations',
                'period_label' => 'Personal Performance Summary',
                'columns' => 3,
                'action_label' => null,
                'action_url' => null,
                'cards' => [
                    [
                        'label' => 'Weekly Evaluation Average',
                        'subnote' => null,
                        'tag' => $evalPass ? 'EXCELLENT' : 'QUALITY',
                        'tag_tone' => $evalPass ? 'pass' : 'champagne',
                        'tag_url' => null,
                        'icon' => 'star',
                        'orb_tone' => $evalPass ? 'emerald' : 'champagne',
                        'hero_main' => $evalAvg !== null ? number_format($evalAvg, 2) : '—',
                        'hero_unit' => '/ 5.0',
                        'delta_highlight' => $evalAvg !== null ? number_format($evalAvgPct, 0) . '%' : 'Not scored yet',
                        'delta_tone' => $evalPass ? 'emerald' : 'champagne',
                        'delta_text' => $evalAvg !== null ? 'average score (Target: ≥ 4.0)' : 'Weekly manager evaluations appear here',
                        'bar_pct' => $evalAvgPct,
                        'bar_tone' => $evalPass ? 'emerald' : 'champagne',
                        'scale_left' => '0',
                        'scale_right' => '5.0 Max',
                    ],
                    [
                        'label' => 'Evaluations Completed',
                        'subnote' => null,
                        'tag' => 'REVIEWS',
                        'tag_tone' => 'muted',
                        'tag_url' => null,
                        'icon' => 'check-circle',
                        'orb_tone' => $evalCount > 0 ? 'emerald' : 'rose',
                        'hero_main' => (string) $evalCount,
                        'hero_unit' => null,
                        'delta_highlight' => $latestEvalDate ? "Latest: {$latestEvalDate}" : '0 recorded',
                        'delta_tone' => $evalCount > 0 ? 'emerald' : 'rose',
                        'delta_text' => 'weekly branch evaluations',
                        'bar_pct' => $evalCount > 0 ? min(100.0, round(($evalCount / 4) * 100, 1)) : 0.0,
                        'bar_tone' => $evalCount > 0 ? 'emerald' : 'rose-gold',
                        'scale_left' => '0',
                        'scale_right' => '4 / Month',
                    ],
                    [
                        'label' => 'Complaints Count',
                        'subnote' => null,
                        'tag' => $complaintsClean ? 'CLEAN RECORD' : 'SERVICE REVIEW',
                        'tag_tone' => $complaintsClean ? 'pass' : 'ruby',
                        'tag_url' => null,
                        'icon' => 'shield',
                        'orb_tone' => $complaintsClean ? 'emerald' : 'ruby',
                        'hero_main' => (string) $complaintsCount,
                        'hero_unit' => null,
                        'delta_highlight' => $complaintsClean ? '0 complaints' : ($unresolvedCount > 0 ? "{$unresolvedCount} under review" : 'All resolved'),
                        'delta_tone' => $complaintsClean ? 'emerald' : ($unresolvedCount > 0 ? 'ruby' : 'champagne'),
                        'delta_text' => 'Target: 0 service complaints',
                        'bar_pct' => $complaintsClean ? 100.0 : max(10.0, 100.0 - ($complaintsCount * 25.0)),
                        'bar_tone' => $complaintsClean ? 'emerald' : 'ruby',
                        'scale_left' => 'Target: 0',
                        'scale_right' => $complaintsClean ? '100% Clean' : 'Logged',
                    ],
                ],
                'ops_items' => null,
                'branch_pending_complaints' => null,
                'weekly_evaluations' => $evals,
                'my_complaints' => $complaints,
            ];
        }

        // 6. Marketing Specialist — Paid Ads KPIs
        if ($roleSlug === 'marketing_specialist') {
            $adLeadColumns = ['id', 'date', 'customer_id', 'phone', 'branch', 'ticket_amount', 'category', 'lead_id'];
            if (Schema::hasColumn('ad_lead_entries', 'old_airport_amount')) {
                $adLeadColumns[] = 'old_airport_amount';
            }
            if (Schema::hasColumn('ad_lead_entries', 'wakrah_amount')) {
                $adLeadColumns[] = 'wakrah_amount';
            }

            $adLeads = Schema::hasTable('ad_lead_entries')
                ? AdLeadEntry::query()->whereBetween('date', [$monthStart, $todayEnd])->get($adLeadColumns)
                : collect();

            $oaRev = round((float) $adLeads->sum(fn (AdLeadEntry $e) => $e->branchRevenue('old_airport')), 0);
            $wkRev = round((float) $adLeads->sum(fn (AdLeadEntry $e) => $e->branchRevenue('wakrah')), 0);
            $mtdBranchTarget = max(1.0, round((DashboardScorecardBuilder::BRANCH_AD_MONTHLY_TARGET_QAR / $daysInMonth) * $daysElapsed, 0));
            $oaPct = min(100.0, round(($oaRev / $mtdBranchTarget) * 100, 1));
            $wkPct = min(100.0, round(($wkRev / $mtdBranchTarget) * 100, 1));
            $oaPass = $oaRev >= $mtdBranchTarget;
            $wkPass = $wkRev >= $mtdBranchTarget;

            $totalAdLeads = $adLeads->count();
            $bookedAdLeads = $adLeads->filter(fn (AdLeadEntry $e) => $e->isBooked())->count();
            $adConvPct = $totalAdLeads > 0 ? round(($bookedAdLeads / $totalAdLeads) * 100, 1) : 0.0;
            $adConvBarPct = min(100.0, round(($adConvPct / DashboardScorecardBuilder::AD_CONVERSION_TARGET_PCT) * 100, 1));
            $adConvPass = $adConvPct >= DashboardScorecardBuilder::AD_CONVERSION_TARGET_PCT;
            $adsUrl = $canOpenKpis ? route('kpi.ads.index') : null;

            return [
                'heading' => 'Marketing & Paid Ads KPIs',
                'period_label' => $periodLabel,
                'columns' => 3,
                'action_label' => $adsUrl ? 'Open Ads Conversion' : null,
                'action_url' => $adsUrl,
                'cards' => [
                    [
                        'label' => 'Old Airport Ad Revenue',
                        'subnote' => null,
                        'tag' => 'OLD AIRPORT',
                        'tag_tone' => $oaPass ? 'pass' : 'rose',
                        'tag_url' => $adsUrl,
                        'icon' => 'trending',
                        'orb_tone' => $oaPass ? 'emerald' : 'rose',
                        'hero_main' => number_format($oaRev, 0),
                        'hero_unit' => 'QAR',
                        'delta_highlight' => number_format($oaPct, 0) . '%',
                        'delta_tone' => $oaPass ? 'emerald' : 'rose',
                        'delta_text' => 'of ' . number_format($mtdBranchTarget, 0) . ' QAR MTD target (15K Full Mo)',
                        'bar_pct' => $oaPct,
                        'bar_tone' => $oaPass ? 'emerald' : 'rose-gold',
                        'scale_left' => '0%',
                        'scale_right' => '100%',
                    ],
                    [
                        'label' => 'Al Wakrah Ad Revenue',
                        'subnote' => null,
                        'tag' => 'AL WAKRAH',
                        'tag_tone' => $wkPass ? 'pass' : 'champagne',
                        'tag_url' => $adsUrl,
                        'icon' => 'trending',
                        'orb_tone' => $wkPass ? 'emerald' : 'champagne',
                        'hero_main' => number_format($wkRev, 0),
                        'hero_unit' => 'QAR',
                        'delta_highlight' => number_format($wkPct, 0) . '%',
                        'delta_tone' => $wkPass ? 'emerald' : 'champagne',
                        'delta_text' => 'of ' . number_format($mtdBranchTarget, 0) . ' QAR MTD target (15K Full Mo)',
                        'bar_pct' => $wkPct,
                        'bar_tone' => $wkPass ? 'emerald' : 'champagne',
                        'scale_left' => '0%',
                        'scale_right' => '100%',
                    ],
                    [
                        'label' => 'Ad Booking Conversion',
                        'subnote' => null,
                        'tag' => $adConvPass ? 'ON TARGET' : 'CONVERSION',
                        'tag_tone' => $adConvPass ? 'pass' : 'rose',
                        'tag_url' => $adsUrl,
                        'icon' => 'check-circle',
                        'orb_tone' => $adConvPass ? 'emerald' : 'rose',
                        'hero_main' => number_format($adConvPct, 1) . '%',
                        'hero_unit' => null,
                        'delta_highlight' => '≥ 20.0% Target',
                        'delta_tone' => $adConvPass ? 'emerald' : 'ruby',
                        'delta_text' => "({$bookedAdLeads}/{$totalAdLeads} ad leads booked)",
                        'bar_pct' => $adConvBarPct,
                        'bar_tone' => $adConvPass ? 'emerald' : 'rose-gold',
                        'scale_left' => '0%',
                        'scale_right' => '20% Target',
                    ],
                ],
                'ops_items' => null,
                'branch_pending_complaints' => null,
                'weekly_evaluations' => null,
                'my_complaints' => null,
            ];
        }

        // 7. Social Media Editor — Organic Content Plan (MTD) & Social Reply SLA
        if ($roleSlug === 'social_media_editor') {
            $firstMondayStr = $monthStart->copy()->startOfWeek(Carbon::MONDAY)->toDateString();
            $lastMondayStr = $nowQatar->copy()->startOfWeek(Carbon::MONDAY)->toDateString();
            $minDateStr = $monthStart->toDateString();
            $maxDateStr = $nowQatar->toDateString();

            $inMonthPlans = Schema::hasTable('social_content_plans')
                ? SocialContentPlan::query()
                    ->whereDate('week_start_date', '>=', $firstMondayStr)
                    ->whereDate('week_start_date', '<=', $lastMondayStr)
                    ->get()
                    ->filter(function (SocialContentPlan $plan) use ($minDateStr, $maxDateStr): bool {
                        $exact = $plan->exactDate()->toDateString();

                        return $exact >= $minDateStr && $exact <= $maxDateStr;
                    })
                    ->values()
                : collect();

            $publishedReels = $inMonthPlans->filter(fn (SocialContentPlan $p) => $p->is_reel_posted && !$p->is_reel_skipped)->count();
            $publishedStories = $inMonthPlans->where('are_stories_posted', true)->count();
            $postedTotal = $publishedReels + $publishedStories;

            $completedDays = max(1, $daysElapsed - 1);
            $storiesTarget = $completedDays;
            $reelsTarget = max(1, (int) floor(($completedDays * SocialContentPlan::WEEKLY_REEL_TARGET) / 7));
            $totalTarget = max(1, $reelsTarget + $storiesTarget);
            $contentPct = min(100.0, round(($postedTotal / $totalTarget) * 100, 1));
            $contentPass = $publishedReels >= $reelsTarget && $publishedStories >= $storiesTarget;

            $lateCount = (Schema::hasTable('social_late_reply_logs')
                ? SocialLateReplyLog::query()->whereBetween('incident_at', [$monthStart, $todayEnd])->count()
                : 0) + (int) $inMonthPlans->sum('late_replies_count');
            $replyPass = $lateCount === 0;
            $contentUrl = $canOpenKpis ? route('kpi.content-calendar.index') : null;

            return [
                'heading' => 'Content & Social KPIs',
                'period_label' => $periodLabel,
                'columns' => 2,
                'action_label' => $contentUrl ? 'Open Content Calendar' : null,
                'action_url' => $contentUrl,
                'cards' => [
                    [
                        'label' => 'Organic Content Plan (MTD)',
                        'subnote' => null,
                        'tag' => $contentPass ? 'ON TARGET' : 'CONTENT',
                        'tag_tone' => $contentPass ? 'pass' : 'champagne',
                        'tag_url' => $contentUrl,
                        'icon' => 'reels',
                        'orb_tone' => $contentPass ? 'emerald' : 'champagne',
                        'hero_main' => (string) $postedTotal,
                        'hero_unit' => "/ {$totalTarget}",
                        'delta_highlight' => "{$publishedReels}/{$reelsTarget} Reels",
                        'delta_tone' => $contentPass ? 'emerald' : 'rose',
                        'delta_text' => "· {$publishedStories}/{$storiesTarget} Stories MTD",
                        'bar_pct' => $contentPct,
                        'bar_tone' => $contentPass ? 'emerald' : 'rose-gold',
                        'scale_left' => '0%',
                        'scale_right' => '100%',
                    ],
                    [
                        'label' => 'Social Reply SLA',
                        'subnote' => null,
                        'tag' => $replyPass ? '100% SLA' : 'SLA BREACH',
                        'tag_tone' => $replyPass ? 'pass' : 'ruby',
                        'tag_url' => $contentUrl,
                        'icon' => 'chat',
                        'orb_tone' => $replyPass ? 'emerald' : 'ruby',
                        'hero_main' => (string) $lateCount,
                        'hero_unit' => 'Late',
                        'delta_highlight' => $replyPass ? '0 late replies' : "{$lateCount} late",
                        'delta_tone' => $replyPass ? 'emerald' : 'ruby',
                        'delta_text' => 'Target: 0 delayed social replies',
                        'bar_pct' => $replyPass ? 100.0 : 4.0,
                        'bar_tone' => $replyPass ? 'emerald' : 'ruby',
                        'scale_left' => '0%',
                        'scale_right' => '100% SLA',
                    ],
                ],
                'ops_items' => null,
                'branch_pending_complaints' => null,
                'weekly_evaluations' => null,
                'my_complaints' => null,
            ];
        }

        // Default / Admin fallback
        return [
            'heading' => 'Performance & KPIs',
            'period_label' => $periodLabel,
            'columns' => 3,
            'action_label' => $canOpenKpis ? 'Open Executive KPIs' : null,
            'action_url' => $canOpenKpis ? route('kpi.hub') : null,
            'cards' => [],
            'empty_message' => 'No individual KPI targets are configured for this role. View team-wide scorecards in Executive KPIs.',
            'ops_items' => null,
            'branch_pending_complaints' => null,
            'weekly_evaluations' => null,
            'my_complaints' => null,
        ];
    }

    private function buildBranchPendingComplaintsByBranch(string $branch): array
    {
        $cleanBranch = strtolower(trim($branch));
        $branchLabel = match ($cleanBranch) {
            'wakrah', 'al_wakrah' => 'Al Wakrah',
            'both' => 'Old Airport & Al Wakrah',
            default => 'Old Airport',
        };
        $branches = $cleanBranch === 'both'
            ? ['old_airport', 'wakrah']
            : (in_array($cleanBranch, ['old_airport', 'wakrah'], true) ? [$cleanBranch] : []);

        if (empty($branches) || !Schema::hasTable('staff_complaints')) {
            return [
                'branch_label' => $branchLabel,
                'items' => [],
            ];
        }

        $items = StaffComplaint::query()
            ->with('staffMembers:id,name')
            ->whereIn('branch', $branches)
            ->whereNull('resolved_at')
            ->orderByDesc('complaint_date')
            ->orderByDesc('id')
            ->limit(15)
            ->get([
                'id',
                'reference_number',
                'complaint_date',
                'branch',
                'category',
                'is_staff_mistake',
                'resolved_at',
            ])
            ->map(fn (StaffComplaint $c) => [
                'id' => $c->id,
                'ref' => $c->reference_number,
                'date_label' => $c->complaint_date ? $c->complaint_date->format('d M Y') : '—',
                'category' => $c->category,
                'staff_names' => $c->staffMembers->pluck('name')->implode(', ') ?: 'Unassigned',
                'status' => 'Pending Action',
                'resolve_url' => route('staffs.index', ['tab' => 'complaints']),
            ])
            ->values()
            ->all();

        return [
            'branch_label' => $branchLabel,
            'items' => $items,
        ];
    }

    private function buildBranchPendingComplaints(Staff $managerStaff): array
    {
        $branch = strtolower(trim((string) $managerStaff->branch));
        $branches = $branch === 'both'
            ? ['old_airport', 'wakrah']
            : (in_array($branch, ['old_airport', 'wakrah'], true) ? [$branch] : []);

        if (empty($branches) || !Schema::hasTable('staff_complaints')) {
            return [
                'branch_label' => $managerStaff->location_label,
                'items' => [],
            ];
        }

        $items = StaffComplaint::query()
            ->with('staffMembers:id,name')
            ->whereIn('branch', $branches)
            ->whereNull('resolved_at')
            ->orderByDesc('complaint_date')
            ->orderByDesc('id')
            ->limit(15)
            ->get([
                'id',
                'reference_number',
                'complaint_date',
                'branch',
                'category',
                'is_staff_mistake',
                'resolved_at',
            ])
            ->map(fn (StaffComplaint $c) => [
                'id' => $c->id,
                'ref' => $c->reference_number,
                'date_label' => $c->complaint_date ? $c->complaint_date->format('d M Y') : '—',
                'category' => $c->category,
                'staff_names' => $c->staffMembers->pluck('name')->implode(', ') ?: 'Unassigned',
                'status' => 'Pending Action',
                'resolve_url' => route('staffs.index', ['tab' => 'complaints']),
            ])
            ->values()
            ->all();

        return [
            'branch_label' => $managerStaff->location_label,
            'items' => $items,
        ];
    }

    /**
     * STRICT PRIVACY: Returns complaints linked strictly to $staff WITHOUT any client
     * phone number, client name, client email, or address fields ever selected or serialized.
     *
     * @return list<array{ref: string, date_label: string, category: string, summary: string, status: string, outcome: string}>
     */
    private function buildPrivacySafeStaffComplaints(Staff $staff): array
    {
        if (!Schema::hasTable('staff_complaints')) {
            return [];
        }

        return StaffComplaint::query()
            ->whereHas('staffMembers', fn ($q) => $q->where('staff.id', $staff->id))
            ->orderByDesc('complaint_date')
            ->orderByDesc('id')
            ->get([
                'id',
                'reference_number',
                'complaint_date',
                'category',
                'what_happened',
                'description',
                'is_staff_mistake',
                'resolved_at',
                'corrective_action',
                'manager_feedback',
            ])
            ->map(function (StaffComplaint $c) {
                $rawSummary = trim((string) ($c->what_happened ?: $c->description ?: ''));
                // Extra defense-in-depth: strip any accidentally pasted phone numbers or email addresses
                $sanitizedSummary = preg_replace('/[\w.+-]+@[\w-]+\.[\w.-]+/', '[redacted]', $rawSummary) ?? $rawSummary;
                $sanitizedSummary = preg_replace('/(\+?\d[\d\s\-()]{6,}\d)/', '[redacted]', $sanitizedSummary) ?? $sanitizedSummary;
                $briefSummary = $sanitizedSummary !== '' ? Str::limit($sanitizedSummary, 160) : 'Service quality review logged.';

                $status = $c->resolved_at ? 'Resolved' : 'Under Review';
                $outcomeParts = [];
                if ($c->is_staff_mistake === true) {
                    $outcomeParts[] = 'Staff Mistake Confirmed';
                } elseif ($c->is_staff_mistake === false) {
                    $outcomeParts[] = 'Cleared / No Staff Fault';
                }
                if (filled($c->corrective_action)) {
                    $outcomeParts[] = trim((string) $c->corrective_action);
                } elseif (filled($c->manager_feedback)) {
                    $outcomeParts[] = trim((string) $c->manager_feedback);
                }

                return [
                    'ref' => (string) $c->reference_number,
                    'date_label' => $c->complaint_date ? $c->complaint_date->format('d M Y') : '—',
                    'category' => (string) ($c->category ?: 'General'),
                    'summary' => $briefSummary,
                    'status' => $status,
                    'outcome' => !empty($outcomeParts) ? implode(' · ', $outcomeParts) : 'Pending review',
                ];
            })
            ->values()
            ->all();
    }

    /**
     * Weekly evaluations submitted by Salon Managers for $staff (read-only).
     *
     * @return list<array{date_label: string, evaluator: string, score: string, notes: ?string}>
     */
    private function buildStaffWeeklyEvaluations(Staff $staff): array
    {
        if (!Schema::hasTable('kpi_staff_evaluations')) {
            return [];
        }

        return KpiStaffEvaluation::query()
            ->with('evaluator:id,name')
            ->where('staff_id', $staff->id)
            ->orderByDesc('evaluation_date')
            ->orderByDesc('id')
            ->limit(12)
            ->get([
                'id',
                'evaluator_id',
                'evaluation_date',
                'overall_average',
                'leader_notes',
            ])
            ->map(fn (KpiStaffEvaluation $e) => [
                'date_label' => $e->evaluation_date ? $e->evaluation_date->format('d M Y') : '—',
                'evaluator' => $e->evaluator?->name ?? 'Branch Manager',
                'score' => number_format((float) $e->overall_average, 2),
                'notes' => filled($e->leader_notes) ? trim((string) $e->leader_notes) : null,
            ])
            ->values()
            ->all();
    }

    private function buildDeductionsPayload(?Staff $staff, Carbon $nowQatar): array
    {
        if (!$staff || !Schema::hasTable('staff_deductions')) {
            return [
                'month_total' => 0.0,
                'month_label' => $nowQatar->format('M Y'),
                'items' => [],
            ];
        }

        $monthStart = $nowQatar->copy()->startOfMonth()->toDateString();
        $monthEnd = $nowQatar->copy()->endOfMonth()->toDateString();

        $rows = StaffDeduction::query()
            ->with('complaint:id,reference_number')
            ->where('staff_id', $staff->id)
            ->orderByDesc('deduction_date')
            ->orderByDesc('id')
            ->get(['id', 'deduction_date', 'amount', 'reason', 'complaint_id']);

        $monthTotal = round((float) $rows
            ->filter(fn (StaffDeduction $d) => $d->deduction_date && $d->deduction_date->toDateString() >= $monthStart && $d->deduction_date->toDateString() <= $monthEnd)
            ->sum('amount'), 2);

        $items = $rows->map(fn (StaffDeduction $d) => [
            'date_label' => $d->deduction_date ? $d->deduction_date->format('d M Y') : '—',
            'amount' => round((float) $d->amount, 2),
            'reason' => (string) $d->reason,
            'source' => $d->complaint ? $d->complaint->reference_number : 'Manual',
            'is_manual' => $d->complaint_id === null,
        ])->values()->all();

        return [
            'month_total' => $monthTotal,
            'month_label' => $nowQatar->format('M Y'),
            'items' => $items,
        ];
    }

    private function buildContractsPayload(User $user, ?Staff $staff): array
    {
        $contracts = collect();
        if (Schema::hasTable('staff_contracts') && ($user->exists || $staff)) {
            $contracts = StaffContract::query()
                ->where(function ($q) use ($user, $staff) {
                    if ($staff) {
                        $q->where('staff_id', $staff->id);
                    }
                    if ($user->exists) {
                        if ($staff) {
                            $q->orWhere('user_id', $user->id);
                        } else {
                            $q->where('user_id', $user->id);
                        }
                    }
                })
                ->orderByRaw("CASE status WHEN 'active' THEN 1 WHEN 'pending_signature' THEN 2 ELSE 3 END ASC")
                ->orderByDesc('start_date')
                ->orderByDesc('id')
                ->get([
                    'id',
                    'contract_number',
                    'template_key',
                    'role_title',
                    'branch',
                    'currency',
                    'base_salary',
                    'housing_allowance',
                    'transport_allowance',
                    'other_allowance',
                    'start_date',
                    'expiry_date',
                    'status',
                    'signed_at',
                ]);
        }

        $signedContractDocs = ($staff && Schema::hasTable('staff_documents'))
            ? StaffDocument::query()
                ->where('staff_id', $staff->id)
                ->where('category', 'signed_contract')
                ->orderByDesc('created_at')
                ->get()
            : collect();

        $formattedContracts = $contracts->map(function (StaffContract $c) {
            $currency = strtoupper(trim((string) ($c->currency ?: 'QAR'))) ?: 'QAR';
            $baseSalary = (float) ($c->base_salary ?? 0);
            $totalAllowances = $c->totalAllowances();
            $totalPackage = $c->totalMonthlyPackage();

            return [
                'id' => $c->id,
                'contract_number' => $c->contract_number,
                'role_title' => $c->designationLabel(),
                'contract_type' => $c->templateLabel(),
                'branch_label' => $c->branchLabel(),
                'currency' => $currency,
                'base_salary' => $baseSalary,
                'formatted_base_salary' => $baseSalary > 0 ? $this->formatCurrencyAmount($baseSalary, $currency) : 'Not set',
                'total_allowances' => $totalAllowances,
                'total_package' => $totalPackage,
                'formatted_total_package' => $totalPackage > 0 ? $this->formatCurrencyAmount($totalPackage, $currency) : 'Not set',
                'start_date' => $c->start_date?->format('d M Y') ?? '—',
                'expiry_date' => $c->expiry_date?->format('d M Y') ?? 'Open-ended',
                'status' => $c->status,
                'status_label' => $c->statusLabel(),
                'signed_at' => $c->signed_at?->format('d M Y'),
                'view_url' => route('portal.contracts.pdf', ['contract' => $c->id, 'inline' => 1]),
                'download_url' => route('portal.contracts.pdf', ['contract' => $c->id]),
            ];
        })->values();

        $formattedDocs = $signedContractDocs->map(fn (StaffDocument $d) => [
            'id' => $d->id,
            'title' => $d->title,
            'document_number' => $d->document_number,
            'issue_date' => $d->issue_date?->format('d M Y') ?? '—',
            'expiry_date' => $d->expiry_date?->format('d M Y') ?? 'Open-ended',
            'original_filename' => $d->original_filename,
            'view_url' => route('staff-documents.download', ['document' => $d->id, 'inline' => 1]),
            'download_url' => route('staff-documents.download', ['document' => $d->id]),
        ])->values();

        $activeContract = $formattedContracts->firstWhere('status', StaffContract::STATUS_ACTIVE)
            ?? $formattedContracts->first();
        $priorContracts = $activeContract
            ? $formattedContracts->reject(fn ($c) => $c['id'] === $activeContract['id'])->values()->all()
            : [];

        return [
            'active_contract' => $activeContract,
            'prior_contracts' => $priorContracts,
            'signed_documents' => $formattedDocs->all(),
        ];
    }

    private function buildDocumentsPayload(?Staff $staff, Carbon $todayQatar): array
    {
        if (!$staff || !Schema::hasTable('staff_documents')) {
            return [];
        }

        return StaffDocument::query()
            ->where('staff_id', $staff->id)
            ->orderByDesc('created_at')
            ->get()
            ->map(function (StaffDocument $d) use ($todayQatar) {
                $exp = $d->expiryStatus($todayQatar);

                return [
                    'id' => $d->id,
                    'category' => $d->category,
                    'category_label' => $d->categoryLabel(),
                    'title' => $d->title,
                    'document_number' => $d->document_number,
                    'issue_date_label' => $d->issue_date?->format('d M Y') ?? '—',
                    'expiry_date_label' => $d->expiry_date?->format('d M Y') ?? '—',
                    'expiry_status' => $exp,
                    'original_filename' => $d->original_filename,
                    'file_size_label' => $d->formattedFileSize(),
                    'view_url' => route('staff-documents.download', ['document' => $d->id, 'inline' => 1]),
                    'download_url' => route('staff-documents.download', ['document' => $d->id]),
                ];
            })
            ->values()
            ->all();
    }

    private function buildLeavesPayload(?Staff $staff, Carbon $nowQatar, string $typeFilter = 'all'): array
    {
        $year = (int) $nowQatar->year;
        $annualEntitled = (int) ($staff?->annual_leave_days ?? 21);
        $sickEntitled = (int) ($staff?->sick_leave_days ?? 14);

        $allLeaves = ($staff && Schema::hasTable('staff_leaves'))
            ? StaffLeave::query()
                ->where('staff_id', $staff->id)
                ->orderByDesc('start_date')
                ->orderByDesc('id')
                ->get()
            : collect();

        $annualUsed = 0;
        $annualPending = 0;
        $sickUsed = 0;
        $sickPending = 0;

        foreach ($allLeaves as $leave) {
            $daysInYear = $leave->daysWithinYear($year);
            if ($daysInYear <= 0) {
                continue;
            }
            if ($leave->leave_type === StaffLeave::TYPE_ANNUAL) {
                if ($leave->status === StaffLeave::STATUS_APPROVED) {
                    $annualUsed += $daysInYear;
                } elseif ($leave->status === StaffLeave::STATUS_PENDING) {
                    $annualPending += $daysInYear;
                }
            } elseif ($leave->leave_type === StaffLeave::TYPE_SICK) {
                if ($leave->status === StaffLeave::STATUS_APPROVED) {
                    $sickUsed += $daysInYear;
                } elseif ($leave->status === StaffLeave::STATUS_PENDING) {
                    $sickPending += $daysInYear;
                }
            }
        }

        $filtered = $typeFilter === 'all'
            ? $allLeaves
            : $allLeaves->where('leave_type', $typeFilter)->values();

        $history = $filtered->map(fn (StaffLeave $l) => [
            'id' => $l->id,
            'leave_type' => $l->leave_type,
            'type_label' => $l->typeLabel(),
            'date_range' => $l->start_date->equalTo($l->end_date)
                ? $l->start_date->format('d M Y')
                : ($l->start_date->format('d M') . ' – ' . $l->end_date->format('d M Y')),
            'total_days' => (int) $l->total_days,
            'reason' => $l->reason,
            'status' => $l->status,
            'status_label' => $l->statusLabel(),
            'admin_notes' => $l->admin_notes,
            'has_certificate' => !empty($l->medical_certificate_path),
            'certificate_url' => !empty($l->medical_certificate_path)
                ? route('staff-leaves.certificate', ['leave' => $l->id, 'inline' => 1])
                : null,
            'can_cancel' => $l->status === StaffLeave::STATUS_PENDING,
            'cancel_url' => route('portal.leaves.cancel', $l->id),
        ])->values()->all();

        return [
            'year' => $year,
            'filter' => $typeFilter,
            'annual' => [
                'entitled' => $annualEntitled,
                'used' => $annualUsed,
                'pending' => $annualPending,
                'remaining' => max(0, $annualEntitled - $annualUsed),
            ],
            'sick' => [
                'entitled' => $sickEntitled,
                'used' => $sickUsed,
                'pending' => $sickPending,
                'remaining' => max(0, $sickEntitled - $sickUsed),
            ],
            'history' => $history,
        ];
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

        $timeOffs = $staff->timeOffs
            ->filter(function ($t) use ($todayQatar, $windowEndQatar) {
                $start = $t->start_date->copy()->startOfDay();
                $end = $t->end_date->copy()->startOfDay();

                return $end->gte($todayQatar) && $start->lte($windowEndQatar);
            })
            ->sortBy('start_date')
            ->values();

        $approvedLeaves = Schema::hasTable('staff_leaves')
            ? StaffLeave::query()
                ->where('staff_id', $staff->id)
                ->approved()
                ->whereDate('end_date', '>=', $todayQatar->toDateString())
                ->whereDate('start_date', '<=', $windowEndQatar->toDateString())
                ->orderBy('start_date')
                ->get()
            : collect();

        $scheduledTimeOffs = $timeOffs->map(function ($t) {
            $start = $t->start_date->copy()->startOfDay();
            $end = $t->end_date->copy()->startOfDay();
            $days = $start->diffInDays($end) + 1;
            $dateRange = $start->equalTo($end)
                ? $start->format('D, d M Y')
                : $start->format('d M') . ' – ' . $end->format('d M Y');

            return [
                'sort_key' => $start->toDateString(),
                'date_range' => $dateRange,
                'days' => $days,
                'reason_label' => $this->formatTimeOffReason($t->reason),
                'reason' => (string) $t->reason,
                'notes' => $t->notes,
            ];
        })->concat(
            $approvedLeaves->map(function (StaffLeave $l) {
                $start = $l->start_date->copy()->startOfDay();
                $end = $l->end_date->copy()->startOfDay();
                $dateRange = $start->equalTo($end)
                    ? $start->format('D, d M Y')
                    : $start->format('d M') . ' – ' . $end->format('d M Y');

                return [
                    'sort_key' => $start->toDateString(),
                    'date_range' => $dateRange,
                    'days' => (int) $l->total_days,
                    'reason_label' => $l->typeLabel(),
                    'reason' => $l->leave_type,
                    'notes' => $l->reason,
                ];
            })
        )->sortBy('sort_key')->values()->all();

        $upcomingWeeklyDates = [];
        for ($i = 0; $i < 30; $i++) {
            $cursor = $todayQatar->copy()->addDays($i);
            $coveredByTimeOff = $timeOffs->contains(fn ($t) => $t->coversDate($cursor))
                || $approvedLeaves->contains(fn (StaffLeave $l) => $l->coversDate($cursor));
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
