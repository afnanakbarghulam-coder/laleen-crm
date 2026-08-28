<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Sale;
use App\Models\KpiAdsConversionReport;
use App\Models\KpiAgentTargetReport;
use App\Models\KpiChatEvaluation;
use App\Models\KpiContentReport;
use App\Support\StaffSalesAnalytics;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class UserController extends Controller
{

    public function dashboard()
    {
        // ---- This month's branch P&L + KPI highlights ----
        $dashFrom = now()->startOfMonth();
        $dashTo = now()->endOfDay();
        $user = Auth::user();

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

                // Daily gross-sales trend this month, for the card's sparkline.
                $row['trend'] = Sale::where('branch', $row['key'])
                    ->whereBetween('created_at', [$dashFrom, $dashTo])
                    ->selectRaw('DATE(created_at) as d, SUM(services_total) + SUM(products_total) as total')
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

            $chatEvals = KpiChatEvaluation::whereBetween('eval_date', [$dashFrom, $dashTo])->get();
            $chatAvg = $chatEvals->count() ? round($chatEvals->avg(fn ($r) => $r->percentage()), 1) : null;
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

            $contentMetrics = KpiContentReport::overallMetrics($dashFrom, $dashTo);
            $contentColor = match ($contentMetrics['grade']) {
                'Excellent', 'Pass' => 'green',
                'Warning' => 'amber',
                default => 'red',
            };
        }

        return view('dashboard', compact(
            'dashFrom',
            'dashTo',
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
            'role' => 'required|in:admin,manager,agent,staff,user',
            'profile_photo' => 'nullable|image|max:2048',
        ]);

        $permissions = $this->validatedPermissions($request);

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
     * exactly the modules defined in config('modules'), defaulting anything
     * missing or invalid to 'none' (secure by default).
     */
    private function validatedPermissions(Request $request): array
    {
        $request->validate([
            'permissions' => 'nullable|array',
            'permissions.*' => 'in:none,view,edit',
        ]);

        $submitted = $request->input('permissions', []);
        $permissions = [];

        foreach (array_keys(config('modules')) as $slug) {
            $permissions[$slug] = in_array($submitted[$slug] ?? 'none', ['none', 'view', 'edit'], true)
                ? $submitted[$slug] ?? 'none'
                : 'none';
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
            'role' => 'required|in:admin,manager,agent,staff,user',
            'profile_photo' => 'nullable|image|max:2048',
            'password' => 'nullable|string|min:8|confirmed',
        ]);

        $permissions = $this->validatedPermissions($request);

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

        return redirect()->route('users.index')->with('success', $user->is_active ? 'Staff member reactivated.' : 'Staff member deactivated.');
    }

    public function profile_edit()
    {
        return view('users.profile');
    }

    public function profile_update(Request $request)
    {
        $user = Auth::user();

        $request->validate([
            'name' => 'required|string|max:255',
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

        $user->name = $request->name;
        $user->email = $request->email;

        if ($request->filled('password')) {
            $user->password = Hash::make($request->password);
        }

        $user->save();

        return redirect()->back()->with('success', 'Profile updated successfully.');
    }
}
