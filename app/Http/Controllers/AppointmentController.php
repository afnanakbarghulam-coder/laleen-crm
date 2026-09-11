<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Lead;
use App\Models\Staff;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\SalePayment;
use App\Models\Product;
use App\Models\Customer;
use App\Models\Service;
use App\Models\AppointmentService;
use App\Models\AppointmentPriceOverride;
use App\Models\AppointmentUpsell;
use App\Models\Appointment;
use App\Models\Combo;
use App\Models\ClientPackage;
use App\Models\ClientPackageService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class AppointmentController extends Controller
{
    const BRANCH_LABELS = [
        'old_airport' => 'Old Airport',
        'wakrah' => 'Al Wakrah',
    ];

    const PERIOD_LABELS = [
        'today' => 'Today',
        'tomorrow' => 'Tomorrow',
        'yesterday' => 'Yesterday',
        'this_week' => 'This Week',
        'this_month' => 'This Month',
        'last_month' => 'Last Month',
        'all_time' => 'All Time',
        'custom' => 'Custom Range',
    ];

    /**
     * Resolve a named period into a [from, to] Carbon range. Picking a date
     * range is a dropdown choice, not free-hand typing.
     */
    private function resolvePeriod(string $period): array
    {
        $now = now();

        return match ($period) {
            'today' => [$now->copy()->startOfDay(), $now->copy()->endOfDay()],
            'tomorrow' => [$now->copy()->addDay()->startOfDay(), $now->copy()->addDay()->endOfDay()],
            'yesterday' => [$now->copy()->subDay()->startOfDay(), $now->copy()->subDay()->endOfDay()],
            'this_week' => [$now->copy()->startOfWeek(Carbon::SUNDAY), $now->copy()->endOfDay()],
            'last_month' => [$now->copy()->subMonthNoOverflow()->startOfMonth(), $now->copy()->subMonthNoOverflow()->endOfMonth()],
            'all_time' => [
                ($earliest = Appointment::min('appointment_datetime'))
                    ? Carbon::parse($earliest)->startOfDay()
                    : $now->copy()->startOfMonth(),
                $now->copy()->endOfDay(),
            ],
            default => [$now->copy()->startOfMonth(), $now->copy()->endOfDay()],
        };
    }

    /**
     * Bookings Analytics: read-only reporting over appointments, filterable
     * by period and branch only. Creating/rescheduling/checking out bookings
     * all happen on the Enhanced Calendar page.
     */
    public function index(Request $request)
    {
        $period = $request->filled('period') && array_key_exists($request->period, self::PERIOD_LABELS)
            ? $request->period
            : 'this_month';

        if ($period === 'custom') {
            $from = $request->filled('from') ? Carbon::parse($request->from)->startOfDay() : now()->startOfMonth();
            $to = $request->filled('to') ? Carbon::parse($request->to)->endOfDay() : now()->endOfDay();
            if ($from->gt($to)) {
                [$from, $to] = [$to->copy()->startOfDay(), $from->copy()->endOfDay()];
            }
        } else {
            [$from, $to] = $this->resolvePeriod($period);
        }

        $branch = $request->filled('branch') && array_key_exists($request->branch, self::BRANCH_LABELS)
            ? $request->branch
            : null;

        $query = Appointment::query()->with(['agent', 'staff'])
            ->whereBetween('appointment_datetime', [$from, $to]);

        if ($branch) {
            $query->where('branch', $branch);
        }

        $search = trim((string) $request->get('search', ''));
        if ($search !== '') {
            // Phone is stored digits-only, so a search like "+974 5551 2345"
            // or "(555) 1234" still matches by comparing digits-only too.
            $searchDigits = preg_replace('/\D/', '', $search);
            $query->where(function ($q) use ($search, $searchDigits) {
                $q->where('customer_name', 'like', '%' . $search . '%');
                if ($searchDigits !== '') {
                    $q->orWhere('phone', 'like', '%' . $searchDigits . '%');
                }
            });
        }

        // Aggregates are computed over the full filtered set, not just the current page.
        $all = (clone $query)->get();

        $totalBookings = $all->count();
        $totalRevenue = $all->sum('price');
        $avgBookingValue = $totalBookings ? $totalRevenue / $totalBookings : 0;
        $uniqueCustomers = $all->pluck('phone')->filter()->unique()->count();

        $statusCounts = $all->groupBy('status')->map->count();
        $statusLabels = $statusCounts->keys()->map(fn($s) => ucwords(str_replace('_', ' ', $s)))->values();

        $branchStats = collect(self::BRANCH_LABELS)->map(function ($label, $key) use ($all) {
            $group = $all->where('branch', $key);
            return [
                'label' => $label,
                'count' => $group->count(),
                'revenue' => $group->sum('price'),
            ];
        })->values();

        // Falls back to weekly buckets once the range is too wide for a legible daily x-axis.
        $weekly = $from->diffInDays($to) > 62;
        $bucketKey = fn($date) => $weekly ? $date->format('o-W') : $date->format('Y-m-d');
        $bucketLabel = fn($date) => $weekly ? 'Wk ' . $date->format('W M') : $date->format('d M');

        $grouped = $all->groupBy(fn($a) => $bucketKey($a->appointment_datetime));
        $dailyTrend = collect();
        $cursor = $from->copy()->startOfDay();
        $end = $to->copy()->startOfDay();
        while ($cursor->lte($end)) {
            $key = $bucketKey($cursor);
            if (!$dailyTrend->has($bucketLabel($cursor))) {
                $dailyTrend[$bucketLabel($cursor)] = $grouped->get($key, collect())->count();
            }
            $cursor->addDay();
        }

        $appointments = $query->orderBy('appointment_datetime', 'desc')->paginate(25)->withQueryString();

        $services = Service::orderBy('name')->get(['id', 'name', 'price', 'duration']);

        return view('appointments.index', compact(
            'appointments',
            'period',
            'branch',
            'search',
            'services',
            'from',
            'to',
            'totalBookings',
            'totalRevenue',
            'avgBookingValue',
            'uniqueCustomers',
            'statusCounts',
            'statusLabels',
            'branchStats',
            'dailyTrend'
        ));
    }

    public function store(Request $request)
    {
        $rules = [
            'customer_name' => 'required|string|max:255',
            'phone' => ['required', 'string', 'max:20', 'regex:/^\+?[1-9]\d{6,14}$/'],
            'appointment_datetime' => 'required|date',
            // "Decide in Salon" bookings defer service selection until the
            // client arrives, and a combo package can stand on its own too,
            // so service_name is only mandatory when neither is set.
            'service_name' => 'required_without_all:decide_in_salon,packages|array',
            'service_name.*' => 'string|max:255',
            'service_price' => 'nullable|array',
            'service_price.*' => 'nullable|numeric|min:0',
            'service_discount_reason' => 'nullable|array',
            'service_discount_reason.*' => 'nullable|string|max:255',
            'branch' => 'required|in:old_airport,wakrah,home_service',
            'price' => 'nullable|numeric',
            'booking_agent_id' => 'nullable|exists:users,id',
            'staff_id' => 'required|exists:staff,id',
            'decide_in_salon' => 'nullable|boolean',
            // A combo package picked at booking time - sold now, paid for
            // whenever this appointment is checked out. Immediate services
            // are the only thing that has to be decided now; anything the
            // package includes but isn't marked "Do today" is automatically
            // banked as a pending balance, not locked to a specific service.
            'packages' => 'nullable|array',
            'packages.*.combo_id' => 'required_with:packages|integer|exists:combos,id',
            'packages.*.immediate_service_ids' => 'nullable|array',
            'packages.*.immediate_service_ids.*' => 'integer|exists:services,id',
            // Optional per-service staff override for combo services done
            // today - keyed by service id, validated individually below.
            'packages.*.service_staff' => 'nullable|array',
            // Previously-purchased pending balance being redeemed at this
            // new booking, before the client has even arrived. Each entry is
            // a "{client_package_id}:{service_id}" token naming which
            // package it draws from and which of that package's still-unused
            // pool services is being claimed - parsed and fully re-validated
            // in buildPackageRedemptionPlans().
            'redeem_service_ids' => 'nullable|array',
            'redeem_service_ids.*' => 'string',
        ];

        $messages = [
            'customer_name.required' => 'Please select an existing client or add a new client.',
            'phone.required' => 'Please select an existing client or add a new client.',
            'appointment_datetime.required' => 'Appointment date and time is required.',
            'service_name.required_without_all' => 'At least one service must be selected.',
            'branch.required' => 'Please select a branch.',
            'branch.in' => 'Selected branch is invalid.',
            'staff_id.required' => 'Please select a staff member.',
            'staff_id.exists' => 'Selected staff does not exist.',
            'booking_agent_id.exists' => 'Selected booking agent does not exist.',
            'service_name.*.string' => 'Each service must be a valid string.',
            'service_name.*.max' => 'Each service name cannot exceed 255 characters.',
            'phone.regex' => 'Enter a valid phone number with country code, e.g. +974XXXXXXXX.'
        ];

        $request->validate($rules, $messages);

        $appointmentDate = Carbon::parse($request->appointment_datetime)->format('Y-m-d');
        $appointmentTime = Carbon::parse($request->appointment_datetime);

        // Get branch working hours
        $workingHours = $this->branchWorkingHours($request->branch, $appointmentTime);
        $startBranch = Carbon::createFromFormat('H:i', $workingHours['start'])
            ->setDate($appointmentTime->year, $appointmentTime->month, $appointmentTime->day);

        $endBranch = Carbon::createFromFormat('H:i', $workingHours['end'])
            ->setDate($appointmentTime->year, $appointmentTime->month, $appointmentTime->day);

        // Check if appointment is outside working hours
        if ($appointmentTime->lt($startBranch) || $appointmentTime->gt($endBranch)) {
            return redirect()->back()
                ->with('error', 'Appointment must be within branch working hours: '
                    . $startBranch->format('H:i') . ' - ' . $endBranch->format('H:i'))
                ->withInput();
        }


        $staffId = $request->staff_id;

        // dd($request->all());
        // Check staff unavailability
        $staff = \App\Models\Staff::find($staffId);
        if ($staff && in_array($staff->availability_status, ['on-leave', 'sick'])) {
            if ($staff->off_from && $staff->off_to) {
                $offFrom = Carbon::parse($staff->off_from)->startOfDay();
                $offTo = Carbon::parse($staff->off_to)->endOfDay();
                $appointment = Carbon::parse($request->appointment_datetime);

                if ($appointment->between($offFrom, $offTo)) {
                    // return redirect()->back()
                    //     ->with('error', 'Selected staff is on leave or sick for this date.')
                    //     ->withInput();

                    return redirect()->route('appointments.calendar', [
                        'date' => Carbon::parse($request->appointment_datetime)->toDateString(),
                        'staff_id' => $staffId
                    ])->with('error', 'Selected staff is on leave or sick for this date.');
                }
            } else {
                // fallback if off_from/off_to is missing
                // return redirect()->back()
                //     ->with('error', 'Selected staff is on leave or sick and unavailable.')
                //     ->withInput();
                return redirect()->route('appointments.calendar', [
                    'staff_id' => $staffId
                ])->with('error', 'Selected staff is on leave or sick and unavailable.');
            }
        }

        $phone = preg_replace('/\D/', '', $request->phone);

        // Every booking now requires a real client (selected or newly added) —
        // there's no walk-in fallback, so a customer record always exists.
        // Resolved early - both new package purchases and redeeming an
        // existing pending service need to know who "this client" is.
        $customer = $this->findOrCreateCustomer($phone, $request->customer_name);

        // The expiration engine: reconcile this client's packages against
        // real time before trusting anything about what's still redeemable.
        ClientPackage::expireDue($customer->id);

        [$packagePlans, $packagePlanError] = $this->buildPackagePurchasePlans($request->input('packages', []));
        if ($packagePlanError) {
            return redirect()->back()->withInput()->with('error', $packagePlanError);
        }

        [$redemptionPlans, $redemptionPlanError] = $this->buildPackageRedemptionPlans($request->input('redeem_service_ids', []), $customer);
        if ($redemptionPlanError) {
            return redirect()->back()->withInput()->with('error', $redemptionPlanError);
        }

        $startTime = Carbon::parse($request->appointment_datetime);
        $serviceNames = $request->service_name ?? [];

        // Every service actually being performed today, each tagged with
        // whichever staff member will do it - manually-picked services and
        // redeemed pending ones stay on the appointment's one "Team Member",
        // but a combo service can be assigned to its own staff independently
        // (validated - skill included - when the package plan was built).
        $scheduleEntries = [];
        foreach ($serviceNames as $name) {
            $scheduleEntries[] = ['name' => $name, 'staff_id' => (int) $staffId];
        }
        foreach ($redemptionPlans as $plan) {
            $scheduleEntries[] = ['name' => $plan['service']->name, 'staff_id' => (int) $staffId];
        }
        foreach ($packagePlans as $plan) {
            foreach ($plan['immediate_ids'] as $sid) {
                $scheduleEntries[] = [
                    'name' => $plan['pool']->get($sid)->name,
                    'staff_id' => (int) ($plan['service_staff'][$sid] ?? $staffId),
                ];
            }
        }

        if (empty($scheduleEntries)) {
            // Nothing performed today (a plain "Decide in Salon", or a combo
            // bought with every service left pending) - still hold a nominal
            // slot on the main staff's calendar, matching the long-standing
            // "Decide in Salon" behavior.
            if ($this->staffHasTimeConflict($staffId, $startTime, 30)) {
                return redirect()->route('appointments.calendar', [
                    'date' => $startTime->toDateString(),
                    'staff_id' => $staffId
                ])->with('error', 'Staff is already booked during this time.');
            }
        } else {
            $namesByStaff = [];
            foreach ($scheduleEntries as $entry) {
                $namesByStaff[$entry['staff_id']][] = $entry['name'];
            }

            foreach ($namesByStaff as $entryStaffId => $names) {
                $unskilled = $this->unskilledServices((int) $entryStaffId, $names);
                if (!empty($unskilled)) {
                    $staffLabel = optional(\App\Models\Staff::find($entryStaffId))->name ?? 'Selected staff';
                    return redirect()->route('appointments.calendar', [
                        'date' => $startTime->toDateString(),
                        'staff_id' => $staffId
                    ])->with('error', "{$staffLabel} is not skilled to do this service: " . implode(', ', $unskilled) . '.');
                }
            }

            // Services stack back-to-back for this one visit regardless of
            // who performs them, so each entry's staff is conflict-checked
            // against its own slice of that shared timeline - never against
            // the whole combined span, since two different staff members
            // working in parallel wouldn't actually conflict with each other.
            $entryCatalog = Service::whereIn('name', array_column($scheduleEntries, 'name'))->get()->keyBy('name');
            $cursor = $startTime->copy();

            foreach ($scheduleEntries as $entry) {
                $entryDuration = optional($entryCatalog->get($entry['name']))->duration ?? 30;

                if ($this->staffHasTimeConflict($entry['staff_id'], $cursor, $entryDuration)) {
                    $staffLabel = optional(\App\Models\Staff::find($entry['staff_id']))->name ?? 'Selected staff';
                    return redirect()->route('appointments.calendar', [
                        'date' => $startTime->toDateString(),
                        'staff_id' => $staffId
                    ])->with('error', "{$staffLabel} is already booked during this time.");
                }

                $cursor = $cursor->copy()->addMinutes($entryDuration);
            }
        }

        // Empty selection means the client hasn't chosen services yet - the
        // front desk saves the slot now and staff pick the real services
        // (via "Add service" on the appointment) once the client arrives.
        $allServiceNames = array_column($scheduleEntries, 'name');
        $services = !empty($allServiceNames) ? implode(', ', $allServiceNames) : 'Decide in Salon';

        $previousRevenue = Appointment::whereRaw("REPLACE(REPLACE(REPLACE(phone, ' ', ''), '(', ''), ')', '') = ?", [$phone])
            ->sum('price');
        $lifetimeRevenue = $previousRevenue + ($request->price ?? 0);

        $appointment = DB::transaction(function () use ($request, $services, $phone, $customer, $lifetimeRevenue, $staffId, $startTime, $serviceNames, $packagePlans, $redemptionPlans) {
            $appointment = Appointment::create(array_merge(
                $request->all(),
                [
                    'lifetime_revenue' => $lifetimeRevenue,
                    'service_name' => $services,
                    'customer_name' => $request->customer_name,
                    'phone' => $phone,
                    'customer_id' => $customer->id,
                    'created_by' => auth()->id(),
                ]
            ));

            $this->createServiceLineItems(
                $appointment,
                $serviceNames,
                (int) $staffId,
                $startTime,
                $request->service_price ?? [],
                $request->service_discount_reason ?? []
            );

            if (!empty($redemptionPlans) || !empty($packagePlans)) {
                $lastLine = $appointment->appointmentServices()->orderByDesc('start_time')->first();
                $cursor = $lastLine ? $lastLine->end_time : $startTime;

                foreach ($redemptionPlans as $plan) {
                    $service = $plan['service'];
                    $package = $plan['client_package'];

                    $appointmentService = $this->addRedeemedServiceLine($appointment, $service, $package->combo_name, $cursor, (int) $staffId);
                    ClientPackageService::create([
                        'client_package_id' => $package->id,
                        'service_id' => $service->id,
                        'service_name' => $service->name,
                        'status' => 'redeemed',
                        'redeemed_at' => now(),
                        'appointment_service_id' => $appointmentService->id,
                    ]);
                    $package->refreshStatus();
                }

                if (!empty($packagePlans)) {
                    $this->createPackagePurchases($appointment, $customer, $packagePlans, $cursor);
                }
            }

            return $appointment;
        });

        if ($request->input('then') === 'checkout') {
            return redirect()->route('appointments.revenue.payment', $appointment->id);
        }

        return redirect()->back()->with('success', 'Appointment booked successfully.');
    }

    /**
     * Find a customer by phone, creating one if needed. Refreshes the stored
     * name so the directory reflects the latest booking's spelling.
     */
    private function findOrCreateCustomer(string $phone, ?string $name): Customer
    {
        $customer = Customer::firstOrNew(['phone' => $phone]);
        if ($name) {
            $customer->name = $name;
        }
        $customer->save();

        return $customer;
    }

    public function update(Request $request, Appointment $appointment)
    {
        $request->validate([
            'customer_name' => 'nullable|string|max:255',
            'phone' => ['nullable', 'string', 'max:20', 'regex:/^\+?[1-9]\d{6,14}$/'],
            'appointment_datetime' => 'required|date',
            'service_name' => 'required|array',
            'service_name.*' => 'string|max:255',
            'branch' => 'required|in:old_airport,wakrah,home_service',
            'price' => 'nullable|numeric',
            'booking_agent_id' => 'nullable|exists:users,id',
            'staff_id' => 'nullable|exists:staff,id',
            'status' => 'nullable|in:pending,arrived,in_progress,completed,no_show,cancelled',
        ], [
            'phone.regex' => 'Enter a valid phone number with country code, e.g. +974XXXXXXXX.'
        ]);

        $services = implode(', ', $request->service_name);
        $phone = $request->phone ? preg_replace('/\D/', '', $request->phone) : '';

        $staffId = $request->staff_id ?? $appointment->staff_id;
        $startTime = Carbon::parse($request->appointment_datetime);
        $duration = $this->totalServiceDuration($request->service_name);

        $unskilled = $this->unskilledServices((int) $staffId, $request->service_name);
        if (!empty($unskilled)) {
            // back() rather than a hardcoded calendar redirect - update() is
            // called from both the Bookings list and the Enhanced Calendar,
            // so the error must return the staff member to wherever they were.
            return redirect()->back()->withInput()
                ->with('error', 'Staff member is not skilled to do this service: ' . implode(', ', $unskilled) . '.');
        }

        if ($this->staffHasTimeConflict($staffId, $startTime, $duration, $appointment->id)) {
            return redirect()->back()->withInput()
                ->with('error', 'Staff is already booked during this time.');
        }


        // Calculate lifetime revenue: sum of previous appointments except current (walk-ins have none)
        $lifetimeRevenue = (float) ($request->price ?? 0);
        if ($phone !== '') {
            $previousRevenue = Appointment::whereRaw("REPLACE(REPLACE(REPLACE(phone, ' ', ''), '(', ''), ')', '') = ?", [$phone])
                ->sum('price');
            $lifetimeRevenue = $previousRevenue + ($request->price ?? 0);
        }
        $appointmentTime = Carbon::parse($request->appointment_datetime);
        $workingHours = $this->branchWorkingHours($request->branch, $appointmentTime);

        $startBranch = Carbon::createFromFormat('H:i', $workingHours['start'])
            ->setDate($appointmentTime->year, $appointmentTime->month, $appointmentTime->day);

        $endBranch = Carbon::createFromFormat('H:i', $workingHours['end'])
            ->setDate($appointmentTime->year, $appointmentTime->month, $appointmentTime->day);

        if ($appointmentTime->lt($startBranch) || $appointmentTime->gt($endBranch)) {
            return redirect()->back()
                ->with('error', 'Appointment must be within branch working hours: ' . $startBranch->format('H:i') . ' - ' . $endBranch->format('H:i'))->withInput();
        }

        $customerId = null;
        if ($phone !== '') {
            $customer = $this->findOrCreateCustomer($phone, $request->customer_name);
            $customerId = $customer->id;
        }

        $appointment->update(array_merge(
            $request->all(),
            [
                'service_name' => $services,
                'lifetime_revenue' => $lifetimeRevenue,
                'customer_name' => $request->customer_name ?: 'Walk-in',
                'phone' => $phone,
                'customer_id' => $customerId,
            ]
        ));

        return redirect()->back()->with('success', 'Appointment updated successfully.');
    }


    public function destroy(Appointment $appointment)
    {
        $appointment->delete();
        return redirect()->back()->with('success', 'Appointment deleted successfully.');
    }

    public function calendar(Request $request)
    {
        $staffs = Staff::orderBy('name')->get();
        $activeStaffs = Staff::active()->orderBy('name')->get();
        $services = Service::orderBy('name')->get();
        $products = Product::orderBy('name')->get();
        $agents = User::where('role', 'agent')->select('id', 'name')->get();

        $servicesCatalog = $services->map(function ($s) {
            return [
                'id' => $s->id,
                'name' => $s->name,
                'price' => (float) $s->price,
                'duration' => (int) $s->duration,
            ];
        })->values();

        $productsCatalog = $products->map(function ($p) {
            return [
                'id' => $p->id,
                'name' => $p->name,
                'price' => (float) $p->price,
            ];
        })->values();

        $combosCatalog = Combo::with('services')->where('status', 'active')->orderBy('name')->get()
            ->map(fn($c) => [
                'id' => $c->id,
                'name' => $c->name,
                'price' => (float) $c->price,
                'quantity_included' => $c->quantity_included ?? $c->services->count(),
                'services' => $c->services->map(fn($s) => ['id' => $s->id, 'name' => $s->name, 'duration' => $s->duration])->values(),
            ])
            ->filter(fn($c) => $c['services']->isNotEmpty())
            ->values();

        return view('appointments.calendar', compact('staffs', 'activeStaffs', 'services', 'products', 'agents', 'servicesCatalog', 'productsCatalog', 'combosCatalog'));
    }

    /**
     * Status colors used consistently across the calendar UI.
     */
    private function statusColors(): array
    {
        return [
            'pending'     => '#c9a66b',
            'arrived'     => '#b98ea3',
            'in_progress' => '#c97b4a',
            'completed'   => '#8ea88a',
            'no_show'     => '#8a7d76',
            'cancelled'   => '#a8524a',
        ];
    }

    /**
     * Determine whether a staff member is off on a given date, and why.
     */
    private function staffOffInfo(Staff $staff, Carbon $date): array
    {
        if ($staff->availability_status === 'sick') {
            return ['off' => true, 'reason' => 'Sick'];
        }

        if ($staff->off_from && $staff->off_to) {
            if ($date->between(Carbon::parse($staff->off_from), Carbon::parse($staff->off_to))) {
                return ['off' => true, 'reason' => $staff->availability_status === 'on-leave' ? 'On Leave' : 'Unavailable'];
            }
        }

        if (!empty($staff->weekly_off)) {
            $weeklyOff = is_array($staff->weekly_off) ? $staff->weekly_off : json_decode($staff->weekly_off, true);
            if (in_array($date->format('l'), (array) $weeklyOff)) {
                return ['off' => true, 'reason' => 'Weekly Off'];
            }
        }

        return ['off' => false, 'reason' => null];
    }

    /**
     * Bulk-load upsell totals/staff names for a set of appointment ids, keyed
     * by appointment_id, so calendar cards can show the upsell distinction
     * without an extra query per appointment.
     */
    private function upsellSummaries($appointmentIds)
    {
        return AppointmentUpsell::whereIn('appointment_id', $appointmentIds)
            ->with('staff')
            ->get()
            ->groupBy('appointment_id')
            ->map(fn($lines) => [
                'total' => (float) $lines->sum('amount'),
                'staff_names' => $lines->map(fn($u) => optional($u->staff)->name)->filter()->unique()->implode(', '),
            ]);
    }

    private function filteredAppointmentQuery(Request $request)
    {
        // Cancelled/no-show bookings no longer occupy a slot, so the
        // calendar (month/week/day) shouldn't render them at all - once a
        // booking is marked either, it drops off the timeline and the slot
        // is immediately free for a new booking.
        $query = Appointment::query()->whereNotIn('status', ['cancelled', 'no_show']);

        if ($request->filled('branch'))       $query->where('branch', $request->branch);
        if ($request->filled('staff_id'))     $query->where('staff_id', $request->staff_id);
        if ($request->filled('service_name')) $query->where('service_name', 'like', '%' . $request->service_name . '%');
        if ($request->filled('agent_id'))     $query->where('booking_agent_id', $request->agent_id);

        return $query;
    }

    /**
     * Per-staff time slices actually being performed for this appointment -
     * one entry per AppointmentService line's own staff/start/duration, so
     * a combo split across several team members renders as separate blocks
     * in each of their calendar columns instead of one block glued to
     * whichever staff the appointment itself was booked under. Falls back
     * to a single synthetic slice from the appointment's own fields when it
     * has no line items yet (e.g. a bare "Decide in Salon" hold).
     */
    private function appointmentStaffSlices(Appointment $appointment): array
    {
        $lines = $appointment->appointmentServices;

        if ($lines->isEmpty()) {
            return [[
                'staff_id' => $appointment->staff_id,
                'start' => Carbon::parse($appointment->appointment_datetime),
                'duration' => $this->totalServiceDuration(explode(',', $appointment->service_name)),
                'service_name' => $appointment->service_name,
            ]];
        }

        return $lines->map(fn($line) => [
            'staff_id' => $line->staff_id,
            'start' => $line->start_time,
            'duration' => $line->duration,
            'service_name' => $line->name,
        ])->all();
    }

    public function calendarData(Request $request)
    {
        $view = in_array($request->view, ['week', '3day', 'month']) ? $request->view : 'day';
        $anchor = $request->date ? Carbon::parse($request->date)->startOfDay() : now()->startOfDay();
        $branch = $request->branch ?? 'old_airport';

        $staffQuery = Staff::select('id', 'name', 'weekly_off', 'availability_status', 'off_from', 'off_to', 'profile_picture')
            ->with('services:id,name')
            ->orderBy('name');

        if ($request->filled('staff_id')) {
            $staffQuery->where('id', $request->staff_id);
        }

        $staffs = $staffQuery->get();

        $palette = ['#1abc9c', '#3498db', '#9b59b6', '#e67e22', '#e74c3c', '#16a085', '#2ecc71', '#8e44ad'];
        $staffColors = [];
        foreach ($staffs as $i => $staff) {
            $staffColors[$staff->id] = $palette[$i % count($palette)];
        }

        $staffPayload = $staffs->map(fn($s) => [
            'id' => $s->id,
            'name' => $s->name,
            'color' => $staffColors[$s->id],
            'profile_picture' => $s->profile_picture
                ? asset(str_replace('\\', '/', $s->profile_picture))
                : asset('design/sneat-admin-template/assets/img/avatars/1.png'),
            // Service names this staff member is trained/assigned for
            // (service_staff pivot). Lets the calendar reject a drag-and-drop
            // onto an unqualified staff member instantly, client-side.
            'skills' => $s->services->pluck('name'),
        ]);

        if ($view === 'month') {
            $monthStart = $anchor->copy()->startOfMonth()->startOfWeek(Carbon::SUNDAY);
            $monthEnd = $anchor->copy()->endOfMonth()->endOfWeek(Carbon::SUNDAY);
            $currentMonth = $anchor->month;

            $days = [];
            for ($d = $monthStart->copy(); $d->lte($monthEnd); $d->addDay()) {
                $days[] = [
                    'date' => $d->format('Y-m-d'),
                    'day_num' => $d->format('j'),
                    'is_today' => $d->isSameDay(now()),
                    'in_month' => $d->month === $currentMonth,
                ];
            }

            $appointments = $this->filteredAppointmentQuery($request)
                ->whereBetween('appointment_datetime', [$monthStart, $monthEnd->copy()->endOfDay()])
                ->orderBy('appointment_datetime')
                ->get()
                ->groupBy(fn($a) => Carbon::parse($a->appointment_datetime)->format('Y-m-d'))
                ->map(fn($list) => $list->map(fn($a) => [
                    'id' => $a->id,
                    'time' => Carbon::parse($a->appointment_datetime)->format('g:i A'),
                    'customer_name' => $a->customer_name,
                    'service_name' => $a->service_name,
                    'status' => $a->status,
                    'staff_id' => $a->staff_id,
                ])->values());

            return response()->json([
                'view' => 'month',
                'month_label' => $anchor->format('F Y'),
                'anchor' => $anchor->format('Y-m-d'),
                'days' => $days,
                'status_colors' => $this->statusColors(),
                'appointments' => $appointments,
            ]);
        }

        if ($view === 'week' || $view === '3day') {
            if ($view === 'week') {
                $weekStart = $anchor->copy()->startOfWeek(Carbon::MONDAY);
                $weekEnd = $weekStart->copy()->endOfWeek(Carbon::SUNDAY);
            } else {
                $weekStart = $anchor->copy();
                $weekEnd = $weekStart->copy()->addDays(2);
            }

            $days = [];
            for ($d = $weekStart->copy(); $d->lte($weekEnd); $d->addDay()) {
                $days[] = [
                    'date'     => $d->format('Y-m-d'),
                    'label'    => $d->format('D'),
                    'day_num'  => $d->format('j'),
                    'is_today' => $d->isSameDay(now()),
                ];
            }

            $weekAppointments = $this->filteredAppointmentQuery($request)
                ->whereBetween('appointment_datetime', [$weekStart, $weekEnd->copy()->endOfDay()])
                ->orderBy('appointment_datetime')
                ->with('appointmentServices')
                ->get();
            $weekUpsellSummaries = $this->upsellSummaries($weekAppointments->pluck('id'));

            // Flatten each appointment into its own per-staff slices first -
            // same reasoning as the day view - so a combo split across
            // several team members shows up in each of their day cells
            // instead of only the appointment's own booked staff.
            $weekSlices = $weekAppointments->flatMap(fn($a) => collect($this->appointmentStaffSlices($a))
                ->map(fn($slice) => (object) [
                    'appointment' => $a,
                    'staff_id' => $slice['staff_id'],
                    'start' => $slice['start'],
                    'service_name' => $slice['service_name'],
                ]));

            $appointments = $weekSlices
                ->groupBy([fn($s) => $s->staff_id, fn($s) => $s->start->format('Y-m-d')])
                ->map(fn($byDate) => $byDate->map(fn($list) => $list->map(function ($s) use ($weekUpsellSummaries) {
                    $a = $s->appointment;
                    $upsell = $weekUpsellSummaries->get($a->id);

                    return [
                        'id'            => $a->id,
                        'time'          => $s->start->format('g:i A'),
                        'start_minutes' => $s->start->hour * 60 + $s->start->minute,
                        'customer_name' => $a->customer_name,
                        'service_name'  => $s->service_name,
                        'status'        => $a->status,
                        'price'         => $a->price,
                        'has_upsell'    => (bool) $upsell,
                        'upsell_total'  => $upsell['total'] ?? 0,
                    ];
                })->values()));

            $off = [];
            foreach ($staffs as $staff) {
                foreach ($days as $day) {
                    $info = $this->staffOffInfo($staff, Carbon::parse($day['date']));
                    if ($info['off']) {
                        $off[$staff->id][$day['date']] = $info['reason'];
                    }
                }
            }

            $blocks = \App\Models\StaffBlock::whereIn('staff_id', $staffs->pluck('id'))
                ->whereBetween('date', [$weekStart->toDateString(), $weekEnd->toDateString()])
                ->get()
                ->groupBy([fn($b) => $b->staff_id, fn($b) => $b->date->format('Y-m-d')])
                ->map(fn($byDate) => $byDate->map(fn($list) => $list->map(fn($b) => [
                    'id' => $b->id,
                    'start' => substr($b->start_time, 0, 5),
                    'end' => substr($b->end_time, 0, 5),
                    'reason' => $b->reason ?: 'Blocked',
                ])->values()));

            return response()->json([
                'view'           => $view,
                'week_start'     => $weekStart->format('Y-m-d'),
                'week_end'       => $weekEnd->format('Y-m-d'),
                'days'           => $days,
                'status_colors'  => $this->statusColors(),
                'staffs'         => $staffPayload,
                'appointments'   => $appointments,
                'off'            => $off,
                'blocks'         => $blocks,
            ]);
        }

        // ---- DAY VIEW ----
        $workingHours = $this->branchWorkingHours($branch, $anchor);
        $uiStart = Carbon::createFromFormat('H:i', $workingHours['start']);
        $uiEnd   = Carbon::createFromTime(22, 0);
        $dayEnd  = $anchor->copy()->setTimeFromTimeString($workingHours['end']);

        $dayAppointments = $this->filteredAppointmentQuery($request)
            ->whereDate('appointment_datetime', $anchor->toDateString())
            ->with('appointmentServices')
            ->get();
        $upsellSummaries = $this->upsellSummaries($dayAppointments->pluck('id'));

        $appointments = [];
        $dayAppointments->each(function ($a) use (&$appointments, $dayEnd, $upsellSummaries) {
                $upsell = $upsellSummaries->get($a->id);

                // A combo split across several team members produces one
                // slice per staff here, so each gets their own block on
                // their own column instead of everything piling onto
                // whichever staff the appointment itself was booked under.
                foreach ($this->appointmentStaffSlices($a) as $slice) {
                    $start = $slice['start'];
                    $end = $start->copy()->addMinutes($slice['duration']);
                    if ($end->gt($dayEnd)) {
                        $end = $dayEnd;
                    }

                    $appointments[$slice['staff_id']][] = [
                        'id'            => $a->id,
                        'start'         => $start->format('H:i'),
                        'end'           => $end->format('H:i'),
                        'duration'      => $slice['duration'],
                        'start_minutes' => $start->hour * 60 + $start->minute,
                        'end_minutes'   => $end->hour * 60 + $end->minute,
                        'service_name'  => $slice['service_name'],
                        'status'        => $a->status,
                        'customer_name' => $a->customer_name,
                        'phone'         => $a->phone,
                        'price'         => $a->price,
                        'has_upsell'    => (bool) $upsell,
                        'upsell_total'  => $upsell['total'] ?? 0,
                        'upsell_staff_names' => $upsell['staff_names'] ?? '',
                    ];
                }
            });

        $slots = [];
        $slot = $uiStart->copy();
        while ($slot <= $uiEnd) {
            $slots[] = $slot->format('H:i');
            $slot->addMinutes(30);
        }

        $offInfo = [];
        foreach ($staffs as $staff) {
            $info = $this->staffOffInfo($staff, $anchor);
            if ($info['off']) {
                $offInfo[$staff->id] = $info['reason'];
            }
        }

        $blocks = [];
        \App\Models\StaffBlock::whereIn('staff_id', $staffs->pluck('id'))
            ->whereDate('date', $anchor->toDateString())
            ->get()
            ->each(function ($b) use (&$blocks) {
                $blocks[$b->staff_id][] = [
                    'id' => $b->id,
                    'start' => substr($b->start_time, 0, 5),
                    'end' => substr($b->end_time, 0, 5),
                    'start_minutes' => (int) substr($b->start_time, 0, 2) * 60 + (int) substr($b->start_time, 3, 2),
                    'end_minutes' => (int) substr($b->end_time, 0, 2) * 60 + (int) substr($b->end_time, 3, 2),
                    'reason' => $b->reason ?: 'Blocked',
                ];
            });

        return response()->json([
            'view'          => 'day',
            'date'          => $anchor->format('Y-m-d'),
            'is_today'      => $anchor->isSameDay(now()),
            'working_hours' => $workingHours,
            'ui_start'      => $uiStart->format('H:i'),
            'time_slots'    => $slots,
            'appointments'  => $appointments,
            'status_colors' => $this->statusColors(),
            'staff_colors'  => $staffColors,
            'off'           => $offInfo,
            'staffs'        => $staffPayload,
            'blocks'        => $blocks,
        ]);
    }


    function branchWorkingHours(string $branch, Carbon $date): array
    {
        $isFriday = $date->isFriday();

        return match ($branch) {
            'old_airport' => $isFriday ? ['start' => '12:00', 'end' => '22:00'] : ['start' => '10:00', 'end' => '22:00'],
            'wakrah' => $isFriday ? ['start' => '12:00', 'end' => '22:00'] : ['start' => '11:00', 'end' => '21:00'],
            default => ['start' => '00:00', 'end' => '23:59'],
        };
    }

    /**
     * Total minutes for a set of service names, summed from the current
     * service catalog. Appointments store their services as a flat
     * "A, B, C" string on `service_name` (there's no duration/end_time
     * column on appointments itself), so every place that needs a
     * duration - conflict checks, reschedule, the calendar grid's block
     * height - must derive it the same way. Falls back to 30 minutes only
     * when nothing in the list matches the catalog (e.g. a renamed/deleted
     * service), never on a 0-minute service.
     */
    private function totalServiceDuration(array $serviceNames): int
    {
        $names = array_filter(array_map('trim', $serviceNames));
        if (empty($names)) {
            return 30;
        }

        return (int) (Service::whereIn('name', $names)->sum('duration') ?: 30);
    }

    /**
     * Names of the given services the staff member is NOT trained/assigned
     * to perform, per Services > Team members (the service_staff pivot).
     * Empty return means fully qualified. A service name that doesn't
     * match the catalog at all is treated as unverified - never skilled.
     */
    private function unskilledServices(int $staffId, array $serviceNames): array
    {
        $names = array_values(array_unique(array_filter(array_map('trim', $serviceNames))));
        if (empty($names)) {
            return [];
        }

        $qualified = Service::whereIn('name', $names)
            ->whereHas('staff', fn($q) => $q->where('staff.id', $staffId))
            ->pluck('name')
            ->all();

        return array_values(array_diff($names, $qualified));
    }

    private function staffHasTimeConflict(int $staffId, Carbon $newStart, int $newDuration, ?int $ignoreAppointmentId = null)
    {
        $newEnd = $newStart->copy()->addMinutes((int) $newDuration);
        // Cancelled/no-show appointments free up their slot immediately -
        // they must never count as a conflict for a new or rescheduled
        // booking.
        $query = Appointment::where('staff_id', $staffId)
            ->whereNotIn('status', ['cancelled', 'no_show']);

        if ($ignoreAppointmentId) {
            $query->where('id', '!=', $ignoreAppointmentId);
        }

        $appointments = $query->get();
        foreach ($appointments as $appointment) {
            $existingStart = Carbon::parse($appointment->appointment_datetime);
            $existingDuration = $this->totalServiceDuration(explode(',', $appointment->service_name));
            $existingEnd = $existingStart->copy()->addMinutes($existingDuration);

            // ✅ OVERLAP CHECK
            if ($newStart->lt($existingEnd) && $newEnd->gt($existingStart)) {
                return true;
            }
        }

        $blocks = \App\Models\StaffBlock::where('staff_id', $staffId)
            ->whereDate('date', $newStart->toDateString())
            ->get();

        foreach ($blocks as $block) {
            $blockStart = Carbon::parse($newStart->toDateString() . ' ' . $block->start_time);
            $blockEnd = Carbon::parse($newStart->toDateString() . ' ' . $block->end_time);

            if ($newStart->lt($blockEnd) && $newEnd->gt($blockStart)) {
                return true;
            }
        }

        return false;
    }


    public function customerProfile($phone)
    {
        // Normalize incoming phone
        $phone = preg_replace('/\D/', '', $phone); // keep only digits
        $appointments = Appointment::whereRaw(
            "REPLACE(REPLACE(REPLACE(phone, ' ', ''), '(', ''), ')', '') = ?",
            [$phone]
        )
            ->orderBy('appointment_datetime', 'asc')->get();

        if ($appointments->isEmpty()) {
            return response()->json(['message' => 'No records found for this customer'], 404);
        }

        $customerName = $appointments->first()->customer_name;
        $totalVisits = $appointments->count();
        $firstVisit = $appointments->first()->appointment_datetime->format('d M Y, h:i A');
        $lastVisit  = $appointments->last()->appointment_datetime->format('d M Y, h:i A');

        $servicesTaken = collect();
        foreach ($appointments as $a) {
            $servicesTaken = $servicesTaken->merge(explode(', ', $a->service_name));
        }
        $servicesTaken = $servicesTaken->unique()->implode(', ');
        $lifetimeRevenue = $appointments->sum('price');

        $appointmentList = $appointments->map(function ($a) {
            return [
                'appointment_datetime' => $a->appointment_datetime->format('d M Y, h:i A'),
                'service_name' => $a->service_name,
                'price' => number_format($a->price, 2),
                'branch' => ucwords(str_replace('_', ' ', $a->branch)),
                'agent' => $a->agent->name ?? '—'
            ];
        });

        return response()->json([
            'customer_id' => $appointments->first()->customer_id,
            'customer_name' => $customerName,
            'phone' => $appointments->first()->phone, // show original format
            'total_visits' => $totalVisits,
            'first_visit' => $firstVisit,
            'last_visit' => $lastVisit,
            'services_taken' => $servicesTaken,
            'lifetime_revenue' => $lifetimeRevenue,
            'appointments' => $appointmentList
        ]);
    }

    // last updated
    // public function availableStaff(Request $request)
    // {
    //     $services = $request->get('services', []);
    //     $appointmentTime = Carbon::parse($request->appointment_datetime);
    //     $branch = $request->branch;

    //     if (empty($services) || !$branch || !$appointmentTime) {
    //         return response()->json([]);
    //     }

    //     $staffs = Staff::where('availability_status', 'present')
    //         ->where(fn($q) => $q->where('branch', $branch)->orWhere('branch', 'both'))
    //         ->get()
    //         ->filter(function ($staff) use ($services, $appointmentTime) {

    //             // 🔹 Normalize staff skills and selected services
    //             $staffSkills = collect($staff->skills ?? [])
    //                 ->filter()
    //                 ->map(fn($s) => strtolower(trim($s)));

    //             $servicesCol = collect($services)
    //                 ->filter()
    //                 ->map(fn($s) => strtolower(trim($s)));

    //             if ($staffSkills->isEmpty() || $servicesCol->isEmpty()) return false;

    //             // ✅ Require ALL selected services to match staff skills
    //             $allMatched = $servicesCol->every(
    //                 fn($service) =>
    //                 $staffSkills->contains(
    //                     fn($skill) =>
    //                     str_contains($skill, $service) || str_contains($service, $skill)
    //                 )
    //             );

    //             if (!$allMatched) return false;

    //             // 🔹 LEAVE check
    //             if (
    //                 $staff->off_from && $staff->off_to &&
    //                 $appointmentTime->between($staff->off_from, $staff->off_to)
    //             ) {
    //                 return false;
    //             }

    //             // 🔹 WEEKLY OFF check
    //             if (in_array($appointmentTime->format('l'), $staff->weekly_off ?? [])) {
    //                 return false;
    //             }

    //             // 🔹 WORKING HOURS check
    //             if ($staff->working_hours) {
    //                 $start = Carbon::parse($appointmentTime->format('Y-m-d') . ' ' . $staff->working_hours['start']);
    //                 $end   = Carbon::parse($appointmentTime->format('Y-m-d') . ' ' . $staff->working_hours['end']);

    //                 if ($end->lessThan($start)) $end->addDay();

    //                 if (!$appointmentTime->between($start, $end)) return false;
    //             }

    //             return true;
    //         })
    //         ->values()
    //         ->map(fn($s) => [
    //             'id' => $s->id,
    //             'name' => $s->name
    //         ]);

    //     return response()->json($staffs);
    // }

    // multiple staff
    // public function availableStaff(Request $request)
    // {
    //     $request->validate([
    //         'services' => 'required|array|min:1',
    //         'appointment_datetime' => 'required|date',
    //         'branch' => 'required'
    //     ]);

    //     $services = collect($request->services)
    //         ->map(fn($s) => strtolower(trim($s)));

    //     $appointmentTime = Carbon::parse($request->appointment_datetime);
    //     $branch = $request->branch;

    //     $duration = Service::whereIn('name', $request->services)
    //         ->sum('duration') ?? 30;

    //     $staffs = Staff::where('availability_status', 'present')
    //         ->where(
    //             fn($q) =>
    //             $q->where('branch', $branch)
    //                 ->orWhere('branch', 'both')
    //         )
    //         ->get()
    //         ->filter(function ($staff) use ($services, $appointmentTime, $duration) {

    //             $staffSkills = collect($staff->skills ?? [])
    //                 ->map(fn($s) => strtolower(trim($s)));

    //             // ✅ MUST HAVE ALL SELECTED SERVICES
    //             if (!$services->every(fn($s) => $staffSkills->contains($s))) {
    //                 return false;
    //             }

    //             // ❌ On Leave
    //             if ($staff->off_from && $staff->off_to) {
    //                 if ($appointmentTime->between(
    //                     Carbon::parse($staff->off_from),
    //                     Carbon::parse($staff->off_to)
    //                 )) {
    //                     return false;
    //                 }
    //             }

    //             // ❌ Weekly Off
    //             if (
    //                 !empty($staff->weekly_off) &&
    //                 in_array($appointmentTime->format('l'), (array)$staff->weekly_off)
    //             ) {
    //                 return false;
    //             }

    //             // ❌ Time Conflict
    //             if ($this->staffHasTimeConflict(
    //                 $staff->id,
    //                 $appointmentTime,
    //                 $duration
    //             )) {
    //                 return false;
    //             }

    //             return true;
    //         })
    //         ->values()
    //         ->map(fn($s) => [
    //             'id' => $s->id,
    //             'name' => $s->name
    //         ]);

    //     return response()->json($staffs);
    // }



    public function availableStaff(Request $request)
    {
        $request->validate([
            // "Decide in Salon" bookings have no service chosen yet, so the
            // services list is only mandatory without that flag.
            'services' => 'required_without:decide_in_salon|array',
            'appointment_datetime' => 'required|date',
            'branch' => 'required',
            // Passed when checking availability for an appointment that's
            // being edited/rescheduled, so it doesn't get excluded as a
            // "conflict" with its own existing booking.
            'exclude_appointment_id' => 'nullable|integer',
            'decide_in_salon' => 'nullable|boolean',
        ]);

        $appointmentTime = Carbon::parse($request->appointment_datetime);
        $branch = $request->branch;
        $excludeAppointmentId = $request->exclude_appointment_id;
        $serviceNames = $request->services ?? [];
        $decideInSalon = $request->boolean('decide_in_salon');

        // Total duration of selected services
        $duration = $this->totalServiceDuration($serviceNames);

        $staffQuery = Staff::where('availability_status', 'present');

        if ($decideInSalon) {
            // No service picked yet - offer every present staff member for
            // the branch rather than filtering by a skill match that can't
            // be evaluated.
        } else {
            // Staff trained/skilled for EVERY selected service - not just one
            // of them - per the explicit service_staff assignment (Services >
            // Team members). A staff member missing even one of the selected
            // services must not be offered here.
            $serviceIds = Service::whereIn('name', $serviceNames)->pluck('id');
            $eligibleStaffIds = \Illuminate\Support\Facades\DB::table('service_staff')
                ->whereIn('service_id', $serviceIds)
                ->groupBy('staff_id')
                ->havingRaw('COUNT(DISTINCT service_id) = ?', [$serviceIds->count()])
                ->pluck('staff_id');

            $staffQuery->whereIn('id', $eligibleStaffIds);
        }

        $staffs = $staffQuery
            ->where(function ($q) use ($branch) {
                $q->where('branch', $branch)
                    ->orWhere('branch', 'both');
            })
            ->get()
            ->filter(function ($staff) use ($appointmentTime, $duration, $excludeAppointmentId) {

                // ❌ On Leave
                if ($staff->off_from && $staff->off_to) {
                    if ($appointmentTime->between(
                        Carbon::parse($staff->off_from),
                        Carbon::parse($staff->off_to)
                    )) {
                        return false;
                    }
                }

                // ❌ Weekly Off
                if (
                    !empty($staff->weekly_off) &&
                    in_array($appointmentTime->format('l'), (array)$staff->weekly_off)
                ) {
                    return false;
                }

                // ❌ Time Conflict
                if ($this->staffHasTimeConflict(
                    $staff->id,
                    $appointmentTime,
                    $duration,
                    $excludeAppointmentId
                )) {
                    return false;
                }

                return true;
            })
            ->values()
            ->map(fn($s) => [
                'id' => $s->id,
                'name' => $s->name
            ]);

        return response()->json($staffs);
    }


    public function updateStatus(Request $request, Appointment $appointment)
    {
        $request->validate(['status' => 'required|in:arrived,in_progress,completed,no_show,cancelled',]);
        $appointment->update(['status' => $request->status,]);

        if (in_array($request->status, ['no_show', 'cancelled'])) {
            $this->syncLeadCategoryFromAppointment($appointment, $request->status === 'no_show' ? 'no_show' : 'cancel');
        }

        if ($request->status === 'completed') {
            return response()->json([
                'success' => true,
                'redirect' => route('appointments.revenue.payment', $appointment->id),
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Appointment status updated successfully.',
        ]);
    }

    /**
     * No-show and Cancel are no longer manually selectable when adding a
     * lead - they're driven entirely by staff marking an appointment that
     * way on the Enhanced Calendar. This always logs a lead scoped to THIS
     * specific appointment (via leads.appointment_id), never one shared with
     * or matched against the client's other leads: a client can have an
     * independently-active Hair Color follow-up and a Haircut
     * no-show/cancellation lead side by side, and this action must never
     * touch the former. Re-toggling the same appointment's status (e.g.
     * correcting No Show to Cancelled) updates that one dedicated lead
     * in place rather than spawning a duplicate.
     */
    private function syncLeadCategoryFromAppointment(Appointment $appointment, string $category): void
    {
        $lead = Lead::where('appointment_id', $appointment->id)->first();

        if ($lead) {
            $lead->update(['category' => $category]);

            return;
        }

        // No default follow-up date: staff need to actually contact the
        // client to learn their preferred rebooking timeline before one can
        // be set. Left blank, this lead surfaces in the "needs a follow-up
        // date" warning on the Leads page instead of a guessed date quietly
        // going stale.
        Lead::create([
            'phone' => $appointment->phone,
            'customer_id' => $appointment->customer_id,
            'appointment_id' => $appointment->id,
            'assigned_agent_id' => $appointment->booking_agent_id,
            'category' => $category,
            'service_interest' => $appointment->service_name,
            'next_followup_date' => null,
        ]);
    }

    /**
     * JSON detail payload for the appointment drawer.
     */
    public function show(Appointment $appointment)
    {
        $appointment->load('customer', 'staff', 'agent', 'appointmentServices.staff', 'upsells.staff');
        $serviceLines = $appointment->appointmentServices->map(fn($s) => $this->formatServiceLine($s))->values()->all();
        $upsellLines = $appointment->upsells->map(fn($u) => $this->formatUpsellLine($u))->values()->all();

        $customer = $appointment->customer;
        $recentVisits = [];
        if ($customer) {
            $recentVisits = $customer->appointments()
                ->where('id', '!=', $appointment->id)
                ->orderByDesc('appointment_datetime')
                ->limit(3)
                ->get(['appointment_datetime', 'service_name', 'status'])
                ->map(fn($a) => [
                    'date' => $a->appointment_datetime->format('d M Y'),
                    'service_name' => $a->service_name,
                    'status' => $a->status,
                ])->all();
        }

        return response()->json([
            'id' => $appointment->id,
            'customer_name' => $appointment->customer_name,
            'phone' => $appointment->phone,
            'customer_id' => $appointment->customer_id,
            'customer_visits' => $customer ? $customer->appointments()->count() : null,
            'customer_allergies' => $customer->allergies ?? null,
            'customer_notes' => $customer->notes ?? null,
            'recent_visits' => $recentVisits,
            'appointment_datetime' => $appointment->appointment_datetime->format('D, d M Y \a\t h:i A'),
            'date' => $appointment->appointment_datetime->format('Y-m-d'),
            'time' => $appointment->appointment_datetime->format('H:i'),
            'branch' => ucwords(str_replace('_', ' ', $appointment->branch)),
            'branch_raw' => $appointment->branch,
            'staff_id' => $appointment->staff_id,
            'staff_name' => $appointment->staff->name ?? 'Unassigned',
            'agent_name' => $appointment->agent->name ?? null,
            'status' => $appointment->status,
            'price' => $appointment->price,
            'notes' => $appointment->notes,
            'services' => $serviceLines,
            'upsells' => $upsellLines,
            'upsell_total' => array_sum(array_column($upsellLines, 'amount')),
            'payment_url' => route('appointments.revenue.payment', $appointment->id),
            'profile_url' => $appointment->customer_id ? route('customers.show', $appointment->customer_id) : null,
        ]);
    }

    /**
     * Move an appointment to a new date/time and, optionally, a different
     * staff member. Used by both calendar drag-and-drop and the drawer's
     * manual "Reschedule" quick action.
     */
    public function reschedule(Request $request, Appointment $appointment)
    {
        $request->validate([
            'appointment_datetime' => 'required|date',
            'staff_id' => 'nullable|exists:staff,id',
        ]);

        $newStart = Carbon::parse($request->appointment_datetime);
        $staffId = $request->staff_id ?: $appointment->staff_id;

        $workingHours = $this->branchWorkingHours($appointment->branch, $newStart);
        $startBranch = Carbon::createFromFormat('H:i', $workingHours['start'])
            ->setDate($newStart->year, $newStart->month, $newStart->day);
        $endBranch = Carbon::createFromFormat('H:i', $workingHours['end'])
            ->setDate($newStart->year, $newStart->month, $newStart->day);

        if ($newStart->lt($startBranch) || $newStart->gt($endBranch)) {
            return response()->json([
                'success' => false,
                'message' => 'Outside branch working hours: ' . $startBranch->format('H:i') . ' - ' . $endBranch->format('H:i'),
            ], 422);
        }

        if ($staffId) {
            $staff = Staff::find($staffId);
            $offInfo = $staff ? $this->staffOffInfo($staff, $newStart) : ['off' => false];
            if ($offInfo['off']) {
                return response()->json([
                    'success' => false,
                    'message' => 'Selected staff is unavailable then: ' . $offInfo['reason'],
                ], 422);
            }
        }

        $duration = $this->totalServiceDuration(explode(',', $appointment->service_name));

        if ($staffId) {
            $unskilled = $this->unskilledServices((int) $staffId, explode(',', $appointment->service_name));
            if (!empty($unskilled)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Staff member is not skilled to do this service: ' . implode(', ', $unskilled) . '.',
                ], 422);
            }
        }

        if ($staffId && $this->staffHasTimeConflict((int) $staffId, $newStart, $duration, $appointment->id)) {
            return response()->json([
                'success' => false,
                'message' => 'That staff member is already booked at this time.',
            ], 422);
        }

        $appointment->update([
            'appointment_datetime' => $newStart,
            'staff_id' => $staffId,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Appointment rescheduled',
        ]);
    }

    /**
     * Build the itemized service lines for an appointment from its real
     * line items (appointment_services), including any per-service price
     * overrides/discounts applied from the drawer. This is the authoritative
     * "what are we charging for" breakdown used by the checkout drawer.
     */
    private function appointmentServiceItems(Appointment $appointment): array
    {
        $lines = $appointment->appointmentServices;

        if ($lines->isEmpty()) {
            // Fallback for any row that somehow has no line items yet.
            $names = array_filter(array_map('trim', explode(',', $appointment->service_name)));
            $catalog = Service::whereIn('name', $names)->get()->keyBy('name');

            return collect($names)->map(fn($name) => [
                'name' => $name,
                'price' => $catalog->get($name)->price ?? 0,
                'original_price' => $catalog->get($name)->price ?? 0,
                'discount_amount' => 0,
                'discount_reason' => null,
                'duration' => $catalog->get($name)->duration ?? 0,
                'staff_id' => $appointment->staff_id,
            ])->values()->all();
        }

        return $lines->map(fn($s) => [
            'name' => $s->name,
            'price' => $s->final_price,
            'original_price' => (float) ($s->original_price ?? $s->price),
            'discount_amount' => (float) $s->discount_amount,
            'discount_reason' => $s->discount_reason,
            'duration' => $s->duration,
            'staff_id' => $s->staff_id,
        ])->values()->all();
    }

    /**
     * Create line items for a freshly booked appointment, stacking each
     * service sequentially from the appointment's start time. $customPrices
     * and $discountReasons are index-aligned with $serviceNames (from the
     * booking drawer's per-service price override); a missing/blank price
     * entry falls back to the service's catalog price. original_price always
     * records the catalog price, independent of any override, so the
     * discount given can be recovered later.
     */
    private function createServiceLineItems(Appointment $appointment, array $serviceNames, int $staffId, Carbon $startTime, array $customPrices = [], array $discountReasons = []): void
    {
        $catalog = Service::whereIn('name', $serviceNames)->get()->keyBy('name');
        $cursor = $startTime->copy();

        foreach ($serviceNames as $index => $name) {
            $service = $catalog->get($name);
            $duration = $service->duration ?? 30;
            $catalogPrice = (float) ($service->price ?? 0);

            $customPrice = $customPrices[$index] ?? null;
            $price = ($customPrice !== null && $customPrice !== '') ? (float) $customPrice : $catalogPrice;

            $line = AppointmentService::create([
                'appointment_id' => $appointment->id,
                'service_id' => $service->id ?? null,
                'staff_id' => $staffId,
                'name' => $name,
                'price' => $price,
                'original_price' => $catalogPrice,
                'duration' => $duration,
                'start_time' => $cursor->copy(),
                'discount_reason' => $discountReasons[$index] ?? null,
            ]);

            $this->logPriceOverrideIfDiscounted($line);

            $cursor->addMinutes($duration);
        }
    }

    /**
     * Appends an immutable audit row to appointment_price_overrides whenever
     * a service line actually carries a discount (original_price >
     * final_price) - called after every create/update of an
     * AppointmentService so booking-time and post-booking overrides alike
     * end up in the same permanent, reportable log.
     */
    private function logPriceOverrideIfDiscounted(AppointmentService $line, ?string $reason = null): void
    {
        if ($line->discount_amount <= 0) {
            return;
        }

        AppointmentPriceOverride::create([
            'appointment_id' => $line->appointment_id,
            'appointment_service_id' => $line->id,
            'service_name' => $line->name,
            'original_price' => $line->original_price,
            'new_price' => $line->price,
            'discount_amount' => $line->discount_amount,
            'discount_reason' => $reason ?: $line->discount_reason,
            'changed_by' => auth()->id(),
        ]);
    }

    /**
     * Add another service to an already-booked appointment (drawer "+ Add service").
     */
    public function addService(Request $request, Appointment $appointment)
    {
        $request->validate([
            'service_id' => 'required|exists:services,id',
            'staff_id' => 'nullable|exists:staff,id',
        ]);

        $service = Service::find($request->service_id);
        $lastLine = $appointment->appointmentServices()->orderByDesc('start_time')->first();
        $startTime = $lastLine ? $lastLine->end_time : $appointment->appointment_datetime;

        $line = AppointmentService::create([
            'appointment_id' => $appointment->id,
            'service_id' => $service->id,
            'staff_id' => $request->staff_id ?: $appointment->staff_id,
            'name' => $service->name,
            'price' => $service->price,
            'original_price' => $service->price,
            'duration' => $service->duration,
            'start_time' => $startTime,
        ]);

        $appointment->syncFromServices();

        return response()->json([
            'success' => true,
            'message' => 'Service added.',
            'service' => $this->formatServiceLine($line),
        ]);
    }

    /**
     * Edit a single service line item ("Edit service" sub-panel): swap the
     * service type, override price, apply a discount, or move its start
     * time/duration/team member independently of the rest of the booking.
     */
    public function updateService(Request $request, Appointment $appointment, AppointmentService $appointmentService)
    {
        if ($appointmentService->appointment_id !== $appointment->id) {
            abort(404);
        }

        $request->validate([
            'service_id' => 'nullable|exists:services,id',
            'price' => 'required|numeric|min:0',
            'duration' => 'required|integer|min:5',
            'start_time' => 'required|date',
            'staff_id' => 'nullable|exists:staff,id',
            'discount_type' => 'nullable|in:flat,percent',
            'discount_value' => 'nullable|numeric|min:0',
            'discount_reason' => 'nullable|string|max:255',
        ]);

        $data = [
            'price' => $request->price,
            'duration' => $request->duration,
            'start_time' => Carbon::parse($request->start_time),
            'staff_id' => $request->staff_id ?: null,
            'discount_type' => $request->discount_type ?: null,
            'discount_value' => $request->discount_value ?: 0,
            'discount_reason' => $request->discount_reason ?: null,
        ];

        if ($request->filled('service_id')) {
            $service = Service::find($request->service_id);
            $data['service_id'] = $service->id;
            $data['name'] = $service->name;

            // Swapping to a different catalog service resets the baseline
            // it's discounted against; otherwise the line keeps whatever
            // original_price it was first booked/added at.
            if ((int) $request->service_id !== (int) $appointmentService->service_id) {
                $data['original_price'] = $service->price;
            }
        }

        $appointmentService->update($data);
        $appointment->syncFromServices();

        $this->logPriceOverrideIfDiscounted($appointmentService->fresh(), $request->discount_reason);

        return response()->json([
            'success' => true,
            'message' => 'Service updated.',
            'service' => $this->formatServiceLine($appointmentService->fresh()),
        ]);
    }

    /**
     * Remove a service line item. An appointment must always keep at least
     * one service, so the last remaining line can't be deleted this way.
     */
    public function destroyService(Appointment $appointment, AppointmentService $appointmentService)
    {
        if ($appointmentService->appointment_id !== $appointment->id) {
            abort(404);
        }

        if ($appointment->appointmentServices()->count() <= 1) {
            return response()->json([
                'success' => false,
                'message' => 'An appointment must have at least one service. Cancel the appointment instead.',
            ], 422);
        }

        $appointmentService->delete();
        $appointment->syncFromServices();

        return response()->json(['success' => true, 'message' => 'Service removed.']);
    }

    private function formatServiceLine(AppointmentService $s): array
    {
        return [
            'id' => $s->id,
            'service_id' => $s->service_id,
            'name' => $s->name,
            'price' => (float) $s->price,
            'original_price' => (float) ($s->original_price ?? $s->price),
            'final_price' => $s->final_price,
            'duration' => $s->duration,
            'discount_type' => $s->discount_type,
            'discount_value' => (float) $s->discount_value,
            'discount_amount' => (float) $s->discount_amount,
            'discount_reason' => $s->discount_reason,
            'start_time' => $s->start_time->format('H:i'),
            'start_time_label' => $s->start_time->format('g:i A'),
            'staff_id' => $s->staff_id,
            'staff_name' => optional($s->staff)->name,
        ];
    }

    /**
     * Validation rule for the staff attributed to an upsell: must be an
     * active (bookable, currently employed) staff member — see
     * Staff::scopeActive().
     */
    private function activeStaffRule()
    {
        return \Illuminate\Validation\Rule::exists('staff', 'id')->where(function ($query) {
            $query->where('bookable', true)
                ->where(function ($q) {
                    $q->whereNull('end_date')->orWhere('end_date', '>=', now()->toDateString());
                });
        });
    }

    /**
     * Validated {type, service_id|product_id} pair for an upsell, plus the
     * catalog item's own name — the upsell is always picked from the real
     * Services/Products catalog, never free-typed, so it flows into checkout
     * as a proper sale line item.
     */
    private function validatedUpsellCatalogItem(Request $request): array
    {
        $request->validate([
            'type' => 'required|in:service,product',
            'service_id' => 'required_if:type,service|nullable|exists:services,id',
            'product_id' => 'required_if:type,product|nullable|exists:products,id',
            'amount' => 'required|numeric|min:0',
            'staff_id' => ['required', $this->activeStaffRule()],
        ], [
            'staff_id.exists' => 'Selected staff member is not active.',
        ]);

        if ($request->type === 'service') {
            $service = Service::findOrFail($request->service_id);
            return ['type' => 'service', 'service_id' => $service->id, 'product_id' => null, 'name' => $service->name];
        }

        $product = Product::findOrFail($request->product_id);
        return ['type' => 'product', 'service_id' => null, 'product_id' => $product->id, 'name' => $product->name];
    }

    /**
     * Log an upsell (extra service or product) against a booking, attributed
     * to the active staff member who performed it (drawer "+ Add upsell").
     */
    public function addUpsell(Request $request, Appointment $appointment)
    {
        $item = $this->validatedUpsellCatalogItem($request);

        $upsell = AppointmentUpsell::create($item + [
            'appointment_id' => $appointment->id,
            'staff_id' => $request->staff_id,
            'amount' => $request->amount,
            'created_by' => auth()->id(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Upsell added.',
            'upsell' => $this->formatUpsellLine($upsell->fresh('staff')),
        ]);
    }

    public function updateUpsell(Request $request, Appointment $appointment, AppointmentUpsell $appointmentUpsell)
    {
        if ($appointmentUpsell->appointment_id !== $appointment->id) {
            abort(404);
        }

        $item = $this->validatedUpsellCatalogItem($request);

        $appointmentUpsell->update($item + [
            'staff_id' => $request->staff_id,
            'amount' => $request->amount,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Upsell updated.',
            'upsell' => $this->formatUpsellLine($appointmentUpsell->fresh('staff')),
        ]);
    }

    public function destroyUpsell(Appointment $appointment, AppointmentUpsell $appointmentUpsell)
    {
        if ($appointmentUpsell->appointment_id !== $appointment->id) {
            abort(404);
        }

        $appointmentUpsell->delete();

        return response()->json(['success' => true, 'message' => 'Upsell removed.']);
    }

    private function formatUpsellLine(AppointmentUpsell $u): array
    {
        return [
            'id' => $u->id,
            'type' => $u->type,
            'service_id' => $u->service_id,
            'product_id' => $u->product_id,
            'name' => $u->name,
            'amount' => (float) $u->amount,
            'staff_id' => $u->staff_id,
            'staff_name' => optional($u->staff)->name ?? 'Unassigned',
        ];
    }

    public function payment(Appointment $appointment)
    {
        $appointment->load('customer', 'staff', 'upsells.staff');
        $serviceItems = $this->appointmentServiceItems($appointment);
        $servicesTotal = array_sum(array_column($serviceItems, 'price'));
        $servicesDuration = array_sum(array_column($serviceItems, 'duration'));
        $serviceDiscountTotal = array_sum(array_column($serviceItems, 'discount_amount'));
        $products = Product::orderBy('name')->get();

        $upsellItems = $appointment->upsells->map(fn($u) => $this->formatUpsellLine($u))->values()->all();
        $upsellsTotal = array_sum(array_column($upsellItems, 'amount'));

        $combos = Combo::with('services')->where('status', 'active')->orderBy('name')->get()
            ->map(fn($c) => [
                'id' => $c->id,
                'name' => $c->name,
                'price' => (float) $c->price,
                'quantity_included' => $c->quantity_included ?? $c->services->count(),
                'services' => $c->services->map(fn($s) => ['id' => $s->id, 'name' => $s->name, 'duration' => $s->duration])->values(),
            ])
            ->filter(fn($c) => $c['services']->isNotEmpty())
            ->values();

        $pendingPackageServices = $appointment->customer
            ? $appointment->customer->redeemablePackageServices()
            : [];

        // A combo can already have been sold at booking time (via the
        // calendar drawer's own "+ Add Combo") - it's just never been paid
        // for yet. Surface it here so the checkout total/summary accounts
        // for it from the moment the page loads, not only after this
        // checkout's own "sell a package" picker is used.
        $unpaidPackages = ClientPackage::where('appointment_id', $appointment->id)
            ->whereNull('sale_id')
            ->get(['id', 'combo_name', 'price_paid']);
        $unpaidPackagesTotal = (float) $unpaidPackages->sum('price_paid');

        return view('revenue.payment', compact(
            'appointment',
            'serviceItems',
            'servicesTotal',
            'servicesDuration',
            'serviceDiscountTotal',
            'products',
            'upsellItems',
            'upsellsTotal',
            'combos',
            'pendingPackageServices',
            'unpaidPackages',
            'unpaidPackagesTotal'
        ));
    }

    public function storePayment(Request $request, Appointment $appointment)
    {
        $request->validate([
            'discount_type' => 'nullable|in:flat,percent',
            'discount_value' => 'nullable|numeric|min:0',
            'tip_amount' => 'nullable|numeric|min:0',
            'products' => 'nullable|array',
            'products.*.product_id' => 'required_with:products|exists:products,id',
            'products.*.quantity' => 'required_with:products|integer|min:1',
            'payments.cash' => 'nullable|numeric|min:0',
            'payments.card' => 'nullable|numeric|min:0',
            'payments.online_transfer' => 'nullable|numeric|min:0',
            // New combo packages sold at this checkout - only services done
            // today need naming; anything else the package includes is
            // automatically banked as a pending balance.
            'packages' => 'nullable|array',
            'packages.*.combo_id' => 'required_with:packages|integer|exists:combos,id',
            'packages.*.immediate_service_ids' => 'nullable|array',
            'packages.*.immediate_service_ids.*' => 'integer|exists:services,id',
            'packages.*.service_staff' => 'nullable|array',
            // Previously-purchased pending balance being redeemed today -
            // each a "{client_package_id}:{service_id}" token, fully
            // re-validated in buildPackageRedemptionPlans().
            'redeem_service_ids' => 'nullable|array',
            'redeem_service_ids.*' => 'string',
        ]);

        $customer = $appointment->customer_id
            ? $appointment->customer
            : $this->findOrCreateCustomer($appointment->phone, $appointment->customer_name);

        // The expiration engine: reconcile this client's packages against
        // real time before trusting anything about what's still redeemable.
        ClientPackage::expireDue($customer->id);

        [$packagePlans, $packagePlanError] = $this->buildPackagePurchasePlans($request->input('packages', []));
        if ($packagePlanError) {
            return redirect()->back()->withInput()->with('error', $packagePlanError);
        }

        [$redemptionPlans, $redemptionPlanError] = $this->buildPackageRedemptionPlans($request->input('redeem_service_ids', []), $customer);
        if ($redemptionPlanError) {
            return redirect()->back()->withInput()->with('error', $redemptionPlanError);
        }

        // Nothing is written yet - redeemed/immediate package services are
        // always priced at 0, so they can't change what's due either way.
        // Writes only happen once the payment total below is confirmed
        // valid, so a rejected checkout never leaves a service half-redeemed
        // with no completed sale behind it.
        $serviceItems = $this->appointmentServiceItems($appointment);
        $servicesTotal = array_sum(array_column($serviceItems, 'price'));

        // A combo bought at booking time already has its ClientPackage row
        // (and any immediate services already added as 0-cost lines) - it's
        // just never been paid for yet. Charge for it here alongside any
        // brand-new combo being sold at this checkout.
        $unpaidBookingPackages = ClientPackage::where('appointment_id', $appointment->id)->whereNull('sale_id')->get();

        $packagesTotal = array_reduce($packagePlans, fn($sum, $plan) => $sum + (float) $plan['combo']->price, 0)
            + (float) $unpaidBookingPackages->sum('price_paid');

        $productLines = [];
        $productsTotal = 0;
        foreach ($request->input('products', []) as $line) {
            $product = Product::find($line['product_id']);
            if (!$product) continue;
            $qty = (int) $line['quantity'];
            $lineTotal = $product->price * $qty;
            $productsTotal += $lineTotal;
            $productLines[] = [
                'product_id' => $product->id,
                'name' => $product->name,
                'price' => $product->price,
                'quantity' => $qty,
                'total' => $lineTotal,
            ];
        }

        // Upsells logged from the calendar drawer are charged at checkout too,
        // folded into services/products revenue by their own type.
        $upsells = $appointment->upsells;
        $upsellsTotal = (float) $upsells->sum('amount');
        $servicesTotal += (float) $upsells->where('type', 'service')->sum('amount');
        $productsTotal += (float) $upsells->where('type', 'product')->sum('amount');

        $subtotal = $servicesTotal + $productsTotal + $packagesTotal;

        $discountType = $request->discount_type;
        $discountValue = (float) ($request->discount_value ?? 0);
        $discountAmount = 0;
        if ($discountType === 'percent') {
            $discountAmount = round($subtotal * min($discountValue, 100) / 100, 2);
        } elseif ($discountType === 'flat') {
            $discountAmount = round(min($discountValue, $subtotal), 2);
        }

        $tipAmount = round((float) ($request->tip_amount ?? 0), 2);
        $totalAmount = round(max(0, $subtotal - $discountAmount) + $tipAmount, 2);

        $payments = array_filter([
            'cash' => (float) ($request->input('payments.cash', 0)),
            'card' => (float) ($request->input('payments.card', 0)),
            'online_transfer' => (float) ($request->input('payments.online_transfer', 0)),
        ], fn($amount) => $amount > 0);

        $paidTotal = round(array_sum($payments), 2);

        // A checkout that's entirely a free package redemption can legitimately
        // have nothing due - only reject when the tendered amount doesn't
        // actually match what's owed, not merely because no method was filled in.
        if (abs($paidTotal - $totalAmount) > 0.01) {
            return redirect()->back()
                ->with('error', sprintf(
                    'Payment total (%.2f QAR) does not match the amount due (%.2f QAR).',
                    $paidTotal,
                    $totalAmount
                ))->withInput();
        }

        // Only now, with the payment confirmed valid, do we actually touch
        // the database - and all of it atomically, so a mid-sequence failure
        // (a bad row, an unexpected exception) can never leave a service
        // marked redeemed with no completed sale behind it, or a sale with
        // no matching appointment update.
        // Seeded with any packages already created at booking time - they
        // still need a sale_id and a SaleItem now that they're being paid for.
        $purchasedPackages = $unpaidBookingPackages->all();
        $pointsEarned = 0;

        DB::transaction(function () use (
            $appointment,
            $customer,
            $redemptionPlans,
            $packagePlans,
            $serviceItems,
            $servicesTotal,
            $productLines,
            $productsTotal,
            $packagesTotal,
            $upsells,
            $discountType,
            $discountValue,
            $discountAmount,
            $tipAmount,
            $totalAmount,
            $payments,
            &$purchasedPackages,
            &$pointsEarned
        ) {
            $lastLine = $appointment->appointmentServices()->orderByDesc('start_time')->first();
            $cursor = $lastLine ? $lastLine->end_time : $appointment->appointment_datetime;

            foreach ($redemptionPlans as $plan) {
                $service = $plan['service'];
                $package = $plan['client_package'];

                $appointmentService = $this->addRedeemedServiceLine($appointment, $service, $package->combo_name, $cursor);
                ClientPackageService::create([
                    'client_package_id' => $package->id,
                    'service_id' => $service->id,
                    'service_name' => $service->name,
                    'status' => 'redeemed',
                    'redeemed_at' => now(),
                    'appointment_service_id' => $appointmentService->id,
                ]);
                $package->refreshStatus();
            }

            $purchasedPackages = array_merge(
                $purchasedPackages,
                $this->createPackagePurchases($appointment, $customer, $packagePlans, $cursor)
            );

            // Re-fetch now that the redemption/immediate lines above actually
            // exist, so the sale's service line items include them (still at
            // their 0 price - $servicesTotal itself doesn't change). The
            // relation was already cached by the earlier pre-write call, so
            // it must be force-reloaded or these new rows won't show up.
            if ($redemptionPlans || $packagePlans) {
                $serviceItems = $this->appointmentServiceItems($appointment->load('appointmentServices'));

                // Fold the newly-performed package services into service_name
                // (replacing a "Decide in Salon" placeholder if that's all
                // there was) so the Enhanced Calendar block - which derives
                // its width from that flat field, not the line items - grows
                // or shrinks to match only what's actually being done today.
                $appointment->syncFromServices();
            }

            $sale = Sale::create([
                'appointment_id' => $appointment->id,
                'customer_id' => $customer->id,
                'staff_id' => $appointment->staff_id,
                'created_by' => auth()->id(),
                'branch' => $appointment->branch,
                'services_total' => $servicesTotal,
                'products_total' => $productsTotal,
                'packages_total' => $packagesTotal,
                'discount_type' => $discountType,
                'discount_value' => $discountValue,
                'discount_amount' => $discountAmount,
                'tip_amount' => $tipAmount,
                'total_amount' => $totalAmount,
            ]);

            foreach ($serviceItems as $item) {
                SaleItem::create([
                    'sale_id' => $sale->id,
                    'type' => 'service',
                    'staff_id' => $item['staff_id'] ?? null,
                    'name' => $item['name'],
                    'price' => $item['price'],
                    'original_price' => $item['original_price'],
                    'discount_amount' => $item['discount_amount'],
                    'quantity' => 1,
                    'total' => $item['price'],
                ]);
            }

            foreach ($productLines as $line) {
                SaleItem::create([
                    'sale_id' => $sale->id,
                    'type' => 'product',
                    'product_id' => $line['product_id'],
                    'name' => $line['name'],
                    'price' => $line['price'],
                    'quantity' => $line['quantity'],
                    'total' => $line['total'],
                ]);
            }

            foreach ($upsells as $upsell) {
                SaleItem::create([
                    'sale_id' => $sale->id,
                    'type' => $upsell->type,
                    'product_id' => $upsell->product_id,
                    'staff_id' => $upsell->staff_id,
                    'name' => $upsell->name . ' (Upsell)',
                    'price' => $upsell->amount,
                    'quantity' => 1,
                    'total' => $upsell->amount,
                ]);
            }

            foreach ($purchasedPackages as $pkg) {
                $pkg->update(['sale_id' => $sale->id]);

                SaleItem::create([
                    'sale_id' => $sale->id,
                    'type' => 'package',
                    'name' => $pkg->combo_name . ' (Package)',
                    'price' => $pkg->price_paid,
                    'original_price' => $pkg->price_paid,
                    'quantity' => 1,
                    'total' => $pkg->price_paid,
                ]);
            }

            foreach ($payments as $method => $amount) {
                SalePayment::create([
                    'sale_id' => $sale->id,
                    'method' => $method,
                    'amount' => round($amount, 2),
                ]);
            }

            $dominantMethod = $payments ? array_search(max($payments), $payments) : null;

            $appointment->update([
                'status' => 'completed',
                'price' => $servicesTotal,
                'payment_method' => $dominantMethod,
                'paid_at' => now(),
            ]);

            $pointsEarned = $customer->earnPointsForSale($sale);
        });

        $packageNote = count($purchasedPackages)
            ? ' ' . count($purchasedPackages) . ' package' . (count($purchasedPackages) === 1 ? '' : 's') . ' sold.'
            : '';

        return redirect()->route('appointments.revenue.index')
            ->with('success', 'Payment recorded successfully.' . $packageNote . ($pointsEarned ? " {$customer->name} earned {$pointsEarned} loyalty points." : ''));
    }

    /**
     * Validates the combo packages a client is buying at this checkout or
     * booking: a package only needs to say which of its pool services (up
     * to quantity_included of them) are being done today - nothing forces
     * every included slot to be decided up front. Whatever's left over is
     * automatically banked as a pending balance, not locked to a specific
     * service, and gets chosen later at redemption time instead. Returns
     * [plans, null] on success or [[], message] on the first invalid entry -
     * nothing is written to the database yet.
     */
    private function buildPackagePurchasePlans(array $packages): array
    {
        $plans = [];

        foreach ($packages as $pkg) {
            $rawImmediateIds = array_values(array_unique(array_map('intval', $pkg['immediate_service_ids'] ?? [])));
            $rawServiceStaff = is_array($pkg['service_staff'] ?? null) ? $pkg['service_staff'] : [];

            $combo = Combo::with('services')->find($pkg['combo_id'] ?? null);
            if (!$combo) {
                return [[], 'Selected combo package no longer exists.'];
            }

            $poolServices = $combo->services->keyBy('id');
            $quantityIncluded = $combo->quantity_included ?? $poolServices->count();

            if (array_diff($rawImmediateIds, $poolServices->keys()->all())) {
                return [[], "Selected services are not part of the \"{$combo->name}\" package."];
            }

            if (count($rawImmediateIds) > $quantityIncluded) {
                return [[], "\"{$combo->name}\" only includes {$quantityIncluded} service(s) for this visit."];
            }

            // Each service being done today can be assigned to its own staff
            // member, independent of the appointment's main "Team Member" -
            // skill-checked right here, since this is the one place that
            // already knows which catalog service each pool id maps to.
            $serviceStaff = [];
            foreach ($rawImmediateIds as $sid) {
                $rawAssigned = $rawServiceStaff[$sid] ?? null;
                if ($rawAssigned === null || $rawAssigned === '') {
                    continue;
                }

                $assignedStaffId = (int) $rawAssigned;
                $service = $poolServices->get($sid);
                $assignedStaff = \App\Models\Staff::find($assignedStaffId);

                if (!$assignedStaff) {
                    return [[], "Selected staff member for \"{$service->name}\" no longer exists."];
                }

                if (!empty($this->unskilledServices($assignedStaffId, [$service->name]))) {
                    return [[], "{$assignedStaff->name} is not skilled to do \"{$service->name}\"."];
                }

                $serviceStaff[$sid] = $assignedStaffId;
            }

            $plans[] = [
                'combo' => $combo,
                'immediate_ids' => $rawImmediateIds,
                'service_staff' => $serviceStaff,
                'pool' => $poolServices,
                'quantity_included' => $quantityIncluded,
            ];
        }

        return [$plans, null];
    }

    /**
     * Validates each "{client_package_id}:{service_id}" token the client
     * wants redeemed: the package must actually belong to them, still be
     * within its validity window, still have an unused slot, the service
     * must be part of that package's combo, and must not already have been
     * redeemed from this specific package before - the one place duplicate
     * redemption across a package's lifecycle gets ruled out. Also checked
     * across the whole batch, so a package with only one slot left can't
     * have two different services claimed against it in the same request.
     * Nothing is written to the database yet.
     */
    private function buildPackageRedemptionPlans(array $tokens, Customer $customer): array
    {
        $plans = [];
        $claimedPerPackage = [];

        foreach ($tokens as $token) {
            $parts = explode(':', (string) $token, 2);
            $package = ClientPackage::with('redeemedServices')->find((int) ($parts[0] ?? 0));
            $service = Service::find((int) ($parts[1] ?? 0));

            if (!$package || !$service) {
                return [[], 'One of the selected package services is no longer available to redeem.'];
            }

            if ((int) $package->customer_id !== (int) $customer->id) {
                return [[], 'That package does not belong to this client.'];
            }

            if (!$package->canRedeem()) {
                $package->refreshStatus();
                return [[], "{$package->combo_name} has expired or has no remaining balance to redeem."];
            }

            $combo = Combo::with('services')->find($package->combo_id);
            if (!$combo || !$combo->services->contains('id', $service->id)) {
                return [[], "\"{$service->name}\" is not part of {$package->combo_name}."];
            }

            if ($package->redeemedServices->contains('service_id', $service->id)) {
                return [[], "\"{$service->name}\" has already been redeemed from {$package->combo_name}."];
            }

            $claimedPerPackage[$package->id] = ($claimedPerPackage[$package->id] ?? 0) + 1;
            if ($package->redeemed_count + $claimedPerPackage[$package->id] > $package->quantity_included) {
                return [[], "{$package->combo_name} doesn't have enough remaining balance for this selection."];
            }

            $plans[] = ['client_package' => $package, 'service' => $service];
        }

        return [$plans, null];
    }

    /**
     * Appends a service line performed today but already paid for via a
     * combo package - priced at 0 through the same discount mechanism used
     * for manual price overrides, so it flows through the existing
     * checkout totals/reporting without any special-casing. $cursor is
     * advanced past this line's duration for whatever gets added next.
     */
    private function addRedeemedServiceLine(Appointment $appointment, Service $service, string $comboName, Carbon &$cursor, ?int $staffId = null): AppointmentService
    {
        $line = AppointmentService::create([
            'appointment_id' => $appointment->id,
            'service_id' => $service->id,
            'staff_id' => $staffId ?? $appointment->staff_id,
            'name' => $service->name,
            'price' => $service->price,
            'original_price' => $service->price,
            'duration' => $service->duration,
            'start_time' => $cursor->copy(),
            'discount_type' => 'percent',
            'discount_value' => 100,
            'discount_reason' => "Redeemed from {$comboName} package",
        ]);

        $cursor = $cursor->copy()->addMinutes($service->duration);

        return $line;
    }

    /**
     * Creates the ClientPackage entitlement for each validated package plan,
     * adding a real 0-cost service line (and its ClientPackageService
     * record) for every service marked immediate. Whatever the package
     * includes beyond that is left as pure unused balance - quantity_included
     * minus however many just got redeemed - with no row of its own until a
     * specific service is actually picked for it, at booking or later. This
     * deliberately never touches Sale/SalePayment - packages can be picked
     * at booking time, long before checkout actually collects payment for
     * them, exactly like a normal booked service isn't "sold" until
     * checkout. Returns the created ClientPackage models so the caller can
     * total/charge them whenever payment does happen.
     */
    private function createPackagePurchases(Appointment $appointment, Customer $customer, array $packagePlans, Carbon &$cursor): array
    {
        $purchased = [];

        foreach ($packagePlans as $plan) {
            $combo = $plan['combo'];

            $clientPackage = ClientPackage::create([
                'customer_id' => $customer->id,
                'combo_id' => $combo->id,
                'appointment_id' => $appointment->id,
                'combo_name' => $combo->name,
                'price_paid' => $combo->price,
                'quantity_included' => $plan['quantity_included'],
                'purchased_at' => now(),
                'expires_at' => now()->addDays($combo->validity_days ?: 7),
                'status' => 'active',
            ]);

            foreach ($plan['immediate_ids'] as $sid) {
                $service = $plan['pool']->get($sid);
                $assignedStaffId = $plan['service_staff'][$sid] ?? null;
                $appointmentService = $this->addRedeemedServiceLine($appointment, $service, $combo->name, $cursor, $assignedStaffId);

                ClientPackageService::create([
                    'client_package_id' => $clientPackage->id,
                    'service_id' => $sid,
                    'service_name' => $service->name,
                    'status' => 'redeemed',
                    'redeemed_at' => now(),
                    'appointment_service_id' => $appointmentService->id,
                ]);
            }

            $clientPackage->refreshStatus();
            $purchased[] = $clientPackage;
        }

        return $purchased;
    }
}
