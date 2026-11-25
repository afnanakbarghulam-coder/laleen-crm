<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Staff;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\Appointment;
use App\Models\AppointmentService;
use App\Models\Expense;
use App\Models\OpsActionItem;
use App\Models\StaffAttendance;
use App\Models\StaffComplaint;
use App\Models\StaffContract;
use App\Models\StaffDeduction;
use App\Models\StaffLeave;
use App\Support\StaffPayrollCalculator;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;

class StaffController extends Controller
{
    public function index(Request $request)
    {
        $isAdminViewer = (bool) ($request->user() && $request->user()->isAdmin());
        $rawTab = strtolower(trim((string) $request->query('tab', 'directory')));

        if ($rawTab === 'payroll') {
            abort_unless($isAdminViewer, 403, 'Only administrators can access Payroll & Wage Settings.');
        }

        $directorySubView = $rawTab === 'workspace' ? 'workspace' : 'directory';
        $payrollSubView = ($rawTab === 'payroll' && $isAdminViewer) ? 'payroll' : 'contracts';
        $shiftsOpsSubView = match ($rawTab) {
            'ops_tracker' => 'ops_tracker',
            'attendance' => 'attendance',
            'leaves' => $isAdminViewer ? 'leaves' : 'shifts',
            default => 'shifts',
        };
        $hrSubView = ($rawTab === 'deductions' || $request->query('hr_sub') === 'deductions') ? 'deductions' : 'complaints';

        if ($rawTab === 'workspace') {
            $activeTab = 'directory';
        } elseif (in_array($rawTab, ['contracts', 'payroll'], true)) {
            $activeTab = 'payroll';
        } elseif (in_array($rawTab, ['shifts_ops', 'shifts', 'ops_tracker', 'attendance', 'leaves'], true)) {
            $activeTab = 'shifts_ops';
        } elseif (in_array($rawTab, ['notices', 'complaints', 'hr', 'deductions'], true)) {
            $activeTab = 'complaints';
        } else {
            $activeTab = 'directory';
        }

        // ---- Staff Directory & Shift Roster Relations ----
        $hasDirectoryFilter = $request->filled('branch') || $request->filled('status') || $request->filled('search');
        $query = Staff::with([
            'user',
            'services:id',
            'shiftPatterns.blocks',
            'timeOffs' => fn ($q) => $q->orderByDesc('start_date'),
        ]);

        if ($request->filled('branch')) {
            if ($request->branch === 'remote') {
                $query->where(function ($q) {
                    $q->where('is_remote', true)
                        ->orWhereNull('branch')
                        ->orWhere('branch', 'remote');
                });
            } else {
                $query->where('branch', $request->branch)
                    ->where(function ($q) {
                        $q->where('is_remote', false)->orWhereNull('is_remote');
                    });
            }
        }

        if ($request->filled('status')) {
            $query->where('availability_status', $request->status);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        $staff = $query->orderBy('name')->get();
        $services = Service::orderBy('name')->get();
        $categories = ServiceCategory::with('services')->orderBy('sort_order')->get();

        // Full roster, used by the pickers in Payroll/Shifts/Complaints tabs.
        $allStaff = $hasDirectoryFilter
            ? Staff::with([
                'user',
                'services:id',
                'shiftPatterns.blocks',
                'timeOffs' => fn ($q) => $q->orderByDesc('start_date'),
            ])->orderBy('name')->get()
            : $staff;

        // Plain-array projections for the tabs' JS (never pass a chained/argument
        // expression to Blade's @json() directly — see StaffPayrollCalculator's
        // sibling views for why; compute the array here instead).
        $allStaffOptions = $allStaff->map(fn ($s) => [
            'id' => $s->id,
            'name' => $s->name,
            'branch' => $s->branch_key,
            'is_remote' => $s->isRemote(),
            'location_label' => $s->location_label,
            'base_salary' => $isAdminViewer ? (float) ($s->base_salary ?? 0) : 0.0,
            'hourly_wage' => $isAdminViewer ? (float) ($s->hourly_wage ?? 0) : 0.0,
        ])->values();

        // For the Complaints & Feedback "Service Received" dropdown.
        $serviceOptions = $services->map(fn ($s) => [
            'id' => $s->id,
            'name' => $s->name,
        ])->values();

        // For the Staff Workspace tab's staff picker - each member's current
        // Services/Location/Settings, so the JS can populate the form
        // in-place without a round trip when the admin switches who they're
        // editing.
        $staffWorkspaceOptions = $allStaff->map(fn ($s) => [
            'id' => $s->id,
            'name' => $s->name,
            'branch' => $s->branch_key,
            'is_remote' => $s->isRemote(),
            'location_label' => $s->location_label,
            'bookable' => (bool) $s->bookable,
            'service_ids' => $s->services->pluck('id'),
        ])->values();
        $selectedWorkspaceStaffId = $request->filled('staff') && $allStaff->contains('id', (int) $request->staff)
            ? (int) $request->staff
            : optional($allStaff->first())->id;

        // ---- Payroll & Wage Settings ----
        $payrollBranch = $request->filled('payroll_branch') && in_array($request->payroll_branch, ['old_airport', 'wakrah'], true)
            ? $request->payroll_branch
            : null;
        $payrollFrom = $request->date('payroll_from') ?? now()->startOfMonth();
        $payrollTo = $request->date('payroll_to') ?? now()->endOfMonth();

        if ($isAdminViewer) {
            $payrollCalculator = new StaffPayrollCalculator($payrollFrom, $payrollTo);
            $payrollStaffQuery = Staff::active()->orderBy('name');
            if ($payrollBranch) {
                $payrollStaffQuery->where(fn ($q) => $q->where('branch', $payrollBranch)->orWhere('branch', 'both'));
            }
            $payrollRows = $payrollCalculator->payrollFor($payrollStaffQuery->get());
        } else {
            $payrollRows = collect();
        }

        // ---- Scheduled Shifts Sub-Tab (In-Memory Roster from Eager-Loaded $allStaff) ----
        $shiftWeekParam = $request->input('shift_week');
        try {
            $shiftWeekStart = $shiftWeekParam
                ? Carbon::parse($shiftWeekParam)->startOfWeek(Carbon::SUNDAY)
                : Carbon::now()->startOfWeek(Carbon::SUNDAY);
        } catch (\Throwable) {
            $shiftWeekStart = Carbon::now()->startOfWeek(Carbon::SUNDAY);
        }
        $shiftWeekEnd = $shiftWeekStart->copy()->addDays(6);
        $shiftDays = collect(range(0, 6))->map(fn ($i) => $shiftWeekStart->copy()->addDays($i));
        $shiftBranch = $request->filled('shift_branch') && in_array($request->shift_branch, ['old_airport', 'wakrah', 'both', 'remote'], true)
            ? $request->shift_branch
            : '';

        $shiftStaffMembers = $shiftBranch !== ''
            ? $allStaff->filter(function (Staff $s) use ($shiftBranch) {
                if ($shiftBranch === 'remote') {
                    return $s->isRemote();
                }

                return !$s->isRemote() && in_array($s->branch, [$shiftBranch, 'both'], true);
            })->values()
            : $allStaff;

        $shiftRoster = [];
        $rawShiftDayTotals = array_fill_keys($shiftDays->map->format('Y-m-d')->all(), 0);
        $shiftConfigs = [];

        foreach ($allStaff as $member) {
            $activePatternNow = $member->activePatternFor(Carbon::now());
            $shiftConfigs[$member->id] = [
                'name' => $member->name,
                'pattern' => $activePatternNow ? [
                    'repeat_frequency' => $activePatternNow->repeat_frequency,
                    'start_date' => optional($activePatternNow->start_date)->format('Y-m-d'),
                    'end_date' => optional($activePatternNow->end_date)->format('Y-m-d'),
                    'blocks' => $activePatternNow->blocks->map(fn ($b) => [
                        'day_of_week' => $b->day_of_week,
                        'start_time' => substr($b->start_time, 0, 5),
                        'end_time' => substr($b->end_time, 0, 5),
                    ])->values()->all(),
                ] : null,
                'time_offs' => $member->timeOffs->map(fn ($t) => [
                    'id' => $t->id,
                    'start_date' => $t->start_date->format('Y-m-d'),
                    'end_date' => $t->end_date->format('Y-m-d'),
                    'reason' => $t->reason,
                    'notes' => $t->notes,
                ])->values()->all(),
            ];
        }

        foreach ($shiftStaffMembers as $member) {
            $weeklyMinutes = 0;
            $cells = [];

            foreach ($shiftDays as $date) {
                $dateKey = $date->format('Y-m-d');
                $timeOff = $member->timeOffs->first(fn ($t) => $t->coversDate($date));

                if ($timeOff) {
                    $cells[$dateKey] = ['type' => 'timeoff', 'reason' => $timeOff->reason, 'blocks' => []];
                    continue;
                }

                $pattern = $member->activePatternFor($date);
                $blocks = $pattern
                    ? $pattern->blocks->where('day_of_week', $date->dayOfWeek)->sortBy('start_time')
                    : collect();

                if ($blocks->isEmpty()) {
                    $cells[$dateKey] = ['type' => 'off', 'blocks' => []];
                    continue;
                }

                $formatted = $blocks->map(function ($block) use (&$weeklyMinutes, &$rawShiftDayTotals, $dateKey) {
                    $minutes = $block->durationMinutes();
                    $weeklyMinutes += $minutes;
                    $rawShiftDayTotals[$dateKey] += $minutes;

                    return [
                        'label' => $this->formatShiftTimeRange($block->start_time, $block->end_time),
                    ];
                });

                $cells[$dateKey] = ['type' => 'working', 'blocks' => $formatted->values()->all()];
            }

            $shiftRoster[] = [
                'staff' => $member,
                'weekly_hours' => round($weeklyMinutes / 60, 1),
                'cells' => $cells,
            ];
        }

        $shiftDayTotals = collect($rawShiftDayTotals)->map(fn ($m) => round($m / 60, 1))->all();

        // ---- HR & Disciplinary (Complaints & Deductions) ----
        $complaintsStaffFilter = $request->filled('complaints_staff') ? (int) $request->complaints_staff : null;
        $complaintsSearch = $request->filled('complaints_search') ? trim((string) $request->input('complaints_search')) : null;
        $complaintsDate = $request->filled('complaints_date') ? $request->input('complaints_date') : null;
        $complaintsFrom = $request->filled('complaints_from') ? $request->input('complaints_from') : null;
        $complaintsTo = $request->filled('complaints_to') ? $request->input('complaints_to') : null;

        $deductionsDate = $request->filled('deductions_date') ? $request->input('deductions_date') : null;
        $deductionsFrom = $request->filled('deductions_from') ? $request->input('deductions_from') : null;
        $deductionsTo = $request->filled('deductions_to') ? $request->input('deductions_to') : null;

        $hrFilterParams = array_filter([
            'complaints_staff' => $complaintsStaffFilter,
            'complaints_search' => $complaintsSearch,
            'complaints_date' => $complaintsDate,
            'complaints_from' => $complaintsFrom,
            'complaints_to' => $complaintsTo,
            'deductions_date' => $deductionsDate,
            'deductions_from' => $deductionsFrom,
            'deductions_to' => $deductionsTo,
        ]);

        $complaints = StaffComplaint::with(['staffMembers', 'customer', 'service', 'deductions.staff', 'notices.staff'])
            ->when($complaintsStaffFilter, fn ($q) => $q->whereHas('staffMembers', fn ($s) => $s->where('staff.id', $complaintsStaffFilter)))
            ->when($complaintsSearch, function ($q) use ($complaintsSearch) {
                $q->where(function ($sub) use ($complaintsSearch) {
                    $sub->where('reference_number', 'like', "%{$complaintsSearch}%")
                        ->orWhere('customer_name', 'like', "%{$complaintsSearch}%")
                        ->orWhere('customer_phone', 'like', "%{$complaintsSearch}%")
                        ->orWhere('category', 'like', "%{$complaintsSearch}%");
                });
            })
            ->when($complaintsDate, fn ($q) => $q->whereDate('complaint_date', $complaintsDate))
            ->when($complaintsFrom, fn ($q) => $q->whereDate('complaint_date', '>=', $complaintsFrom))
            ->when($complaintsTo, fn ($q) => $q->whereDate('complaint_date', '<=', $complaintsTo))
            ->orderByDesc('complaint_date')->orderByDesc('id')
            ->paginate(15, ['*'], 'complaints_page')
            ->appends(array_merge($hrFilterParams, ['tab' => 'complaints']));

        $deductions = StaffDeduction::with(['staff', 'complaint'])
            ->when($complaintsStaffFilter, fn ($q) => $q->where('staff_id', $complaintsStaffFilter))
            ->when($deductionsDate, fn ($q) => $q->whereDate('deduction_date', $deductionsDate))
            ->when($deductionsFrom, fn ($q) => $q->whereDate('deduction_date', '>=', $deductionsFrom))
            ->when($deductionsTo, fn ($q) => $q->whereDate('deduction_date', '<=', $deductionsTo))
            ->orderByDesc('deduction_date')->orderByDesc('id')
            ->paginate(15, ['*'], 'deductions_page')
            ->appends(array_merge($hrFilterParams, ['tab' => 'deductions']));

        // For the deduction quick-add's "link to penalty" dropdown.
        $openComplaints = StaffComplaint::with('staffMembers')->orderByDesc('complaint_date')->get();
        $openComplaintOptions = $openComplaints->map(fn ($c) => [
            'id' => $c->id,
            'reference_number' => $c->reference_number,
            'staff_name' => $c->staffMembers->pluck('name')->implode(', ') ?: '—',
            'category' => $c->category,
            'complaint_date_label' => $c->complaint_date->format('d M Y'),
        ])->values();

        // ---- Ops & Action Tracker Sub-Tab ----
        $pendingOwnerActionCount = 0;
        $openOpsTasksCount = 0;
        $opsMonday = now()->startOfWeek(Carbon::MONDAY)->startOfDay();
        if ($request->filled('ops_week')) {
            try {
                $opsMonday = Carbon::parse($request->input('ops_week'))->startOfWeek(Carbon::MONDAY)->startOfDay();
            } catch (\Throwable) {
                $opsMonday = now()->startOfWeek(Carbon::MONDAY)->startOfDay();
            }
        }
        $opsSunday = $opsMonday->copy()->endOfWeek(Carbon::SUNDAY);
        $opsCurrentMonday = now()->startOfWeek(Carbon::MONDAY)->startOfDay();
        $opsIsCurrentWeek = $opsMonday->equalTo($opsCurrentMonday);
        $opsPrevWeek = $opsMonday->copy()->subWeek()->toDateString();
        $opsNextWeek = $opsMonday->copy()->addWeek()->toDateString();
        $opsStatus = strtolower(trim((string) $request->input('ops_status', 'open'))) === 'completed' ? 'completed' : 'open';
        $opsMine = $request->boolean('ops_mine');
        $opsSelectedStaffId = $request->filled('ops_staff_id') && is_numeric($request->input('ops_staff_id'))
            ? (int) $request->input('ops_staff_id')
            : null;
        $opsSelectedStaffName = null;
        $opsItems = collect();
        $opsOpenCount = 0;
        $opsCompletedCount = 0;
        $opsTotalCount = 0;
        $opsCompletionRate = 0;
        $opsMyOpenCount = 0;
        $opsAssigneeOptions = collect();
        $opsScopes = OpsActionItem::SCOPES;
        $opsSlaOptions = OpsActionItem::SLA_OPTIONS;

        try {
            OpsActionItem::$referenceWeekMonday = $opsMonday->toDateString();

            $staffAccessUsers = User::where('is_active', true)
                ->orderByRaw("CASE role WHEN 'admin' THEN 1 WHEN 'manager' THEN 2 WHEN 'agent' THEN 3 ELSE 4 END ASC")
                ->orderBy('name')
                ->get(['id', 'name', 'email', 'role']);
            $usersById = $staffAccessUsers->keyBy('id');

            $rawOpsItems = OpsActionItem::forWeek($opsMonday)->executionOrder()->get();
            $openOverdue = $rawOpsItems->filter(fn (OpsActionItem $i) => !$i->is_completed && $i->is_overdue)->values();
            $openOnTrack = $rawOpsItems->filter(fn (OpsActionItem $i) => !$i->is_completed && !$i->is_overdue)->values();
            $completedOps = $rawOpsItems->filter(fn (OpsActionItem $i) => $i->is_completed)->values();
            $openOpsItems = $openOverdue->concat($openOnTrack)->values();

            $opsOpenCount = $openOpsItems->count();
            $opsCompletedCount = $completedOps->count();
            $opsTotalCount = $opsOpenCount + $opsCompletedCount;
            $opsCompletionRate = $opsTotalCount > 0 ? (int) round(($opsCompletedCount / $opsTotalCount) * 100) : 0;

            $openOpsTasksCount = $opsOpenCount;
            $pendingOwnerActionCount = $openOpsItems
                ->filter(fn (OpsActionItem $item) => $item->involvesBucket('owner', $usersById))
                ->count();

            $authUser = $request->user();
            $opsMyOpenCount = $authUser
                ? $openOpsItems->filter(fn (OpsActionItem $item) => $item->isAssignedToUser((int) $authUser->id, $authUser->name))->count()
                : 0;

            $staffOpenCounts = [];
            foreach ($staffAccessUsers as $u) {
                $staffOpenCounts[$u->id] = $openOpsItems
                    ->filter(fn (OpsActionItem $item) => $item->isAssignedToUser((int) $u->id, $u->name))
                    ->count();
            }

            if ($opsSelectedStaffId !== null) {
                $opsSelectedStaffName = $usersById->get($opsSelectedStaffId)?->name;
            }

            if ($opsStatus === 'completed') {
                $opsItems = $completedOps;
            } elseif ($opsMine && $authUser) {
                $opsItems = $openOpsItems
                    ->filter(fn (OpsActionItem $item) => $item->isAssignedToUser((int) $authUser->id, $authUser->name))
                    ->values();
            } elseif ($opsSelectedStaffId !== null) {
                $opsItems = $openOpsItems
                    ->filter(fn (OpsActionItem $item) => $item->isAssignedToUser($opsSelectedStaffId, $opsSelectedStaffName))
                    ->values();
            } else {
                $opsItems = $openOpsItems;
            }

            $opsAssigneeOptions = $staffAccessUsers->map(function (User $u) use ($staffOpenCounts) {
                $roleLabel = match ($u->role) {
                    'admin' => 'Owner / Admin',
                    'manager' => 'Ops Lead / Manager',
                    default => User::roleLabel($u->role),
                };

                return [
                    'id' => $u->id,
                    'user_id' => $u->id,
                    'type' => OpsActionItem::classifyAssigneeBucket($u->name, $u->role),
                    'name' => $u->name,
                    'role' => $u->role,
                    'role_label' => $roleLabel,
                    'open_count' => $staffOpenCounts[$u->id] ?? 0,
                ];
            })->values();
        } catch (\Throwable) {
            $pendingOwnerActionCount = 0;
            $openOpsTasksCount = 0;
        }
        $pendingOwnerCount = $pendingOwnerActionCount;

        $isAdminViewer = (bool) ($request->user() && $request->user()->isAdmin());
        $staffContracts = collect();
        $contractSalonMembers = collect();
        $contractAccessMembers = collect();

        if ($isAdminViewer) {
            try {
                $todayDate = now('Asia/Qatar')->startOfDay();
                $activeSalonStaff = $allStaff->filter(function (Staff $s) use ($todayDate) {
                    return !$s->end_date || $s->end_date->gte($todayDate);
                })->values();

                $contractSalonMembers = $activeSalonStaff->map(function (Staff $s) {
                    $isRemote = $s->isRemote();
                    $branchKey = $isRemote
                        ? 'remote'
                        : (array_key_exists((string) $s->branch, StaffContract::BRANCHES)
                            ? (string) $s->branch
                            : 'old_airport');
                    $isAgentUser = $s->user && strtolower(trim((string) $s->user->role)) === 'agent';

                    return [
                        'key' => 'staff:' . $s->id,
                        'type' => 'staff',
                        'id' => $s->id,
                        'name' => $s->name,
                        'tag' => $isRemote ? 'Remote' : (StaffContract::BRANCHES[$branchKey] ?? 'Salon Staff'),
                        'template_key' => ($isRemote || $isAgentUser) ? 'remote_chat_creative' : 'salon_beautician',
                        'role_title' => $isAgentUser ? 'Chat Agent' : ($isRemote ? 'Remote Team Member' : 'Salon Beautician'),
                        'branch' => $branchKey,
                        'currency' => ($isRemote || $isAgentUser) ? 'PKR' : 'QAR',
                        'base_salary' => 0.0,
                        'has_custom_pin' => $s->hasCustomContractPin(),
                    ];
                })->values();

                $linkedUserIds = $allStaff->pluck('user_id')
                    ->filter()
                    ->map(fn ($id) => (int) $id)
                    ->unique()
                    ->all();

                $linkedStaffEmails = $allStaff->pluck('email')
                    ->filter()
                    ->map(fn ($email) => strtolower(trim((string) $email)))
                    ->unique()
                    ->all();

                $accessUsers = User::where('is_active', true)
                    ->orderByRaw("CASE role WHEN 'manager' THEN 1 WHEN 'agent' THEN 2 WHEN 'staff' THEN 3 WHEN 'user' THEN 4 WHEN 'admin' THEN 5 ELSE 6 END ASC")
                    ->orderBy('name')
                    ->get();

                $contractAccessMembers = $accessUsers
                    ->reject(function (User $u) use ($linkedUserIds, $linkedStaffEmails) {
                        if (in_array((int) $u->id, $linkedUserIds, true)) {
                            return true;
                        }
                        $cleanEmail = strtolower(trim((string) $u->email));
                        return $cleanEmail !== '' && in_array($cleanEmail, $linkedStaffEmails, true);
                    })
                    ->map(function (User $u) {
                        $profile = StaffContract::resolveUserContractProfile($u);

                        return [
                            'key' => 'user:' . $u->id,
                            'type' => 'user',
                            'id' => $u->id,
                            'name' => $u->name,
                            'tag' => $profile['tag'],
                            'template_key' => $profile['template_key'],
                            'role_title' => $profile['role_title'],
                            'branch' => $profile['branch'],
                            'currency' => $profile['currency'],
                            'base_salary' => 0.0,
                            'has_custom_pin' => $u->hasCustomContractPin(),
                        ];
                    })
                    ->values();

                $allStaffById = $allStaff->keyBy('id');
                $staffContracts = StaffContract::with(['staff', 'user'])
                    ->orderByDesc('id')
                    ->get()
                    ->each(function (StaffContract $c) use ($allStaffById) {
                        if ($c->staff_id && $allStaffById->has($c->staff_id)) {
                            $c->setRelation('staff', $allStaffById->get($c->staff_id));
                        }
                    });
            } catch (\Throwable) {
                $staffContracts = collect();
            }
        } elseif ($actor = $request->user()) {
            try {
                $actorStaffIds = Staff::where('user_id', $actor->id)->pluck('id')->all();
                $allStaffById = $allStaff->keyBy('id');
                $staffContracts = StaffContract::with(['staff', 'user'])
                    ->where(function ($q) use ($actor, $actorStaffIds) {
                        $q->where('user_id', $actor->id);
                        if (!empty($actorStaffIds)) {
                            $q->orWhereIn('staff_id', $actorStaffIds);
                        }
                    })
                    ->orderByDesc('id')
                    ->get()
                    ->each(function (StaffContract $c) use ($allStaffById) {
                        if ($c->staff_id && $allStaffById->has($c->staff_id)) {
                            $c->setRelation('staff', $allStaffById->get($c->staff_id));
                        }
                    });
            } catch (\Throwable) {
                $staffContracts = collect();
            }
        }
        $contractTemplates = $isAdminViewer
            ? StaffContract::allTemplates()
            : StaffContract::TEMPLATES;

        $todayQatar = now('Asia/Qatar')->toDateString();
        $activeStaffForDeductions = $allStaff->filter(function (Staff $s) use ($todayQatar) {
            if (! $s->bookable) {
                return false;
            }
            $startOk = ! $s->start_date || Carbon::parse($s->start_date)->toDateString() <= $todayQatar;
            $endOk = ! $s->end_date || Carbon::parse($s->end_date)->toDateString() >= $todayQatar;

            return $startOk && $endOk;
        })->values();
        $canManageManualDeductions = StaffDeductionController::canManage($request->user());

        // ---- Attendance Log & Leave Requests Queue (scoped cleanly to keep /staffs?tab=payroll query budget low) ----
        $attDateParam = (string) $request->input('att_date', $todayQatar);
        try {
            $attendanceDate = Carbon::parse($attDateParam, 'Asia/Qatar')->toDateString();
        } catch (\Throwable) {
            $attendanceDate = $todayQatar;
        }
        $rawAttBranch = strtolower(trim((string) $request->input('att_branch', '')));
        $attendanceBranch = in_array($rawAttBranch, ['old_airport', 'wakrah', 'remote'], true) ? $rawAttBranch : '';

        $attendanceStaffMembers = $attendanceBranch !== ''
            ? $allStaff->filter(function (Staff $s) use ($attendanceBranch) {
                if ($attendanceBranch === 'remote') {
                    return $s->isRemote();
                }

                return !$s->isRemote() && in_array($s->branch, [$attendanceBranch, 'both'], true);
            })->values()
            : $allStaff;

        $attendanceMap = collect();
        $pendingLeavesCount = 0;
        $staffLeaves = collect();
        $leaveConflictsById = [];
        $leaveEligibleStaff = $allStaff->filter(fn (Staff $s) => $s->isLeaveEligible())->values();

        if ($activeTab === 'shifts_ops' || $rawTab === 'directory') {
            if (Schema::hasTable('staff_leaves') && $isAdminViewer) {
                if ($activeTab === 'shifts_ops') {
                    $staffLeaves = StaffLeave::with(['staff.user', 'reviewer:id,name'])
                        ->orderByRaw("CASE status WHEN 'pending' THEN 1 WHEN 'approved' THEN 2 WHEN 'rejected' THEN 3 ELSE 4 END ASC")
                        ->orderByDesc('start_date')
                        ->get();
                    $pendingLeavesCount = $staffLeaves->where('status', StaffLeave::STATUS_PENDING)->count();

                    foreach ($staffLeaves->where('status', StaffLeave::STATUS_PENDING) as $pendingLeave) {
                        $leaveConflictsById[$pendingLeave->id] = StaffLeaveController::findBookingConflicts(
                            (int) $pendingLeave->staff_id,
                            $pendingLeave->start_date->toDateString(),
                            $pendingLeave->end_date->toDateString()
                        );
                    }
                } else {
                    $pendingLeavesCount = StaffLeave::pending()->count();
                }
            }

            if ($activeTab === 'shifts_ops' && Schema::hasTable('staff_attendances')) {
                $attendanceMap = StaffAttendance::whereDate('attendance_date', $attendanceDate)
                    ->get()
                    ->keyBy('staff_id');
            }
        }

        return view('staff.index', compact(
            'staff', 'services', 'categories', 'activeTab',
            'directorySubView', 'payrollSubView', 'shiftsOpsSubView', 'hrSubView',
            'allStaff', 'allStaffOptions', 'serviceOptions',
            'activeStaffForDeductions', 'canManageManualDeductions',
            'staffWorkspaceOptions', 'selectedWorkspaceStaffId',
            'payrollBranch', 'payrollFrom', 'payrollTo', 'payrollRows',
            'shiftWeekStart', 'shiftWeekEnd', 'shiftDays', 'shiftRoster', 'shiftDayTotals', 'shiftConfigs', 'shiftBranch',
            'attendanceDate', 'attendanceBranch', 'attendanceStaffMembers', 'attendanceMap',
            'pendingLeavesCount', 'staffLeaves', 'leaveConflictsById', 'leaveEligibleStaff',
            'opsItems', 'opsOpenCount', 'opsCompletedCount', 'opsTotalCount', 'opsCompletionRate',
            'opsMyOpenCount', 'opsStatus', 'opsMine', 'opsSelectedStaffId', 'opsSelectedStaffName',
            'opsAssigneeOptions', 'opsMonday', 'opsSunday', 'opsPrevWeek', 'opsNextWeek', 'opsIsCurrentWeek',
            'opsScopes', 'opsSlaOptions',
            'staffContracts', 'contractTemplates', 'contractSalonMembers', 'contractAccessMembers', 'isAdminViewer',
            'complaints', 'deductions', 'complaintsStaffFilter', 'complaintsSearch',
            'complaintsDate', 'complaintsFrom', 'complaintsTo',
            'deductionsDate', 'deductionsFrom', 'deductionsTo',
            'openComplaints', 'openComplaintOptions',
            'pendingOwnerActionCount', 'pendingOwnerCount', 'openOpsTasksCount'
        ));
    }

    public function store(Request $request)
    {
        $this->authorizeAccessSync($request, null);

        $validated = $this->validated($request);
        $profilePath = $this->handlePhoto($request);

        $requestedBranch = strtolower(trim((string) ($validated['branch'] ?? '')));
        $isRemoteAccess = ($request->boolean('has_access') || $request->boolean('login_access'))
            && Staff::isRemoteRole((string) $request->input('access_role', ''));

        if ($requestedBranch === 'remote' || ($isRemoteAccess && !in_array($requestedBranch, Staff::PHYSICAL_BRANCHES, true))) {
            $validated['branch'] = null;
            $validated['is_remote'] = true;
            $validated['bookable'] = false;
        } elseif (in_array($requestedBranch, Staff::PHYSICAL_BRANCHES, true)) {
            $validated['branch'] = $requestedBranch;
            $validated['is_remote'] = false;
        } else {
            $validated['branch'] = 'old_airport';
            $validated['is_remote'] = false;
        }

        $staff = Staff::create(array_merge($validated, [
            'name' => $this->fullName($validated),
            'profile_picture' => $profilePath,
        ]));

        if ($request->filled('service_ids') || $request->filled('skills')) {
            $rawSkills = collect($request->input('service_ids', $request->input('skills', [])))->filter();
            $numericIds = $rawSkills->filter(fn ($v) => is_numeric($v))->map(fn ($v) => (int) $v);
            $stringNames = $rawSkills->reject(fn ($v) => is_numeric($v))->values();
            $resolvedIds = $numericIds;
            if ($stringNames->isNotEmpty()) {
                $resolvedIds = $resolvedIds->merge(Service::whereIn('name', $stringNames)->pluck('id'));
            }
            $staff->services()->sync($resolvedIds->unique()->values()->all());
        }

        $this->syncAccess($request, $staff);

        return redirect()->back()->with('success', 'Staff member added.');
    }

    public function update(Request $request, Staff $staff)
    {
        $this->authorizeAccessSync($request, $staff);

        $validated = $this->validated($request);

        if ($request->hasFile('profile_picture')) {
            if ($staff->profile_picture && file_exists(public_path($staff->profile_picture))) {
                unlink(public_path($staff->profile_picture));
            }
            $validated['profile_picture'] = $this->handlePhoto($request);
        }

        $validated['name'] = $this->fullName($validated);

        if ($request->filled('branch')) {
            $requestedBranch = strtolower(trim((string) $validated['branch']));
            if ($requestedBranch === 'remote') {
                $validated['branch'] = null;
                $validated['is_remote'] = true;
                $validated['bookable'] = false;
            } else {
                $validated['branch'] = $requestedBranch;
                $validated['is_remote'] = false;
            }
        } else {
            unset($validated['branch']);
        }

        $staff->update($validated);
        $this->syncAccess($request, $staff);

        return redirect()->back()->with('success', 'Staff updated.');
    }

    /**
     * Saves the Services/Location/Settings trio from the standalone Staff
     * Workspace tab. Split out from update() because that method's
     * validation requires the full HR profile (first_name, etc.) - the
     * Workspace form only ever submits these three fields for whichever
     * staff member is currently selected.
     */
    public function updateWorkspace(Request $request, Staff $staff)
    {
        $validated = $request->validate([
            'branch' => ['required', Rule::in(['old_airport', 'wakrah', 'both', 'remote'])],
            'bookable' => 'nullable|boolean',
        ]);

        $isRemote = $validated['branch'] === 'remote';
        $isAgent = $staff->user && strtolower(trim((string) $staff->user->role)) === 'agent';

        $staff->update([
            'branch' => $isRemote ? null : $validated['branch'],
            'is_remote' => $isRemote,
            'bookable' => ($isRemote || $isAgent) ? false : $request->boolean('bookable'),
        ]);
        $staff->services()->sync($request->input('service_ids', []));

        return redirect()->route('staffs.index', ['tab' => 'workspace', 'staff' => $staff->id])
            ->with('success', 'Workspace updated for ' . $staff->name . '.');
    }

    /**
     * Quick-edit a team member's Base Salary and Commission Rate
     * directly from the Payroll & Overtime tab.
     */
    public function updateWages(Request $request, Staff $staff)
    {
        abort_unless($request->user()?->isAdmin(), 403, 'Only administrators can edit staff wages.');

        $validated = $request->validate([
            'base_salary' => 'nullable|numeric|min:0',
            'commission_rate' => 'nullable|numeric|min:0|max:100',
        ]);

        $staff->update([
            'base_salary' => $validated['base_salary'] ?? 0,
            'commission_rate' => $validated['commission_rate'] ?? 0,
        ]);

        return redirect()->back()->with('success', 'Wage settings updated for ' . $staff->name . '.');
    }

    public function destroy(Request $request, Staff $staff)
    {
        $existingUser = $staff->user_id ? ($staff->user ?? User::find($staff->user_id)) : null;
        $actor = $request->user();
        $actorIsAdmin = $actor && ($actor->isSuperAdmin() || $actor->isAdmin());

        if ($existingUser && ($existingUser->isAdmin() || $existingUser->isSuperAdmin()) && !$actorIsAdmin) {
            abort(403, 'Only an administrator can remove or deactivate an admin account.');
        }

        DB::transaction(function () use ($staff) {
            if ($staff->user_id) {
                User::where('id', $staff->user_id)->update(['is_active' => false]);
                if (Schema::hasTable('sessions')) {
                    DB::table('sessions')->where('user_id', $staff->user_id)->delete();
                }
            }

            // Move any open/upcoming appointments assigned to this staff member
            // into the Unscheduled Queue (start_time = null) so deleting a staff
            // member never leaves invisible ghost bookings on the Calendar.
            $openAppointments = Appointment::with('appointmentServices')
                ->whereNotIn('status', ['completed', 'cancelled', 'no_show'])
                ->where(function ($q) use ($staff) {
                    $q->where('staff_id', $staff->id)
                        ->orWhereHas('appointmentServices', fn ($sq) => $sq->where('staff_id', $staff->id));
                })
                ->get();

            foreach ($openAppointments as $appointment) {
                AppointmentService::where('appointment_id', $appointment->id)
                    ->where('staff_id', $staff->id)
                    ->update(['staff_id' => null]);

                $remainingStaffId = AppointmentService::where('appointment_id', $appointment->id)
                    ->whereNotNull('staff_id')
                    ->value('staff_id');

                if ($remainingStaffId) {
                    $appointment->update(['staff_id' => $remainingStaffId]);
                } else {
                    $bookingDate = $appointment->booking_date
                        ?: ($appointment->appointment_datetime ? Carbon::parse($appointment->appointment_datetime)->toDateString() : now()->toDateString());

                    $appointment->update([
                        'staff_id' => null,
                        'start_time' => null,
                        'booking_date' => $bookingDate,
                    ]);
                }
            }

            $staff->delete();
        });

        return redirect()->back()->with('success', 'Staff removed.');
    }

    /**
     * Post calculated Net Payroll for the selected period & branch into the
     * Finance Expenses table ('Salaries & Wages') idempotently using a
     * deterministic period tag so repeat clicks update the existing record.
     */
    public function postPayrollToFinance(Request $request)
    {
        abort_unless($request->user()?->isAdmin(), 403, 'Only administrators can post payroll to Finance.');

        $from = $request->date('payroll_from') ?? $request->date('from') ?? now()->startOfMonth();
        $to = $request->date('payroll_to') ?? $request->date('to') ?? now()->endOfMonth();
        if ($from->gt($to)) {
            [$from, $to] = [$to->copy()->startOfDay(), $from->copy()->endOfDay()];
        }

        $rawBranch = $request->input('payroll_branch') ?? $request->input('branch');
        $payrollBranch = in_array($rawBranch, ['old_airport', 'wakrah'], true) ? $rawBranch : null;

        $calculator = new StaffPayrollCalculator($from, $to);
        $allActiveStaff = Staff::active()->orderBy('name')->get();
        $allRows = $calculator->payrollFor($allActiveStaff);

        $branchBuckets = [
            'old_airport' => ['label' => 'Old Airport', 'amount' => 0.0, 'count' => 0],
            'wakrah' => ['label' => 'Al Wakrah', 'amount' => 0.0, 'count' => 0],
        ];

        foreach ($allRows as $row) {
            $net = round((float) ($row['net_salary'] ?? 0), 2);
            $staffBranch = strtolower(trim((string) ($row['branch'] ?? '')));

            if ($staffBranch === '' || $staffBranch === 'remote' || !empty($row['is_remote'])) {
                continue;
            }

            if ($staffBranch === 'both') {
                $oaShare = round($net * 0.5, 2);
                $wkShare = round($net - $oaShare, 2);
                $branchBuckets['old_airport']['amount'] = round($branchBuckets['old_airport']['amount'] + $oaShare, 2);
                $branchBuckets['old_airport']['count']++;
                $branchBuckets['wakrah']['amount'] = round($branchBuckets['wakrah']['amount'] + $wkShare, 2);
                $branchBuckets['wakrah']['count']++;
            } elseif ($staffBranch === 'wakrah') {
                $branchBuckets['wakrah']['amount'] = round($branchBuckets['wakrah']['amount'] + $net, 2);
                $branchBuckets['wakrah']['count']++;
            } else {
                $branchBuckets['old_airport']['amount'] = round($branchBuckets['old_airport']['amount'] + $net, 2);
                $branchBuckets['old_airport']['count']++;
            }
        }

        $tag = sprintf('[Payroll: %s to %s]', $from->format('Y-m-d'), $to->format('Y-m-d'));

        $targetBranches = $payrollBranch
            ? [$payrollBranch]
            : ['old_airport', 'wakrah'];

        $totalNetPayroll = round(collect($targetBranches)->sum(fn (string $b) => $branchBuckets[$b]['amount']), 2);

        $expense = DB::transaction(function () use ($tag, $payrollBranch, $targetBranches, $branchBuckets, $to) {
            // Remove any legacy unallocated (branch IS NULL) payroll rows for the same period
            // so switching between "All Branches" and per-branch posting never double-posts.
            Expense::where('category', 'Salaries & Wages')
                ->whereNull('branch')
                ->where('description', 'like', '%' . $tag . '%')
                ->lockForUpdate()
                ->delete();

            $primaryExpense = null;

            foreach ($targetBranches as $branchKey) {
                $bucket = $branchBuckets[$branchKey];
                $description = sprintf('%s Staff Payroll (%d staff) %s', $bucket['label'], $bucket['count'], $tag);

                $existing = Expense::where('category', 'Salaries & Wages')
                    ->where('branch', $branchKey)
                    ->where('description', 'like', '%' . $tag . '%')
                    ->lockForUpdate()
                    ->first();

                if ($payrollBranch === null && $bucket['count'] === 0 && $bucket['amount'] <= 0) {
                    if ($existing) {
                        $existing->delete();
                    }
                    continue;
                }

                if ($existing) {
                    $existing->update([
                        'amount' => $bucket['amount'],
                        'expense_date' => $to->toDateString(),
                        'description' => $description,
                    ]);
                    $primaryExpense = $primaryExpense ?? $existing;
                } else {
                    $created = Expense::create([
                        'branch' => $branchKey,
                        'category' => 'Salaries & Wages',
                        'amount' => $bucket['amount'],
                        'expense_date' => $to->toDateString(),
                        'description' => $description,
                        'created_by' => auth()->id(),
                    ]);
                    $primaryExpense = $primaryExpense ?? $created;
                }
            }

            return $primaryExpense;
        });

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => "Posted QAR " . number_format($totalNetPayroll, 2) . " payroll to Finance Expenses.",
                'expense_id' => $expense?->id,
                'amount' => (float) $totalNetPayroll,
            ]);
        }

        return redirect()->route('staffs.index', array_filter([
            'tab' => 'payroll',
            'payroll_branch' => $payrollBranch,
            'payroll_from' => $from->format('Y-m-d'),
            'payroll_to' => $to->format('Y-m-d'),
        ]))->with('success', "Posted QAR " . number_format($totalNetPayroll, 2) . " payroll to Finance Expenses.");
    }

    private function fullName(array $validated): string
    {
        return trim($validated['first_name'] . ' ' . ($validated['last_name'] ?? ''));
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'first_name' => 'required|string|max:100',
            'last_name' => 'nullable|string|max:100',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:30',
            'birthday' => 'nullable|date',
            'address_line1' => 'nullable|string|max:255',
            'city' => 'nullable|string|max:100',
            'country' => 'nullable|string|max:100',
            'emergency_contact_name' => 'nullable|string|max:255',
            'emergency_contact_phone' => 'nullable|string|max:30',
            'emergency_contact_relationship' => 'nullable|string|max:100',

            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'branch' => ['nullable', Rule::in(['old_airport', 'wakrah', 'both', 'remote'])],
            'annual_leave_days' => 'nullable|integer|min:0|max:90',
            'sick_leave_days' => 'nullable|integer|min:0|max:90',
            'employment_type' => 'nullable|string|max:30',
            'staff_member_id' => 'nullable|string|max:100',
            'internal_notes' => 'nullable|string|max:1000',
            'hourly_wage' => 'nullable|numeric|min:0',
            'commission_rate' => 'nullable|numeric|min:0|max:100',
            'base_salary' => 'nullable|numeric|min:0',

            'profile_picture' => 'nullable|image|max:2048',
        ]);

        unset($data['profile_picture']);

        if (!$request->user()?->isAdmin()) {
            unset($data['hourly_wage'], $data['commission_rate'], $data['base_salary'], $data['annual_leave_days'], $data['sick_leave_days']);
        } else {
            foreach (['hourly_wage', 'commission_rate', 'base_salary', 'annual_leave_days', 'sick_leave_days'] as $adminField) {
                if (!$request->has($adminField) || $request->input($adminField) === null || $request->input($adminField) === '') {
                    unset($data[$adminField]);
                }
            }
        }

        return $data;
    }

    private function handlePhoto(Request $request): ?string
    {
        if (!$request->hasFile('profile_picture')) {
            return null;
        }

        $destinationPath = public_path('staff/profile-picture');
        if (!file_exists($destinationPath)) {
            mkdir($destinationPath, 0755, true);
        }

        $file = $request->file('profile_picture');
        $fileName = time() . '_' . $file->getClientOriginalName();
        $file->move($destinationPath, $fileName);

        return 'staff/profile-picture/' . $fileName;
    }

    /**
     * Block non-admin staff managers from assigning role='admin' or mutating/revoking
     * an existing admin or super-admin account via the Staff Management form.
     */
    private function authorizeAccessSync(Request $request, ?Staff $staff): void
    {
        $actor = $request->user();
        $actorIsAdmin = $actor && ($actor->isSuperAdmin() || $actor->isAdmin());

        if ($actorIsAdmin) {
            return;
        }

        $hasAccess = $request->boolean('has_access') || $request->boolean('login_access');
        $requestedRole = strtolower(trim((string) $request->input('access_role', '')));

        if ($hasAccess && in_array($requestedRole, ['admin', 'owner', 'super_admin'], true)) {
            abort(403, 'Only an administrator can assign the admin role.');
        }

        if ($staff && $staff->user_id) {
            $existingUser = $staff->user ?? User::find($staff->user_id);
            if ($existingUser && ($existingUser->isAdmin() || $existingUser->isSuperAdmin())) {
                abort(403, 'Only an administrator can modify or revoke an admin account.');
            }
        }
    }

    /**
     * Grant, update, or revoke this team member's CRM login + role.
     * Revoking unlinks the account without deleting it, so access can be
     * restored later without losing the user's history.
     */
    private function syncAccess(Request $request, Staff $staff): void
    {
        $this->authorizeAccessSync($request, $staff);

        $hasAccess = $request->boolean('has_access') || $request->boolean('login_access');

        if (!$hasAccess) {
            if ($staff->user_id) {
                $deactivatedUserId = $staff->user_id;
                User::where('id', $deactivatedUserId)->update(['is_active' => false]);
                if (Schema::hasTable('sessions')) {
                    DB::table('sessions')->where('user_id', $deactivatedUserId)->delete();
                }
                $staff->update(['user_id' => null]);
            }
            return;
        }

        $existingUserId = $staff->user_id;

        $request->validate([
            'access_role' => ['required', Rule::in(User::validRoleSlugs())],
            'access_email' => [
                $existingUserId ? 'nullable' : 'required',
                'nullable',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($existingUserId),
            ],
            'access_password' => 'nullable|string|min:8',
        ]);

        if ($existingUserId) {
            $user = $staff->user ?? User::find($existingUserId);
            if ($user) {
                $user->role = $request->access_role;
                $user->is_active = true;
                if ($request->filled('access_email')) {
                    $user->email = $request->access_email;
                }
                if ($request->filled('access_password')) {
                    $user->password = Hash::make($request->access_password);
                }
                $user->save();
                return;
            }
        }

        $user = User::create([
            'name' => $staff->name,
            'email' => $request->access_email,
            'password' => Hash::make($request->access_password ?: str()->random(12)),
            'role' => $request->access_role,
            'permissions' => User::defaultPermissionsForRole($request->access_role),
            'is_active' => true,
        ]);

        $staff->update(['user_id' => $user->id]);
    }

    /**
     * Admin-only: Create a Self-Service Portal / CRM login directly from
     * Staff Management -> Directory & Profiles and link it 1-to-1 to $staff.
     */
    public function createPortalLogin(Request $request, Staff $staff)
    {
        abort_unless($request->user()?->isAdmin(), 403, 'Only administrators can create staff portal logins.');

        if ($staff->user_id && User::whereKey($staff->user_id)->exists()) {
            $msg = 'This team member already has a portal login.';
            if ($request->expectsJson() || $request->ajax()) {
                return response()->json(['success' => false, 'message' => $msg], 422);
            }

            return redirect()->back()->with('error', $msg);
        }

        $allowedRoles = array_values(array_diff(User::validRoleSlugs(), ['admin']));
        if ($request->user()->isAdmin()) {
            $allowedRoles = User::validRoleSlugs();
        }

        $validated = $request->validate([
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')],
            'password' => ['required', 'string', 'min:8', 'max:128'],
            'role' => ['required', Rule::in($allowedRoles)],
        ]);

        $user = DB::transaction(function () use ($staff, $validated) {
            $created = User::create([
                'name' => $staff->name,
                'email' => strtolower(trim($validated['email'])),
                'password' => Hash::make($validated['password']),
                'role' => $validated['role'],
                'permissions' => User::defaultPermissionsForRole($validated['role']),
                'is_active' => true,
            ]);

            $staffUpdates = ['user_id' => $created->id];
            if (empty($staff->email)) {
                $staffUpdates['email'] = $created->email;
            }
            $staff->update($staffUpdates);

            return $created;
        });

        $displayRole = $user->role === 'agent' ? 'Chat Agent' : $user->roleBadgeLabel();

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => "Portal login created for {$staff->name} ({$displayRole}).",
                'user' => [
                    'id' => $user->id,
                    'email' => $user->email,
                    'role' => $user->role,
                    'role_label' => $displayRole,
                    'is_active' => (bool) $user->is_active,
                ],
            ], 201);
        }

        return redirect()->back()->with('success', "Portal login created for {$staff->name} ({$displayRole}).");
    }

    /**
     * Admin-only: Toggle a linked staff member's portal login between Active and Disabled.
     * Disabling immediately purges all stored DB sessions (and EnsureActiveUser blocks any active session).
     */
    public function togglePortalAccess(Request $request, Staff $staff)
    {
        abort_unless($request->user()?->isAdmin(), 403, 'Only administrators can manage staff portal access.');

        $user = $staff->user_id ? ($staff->user ?? User::find($staff->user_id)) : null;
        if (!$user) {
            $msg = 'This team member does not have a portal login yet.';
            if ($request->expectsJson() || $request->ajax()) {
                return response()->json(['success' => false, 'message' => $msg], 422);
            }

            return redirect()->back()->with('error', $msg);
        }

        if ((int) $user->id === (int) $request->user()->id) {
            $msg = 'You cannot disable your own account.';
            if ($request->expectsJson() || $request->ajax()) {
                return response()->json(['success' => false, 'message' => $msg], 422);
            }

            return redirect()->back()->with('error', $msg);
        }

        $user->is_active = !$user->is_active;
        $user->save();

        if (!$user->is_active && Schema::hasTable('sessions')) {
            DB::table('sessions')->where('user_id', $user->id)->delete();
        }

        $statusWord = $user->is_active ? 'activated' : 'disabled';
        $message = "Portal access {$statusWord} for {$staff->name}.";

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'is_active' => (bool) $user->is_active,
                'message' => $message,
            ]);
        }

        return redirect()->back()->with('success', $message);
    }

    /**
     * Admin-only: Reset the password of a linked staff member's portal login.
     */
    public function resetPortalPassword(Request $request, Staff $staff)
    {
        abort_unless($request->user()?->isAdmin(), 403, 'Only administrators can reset staff portal passwords.');

        $user = $staff->user_id ? ($staff->user ?? User::find($staff->user_id)) : null;
        if (!$user) {
            $msg = 'This team member does not have a portal login yet.';
            if ($request->expectsJson() || $request->ajax()) {
                return response()->json(['success' => false, 'message' => $msg], 422);
            }

            return redirect()->back()->with('error', $msg);
        }

        $validated = $request->validate([
            'password' => ['required', 'string', 'min:8', 'max:128'],
        ]);

        $user->forceFill([
            'password' => Hash::make($validated['password']),
        ])->save();

        $message = "Password reset for {$staff->name}.";

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => $message,
            ]);
        }

        return redirect()->back()->with('success', $message);
    }

    private function formatShiftTimeRange(string $start, string $end): string
    {
        $format = function (string $t): string {
            $carbon = Carbon::createFromFormat('H:i:s', strlen($t) === 5 ? $t . ':00' : $t);
            return strtolower($carbon->format($carbon->minute === 0 ? 'ga' : 'g:ia'));
        };

        return $format($start) . ' - ' . $format($end);
    }
}
