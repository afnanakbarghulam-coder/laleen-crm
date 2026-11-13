<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Models\Customer;
use App\Models\KpiAdsConversionReport;
use App\Models\KpiAgentTargetReport;
use App\Models\KpiChatEvaluation;
use App\Models\KpiContentReport;
use App\Models\Lead;
use App\Models\Sale;
use App\Models\SocialContentPlan;
use App\Models\Staff;
use App\Models\User;
use App\Support\StaffSalesAnalytics;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;

class UserController extends Controller
{

    public function dashboard(Request $request)
    {
        // ---- Date range filter (defaults to start of current month -> today) ----
        $dashFrom = $request->filled('date_from')
            ? Carbon::parse($request->input('date_from'))->startOfDay()
            : now()->startOfMonth();
        $dashTo = $request->filled('date_to')
            ? Carbon::parse($request->input('date_to'))->endOfDay()
            : now()->endOfDay();

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
        $totalLeads = Lead::open()->count();
        $pendingFollowups = $leadsDueToday + $leadsOverdue;

        $availableStaff = Staff::active()->count();
        $presentStaffCount = Staff::active()->where('availability_status', 'present')->count();
        $totalClientsCount = Customer::count();

        $operationalPulse = [
            'today_appointments' => $todayAppointments,
            'today_completed' => $todayCompletedAppointments,
            'leads_due_today' => $leadsDueToday,
            'leads_overdue' => $leadsOverdue,
            'leads_unscheduled' => $leadsUnscheduled,
            'total_open_leads' => $totalLeads,
            'active_staff' => $availableStaff,
            'present_staff' => $presentStaffCount,
            'total_clients' => $totalClientsCount,
        ];

        $branchPnl = null;
        if ($user->canView('finance')) {
            $branchPnl = FinanceController::branchBreakdown($dashFrom, $dashTo);
            foreach ($branchPnl as &$row) {
                $row['margin_pct'] = $row['sales'] > 0 ? round($row['profit'] / $row['sales'] * 100, 1) : 0.0;
                $row['color'] = match (true) {
                    $row['margin_pct'] >= 20 => 'green',
                    $row['margin_pct'] >= 0 => 'amber',
                    default => 'red',
                };

                // Daily net-sales trend this month, for the card's sparkline.
                $trendQuery = Sale::whereBetween('created_at', [$dashFrom, $dashTo]);
                if ($row['key'] === 'general') {
                    $trendQuery->whereNull('branch');
                } else {
                    $trendQuery->where('branch', $row['key']);
                }

                $row['trend'] = $trendQuery
                    ->selectRaw('DATE(created_at) as d, SUM(CASE WHEN (services_total + products_total + packages_total - discount_amount) > 0 THEN (services_total + products_total + packages_total - discount_amount) ELSE 0 END) as total')
                    ->groupBy('d')
                    ->orderBy('d')
                    ->pluck('total')
                    ->map(fn ($v) => (float) $v)
                    ->values();
            }
            unset($row);
        }

        $adsTotals = null;
        $adsColor = null;
        $agentShifts = null;
        $staffSalesComparison = null;
        $chatQuality = null;
        $contentMetrics = null;
        $contentColor = null;

        if ($user->canView('kpis')) {
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
            'operationalPulse',
            'todayAppointments',
            'todayCompletedAppointments',
            'totalLeads',
            'pendingFollowups',
            'availableStaff',
            'totalClientsCount',
            'branchPnl',
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
    private function resolveDashboardContentMetrics(\Illuminate\Support\Carbon|Carbon $from, \Illuminate\Support\Carbon|Carbon $to): array
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
        $query = User::query();

        if ($request->filled('role')) {
            $query->where('role', $request->role);
        }

        if ($request->filled('search')) {
            $query->where(function ($q) use ($request) {
                $q->where('name', 'like', '%' . $request->search . '%')
                    ->orWhere('email', 'like', '%' . $request->search . '%');
            });
        }

        $users = $query->orderBy('created_at', 'desc')->get();

        return view('users.index', compact('users'));
    }

    // Store new user
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')],
            'password' => 'required|string|min:8|confirmed',
            'role' => ['required', Rule::in(User::validRoleSlugs())],
            'profile_photo' => 'nullable|image|max:2048',
        ]);

        $permissions = $this->validatedPermissions($request, (string) $request->role);

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

        User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'role' => $request->role,
            'permissions' => $permissions,
            'profile_photo' => $profilePath,
        ]);

        return redirect()->route('users.index')->with('success', 'Staff member added successfully');
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

    public function destroy(User $user)
    {
        $user->delete();
        return redirect()->route('users.index')->with('success', 'Staff member deleted successfully');
    }

    public function update(Request $request, User $user)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'role' => ['required', Rule::in(User::validRoleSlugs())],
            'profile_photo' => 'nullable|image|max:2048',
            'password' => 'nullable|string|min:8|confirmed',
        ]);

        $permissions = $this->validatedPermissions($request, (string) $request->role, $user);

        $user->name = $request->name;
        $user->email = $request->email;
        $user->role = $request->role;
        $user->permissions = $permissions;

        if ($request->hasFile('profile_photo')) {
            $destinationPath = public_path('user/profile-picture');
            if (!file_exists($destinationPath)) {
                mkdir($destinationPath, 0755, true);
            }
            $file = $request->file('profile_photo');
            $fileName = time() . '_' . $file->getClientOriginalName();
            $file->move($destinationPath, $fileName);
            $user->profile_photo = 'user/profile-picture/' . $fileName;
        }

        if ($request->password) {
            $user->password = Hash::make($request->password);
        }

        $user->save();

        return redirect()->route('users.index')->with('success', 'Staff member updated successfully');
    }

    // Toggle a staff member's login access on/off without deleting their account or history.
    public function toggleActive(Request $request, User $user)
    {
        if ($user->id === Auth::id()) {
            return redirect()->route('users.index')->with('error', 'You cannot deactivate your own account.');
        }

        $user->is_active = !$user->is_active;
        $user->save();

        if (!$user->is_active && Schema::hasTable('sessions')) {
            DB::table('sessions')->where('user_id', $user->id)->delete();
        }

        return redirect()->route('users.index')->with('success', $user->is_active ? 'Staff member reactivated.' : 'Staff member deactivated.');
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
