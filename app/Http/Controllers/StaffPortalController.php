<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Models\KpiChatEvaluation;
use App\Models\KpiStaffEvaluation;
use App\Models\OpsActionItem;
use App\Models\ShiftPattern;
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
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

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

        // ---- Skills Card (Salon Staff ONLY) ----
        $skillsPayload = $isSalonStaff ? $this->buildSalonSkillsPayload($staff) : null;

        // ---- Off Days (Weekly Fixed + Next 30 Days incl. Approved Leaves & Time Off) ----
        $offDaysPayload = $this->buildOffDaysPayload($staff, $activePattern, $todayQatar, $windowEndQatar);

        // ---- Absentees Card (ALL roles — live from staff_attendances) ----
        $absenteesPayload = $this->buildAbsenteesPayload($staff, $nowQatar);

        // ---- Role-Specific Operational KPI / Tracker Card ----
        $roleKpiPayload = $this->buildRoleKpiPayload(
            $user,
            $staff,
            $roleSlug,
            $nowQatar,
            $isAdminPreview
        );

        // ---- Salon Manager ONLY: Pending Branch Complaints for Action ----
        $branchPendingComplaints = ($isSalonManager && $staff && !$staff->isRemote())
            ? $this->buildBranchPendingComplaints($staff)
            : null;

        // ---- Salon Staff ONLY: My Complaints (STRICT PRIVACY — zero client PII!) & Weekly Evaluations ----
        $myComplaints = ($isSalonStaff && $staff)
            ? $this->buildPrivacySafeStaffComplaints($staff)
            : null;

        $weeklyEvaluations = ($isSalonStaff && $staff)
            ? $this->buildStaffWeeklyEvaluations($staff)
            : null;

        // ---- Deductions & Penalties (ALL roles — Read-Only, zero client contact info) ----
        $deductionsPayload = $this->buildDeductionsPayload($staff, $nowQatar);

        // ---- Contracts Tab (ALL roles — Read-Only, strictly own records) ----
        $contractsPayload = $this->buildContractsPayload($user, $staff);

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
            'skillsPayload' => $skillsPayload,
            'offDaysPayload' => $offDaysPayload,
            'absenteesPayload' => $absenteesPayload,
            'roleKpiPayload' => $roleKpiPayload,
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
                'label' => 'Remote',
                'subtitle' => $user->isAdmin() ? 'Central Operations & HQ' : 'Laleen Remote Team',
                'is_remote' => true,
            ],
        };
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

    private function buildRoleKpiPayload(
        User $user,
        ?Staff $staff,
        string $roleSlug,
        Carbon $nowQatar,
        bool $isAdminPreview
    ): ?array {
        $monthStart = $nowQatar->copy()->startOfMonth()->startOfDay();
        $todayEnd = $nowQatar->copy()->endOfDay();
        $periodDays = max(1, (int) $monthStart->diffInDays($nowQatar->copy()->startOfDay()) + 1);
        $daysInMonth = max(1, (int) $nowQatar->daysInMonth);

        // 1. Chat Agent — Today's Daily Booking Target + MTD Booking Quota (exact AgentBookingQuotaCalculator rules)
        if ($roleSlug === 'agent') {
            $todayBounds = AgentBookingQuotaCalculator::qatarDayBounds($nowQatar);
            $dailyTarget = DashboardScorecardBuilder::AGENT_DAILY_BOOKING_QUOTA;
            $mtdTarget = max(1, $dailyTarget * $periodDays);

            $todayBookings = 0;
            $mtdBookings = 0;
            $chatQaAvg = null;

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

                $evals = KpiChatEvaluation::query()
                    ->forAgentIds([$user->id])
                    ->whereBetween('evaluation_date', [$monthStart, $todayEnd])
                    ->get(['percentage']);
                if ($evals->isNotEmpty()) {
                    $chatQaAvg = round((float) $evals->avg('percentage'), 1);
                }
            }

            $todayPct = min(100.0, round(($todayBookings / $dailyTarget) * 100, 1));
            $mtdPct = min(100.0, round(($mtdBookings / $mtdTarget) * 100, 1));

            return [
                'mode' => 'metrics',
                'title' => 'Chat Agent Booking Targets',
                'subtitle' => '12 AM–12 AM Qatar time · Created bookings (1 per appointment)',
                'icon' => 'bx-target-lock',
                'action_label' => (!$isAdminPreview && $user->canView('kpis')) ? 'Open Agent Scorecard' : null,
                'action_url' => (!$isAdminPreview && $user->canView('kpis')) ? route('kpi.agents.index') : null,
                'metrics' => [
                    [
                        'label' => "Today's Daily Booking Target",
                        'value' => "{$todayBookings} / {$dailyTarget}",
                        'pct' => $todayPct,
                    ],
                    [
                        'label' => 'MTD Booking Quota',
                        'value' => "{$mtdBookings} / {$mtdTarget}",
                        'pct' => $mtdPct,
                    ],
                    [
                        'label' => 'Chat QA Average',
                        'value' => $chatQaAvg !== null ? "{$chatQaAvg}%" : 'Not scored yet',
                        'pct' => $chatQaAvg !== null ? min(100.0, $chatQaAvg) : 0.0,
                    ],
                ],
                'empty_message' => null,
            ];
        }

        // 2. Ops Lead — Ops Tracker Pending KPI (pending count, overdue/urgent count, live pending items list)
        if ($roleSlug === 'ops_lead') {
            $currentMonday = $nowQatar->copy()->startOfWeek(Carbon::MONDAY)->startOfDay();
            OpsActionItem::$referenceWeekMonday = $currentMonday->toDateString();

            $openItems = Schema::hasTable('ops_action_items')
                ? OpsActionItem::query()
                    ->where('is_completed', false)
                    ->executionOrder()
                    ->get()
                : collect();

            $pendingCount = $openItems->count();
            $overdueOrUrgentCount = $openItems
                ->filter(fn (OpsActionItem $i) => $i->is_overdue || $i->priority === 'urgent')
                ->count();

            $itemList = $openItems->take(8)->map(fn (OpsActionItem $i) => [
                'id' => $i->id,
                'title' => $i->title,
                'scope' => $i->scope,
                'priority' => $i->priority,
                'is_overdue' => (bool) $i->is_overdue,
                'due_label' => $i->effectiveDueAt()->timezone('Asia/Qatar')->format('d M, g:i A'),
                'assignees' => implode(', ', $i->assignee_list),
            ])->values()->all();

            return [
                'mode' => 'ops_tracker',
                'title' => 'Ops Tracker KPI',
                'subtitle' => 'Live pending operational actions & SLA alerts',
                'icon' => 'bx-bolt-circle',
                'action_label' => (!$isAdminPreview && $user->canView('staff')) ? 'Open Ops Tracker' : null,
                'action_url' => (!$isAdminPreview && $user->canView('staff')) ? route('staff.ops-tracker.index') : null,
                'pending_count' => $pendingCount,
                'overdue_urgent_count' => $overdueOrUrgentCount,
                'items' => $itemList,
            ];
        }

        // 3. Sales Coordinator — Sales Coordinator KPIs from DashboardScorecardBuilder
        if ($roleSlug === 'sales_coordinator') {
            $builder = new DashboardScorecardBuilder($monthStart, $todayEnd, $user);
            $cards = collect($builder->build());
            $salesCard = $cards->firstWhere('key', 'laden');

            $metrics = $salesCard
                ? collect($salesCard['kpis'] ?? [])->map(fn ($k) => [
                    'label' => $k['label'],
                    'value' => $k['value_label'],
                    'pct' => (float) ($k['pct'] ?? 0),
                ])->values()->all()
                : [];

            return [
                'mode' => 'metrics',
                'title' => 'Sales Coordinator KPIs',
                'subtitle' => 'MTD team bookings, lead follow-ups & conversion',
                'icon' => 'bx-line-chart',
                'action_label' => (!$isAdminPreview && $user->canView('kpis')) ? 'Open KPI Hub' : null,
                'action_url' => (!$isAdminPreview && $user->canView('kpis')) ? route('kpi.hub') : null,
                'metrics' => $metrics,
                'empty_message' => empty($metrics) ? 'Sales Coordinator KPIs have not been configured for this period yet.' : null,
            ];
        }

        // 4. Salon Manager (and legacy Manager) — Branch Manager KPIs for assigned branch
        if (in_array($roleSlug, ['salon_manager', 'manager'], true)) {
            $assignedBranch = ($staff && in_array($staff->branch, ['old_airport', 'wakrah'], true))
                ? $staff->branch
                : (KpiStaffEvaluation::defaultBranchFor($user) ?? 'old_airport');
            $branchLabel = $assignedBranch === 'wakrah' ? 'Al Wakrah' : 'Old Airport';
            $targetKey = $assignedBranch === 'wakrah' ? 'rhea' : 'ahana';

            $builder = new DashboardScorecardBuilder($monthStart, $todayEnd, $user);
            $branchCard = collect($builder->build())->firstWhere('key', $targetKey);

            $metrics = $branchCard
                ? collect($branchCard['kpis'] ?? [])->map(fn ($k) => [
                    'label' => $k['label'],
                    'value' => $k['value_label'],
                    'pct' => (float) ($k['pct'] ?? 0),
                ])->values()->all()
                : [];

            return [
                'mode' => 'metrics',
                'title' => "Branch Manager KPIs — {$branchLabel}",
                'subtitle' => "MTD chair upsells, weekly evaluations & branch audits ({$branchLabel})",
                'icon' => 'bx-store-alt',
                'action_label' => (!$isAdminPreview && $user->canView('kpis')) ? 'Open Branch KPIs' : null,
                'action_url' => (!$isAdminPreview && $user->canView('kpis')) ? route('kpi.staff-sales.index', ['branch' => $assignedBranch]) : null,
                'metrics' => $metrics,
                'empty_message' => empty($metrics) ? "No branch KPI data recorded yet for {$branchLabel}." : null,
            ];
        }

        // 5. Marketing Specialist — Paid Ads KPIs
        if ($roleSlug === 'marketing_specialist') {
            $builder = new DashboardScorecardBuilder($monthStart, $todayEnd, $user);
            $card = collect($builder->build())->firstWhere('key', 'ayan');
            $metrics = $card
                ? collect($card['kpis'] ?? [])->map(fn ($k) => [
                    'label' => $k['label'],
                    'value' => $k['value_label'],
                    'pct' => (float) ($k['pct'] ?? 0),
                ])->values()->all()
                : [];

            return [
                'mode' => 'metrics',
                'title' => 'Marketing Specialist KPIs',
                'subtitle' => 'MTD ad revenue, lead conversion & funnel pace',
                'icon' => 'bx-trending-up',
                'action_label' => (!$isAdminPreview && $user->canView('kpis')) ? 'Open Ads KPIs' : null,
                'action_url' => (!$isAdminPreview && $user->canView('kpis')) ? route('kpi.ads.index') : null,
                'metrics' => $metrics,
                'empty_message' => empty($metrics) ? 'Marketing KPIs are not configured for this period yet.' : null,
            ];
        }

        // 6. Social Media Editor — Content KPIs
        if ($roleSlug === 'social_media_editor') {
            $builder = new DashboardScorecardBuilder($monthStart, $todayEnd, $user);
            $card = collect($builder->build())->firstWhere('key', 'sana');
            $metrics = $card
                ? collect($card['kpis'] ?? [])->map(fn ($k) => [
                    'label' => $k['label'],
                    'value' => $k['value_label'],
                    'pct' => (float) ($k['pct'] ?? 0),
                ])->values()->all()
                : [];

            return [
                'mode' => 'metrics',
                'title' => 'Social Media Editor KPIs',
                'subtitle' => 'MTD reels published, daily stories & reply SLA',
                'icon' => 'bx-movie-play',
                'action_label' => (!$isAdminPreview && $user->canView('kpis')) ? 'Open Content Calendar' : null,
                'action_url' => (!$isAdminPreview && $user->canView('kpis')) ? route('kpi.content-calendar.index') : null,
                'metrics' => $metrics,
                'empty_message' => empty($metrics) ? 'Content KPIs are not configured for this period yet.' : null,
            ];
        }

        return null;
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

        $formattedContracts = $contracts->map(fn (StaffContract $c) => [
            'id' => $c->id,
            'contract_number' => $c->contract_number,
            'role_title' => $c->designationLabel(),
            'contract_type' => $c->templateLabel(),
            'branch_label' => $c->branchLabel(),
            'start_date' => $c->start_date?->format('d M Y') ?? '—',
            'expiry_date' => $c->expiry_date?->format('d M Y') ?? 'Open-ended',
            'status' => $c->status,
            'status_label' => $c->statusLabel(),
            'signed_at' => $c->signed_at?->format('d M Y'),
            'view_url' => route('portal.contracts.pdf', ['contract' => $c->id, 'inline' => 1]),
            'download_url' => route('portal.contracts.pdf', ['contract' => $c->id]),
        ])->values();

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
