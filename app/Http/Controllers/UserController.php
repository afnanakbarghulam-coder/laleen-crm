<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Models\Customer;
use App\Models\KpiAdsConversionReport;
use App\Models\KpiAgentTargetReport;
use App\Models\KpiChatEvaluation;
use App\Models\KpiContentReport;
use App\Models\Lead;
use App\Models\OpsActionItem;
use App\Models\Sale;
use App\Models\SocialContentPlan;
use App\Models\Staff;
use App\Models\User;
use App\Support\DashboardScorecardBuilder;
use App\Support\StaffSalesAnalytics;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;

class UserController extends Controller
{

    public function dashboard(Request $request)
    {
        // ---- Date range (defaults automatically to Month-to-Date in Asia/Qatar; honors optional ?from=&to= or ?date_from=&date_to=) ----
        $rawFrom = $request->input('from') ?: $request->input('date_from');
        $rawTo = $request->input('to') ?: $request->input('date_to');
        $nowQatar = now('Asia/Qatar');

        $dashFrom = filled($rawFrom)
            ? Carbon::parse($rawFrom, 'Asia/Qatar')->startOfDay()
            : $nowQatar->copy()->startOfMonth()->startOfDay();
        $dashTo = filled($rawTo)
            ? Carbon::parse($rawTo, 'Asia/Qatar')->endOfDay()
            : $nowQatar->copy()->endOfDay();

        if ($dashFrom->gt($dashTo)) {
            [$dashFrom, $dashTo] = [$dashTo->copy()->startOfDay(), $dashFrom->copy()->endOfDay()];
        }

        $user = Auth::user();

        // ---- Live Today Operational Salon Pulse ----
        $todayStr = today()->toDateString();
        $todayAppointments = Appointment::whereNotIn('status', ['cancelled', 'no_show'])
            ->where(function ($q) use ($todayStr) {
                $q->whereDate('booking_date', $todayStr)
                    ->orWhere(function ($sub) use ($todayStr) {
                        $sub->whereNull('booking_date')
                            ->whereDate('appointment_datetime', $todayStr);
                    });
            })
            ->count();

        $todayCompletedAppointments = Appointment::where('status', 'completed')
            ->where(function ($q) use ($todayStr) {
                $q->whereDate('booking_date', $todayStr)
                    ->orWhere(function ($sub) use ($todayStr) {
                        $sub->whereNull('booking_date')
                            ->whereDate('appointment_datetime', $todayStr);
                    });
            })
            ->count();

        $leadsDueToday = Lead::open()->whereDate('next_followup_date', $todayStr)->count();
        $leadsOverdue = Lead::open()->whereNotNull('next_followup_date')->whereDate('next_followup_date', '<', $todayStr)->count();
        $leadsUnscheduled = Lead::open()->whereNull('next_followup_date')->count();
        $leadsActionQueue = $leadsDueToday + $leadsOverdue + $leadsUnscheduled;
        $totalLeads = Lead::open()->count();
        $pendingFollowups = $leadsActionQueue;

        $availableStaff = Staff::active()->count();
        $presentStaffCount = Staff::active()->where('availability_status', 'present')->count();
        $totalClientsCount = Customer::count();

        $operationalPulse = [
            'today_appointments' => $todayAppointments,
            'today_completed' => $todayCompletedAppointments,
            'leads_due_today' => $leadsDueToday,
            'leads_overdue' => $leadsOverdue,
            'leads_unscheduled' => $leadsUnscheduled,
            'leads_action_queue' => $leadsActionQueue,
            'total_open_leads' => $totalLeads,
            'active_staff' => $availableStaff,
            'present_staff' => $presentStaffCount,
            'total_clients' => $totalClientsCount,
        ];

        // ---- Strict Admin-Only Guard for Branch Revenue & Expenses To-Date ----
        $isAdmin = $user && $user->isAdmin();
        $branchPnl = null;
        $branchCards = [];
        $combinedPnl = null;

        if ($isAdmin) {
            $rawBreakdown = FinanceController::branchBreakdown($dashFrom, $dashTo);
            $combinedSales = round((float) collect($rawBreakdown)->sum('sales'), 2);
            $combinedExpenses = round((float) collect($rawBreakdown)->sum('expenses'), 2);
            $combinedProfit = round($combinedSales - $combinedExpenses, 2);

            $combinedPnl = [
                'sales' => $combinedSales,
                'expenses' => $combinedExpenses,
                'profit' => $combinedProfit,
                'has_expenses' => $combinedExpenses > 0,
                'margin_pct' => ($combinedExpenses > 0 && $combinedSales > 0)
                    ? round(($combinedProfit / $combinedSales) * 100, 1)
                    : null,
            ];

            foreach ($rawBreakdown as $row) {
                if (!in_array($row['key'], ['old_airport', 'wakrah'], true)) {
                    continue;
                }

                $sales = round((float) ($row['sales'] ?? 0), 2);
                $expenses = round((float) ($row['expenses'] ?? 0), 2);
                $profit = round((float) ($row['profit'] ?? ($sales - $expenses)), 2);
                $hasExpenses = $expenses > 0;
                $marginPct = ($hasExpenses && $sales > 0)
                    ? round(($profit / $sales) * 100, 1)
                    : null;

                $branchCards[] = [
                    'key' => $row['key'],
                    'label' => $row['label'],
                    'header_label' => $row['key'] === 'old_airport'
                        ? 'OLD AIRPORT — TO DATE'
                        : 'AL WAKRAH — TO DATE',
                    'sales' => $sales,
                    'expenses' => $expenses,
                    'profit' => $profit,
                    'has_expenses' => $hasExpenses,
                    'margin_pct' => $marginPct,
                ];
            }

            $branchPnl = $branchCards;
        }

        // ---- Team KPI 2-Column Split Scorecards ----
        $teamScorecards = (new DashboardScorecardBuilder($dashFrom, $dashTo, $user))->build();

        $adsTotals = null;
        $adsColor = null;
        $agentShifts = null;
        $staffSalesComparison = null;
        $chatQuality = null;
        $contentMetrics = null;
        $contentColor = null;

        if ($user && $user->canView('kpis')) {
            $adsReport = new KpiAdsConversionReport(['date_from' => $dashFrom, 'date_to' => $dashTo]);
            $adsTotals = $adsReport->totals();
            $adsColor = match ($adsReport->statusFor($adsTotals['overall_conversion'])) {
                'Above' => 'green',
                'Near', 'Below' => 'amber',
                default => 'red',
            };

            $agentShifts = (new KpiAgentTargetReport(['date_from' => $dashFrom, 'date_to' => $dashTo]))->shiftStats();

            $staffSalesComparison = (new StaffSalesAnalytics($dashFrom, $dashTo))->branchComparison();

            $chatEvals = KpiChatEvaluation::whereBetween('evaluation_date', [$dashFrom, $dashTo])->get();
            $chatAvg = $chatEvals->count() ? round($chatEvals->avg(fn ($r) => (float) $r->percentage), 1) : null;
            $chatQuality = [
                'avg' => $chatAvg,
                'grade' => $chatAvg !== null ? KpiChatEvaluation::gradeFor($chatAvg) : null,
                'count' => $chatEvals->count(),
                'color' => match ($chatAvg !== null ? KpiChatEvaluation::gradeFor($chatAvg) : null) {
                    'Excellent', 'Pass' => 'green',
                    'Warning' => 'amber',
                    'Fail' => 'red',
                    default => 'gray',
                },
            ];

            $contentMetrics = $this->resolveDashboardContentMetrics($dashFrom, $dashTo);
            $contentColor = match ($contentMetrics['grade']) {
                'Excellent', 'Pass' => 'green',
                'Warning' => 'amber',
                default => 'red',
            };
        }

        return view('dashboard', compact(
            'dashFrom',
            'dashTo',
            'isAdmin',
            'operationalPulse',
            'todayAppointments',
            'todayCompletedAppointments',
            'totalLeads',
            'pendingFollowups',
            'availableStaff',
            'totalClientsCount',
            'branchPnl',
            'branchCards',
            'combinedPnl',
            'teamScorecards',
            'adsTotals',
            'adsColor',
            'agentShifts',
            'staffSalesComparison',
            'chatQuality',
            'contentMetrics',
            'contentColor'
        ));
    }

