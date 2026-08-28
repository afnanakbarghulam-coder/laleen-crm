<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Staff;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\StaffComplaint;
use App\Models\StaffDeduction;
use App\Models\StaffNotice;
use App\Models\StaffOvertimeEntry;
use App\Support\StaffPayrollCalculator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class StaffController extends Controller
{
    public function index(Request $request)
    {
        $activeTab = in_array($request->query('tab'), ['directory', 'payroll', 'complaints', 'notices'], true)
            ? $request->query('tab')
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
        $allStaff = Staff::orderBy('name')->get();

        // Plain-array projections for the tabs' JS (never pass a chained/argument
        // expression to Blade's @json() directly — see StaffPayrollCalculator's
        // sibling views for why; compute the array here instead).
        $allStaffOptions = $allStaff->map(fn ($s) => [
            'id' => $s->id,
            'name' => $s->name,
            'branch' => $s->branch,
        ])->values();

        // For the Complaints & Feedback "Service Received" dropdown.
        $serviceOptions = $services->map(fn ($s) => [
            'id' => $s->id,
            'name' => $s->name,
        ])->values();

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

        $complaints = StaffComplaint::with(['staffMembers', 'customer', 'service'])
            ->when($complaintsStaffFilter, fn ($q) => $q->whereHas('staffMembers', fn ($s) => $s->where('staff.id', $complaintsStaffFilter)))
            ->orderByDesc('complaint_date')->orderByDesc('id')
            ->paginate(15, ['*'], 'complaints_page')
            ->appends(array_filter(['complaints_staff' => $complaintsStaffFilter, 'tab' => 'complaints']));

        $deductions = StaffDeduction::with(['staff', 'complaint'])
            ->when($complaintsStaffFilter, fn ($q) => $q->where('staff_id', $complaintsStaffFilter))
            ->orderByDesc('deduction_date')->orderByDesc('id')
            ->paginate(15, ['*'], 'deductions_page')
            ->appends(array_filter(['complaints_staff' => $complaintsStaffFilter, 'tab' => 'complaints']));

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
        $noticesStaffFilter = $request->filled('notices_staff') ? (int) $request->notices_staff : null;

        $notices = StaffNotice::with('staff')
            ->when($noticesStaffFilter, fn ($q) => $q->where('staff_id', $noticesStaffFilter))
            ->orderByDesc('notice_date')->orderByDesc('id')
            ->paginate(15, ['*'], 'notices_page')
            ->appends(array_filter(['notices_staff' => $noticesStaffFilter, 'tab' => 'notices']));

        return view('staff.index', compact(
            'staff', 'services', 'categories', 'activeTab', 'allStaff', 'allStaffOptions', 'serviceOptions',
            'payrollBranch', 'payrollFrom', 'payrollTo', 'payrollRows', 'overtimeEntries',
            'complaints', 'deductions', 'complaintsStaffFilter', 'openComplaints', 'openComplaintOptions',
            'notices', 'noticesStaffFilter'
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

        $staff->services()->sync($request->input('service_ids', []));
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

        $staff->update($validated);
        $staff->services()->sync($request->input('service_ids', []));
        $this->syncAccess($request, $staff);

        return redirect()->back()->with('success', 'Staff updated.');
    }

    public function destroy(Staff $staff)
    {
        $staff->delete();
        return redirect()->back()->with('success', 'Staff removed.');
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

            'branch' => ['required', Rule::in(['old_airport', 'wakrah', 'both'])],
            'bookable' => 'nullable|boolean',

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

        $data['bookable'] = $request->boolean('bookable');

        unset($data['profile_picture']);

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
        if (!$request->boolean('has_access')) {
            if ($staff->user_id) {
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
            $user = $staff->user;
            $user->role = $request->access_role;
            if ($request->filled('access_email')) {
                $user->email = $request->access_email;
            }
            if ($request->filled('access_password')) {
                $user->password = Hash::make($request->access_password);
            }
            $user->save();
            return;
        }

        $user = User::create([
            'name' => $staff->name,
            'email' => $request->access_email,
            'password' => Hash::make($request->access_password ?: str()->random(12)),
            'role' => $request->access_role,
        ]);

        $staff->update(['user_id' => $user->id]);
    }
}
