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
use App\Models\StaffComplaint;
use App\Models\StaffDeduction;
use App\Models\StaffNotice;
use App\Models\StaffOvertimeEntry;
use App\Support\StaffPayrollCalculator;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class StaffController extends Controller
{
    public function index(Request $request)
    {
        $rawTab = $request->query('tab');
        if ($rawTab === 'notices') {
            $rawTab = 'complaints';
        }
        $activeTab = in_array($rawTab, ['directory', 'payroll', 'complaints', 'workspace'], true)
            ? $rawTab
            : 'directory';

        // ---- Staff Directory ----
        $today = now()->toDateString();
        $query = Staff::with(['user', 'timeOffs' => fn ($q) => $q->where('start_date', '<=', $today)->where('end_date', '>=', $today)]);

        if ($request->filled('branch')) {
            $query->where('branch', $request->branch);
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

        // Full roster, used by the pickers in the Payroll/Complaints/Notices tabs.
        $allStaff = Staff::with('services:id')->orderBy('name')->get();

        // Plain-array projections for the tabs' JS (never pass a chained/argument
        // expression to Blade's @json() directly — see StaffPayrollCalculator's
        // sibling views for why; compute the array here instead).
        $allStaffOptions = $allStaff->map(fn ($s) => [
            'id' => $s->id,
            'name' => $s->name,
            'branch' => $s->branch,
            'base_salary' => (float) ($s->base_salary ?? 0),
            'hourly_wage' => (float) ($s->hourly_wage ?? 0),
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
            'branch' => $s->branch,
            'bookable' => (bool) $s->bookable,
            'service_ids' => $s->services->pluck('id'),
        ])->values();
        $selectedWorkspaceStaffId = $request->filled('staff') && $allStaff->contains('id', (int) $request->staff)
            ? (int) $request->staff
            : optional($allStaff->first())->id;

        // ---- Payroll & Overtime ----
        $payrollBranch = $request->filled('payroll_branch') && in_array($request->payroll_branch, ['old_airport', 'wakrah'], true)
            ? $request->payroll_branch
            : null;
        $payrollFrom = $request->date('payroll_from') ?? now()->startOfMonth();
        $payrollTo = $request->date('payroll_to') ?? now()->endOfMonth();

        $payrollCalculator = new StaffPayrollCalculator($payrollFrom, $payrollTo);
        $payrollStaffQuery = Staff::active()->orderBy('name');
        if ($payrollBranch) {
            $payrollStaffQuery->where(fn ($q) => $q->where('branch', $payrollBranch)->orWhere('branch', 'both'));
        }
        $payrollRows = $payrollCalculator->payrollFor($payrollStaffQuery->get());

        $overtimeEntries = StaffOvertimeEntry::with('staff')
            ->whereBetween('entry_date', [$payrollFrom->copy()->startOfDay(), $payrollTo->copy()->endOfDay()])
            ->orderByDesc('entry_date')->orderByDesc('id')
            ->paginate(15, ['*'], 'overtime_page')
            ->appends(array_filter([
                'payroll_branch' => $payrollBranch,
                'payroll_from' => $payrollFrom->format('Y-m-d'),
                'payroll_to' => $payrollTo->format('Y-m-d'),
                'tab' => 'payroll',
            ]));

        // ---- Complaints & Deductions ----
        $complaintsStaffFilter = $request->filled('complaints_staff') ? (int) $request->complaints_staff : null;
        $complaintsSearch = $request->filled('complaints_search') ? trim((string) $request->input('complaints_search')) : null;
        $complaintsDate = $request->filled('complaints_date') ? $request->input('complaints_date') : null;
        $complaintsFrom = $request->filled('complaints_from') ? $request->input('complaints_from') : null;
        $complaintsTo = $request->filled('complaints_to') ? $request->input('complaints_to') : null;

        $deductionsDate = $request->filled('deductions_date') ? $request->input('deductions_date') : null;
        $deductionsFrom = $request->filled('deductions_from') ? $request->input('deductions_from') : null;
        $deductionsTo = $request->filled('deductions_to') ? $request->input('deductions_to') : null;

        $hrAppendParams = array_filter([
            'complaints_staff' => $complaintsStaffFilter,
            'complaints_search' => $complaintsSearch,
            'complaints_date' => $complaintsDate,
            'complaints_from' => $complaintsFrom,
            'complaints_to' => $complaintsTo,
            'deductions_date' => $deductionsDate,
            'deductions_from' => $deductionsFrom,
            'deductions_to' => $deductionsTo,
            'tab' => 'complaints',
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
            ->appends($hrAppendParams);

        $deductions = StaffDeduction::with(['staff', 'complaint'])
            ->when($complaintsStaffFilter, fn ($q) => $q->where('staff_id', $complaintsStaffFilter))
            ->when($deductionsDate, fn ($q) => $q->whereDate('deduction_date', $deductionsDate))
            ->when($deductionsFrom, fn ($q) => $q->whereDate('deduction_date', '>=', $deductionsFrom))
            ->when($deductionsTo, fn ($q) => $q->whereDate('deduction_date', '<=', $deductionsTo))
            ->orderByDesc('deduction_date')->orderByDesc('id')
            ->paginate(15, ['*'], 'deductions_page')
            ->appends($hrAppendParams);

        // For the deduction quick-add's "link to penalty" dropdown.
        $openComplaints = StaffComplaint::with('staffMembers')->orderByDesc('complaint_date')->get();
        $openComplaintOptions = $openComplaints->map(fn ($c) => [
            'id' => $c->id,
            'reference_number' => $c->reference_number,
            'staff_name' => $c->staffMembers->pluck('name')->implode(', ') ?: '—',
            'category' => $c->category,
            'complaint_date_label' => $c->complaint_date->format('d M Y'),
        ])->values();

        // ---- Notices ----
        $noticesStaffFilter = $request->filled('notices_staff')
            ? (int) $request->notices_staff
            : $complaintsStaffFilter;

        $notices = StaffNotice::with('staff')
            ->when($noticesStaffFilter, fn ($q) => $q->where('staff_id', $noticesStaffFilter))
            ->orderByDesc('notice_date')->orderByDesc('id')
            ->paginate(15, ['*'], 'notices_page')
            ->appends(array_merge($hrAppendParams, array_filter(['notices_staff' => $request->input('notices_staff')])));

        $pendingOwnerActionCount = 0;
        try {
            $usersById = User::where('is_active', true)->get(['id', 'name', 'role'])->keyBy('id');
            $pendingOwnerActionCount = OpsActionItem::forWeek(now()->startOfWeek(Carbon::MONDAY))
                ->where('is_completed', false)
                ->get()
                ->filter(fn (OpsActionItem $item) => $item->involvesBucket('owner', $usersById))
                ->count();
        } catch (\Throwable) {
            $pendingOwnerActionCount = 0;
        }
        $pendingOwnerCount = $pendingOwnerActionCount;

        return view('staff.index', compact(
            'staff', 'services', 'categories', 'activeTab', 'allStaff', 'allStaffOptions', 'serviceOptions',
            'staffWorkspaceOptions', 'selectedWorkspaceStaffId',
            'payrollBranch', 'payrollFrom', 'payrollTo', 'payrollRows', 'overtimeEntries',
            'complaints', 'deductions', 'complaintsStaffFilter', 'complaintsSearch',
            'complaintsDate', 'complaintsFrom', 'complaintsTo',
            'deductionsDate', 'deductionsFrom', 'deductionsTo',
            'openComplaints', 'openComplaintOptions',
            'notices', 'noticesStaffFilter',
            'pendingOwnerActionCount', 'pendingOwnerCount'
        ));
    }

    public function store(Request $request)
    {
        $validated = $this->validated($request);
        $profilePath = $this->handlePhoto($request);

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
        $validated = $this->validated($request);

        if ($request->hasFile('profile_picture')) {
            if ($staff->profile_picture && file_exists(public_path($staff->profile_picture))) {
                unlink(public_path($staff->profile_picture));
            }
            $validated['profile_picture'] = $this->handlePhoto($request);
        }

        $validated['name'] = $this->fullName($validated);

        // Branch, bookability, and services are managed on the Staff
        // Workspace tab now, not this form - leave them untouched here.
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
            'branch' => ['required', Rule::in(['old_airport', 'wakrah', 'both'])],
            'bookable' => 'nullable|boolean',
        ]);
        $validated['bookable'] = $request->boolean('bookable');

        $staff->update($validated);
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

    public function destroy(Staff $staff)
    {
        DB::transaction(function () use ($staff) {
            if ($staff->user_id) {
                User::where('id', $staff->user_id)->update(['is_active' => false]);
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
        $from = $request->date('payroll_from') ?? $request->date('from') ?? now()->startOfMonth();
        $to = $request->date('payroll_to') ?? $request->date('to') ?? now()->endOfMonth();
        if ($from->gt($to)) {
            [$from, $to] = [$to->copy()->startOfDay(), $from->copy()->endOfDay()];
        }

        $rawBranch = $request->input('payroll_branch') ?? $request->input('branch');
        $payrollBranch = in_array($rawBranch, ['old_airport', 'wakrah'], true) ? $rawBranch : null;

        $calculator = new StaffPayrollCalculator($from, $to);
        $staffQuery = Staff::active()->orderBy('name');
        if ($payrollBranch) {
            $staffQuery->where(fn ($q) => $q->where('branch', $payrollBranch)->orWhere('branch', 'both'));
        }

        $rows = $calculator->payrollFor($staffQuery->get());
        $totalNetPayroll = round((float) $rows->sum('net_salary'), 2);

        $tag = sprintf('[Payroll: %s to %s]', $from->format('Y-m-d'), $to->format('Y-m-d'));
        $branchLabel = $payrollBranch === 'old_airport'
            ? 'Old Airport'
            : ($payrollBranch === 'wakrah' ? 'Al Wakrah' : 'All Branches');
        $description = sprintf('%s Staff Payroll (%d staff) %s', $branchLabel, $rows->count(), $tag);

        $expense = DB::transaction(function () use ($tag, $payrollBranch, $totalNetPayroll, $to, $description) {
            $existingQuery = Expense::where('category', 'Salaries & Wages')
                ->where('description', 'like', '%' . $tag . '%');

            if ($payrollBranch) {
                $existingQuery->where('branch', $payrollBranch);
            } else {
                $existingQuery->whereNull('branch');
            }

            $existing = $existingQuery->lockForUpdate()->first();

            if ($existing) {
                $existing->update([
                    'amount' => $totalNetPayroll,
                    'expense_date' => $to->toDateString(),
                    'description' => $description,
                ]);
                return $existing;
            }

            return Expense::create([
                'branch' => $payrollBranch,
                'category' => 'Salaries & Wages',
                'amount' => $totalNetPayroll,
                'expense_date' => $to->toDateString(),
                'description' => $description,
                'created_by' => auth()->id(),
            ]);
        });

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => "Posted QAR " . number_format($totalNetPayroll, 2) . " payroll to Finance Expenses.",
                'expense_id' => $expense->id,
                'amount' => (float) $expense->amount,
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
            'employment_type' => 'nullable|string|max:30',
            'staff_member_id' => 'nullable|string|max:100',
            'internal_notes' => 'nullable|string|max:1000',
            'hourly_wage' => 'nullable|numeric|min:0',
            'commission_rate' => 'nullable|numeric|min:0|max:100',
            'base_salary' => 'nullable|numeric|min:0',

            'profile_picture' => 'nullable|image|max:2048',
        ]);

        unset($data['profile_picture']);

        foreach (['hourly_wage', 'commission_rate', 'base_salary'] as $wageField) {
            if (!$request->has($wageField)) {
                unset($data[$wageField]);
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
     * Grant, update, or revoke this team member's CRM login + role.
     * Revoking unlinks the account without deleting it, so access can be
     * restored later without losing the user's history.
     */
    private function syncAccess(Request $request, Staff $staff): void
    {
        $hasAccess = $request->boolean('has_access') || $request->boolean('login_access');

        if (!$hasAccess) {
            if ($staff->user_id) {
                User::where('id', $staff->user_id)->update(['is_active' => false]);
                $staff->update(['user_id' => null]);
            }
            return;
        }

        $existingUserId = $staff->user_id;

        $request->validate([
            'access_role' => ['required', Rule::in(['admin', 'manager', 'agent', 'staff', 'user'])],
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
            'is_active' => true,
        ]);

        $staff->update(['user_id' => $user->id]);
    }
}