    /**
     * Computes Content KPI compliance strictly from the active Weekly Content Calendar
     * (SocialContentPlan / social_content_plans) overlapping [$from, $to].
     */
    private function resolveDashboardContentMetrics(Carbon|\Carbon\Carbon $from, Carbon|\Carbon\Carbon $to): array
    {
        $startWeek = Carbon::instance($from)->startOfWeek(Carbon::MONDAY)->toDateString();
        $endWeek = Carbon::instance($to)->startOfWeek(Carbon::MONDAY)->toDateString();

        $socialPlans = SocialContentPlan::query()
            ->where('week_start_date', '>=', $startWeek)
            ->where('week_start_date', '<=', $endWeek)
            ->get();

        $hasPlanActivity = $socialPlans->contains(
            fn (SocialContentPlan $p) => $p->is_reel_posted
                || $p->are_stories_posted
                || filled($p->reel_content)
                || filled($p->stories_content)
                || filled($p->reel_link)
        );

        if ($socialPlans->isNotEmpty() && $hasPlanActivity) {
            $weeksCount = max(1, $socialPlans->pluck('week_start_date')->unique()->count());
            $reelsPosted = $socialPlans->where('is_reel_posted', true)->where('is_reel_skipped', false)->count();
            $storiesPosted = $socialPlans->where('are_stories_posted', true)->count();

            $reelsTarget = $weeksCount * SocialContentPlan::WEEKLY_REEL_TARGET;
            $storiesTarget = $weeksCount * SocialContentPlan::WEEKLY_STORIES_TARGET;
            $totalTarget = $reelsTarget + $storiesTarget;

            $completed = min($reelsPosted, $reelsTarget) + min($storiesPosted, $storiesTarget);
            $overall = $totalTarget > 0 ? min(100.0, round(($completed / $totalTarget) * 100, 1)) : 0.0;

            return [
                'overall' => $overall,
                'grade' => KpiContentReport::gradeFor($overall),
                'entry_count' => $socialPlans->count(),
                'reels_posted' => $reelsPosted,
                'stories_posted' => $storiesPosted,
            ];
        }

        return [
            'overall' => 0.0,
            'grade' => KpiContentReport::gradeFor(0.0),
            'entry_count' => 0,
            'reels_posted' => 0,
            'stories_posted' => 0,
        ];
    }

