<?php

namespace App\Http\Controllers;

use App\Models\KpiStaffEvaluation;
use App\Models\ShiftPattern;
use App\Models\Staff;
use App\Models\StaffAttendance;
use App\Models\StaffComplaint;
use App\Models\StaffContract;
use App\Models\StaffDeduction;
use App\Models\StaffDocument;
use App\Models\StaffLeave;
use App\Models\User;
use App\Support\StaffContractPdfGenerator;
use App\Support\StaffKpiScorecardService;
use App\Support\StaffServiceProductionService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class StaffPortalController extends Controller
{
    public function __construct(
        private readonly StaffKpiScorecardService $scorecardService,
        private readonly StaffServiceProductionService $productionService
    ) {}

    private const ALLOWED_TABS = ['overview', 'performance', 'skills', 'contracts', 'documents', 'leaves'];

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
        $isSkillsEligible = $isLeaveEligible;

        if ($rawTab === 'leaves' && !$isLeaveEligible) {
            abort(403, 'Leave tracking is only available for branch-assigned Salon Managers and Salon Staff.');
        }

        if ($rawTab === 'skills' && !$isSkillsEligible) {
            abort(403, 'Assigned service skills are only available for branch-assigned Salon Managers and Salon Staff.');
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
        $isSkillsEligible = $isLeaveEligible;

        if ($rawTab === 'leaves' && !$isLeaveEligible) {
            abort(403, 'Leave tracking is only available for branch-assigned Salon Managers and Salon Staff.');
        }

        if ($rawTab === 'skills' && !$isSkillsEligible) {
            abort(403, 'Assigned service skills are only available for branch-assigned Salon Managers and Salon Staff.');
        }

        $activeTab = in_array($rawTab, self::ALLOWED_TABS, true) ? $rawTab : 'overview';

        return view('portal.my-profile', $this->buildPortalViewData($targetUser, $staff, $activeTab, true, $request));
    }

    /**
     * Self-view JSON endpoint (`GET /my-profile/production-breakdown`):
     * Strictly scoped to `auth()->user()->staff` (NEVER accepts `staff_id` / `user_id` parameters).
     * Returns HTTP 403 for Remote or ineligible roles.
     * STRICT PRIVACY: Client first name is NEVER included for `Salon Staff` (only `Salon Manager` / `Admin`),
     * and customer phone/email/address are NEVER included for anyone.
     */
    public function productionBreakdown(Request $request)
    {
        /** @var User $user */
        $user = $request->user();
        $roleSlug = strtolower(trim((string) $user->role));
        $staff = $user->staff()->first();

        $isEligibleRole = !User::isRemoteRole($roleSlug)
            && ($user->isLeaveEligible() || $user->isSalonStaff() || in_array($roleSlug, ['salon_manager', 'manager'], true));

        if (!$isEligibleRole || !$staff || $staff->isRemote()) {
            abort(403, 'Service production breakdown is only available for branch-assigned Salon Managers and Salon Staff.');
        }

        $includeClientFirstName = $user->isAdmin() || in_array($roleSlug, ['salon_manager', 'manager'], true);

        return response()->json(
            $this->productionService->buildBreakdownForStaff(
                staff: $staff,
                asOfQatar: Carbon::now('Asia/Qatar'),
                includeClientFirstName: $includeClientFirstName
            )
        );
    }

    /**
     * Admin-only JSON endpoint (`GET /staff-portal-preview/{staff}/production-breakdown`):
     * Strictly gated behind Admin authorization (`role:admin` + explicit `isAdmin()` check).
     */
    public function adminPreviewProductionBreakdown(Request $request, Staff $staff)
    {
        abort_unless($request->user()?->isAdmin(), 403, 'Only administrators can inspect staff production breakdowns.');

        $staff->loadMissing('user');
        $linkedRole = strtolower(trim((string) ($staff->user?->role ?? '')));
        if ($staff->isRemote() || ($linkedRole !== '' && User::isRemoteRole($linkedRole))) {
            abort(403, 'Service production breakdown is only available for branch-assigned Salon Managers and Salon Staff.');
        }

        return response()->json(
            $this->productionService->buildBreakdownForStaff(
                staff: $staff,
                asOfQatar: Carbon::now('Asia/Qatar'),
                includeClientFirstName: true
            )
        );
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
        $isSkillsEligible = $isLeaveEligible;

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

        // ---- Location & Shift Schedule (7-day Saturday–Friday + compact summary) ----
        $branchInfo = $this->resolveBranchInfo($user, $staff);
        $activePattern = $staff?->activePatternFor($todayQatar);
        $shiftSchedule = $this->buildWeeklyShiftSummary($staff, $activePattern, $todayQatar);
        $branchInfo['compact_subtitle'] = $this->buildCompactLocationSubtitle($branchInfo, $shiftSchedule);

        // ---- Dedicated Skills Tab (Salon Manager & Salon Staff ONLY) ----
        $skillsPayload = $isSkillsEligible ? $this->buildSalonSkillsPayload($staff, $branchInfo['label']) : null;

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

        // ---- Dedicated Performance Tab (1-to-1 Single Source of Truth via StaffKpiScorecardService) ----
        $performanceScorecard = null;
        $mtdProduction = null;
        $isProductionEligible = $isSkillsEligible || $isSalonManager || $isSalonStaff;
        if ($activeTab === 'performance') {
            $effectiveViewer = $isAdminPreview
                ? ($user->exists ? $user : $request->user())
                : $request->user();

            $performanceScorecard = $this->scorecardService->buildForPortalMember(
                targetUser: $user,
                staff: $staff,
                effectiveViewer: $effectiveViewer,
                asOf: $nowQatar,
                weeklyEvaluations: $weeklyEvaluations,
                myComplaints: $myComplaints
            );

            // MTD Service Production: strictly for Salon Manager & Salon Staff (hidden for all Remote roles)
            if ($isProductionEligible && !User::isRemoteRole($roleSlug) && $staff && !$staff->isRemote()) {
                $mtdProduction = $this->productionService->buildForStaff($staff, $nowQatar);
            }
        }

        $productionBreakdownUrl = $mtdProduction !== null
            ? (($isAdminPreview && $staff)
                ? route('portal.admin-preview.production-breakdown', $staff)
                : route('portal.production-breakdown'))
            : null;

        // ---- Documents Tab (Personal Self-Upload + Admin Inline Upload & Verification) ----
        /** @var User $actorUser */
        $actorUser = $request->user();
        $documentsPayload = $this->buildDocumentsPayload($staff, $todayQatar, $actorUser, $isAdminPreview);
        $canUploadDocuments = $staff !== null && ($isAdminPreview ? $actorUser->isAdmin() : true);
        $documentUploadUrl = ($isAdminPreview && $staff)
            ? route('staff-documents.store', $staff)
            : route('portal.documents.store');
        $documentUploadCategories = $isAdminPreview
            ? StaffDocument::CATEGORIES
            : StaffDocument::SELF_UPLOAD_CATEGORIES;

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
            'isSkillsEligible' => $isSkillsEligible,
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
            'performanceScorecard' => $performanceScorecard,
            'mtdProduction' => $mtdProduction,
            'productionBreakdownUrl' => $productionBreakdownUrl,
            'branchPendingComplaints' => $branchPendingComplaints,
            'myComplaints' => $myComplaints,
            'weeklyEvaluations' => $weeklyEvaluations,
            'deductionsPayload' => $deductionsPayload,
            'contractsPayload' => $contractsPayload,
            'documentsPayload' => $documentsPayload,
            'canUploadDocuments' => $canUploadDocuments,
            'documentUploadUrl' => $documentUploadUrl,
            'documentUploadCategories' => $documentUploadCategories,
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
                'legal_team' => 'Legal Compliance & Documentation Vault',
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

    private function buildSalonSkillsPayload(?Staff $staff, string $branchLabel = 'Salon'): array
    {
        $serviceSkills = [];
        if ($staff && $staff->services->isNotEmpty()) {
            $serviceSkills = $staff->services->map(fn ($s) => [
                'name' => $s->name,
                'duration' => (int) ($s->duration ?: 30),
                'category' => filled($s->category?->name) ? trim((string) $s->category->name) : 'General Services',
            ])->values()->all();
        } elseif ($staff && is_array($staff->skills) && !empty($staff->skills)) {
            $serviceSkills = collect($staff->skills)
                ->filter(fn ($v) => is_string($v) && trim($v) !== '')
                ->map(fn ($name) => [
                    'name' => trim($name),
                    'duration' => null,
                    'category' => 'General Services',
                ])
                ->values()
                ->all();
        }

        $grouped = collect($serviceSkills)
            ->groupBy(fn (array $item) => $item['category'] ?: 'General Services')
            ->map(function ($items, $categoryName) {
                $sorted = collect($items)->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)->values()->all();

                return [
                    'name' => (string) $categoryName,
                    'count' => count($sorted),
                    'items' => $sorted,
                ];
            })
            ->sortBy(fn (array $group) => ($group['name'] === 'General Services' ? 'zzz_' : '') . strtolower($group['name']))
            ->values()
            ->all();

        return [
            'title' => 'Assigned Service Skills',
            'total_count' => count($serviceSkills),
            'branch_label' => $branchLabel,
            'categories' => $grouped,
            'items' => $serviceSkills,
            'assign_url' => $staff
                ? route('staffs.index', ['tab' => 'workspace', 'staff' => $staff->id])
                : route('staffs.index'),
        ];
    }

    private function buildAbsenteesPayload(?Staff $staff, Carbon $nowQatar): array
    {
        if (!$staff || !Schema::hasTable('staff_attendances')) {
            return [
                'month_count' => 0,
                'ytd_count' => 0,
                'month_late_count' => 0,
                'month_late_minutes' => 0,
                'month_late_formatted' => '0 min',
                'recent_dates' => [],
                'recent_checkins' => [],
                'month_label' => $nowQatar->format('M Y'),
                'year_label' => (string) $nowQatar->year,
            ];
        }

        $yearStart = $nowQatar->copy()->startOfYear()->toDateString();
        $monthStart = $nowQatar->copy()->startOfMonth()->toDateString();
        $fourteenDaysAgo = $nowQatar->copy()->subDays(13)->toDateString();
        $recentWindowStart = $fourteenDaysAgo < $monthStart ? $fourteenDaysAgo : $monthStart;
        $todayStr = $nowQatar->toDateString();

        $ytdRecords = StaffAttendance::query()
            ->where('staff_id', $staff->id)
            ->whereDate('attendance_date', '>=', $yearStart)
            ->whereDate('attendance_date', '<=', $todayStr)
            ->orderByDesc('attendance_date')
            ->orderByDesc('id')
            ->get([
                'id',
                'attendance_date',
                'status',
                'scheduled_start_time',
                'scheduled_end_time',
                'checked_in_at',
                'checked_out_at',
                'late_minutes',
                'is_locked',
                'notes',
            ]);

        $ytdAbsences = $ytdRecords
            ->filter(fn (StaffAttendance $a) => $a->status === StaffAttendance::STATUS_ABSENT)
            ->values();

        $monthAbsences = $ytdAbsences
            ->filter(fn (StaffAttendance $a) => $a->attendance_date->toDateString() >= $monthStart)
            ->count();

        $monthLates = $ytdRecords->filter(fn (StaffAttendance $a) => $a->status === StaffAttendance::STATUS_LATE
            && $a->attendance_date->toDateString() >= $monthStart);

        $monthLateCount = $monthLates->count();
        $monthLateMinutes = (int) $monthLates->sum('late_minutes');

        $recentCheckins = $ytdRecords
            ->filter(fn (StaffAttendance $a) => $a->attendance_date->toDateString() >= $recentWindowStart)
            ->take(20)
            ->map(function (StaffAttendance $a) use ($staff) {
                $badge = $a->statusBadgePayload();
                $shiftLabel = $a->scheduledShiftLabel();
                if ($shiftLabel === null) {
                    if ($a->status === StaffAttendance::STATUS_OFF) {
                        $shiftLabel = 'Off Day';
                    } else {
                        $resolved = $staff->resolveShiftForDate($a->attendance_date);
                        $shiftLabel = $resolved['type'] === 'unscheduled' ? '—' : $resolved['display'];
                    }
                }

                return [
                    'date_label' => $a->attendance_date->format('D, d M Y'),
                    'shift_label' => $shiftLabel,
                    'checkin_label' => $a->checkedInQatarLabel() ?? '—',
                    'checkout_label' => $a->checkedOutQatarLabel(),
                    'status' => $a->status,
                    'status_label' => $badge['label'],
                    'status_tone' => $badge['tone'],
                    'late_minutes' => (int) ($a->late_minutes ?? 0),
                    'notes' => $a->notes,
                ];
            })
            ->values()
            ->all();

        return [
            'month_count' => $monthAbsences,
            'ytd_count' => $ytdAbsences->count(),
            'month_late_count' => $monthLateCount,
            'month_late_minutes' => $monthLateMinutes,
            'month_late_formatted' => StaffAttendance::formatTotalLateMinutes($monthLateMinutes),
            'recent_dates' => $ytdAbsences->take(6)->map(fn (StaffAttendance $a) => [
                'date_label' => $a->attendance_date->format('D, d M Y'),
                'notes' => $a->notes,
            ])->values()->all(),
            'recent_checkins' => $recentCheckins,
            'month_label' => $nowQatar->format('M Y'),
            'year_label' => (string) $nowQatar->year,
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

    private function buildDocumentsPayload(?Staff $staff, Carbon $todayQatar, User $viewer, bool $isAdminPreview): array
    {
        if (!$staff || !Schema::hasTable('staff_documents')) {
            return [];
        }

        return StaffDocument::query()
            ->with(['uploader:id,name', 'verifier:id,name'])
            ->where('staff_id', $staff->id)
            ->orderByRaw("CASE verification_status WHEN 'pending_review' THEN 0 ELSE 1 END ASC")
            ->orderByDesc('created_at')
            ->get()
            ->map(function (StaffDocument $d) use ($todayQatar, $viewer, $isAdminPreview) {
                $exp = $d->expiryStatus($todayQatar);
                $ver = $d->verificationStatusBadge();
                $isAdmin = $viewer->isAdmin();
                $canDelete = $isAdmin
                    || (!$isAdminPreview
                        && $d->isPendingReview()
                        && $d->uploaded_by !== null
                        && (int) $d->uploaded_by === (int) $viewer->id);

                return [
                    'id' => $d->id,
                    'category' => $d->category,
                    'category_label' => $d->categoryLabel(),
                    'title' => $d->title,
                    'document_number' => $d->document_number,
                    'issue_date_label' => $d->issue_date?->format('d M Y') ?? '—',
                    'expiry_date_label' => $d->expiry_date?->format('d M Y') ?? '—',
                    'expiry_status' => $exp,
                    'verification_status' => $d->verification_status ?? StaffDocument::STATUS_VERIFIED,
                    'verification_badge' => $ver,
                    'is_pending_review' => $d->isPendingReview(),
                    'original_filename' => $d->original_filename,
                    'file_size_label' => $d->formattedFileSize(),
                    'view_url' => route('staff-documents.download', ['document' => $d->id, 'inline' => 1]),
                    'download_url' => route('staff-documents.download', ['document' => $d->id]),
                    'can_verify' => $isAdmin && $d->isPendingReview(),
                    'verify_url' => route('staff-documents.verify', ['document' => $d->id]),
                    'can_delete' => $canDelete,
                    'delete_url' => $isAdmin
                        ? route('staff-documents.destroy', ['document' => $d->id])
                        : route('portal.documents.destroy', ['document' => $d->id]),
                ];
            })
            ->values()
            ->all();
    }

    private function buildLeavesPayload(?Staff $staff, Carbon $nowQatar, string $typeFilter = 'all'): array
    {
        $year = (int) $nowQatar->year;
        $annualEntitled = (int) ($staff?->annual_leave_days ?? 21);

        $allLeaves = ($staff && Schema::hasTable('staff_leaves'))
            ? StaffLeave::query()
                ->with('deduction')
                ->where('staff_id', $staff->id)
                ->orderByDesc('start_date')
                ->orderByDesc('id')
                ->get()
            : collect();

        $annualUsed = 0;
        $annualPending = 0;
        $sickTaken = 0;
        $sickPending = 0;
        $sickDeductedUnapproved = 0;

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
                    $sickTaken += $daysInYear;
                } elseif ($leave->status === StaffLeave::STATUS_PENDING) {
                    $sickPending += $daysInYear;
                }
                if ($leave->status === StaffLeave::STATUS_REJECTED || (bool) $leave->deduction_applied) {
                    $sickDeductedUnapproved += $daysInYear;
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
            'submitted_within_window' => (bool) ($l->submitted_within_window ?? true),
            'deduction_applied' => (bool) ($l->deduction_applied ?? false),
            'deduction_amount' => $l->deduction ? round((float) $l->deduction->amount, 2) : null,
            'compliance_badges' => $l->complianceBadges(),
            'has_certificate' => !empty($l->medical_certificate_path),
            'certificate_url' => !empty($l->medical_certificate_path)
                ? route('staff-leaves.certificate', ['leave' => $l->id, 'inline' => 1])
                : null,
            'can_upload_certificate' => $l->canUploadMissingCertificate($nowQatar),
            'upload_certificate_url' => route('portal.leaves.certificate.upload', $l->id),
            'can_cancel' => $l->status === StaffLeave::STATUS_PENDING,
            'cancel_url' => route('portal.leaves.cancel', $l->id),
        ])->values()->all();

        return [
            'year' => $year,
            'filter' => $typeFilter,
            'policy_notice' => StaffLeave::SICK_POLICY_NOTICE,
            'annual' => [
                'entitled' => $annualEntitled,
                'used' => $annualUsed,
                'pending' => $annualPending,
                'remaining' => max(0, $annualEntitled - $annualUsed),
            ],
            'sick' => [
                'taken' => $sickTaken,
                'pending' => $sickPending,
                'deducted_unapproved' => $sickDeductedUnapproved,
            ],
            'history' => $history,
        ];
    }

    private function buildWeeklyShiftSummary(?Staff $staff, ?ShiftPattern $activePattern, ?Carbon $todayQatar = null): array
    {
        $todayDow = (int) (($todayQatar ?? Carbon::now('Asia/Qatar'))->dayOfWeek);
        // Exact 7-day Laleen salon week order: Saturday -> Sunday -> Monday -> Tuesday -> Wednesday -> Thursday -> Friday
        $satToFriOrder = [6, 0, 1, 2, 3, 4, 5];

        if (!$staff) {
            return [
                'has_schedule' => false,
                'rows' => [],
                'days' => array_map(fn (int $dow) => [
                    'dow' => $dow,
                    'day_name' => self::DAY_NAMES[$dow],
                    'is_today' => $dow === $todayDow,
                    'is_off' => false,
                    'is_unscheduled' => true,
                    'hours' => null,
                ], $satToFriOrder),
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

            $days = array_map(fn (int $d) => [
                'dow' => $d,
                'day_name' => self::DAY_NAMES[$d],
                'is_today' => $d === $todayDow,
                'is_off' => $dayEntries[$d] === null,
                'is_unscheduled' => false,
                'hours' => $dayEntries[$d],
            ], $satToFriOrder);

            return [
                'has_schedule' => !empty($groupedRows),
                'rows' => $groupedRows,
                'days' => $days,
                'weekly_hours' => round($weeklyMinutes / 60, 1),
            ];
        }

        $legacyOffNames = collect(is_array($staff->weekly_off) ? $staff->weekly_off : [])
            ->filter(fn ($d) => is_string($d) && trim($d) !== '')
            ->map(fn ($d) => strtolower(trim($d)))
            ->all();

        $wh = is_array($staff->working_hours) ? $staff->working_hours : [];
        $hasLegacyHours = !empty($wh['start']) && !empty($wh['end']);
        $legacyRange = $hasLegacyHours
            ? $this->formatTimeRange((string) $wh['start'], (string) $wh['end'])
            : null;
        $legacyWeeklyMinutes = 0;

        if ($hasLegacyHours) {
            try {
                $sNorm = strlen(trim((string) $wh['start'])) === 5 ? trim((string) $wh['start']) . ':00' : trim((string) $wh['start']);
                $eNorm = strlen(trim((string) $wh['end'])) === 5 ? trim((string) $wh['end']) . ':00' : trim((string) $wh['end']);
                $perDayMins = abs(Carbon::createFromFormat('H:i:s', $eNorm)->diffInMinutes(Carbon::createFromFormat('H:i:s', $sNorm)));
            } catch (\Throwable) {
                $perDayMins = 0;
            }
        } else {
            $perDayMins = 0;
        }

        $days = [];
        foreach ($satToFriOrder as $d) {
            $dayName = self::DAY_NAMES[$d];
            $isOff = in_array(strtolower($dayName), $legacyOffNames, true);
            if (!$isOff && $hasLegacyHours) {
                $legacyWeeklyMinutes += $perDayMins;
            }
            $days[] = [
                'dow' => $d,
                'day_name' => $dayName,
                'is_today' => $d === $todayDow,
                'is_off' => $isOff,
                'is_unscheduled' => !$isOff && !$hasLegacyHours,
                'hours' => (!$isOff && $hasLegacyHours) ? $legacyRange : null,
            ];
        }

        if ($hasLegacyHours) {
            return [
                'has_schedule' => true,
                'rows' => [[
                    'days' => 'Standard Shift',
                    'hours' => $legacyRange,
                ]],
                'days' => $days,
                'weekly_hours' => round($legacyWeeklyMinutes / 60, 1),
            ];
        }

        return [
            'has_schedule' => false,
            'rows' => [],
            'days' => $days,
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