    public function index(Request $request)
    {
        $rawView = strtolower(trim((string) $request->query('view', 'all')));
        $activeView = in_array($rawView, ['all', 'unlinked_staff', 'disabled'], true) ? $rawView : 'all';

        $allAccountsCount = User::count();
        $disabledAccountsCount = User::where('is_active', false)->count();
        $staffWithoutLoginCount = Staff::whereNull('user_id')->count();

        $query = User::with('staff');

        if ($activeView === 'disabled') {
            $query->where('is_active', false);
        }

        if ($request->filled('role')) {
            $query->where('role', $request->role);
        }

        if ($request->filled('search')) {
            $search = trim((string) $request->search);
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', '%' . $search . '%')
                    ->orWhere('email', 'like', '%' . $search . '%')
                    ->orWhereHas('staff', fn ($sq) => $sq->where('name', 'like', '%' . $search . '%'));
            });
        }

        $users = $query->orderByDesc('is_active')->orderBy('name')->get();

        $unlinkedStaffQuery = Staff::whereNull('user_id')->orderBy('name');
        if ($request->filled('search')) {
            $search = trim((string) $request->search);
            $unlinkedStaffQuery->where(function ($q) use ($search) {
                $q->where('name', 'like', '%' . $search . '%')
                    ->orWhere('email', 'like', '%' . $search . '%')
                    ->orWhere('phone', 'like', '%' . $search . '%');
            });
        }
        $unlinkedStaff = $unlinkedStaffQuery->get();

        $allLinkableStaff = Staff::orderBy('name')
            ->get(['id', 'name', 'email', 'phone', 'branch', 'is_remote', 'bookable', 'user_id', 'profile_picture', 'availability_status']);

        $pendingOwnerActionCount = 0;
        try {
            $opsMonday = Carbon::now('Asia/Qatar')->startOfWeek(Carbon::MONDAY);
            OpsActionItem::$referenceWeekMonday = $opsMonday->toDateString();
            $usersById = User::where('is_active', true)->get(['id', 'name', 'email', 'role'])->keyBy('id');
            $pendingOwnerActionCount = OpsActionItem::forWeek($opsMonday)
                ->get()
                ->filter(fn (OpsActionItem $item) => !$item->is_completed && $item->involvesBucket('owner', $usersById))
                ->count();
        } catch (\Throwable) {
            $pendingOwnerActionCount = 0;
        }

        return view('users.index', compact(
            'users',
            'activeView',
            'allAccountsCount',
            'staffWithoutLoginCount',
            'disabledAccountsCount',
            'unlinkedStaff',
            'allLinkableStaff',
            'pendingOwnerActionCount'
        ));
    }

    // Store new user (supports creating a brand-new account OR granting login to an existing unlinked Staff record)
    public function store(Request $request)
    {
        $existingStaffId = $request->filled('existing_staff_id') ? (int) $request->input('existing_staff_id') : null;
        $existingStaff = null;

        if ($existingStaffId !== null) {
            $existingStaff = Staff::find($existingStaffId);
            if (!$existingStaff) {
                return $this->accessValidationFailure($request, 'existing_staff_id', 'Selected staff profile was not found.');
            }
            if ($existingStaff->user_id && User::whereKey($existingStaff->user_id)->exists()) {
                return $this->accessValidationFailure($request, 'existing_staff_id', "{$existingStaff->name} already has a login account.");
            }
            if (!$request->filled('name')) {
                $request->merge(['name' => $existingStaff->name]);
            }
        }

        $passwordRules = $request->has('password_confirmation')
            ? ['required', 'string', 'min:8', 'max:128', 'confirmed']
            : ['required', 'string', 'min:8', 'max:128'];

        $request->validate([
            'name' => 'required|string|max:255',
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')],
            'password' => $passwordRules,
            'role' => ['required', Rule::in(User::validRoleSlugs())],
            'profile_photo' => 'nullable|image|max:2048',
            'create_staff_profile' => 'nullable|boolean',
            'staff_branch' => ['nullable', Rule::in(['old_airport', 'wakrah', 'both', 'remote'])],
        ]);

        $role = (string) $request->role;
        $permissions = $role === 'salon_staff'
            ? User::defaultPermissionsForRole('salon_staff')
            : $this->validatedPermissions($request, $role);

        $profilePath = null;
        if ($request->hasFile('profile_photo')) {
            $destinationPath = public_path('user/profile-picture');
            if (!file_exists($destinationPath)) {
                mkdir($destinationPath, 0755, true);
            }
            $file = $request->file('profile_photo');
            $fileName = time() . '_' . $file->getClientOriginalName();
            $file->move($destinationPath, $fileName);
            $profilePath = 'user/profile-picture/' . $fileName;
        }

        $user = DB::transaction(function () use ($request, $role, $permissions, $profilePath, $existingStaff) {
            $created = User::create([
                'name' => trim((string) $request->name),
                'email' => strtolower(trim((string) $request->email)),
                'password' => Hash::make($request->password),
                'role' => $role,
                'permissions' => $permissions,
                'profile_photo' => $profilePath,
                'is_active' => true,
            ]);

            if ($existingStaff) {
                $staffUpdates = ['user_id' => $created->id];
                if (empty($existingStaff->email)) {
                    $staffUpdates['email'] = $created->email;
                }
                $existingStaff->update($staffUpdates);
            } elseif ($request->boolean('create_staff_profile')) {
                $this->provisionLinkedStaffForUser($created, (string) $request->input('staff_branch', ''));
            }

            return $created;
        });

        $message = $existingStaff
            ? "Portal login created and linked for {$existingStaff->name}."
            : 'Staff account added successfully.';

        if ($request->expectsJson() || $request->ajax()) {
            $user->load('staff');

            return response()->json([
                'success' => true,
                'message' => $message,
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $user->role,
                    'role_label' => $user->role === 'agent' ? 'Chat Agent' : $user->roleBadgeLabel(),
                    'is_active' => (bool) $user->is_active,
                    'staff_id' => $user->staff?->id,
                ],
            ], 201);
        }

        return redirect()->route('users.index')->with('success', $message);
    }

    /**
     * Validate the submitted module permission levels and normalize them to
     * all 14 modules defined in User::MODULES / config('modules').
     * Accepts both canonical ('none'|'view'|'edit') and verbose
     * ('no_access'|'view_only'|'full_edit') level tokens, as well as the
     * 'staff_management' <-> 'staff' module key alias.
     */
    private function validatedPermissions(Request $request, ?string $role = null, ?User $existingUser = null): array
    {
        $request->validate([
            'permissions' => 'nullable|array',
            'permissions.*' => 'in:none,view,edit,no_access,view_only,full_edit',
        ]);

        if (!$request->has('permissions')) {
            if ($existingUser) {
                return $existingUser->effectivePermissionsMap();
            }

            return User::defaultPermissionsForRole($role);
        }

        $submitted = (array) $request->input('permissions', []);
        $permissions = [];

        foreach (array_keys(User::MODULES) as $slug) {
            $rawValue = $submitted[$slug] ?? null;
            if ($rawValue === null && $slug === 'staff' && array_key_exists('staff_management', $submitted)) {
                $rawValue = $submitted['staff_management'];
            }

            $permissions[$slug] = User::normalizePermissionLevel($rawValue !== null ? (string) $rawValue : 'none');
        }

        return $permissions;
    }

    public function destroy(Request $request, User $user)
    {
        if ((int) $user->id === (int) Auth::id()) {
            $msg = 'You cannot delete your own account.';
            if ($request->expectsJson() || $request->ajax()) {
                return response()->json(['success' => false, 'message' => $msg], 422);
            }

            return redirect()->route('users.index')->with('error', $msg);
        }

        DB::transaction(function () use ($user) {
            Staff::where('user_id', $user->id)->update(['user_id' => null]);
            if (Schema::hasTable('sessions')) {
                DB::table('sessions')->where('user_id', $user->id)->delete();
            }
            $user->delete();
        });

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json(['success' => true, 'message' => 'Staff account deleted successfully.']);
        }

        return redirect()->route('users.index')->with('success', 'Staff member deleted successfully');
    }

    public function update(Request $request, User $user)
    {
        $passwordRules = $request->has('password_confirmation')
            ? ['nullable', 'string', 'min:8', 'max:128', 'confirmed']
            : ['nullable', 'string', 'min:8', 'max:128'];

        $request->validate([
            'name' => 'required|string|max:255',
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'role' => ['required', Rule::in(User::validRoleSlugs())],
            'profile_photo' => 'nullable|image|max:2048',
            'password' => $passwordRules,
            'is_active' => 'nullable|boolean',
            'staff_id' => 'nullable|integer|exists:staff,id',
        ]);

        // Validate 1-to-1 staff link target if submitted
        $shouldSyncStaffLink = $request->exists('staff_id');
        $targetStaffId = $request->filled('staff_id') ? (int) $request->input('staff_id') : null;
        if ($shouldSyncStaffLink && $targetStaffId !== null) {
            $targetStaff = Staff::find($targetStaffId);
            if ($targetStaff && $targetStaff->user_id !== null && (int) $targetStaff->user_id !== (int) $user->id) {
                return $this->accessValidationFailure(
                    $request,
                    'staff_id',
                    "{$targetStaff->name} is already linked to another login account."
                );
            }
        }

        $role = (string) $request->role;
        $permissions = $role === 'salon_staff'
            ? User::defaultPermissionsForRole('salon_staff')
            : $this->validatedPermissions($request, $role, $user);

        // Enforce self-protection if is_active was explicitly sent
        $desiredActive = $user->is_active;
        if ($request->exists('is_active')) {
            $requestedActive = $request->boolean('is_active');
            if (!$requestedActive && (int) $user->id === (int) Auth::id()) {
                return $this->accessValidationFailure($request, 'is_active', 'You cannot deactivate your own account.');
            }
            $desiredActive = $requestedActive;
        }

        $profilePath = $user->profile_photo;
        if ($request->hasFile('profile_photo')) {
            $destinationPath = public_path('user/profile-picture');
            if (!file_exists($destinationPath)) {
                mkdir($destinationPath, 0755, true);
            }
            $file = $request->file('profile_photo');
            $fileName = time() . '_' . $file->getClientOriginalName();
            $file->move($destinationPath, $fileName);
            $profilePath = 'user/profile-picture/' . $fileName;
        }

        DB::transaction(function () use ($request, $user, $role, $permissions, $desiredActive, $profilePath, $shouldSyncStaffLink, $targetStaffId) {
            $user->name = trim((string) $request->name);
            $user->email = strtolower(trim((string) $request->email));
            $user->role = $role;
            $user->permissions = $permissions;
            $user->is_active = $desiredActive;
            $user->profile_photo = $profilePath;

            if ($request->filled('password')) {
                $user->password = Hash::make($request->password);
            }

            $user->save();

            if (!$user->is_active && Schema::hasTable('sessions')) {
                DB::table('sessions')->where('user_id', $user->id)->delete();
            }

            if ($shouldSyncStaffLink) {
                Staff::where('user_id', $user->id)
                    ->when($targetStaffId !== null, fn ($q) => $q->where('id', '!=', $targetStaffId))
                    ->update(['user_id' => null]);

                if ($targetStaffId !== null) {
                    $staffToLink = Staff::find($targetStaffId);
                    if ($staffToLink) {
                        $updates = ['user_id' => $user->id];
                        if (empty($staffToLink->email)) {
                            $updates['email'] = $user->email;
                        }
                        $staffToLink->update($updates);
                    }
                }
            }
        });

        if ($request->expectsJson() || $request->ajax()) {
            $user->refresh()->load('staff');

            return response()->json([
                'success' => true,
                'message' => "Access settings updated for {$user->name}.",
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $user->role,
                    'role_label' => $user->role === 'agent' ? 'Chat Agent' : $user->roleBadgeLabel(),
                    'is_active' => (bool) $user->is_active,
                    'staff_id' => $user->staff?->id,
                ],
            ]);
        }

        return redirect()->route('users.index')->with('success', 'Staff member updated successfully');
    }

    // Toggle a staff member's login access on/off without deleting their account or history.
    public function toggleActive(Request $request, User $user)
    {
        if ((int) $user->id === (int) Auth::id()) {
            $msg = 'You cannot deactivate your own account.';
            if ($request->expectsJson() || $request->ajax()) {
                return response()->json(['success' => false, 'message' => $msg], 422);
            }

            return redirect()->route('users.index')->with('error', $msg);
        }

        $user->is_active = !$user->is_active;
        $user->save();

        if (!$user->is_active && Schema::hasTable('sessions')) {
            DB::table('sessions')->where('user_id', $user->id)->delete();
        }

        $message = $user->is_active ? 'Staff member reactivated.' : 'Staff member deactivated.';

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'is_active' => (bool) $user->is_active,
                'message' => $message,
            ]);
        }

        return redirect()->route('users.index')->with('success', $message);
    }

    /**
     * Admin-only quick password reset directly from Roles & Access or the unified Access modal.
     */
    public function resetPassword(Request $request, User $user)
    {
        $validated = $request->validate([
            'password' => ['required', 'string', 'min:8', 'max:128'],
        ]);

        $user->forceFill([
            'password' => Hash::make($validated['password']),
        ])->save();

        $message = "Password updated for {$user->name}.";

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json(['success' => true, 'message' => $message]);
        }

        return redirect()->route('users.index')->with('success', $message);
    }

    /**
     * Admin-only 1-to-1 link/unlink between an existing User login and a Staff HR profile.
     */
    public function linkStaff(Request $request, User $user)
    {
        $request->validate([
            'staff_id' => 'nullable|integer|exists:staff,id',
        ]);

        $targetStaffId = $request->filled('staff_id') ? (int) $request->input('staff_id') : null;

        if ($targetStaffId !== null) {
            $targetStaff = Staff::find($targetStaffId);
            if ($targetStaff && $targetStaff->user_id !== null && (int) $targetStaff->user_id !== (int) $user->id) {
                return $this->accessValidationFailure(
                    $request,
                    'staff_id',
                    "{$targetStaff->name} is already linked to another login account."
                );
            }
        }

        DB::transaction(function () use ($user, $targetStaffId) {
            Staff::where('user_id', $user->id)
                ->when($targetStaffId !== null, fn ($q) => $q->where('id', '!=', $targetStaffId))
                ->update(['user_id' => null]);

            if ($targetStaffId !== null) {
                $staff = Staff::find($targetStaffId);
                if ($staff) {
                    $updates = ['user_id' => $user->id];
                    if (empty($staff->email)) {
                        $updates['email'] = $user->email;
                    }
                    $staff->update($updates);
                }
            }
        });

        $linked = Staff::where('user_id', $user->id)->first();
        $message = $linked
            ? "Linked {$user->name} to HR profile {$linked->name}."
            : "Unlinked HR profile from {$user->name}.";

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => $message,
                'staff' => $linked ? [
                    'id' => $linked->id,
                    'name' => $linked->name,
                    'branch' => $linked->branch,
                    'branch_key' => $linked->branch_key,
                    'is_remote' => $linked->isRemote(),
                    'location_label' => $linked->location_label,
                    'bookable' => (bool) $linked->bookable,
                ] : null,
            ]);
        }

        return redirect()->route('users.index')->with('success', $message);
    }

    /**
     * Admin-only 1-click creation of a linked Staff HR profile for an existing unlinked User
     * (e.g., Zoya, Areeba, Sundus, Sana Kazi) so their /my-profile portal is immediately ready.
     */
    public function createStaffProfile(Request $request, User $user)
    {
        $existing = $user->staff()->first();
        if ($existing) {
            $msg = "{$user->name} is already linked to HR profile {$existing->name}.";
            if ($request->expectsJson() || $request->ajax()) {
                return response()->json(['success' => false, 'message' => $msg], 422);
            }

            return redirect()->route('users.index')->with('error', $msg);
        }

        $request->validate([
            'branch' => ['nullable', Rule::in(['old_airport', 'wakrah', 'both', 'remote'])],
        ]);

        $staff = $this->provisionLinkedStaffForUser($user, (string) $request->input('branch', ''));
        $message = "Created and linked Staff HR profile for {$user->name}.";

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => $message,
                'staff' => [
                    'id' => $staff->id,
                    'name' => $staff->name,
                    'branch' => $staff->branch,
                    'branch_key' => $staff->branch_key,
                    'is_remote' => $staff->isRemote(),
                    'location_label' => $staff->location_label,
                    'bookable' => (bool) $staff->bookable,
                    'preview_url' => route('portal.admin-preview', $staff),
                ],
            ], 201);
        }

        return redirect()->route('users.index')->with('success', $message);
    }

    private function provisionLinkedStaffForUser(User $user, string $requestedBranch = ''): Staff
    {
        $parts = preg_split('/\s+/', trim($user->name), 2);
        $firstName = $parts[0] ?? $user->name;
        $lastName = $parts[1] ?? null;

        $role = strtolower(trim((string) $user->role));
        $normalizedRequested = strtolower(trim($requestedBranch));
        $isRemoteRole = Staff::isRemoteRole($role);
        $isPhysicalSalonRole = in_array($role, ['salon_staff', 'salon_manager', 'manager', 'staff'], true);

        if ($isRemoteRole) {
            // All remote roles (agent, sales_coordinator, ops_lead, marketing_specialist, social_media_editor)
            // always default to Remote (branch = null, is_remote = true, bookable = false)
            $isRemote = true;
            $branch = null;
            $bookable = false;
        } elseif ($isPhysicalSalonRole) {
            // Salon Staff / Salon Managers require/default a physical salon branch
            $isRemote = false;
            $branch = in_array($normalizedRequested, Staff::PHYSICAL_BRANCHES, true)
                ? $normalizedRequested
                : 'old_airport';
            $bookable = in_array($role, ['salon_staff', 'staff'], true);
        } elseif ($normalizedRequested === 'remote') {
            $isRemote = true;
            $branch = null;
            $bookable = false;
        } elseif (in_array($normalizedRequested, Staff::PHYSICAL_BRANCHES, true)) {
            $isRemote = false;
            $branch = $normalizedRequested;
            $bookable = false;
        } else {
            // Non-salon roles (Admin, etc.) without a physical branch default to Remote
            $isRemote = true;
            $branch = null;
            $bookable = false;
        }

        return Staff::create([
            'name' => $user->name,
            'first_name' => $firstName,
            'last_name' => $lastName,
            'email' => $user->email,
            'branch' => $branch,
            'is_remote' => $isRemote,
            'bookable' => $bookable,
            'availability_status' => 'present',
            'employment_type' => 'full_time',
            'user_id' => $user->id,
        ]);
    }

    private function accessValidationFailure(Request $request, string $field, string $message)
    {
        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'success' => false,
                'message' => $message,
                'errors' => [$field => [$message]],
            ], 422);
        }

        return redirect()->back()->withErrors([$field => $message])->withInput();
    }

    public function profile_edit()
    {
        return view('users.profile');
    }

    public function profile_update(Request $request)
    {
        $user = Auth::user();
        $isAdmin = $user && $user->isAdmin();

        if (!$isAdmin && $request->has('name') && trim((string) $request->input('name')) !== $user->name) {
            abort(403, 'Only an administrator can change a staff account name.');
        }

        $request->validate([
            'name' => $isAdmin ? 'required|string|max:255' : 'sometimes|string|max:255',
            'email' => 'required|email|max:255|unique:users,email,' . $user->id,
            'password' => 'nullable|string|min:8|confirmed',
            'profile_photo' => 'nullable|image|max:2048', // max 2MB
        ]);

        if ($request->hasFile('profile_photo')) {
            $file = $request->file('profile_photo');
            $filename = 'profile_' . $user->id . '.' . $file->getClientOriginalExtension();
            $file->move(public_path('uploads/profiles'), $filename);
            $user->profile_photo = 'uploads/profiles/' . $filename;
        }

        if ($isAdmin) {
            $user->name = $request->name;
        }
        $user->email = $request->email;

        if ($request->filled('password')) {
            $user->password = Hash::make($request->password);
        }

        $user->save();

        return redirect()->back()->with('success', 'Profile updated successfully.');
    }
}
