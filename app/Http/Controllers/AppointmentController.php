<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Staff;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\SalePayment;
use App\Models\Product;
use App\Models\Customer;
use App\Models\Service;
use App\Models\AppointmentService;
use App\Models\Appointment;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

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

        return view('appointments.index', compact(
            'appointments',
            'period',
            'branch',
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
            'customer_name' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:20',
            'appointment_datetime' => 'required|date',
            'service_name' => 'required|array|min:1',
            'service_name.*' => 'string|max:255',
            'branch' => 'required|in:old_airport,wakrah,home_service',
            'price' => 'nullable|numeric',
            'booking_agent_id' => 'nullable|exists:users,id',
            'staff_id' => 'required|exists:staff,id'
        ];

        $messages = [
            'appointment_datetime.required' => 'Appointment date and time is required.',
            'service_name.required' => 'At least one service must be selected.',
            'branch.required' => 'Please select a branch.',
            'branch.in' => 'Selected branch is invalid.',
            'staff_id.required' => 'Please select a staff member.',
            'staff_id.exists' => 'Selected staff does not exist.',
            'booking_agent_id.exists' => 'Selected booking agent does not exist.',
            'service_name.*.string' => 'Each service must be a valid string.',
            'service_name.*.max' => 'Each service name cannot exceed 255 characters.'
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

        $startTime = Carbon::parse($request->appointment_datetime);
        // total duration of selected services
        $duration = Service::whereIn('name', $request->service_name)->sum('duration') ?? 30;

        if ($this->staffHasTimeConflict($staffId, $startTime, $duration)) {
            // return redirect()->back()->with('error', 'Staff is already booked during this time.')->withInput();
            return redirect()->route('appointments.calendar', [
                'date' => $startTime->toDateString(),
                'staff_id' => $staffId
            ])->with('error', 'Staff is already booked during this time.');
        }


        $services = implode(', ', $request->service_name);
        $phone = $request->phone ? preg_replace('/\D/', '', $request->phone) : '';

        // Walk-ins (no phone given) aren't linked to a client profile or revenue history.
        $customerId = null;
        $lifetimeRevenue = (float) ($request->price ?? 0);

        if ($phone !== '') {
            $previousRevenue = Appointment::whereRaw("REPLACE(REPLACE(REPLACE(phone, ' ', ''), '(', ''), ')', '') = ?", [$phone])
                ->sum('price');
            $lifetimeRevenue = $previousRevenue + ($request->price ?? 0);

            $customer = $this->findOrCreateCustomer($phone, $request->customer_name);
            $customerId = $customer->id;
        }

        $appointment = Appointment::create(array_merge(
            $request->all(),
            [
                'lifetime_revenue' => $lifetimeRevenue,
                'service_name' => $services,
                'customer_name' => $request->customer_name ?: 'Walk-in',
                'phone' => $phone,
                'customer_id' => $customerId,
            ]
        ));

        $this->createServiceLineItems($appointment, $request->service_name, (int) $staffId, $startTime);

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
            'phone' => 'nullable|string|max:20',
            'appointment_datetime' => 'required|date',
            'service_name' => 'required|array',
            'service_name.*' => 'string|max:255',
            'branch' => 'required|in:old_airport,wakrah,home_service',
            'price' => 'nullable|numeric',
            'booking_agent_id' => 'nullable|exists:users,id',
            'staff_id' => 'nullable|exists:staff,id'
        ]);

        $services = implode(', ', $request->service_name);
        $phone = $request->phone ? preg_replace('/\D/', '', $request->phone) : '';

        $staffId = $request->staff_id ?? $appointment->staff_id;
        $startTime = Carbon::parse($request->appointment_datetime);
        $duration = Service::whereIn('name', $request->service_name)->sum('duration') ?? 30;

        if ($this->staffHasTimeConflict($staffId, $startTime, $duration, $appointment->id)) {
            // return redirect()->back()->with('error', 'Staff is already booked during this time.')->withInput();
            return redirect()->route('appointments.calendar', [
                'date' => $startTime->toDateString(),
                'staff_id' => $staffId
            ])->with('error', 'Staff is already booked during this time.');
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
        $services = Service::orderBy('name')->get();
        $agents = User::where('role', 'agent')->select('id', 'name')->get();

        $servicesCatalog = $services->map(function ($s) {
            return [
                'id' => $s->id,
                'name' => $s->name,
                'price' => (float) $s->price,
                'duration' => (int) $s->duration,
            ];
        })->values();

        return view('appointments.calendar', compact('staffs', 'services', 'agents', 'servicesCatalog'));
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

    private function filteredAppointmentQuery(Request $request)
    {
        $query = Appointment::query();

        if ($request->filled('branch'))       $query->where('branch', $request->branch);
        if ($request->filled('staff_id'))     $query->where('staff_id', $request->staff_id);
        if ($request->filled('service_name')) $query->where('service_name', 'like', '%' . $request->service_name . '%');
        if ($request->filled('agent_id'))     $query->where('booking_agent_id', $request->agent_id);

        return $query;
    }

    public function calendarData(Request $request)
    {
        $view = in_array($request->view, ['week', '3day', 'month']) ? $request->view : 'day';
        $anchor = $request->date ? Carbon::parse($request->date)->startOfDay() : now()->startOfDay();
        $branch = $request->branch ?? 'old_airport';

        $staffQuery = Staff::select('id', 'name', 'weekly_off', 'availability_status', 'off_from', 'off_to', 'profile_picture')
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

            $appointments = $this->filteredAppointmentQuery($request)
                ->whereBetween('appointment_datetime', [$weekStart, $weekEnd->copy()->endOfDay()])
                ->orderBy('appointment_datetime')
                ->get()
                ->groupBy([fn($a) => $a->staff_id, fn($a) => Carbon::parse($a->appointment_datetime)->format('Y-m-d')])
                ->map(fn($byDate) => $byDate->map(fn($list) => $list->map(fn($a) => [
                    'id'            => $a->id,
                    'time'          => Carbon::parse($a->appointment_datetime)->format('g:i A'),
                    'start_minutes' => Carbon::parse($a->appointment_datetime)->hour * 60 + Carbon::parse($a->appointment_datetime)->minute,
                    'customer_name' => $a->customer_name,
                    'service_name'  => $a->service_name,
                    'status'        => $a->status,
                    'price'         => $a->price,
                ])->values()));

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

        $appointments = [];
        $this->filteredAppointmentQuery($request)
            ->whereDate('appointment_datetime', $anchor->toDateString())
            ->get()
            ->each(function ($a) use (&$appointments, $dayEnd) {
                $start = Carbon::parse($a->appointment_datetime);
                $duration = Service::where('name', $a->service_name)->value('duration') ?? 30;
                $end = $start->copy()->addMinutes($duration);
                if ($end->gt($dayEnd)) {
                    $end = $dayEnd;
                }

                $appointments[$a->staff_id][] = [
                    'id'            => $a->id,
                    'start'         => $start->format('H:i'),
                    'end'           => $end->format('H:i'),
                    'duration'      => $duration,
                    'start_minutes' => $start->hour * 60 + $start->minute,
                    'end_minutes'   => $end->hour * 60 + $end->minute,
                    'service_name'  => $a->service_name,
                    'status'        => $a->status,
                    'customer_name' => $a->customer_name,
                    'phone'         => $a->phone,
                    'price'         => $a->price,
                ];
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

    private function staffHasTimeConflict(int $staffId, Carbon $newStart, int $newDuration, ?int $ignoreAppointmentId = null)
    {
        $newEnd = $newStart->copy()->addMinutes((int) $newDuration);
        $query = Appointment::where('staff_id', $staffId);

        if ($ignoreAppointmentId) {
            $query->where('id', '!=', $ignoreAppointmentId);
        }

        $appointments = $query->get();
        foreach ($appointments as $appointment) {
            $existingStart = Carbon::parse($appointment->appointment_datetime);
            $serviceNames = array_map('trim', explode(',', $appointment->service_name));
            $existingDuration = Service::whereIn('name', $serviceNames)->sum('duration');

            // fallback safety
            $existingDuration = (int) ($existingDuration ?: 30);
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
            'services' => 'required|array|min:1',
            'appointment_datetime' => 'required|date',
            'branch' => 'required'
        ]);

        $appointmentTime = Carbon::parse($request->appointment_datetime);
        $branch = $request->branch;

        // Total duration of selected services
        $duration = Service::whereIn('name', $request->services)
            ->sum('duration') ?? 30;

        // Staff eligible for at least one of the selected services, per the
        // explicit service_staff team-member assignment (Services > Team members).
        $eligibleStaffIds = \Illuminate\Support\Facades\DB::table('service_staff')
            ->join('services', 'services.id', '=', 'service_staff.service_id')
            ->whereIn('services.name', $request->services)
            ->pluck('service_staff.staff_id')
            ->unique();

        $staffs = Staff::where('availability_status', 'present')
            ->whereIn('id', $eligibleStaffIds)
            ->where(function ($q) use ($branch) {
                $q->where('branch', $branch)
                    ->orWhere('branch', 'both');
            })
            ->get()
            ->filter(function ($staff) use ($appointmentTime, $duration) {

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
                    $duration
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
     * JSON detail payload for the appointment drawer.
     */
    public function show(Appointment $appointment)
    {
        $appointment->load('customer', 'staff', 'agent', 'appointmentServices.staff');
        $serviceLines = $appointment->appointmentServices->map(fn($s) => $this->formatServiceLine($s))->values()->all();

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

        $duration = Service::whereIn('name', array_map('trim', explode(',', $appointment->service_name)))->sum('duration') ?: 30;

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
                'duration' => $catalog->get($name)->duration ?? 0,
            ])->values()->all();
        }

        return $lines->map(fn($s) => [
            'name' => $s->name,
            'price' => $s->final_price,
            'duration' => $s->duration,
        ])->values()->all();
    }

    /**
     * Create line items for a freshly booked appointment, stacking each
     * service sequentially from the appointment's start time.
     */
    private function createServiceLineItems(Appointment $appointment, array $serviceNames, int $staffId, Carbon $startTime): void
    {
        $catalog = Service::whereIn('name', $serviceNames)->get()->keyBy('name');
        $cursor = $startTime->copy();

        foreach ($serviceNames as $name) {
            $service = $catalog->get($name);
            $duration = $service->duration ?? 30;

            AppointmentService::create([
                'appointment_id' => $appointment->id,
                'service_id' => $service->id ?? null,
                'staff_id' => $staffId,
                'name' => $name,
                'price' => $service->price ?? 0,
                'duration' => $duration,
                'start_time' => $cursor->copy(),
            ]);

            $cursor->addMinutes($duration);
        }
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
        ]);

        $data = [
            'price' => $request->price,
            'duration' => $request->duration,
            'start_time' => Carbon::parse($request->start_time),
            'staff_id' => $request->staff_id ?: null,
            'discount_type' => $request->discount_type ?: null,
            'discount_value' => $request->discount_value ?: 0,
        ];

        if ($request->filled('service_id')) {
            $service = Service::find($request->service_id);
            $data['service_id'] = $service->id;
            $data['name'] = $service->name;
        }

        $appointmentService->update($data);
        $appointment->syncFromServices();

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
            'final_price' => $s->final_price,
            'duration' => $s->duration,
            'discount_type' => $s->discount_type,
            'discount_value' => (float) $s->discount_value,
            'start_time' => $s->start_time->format('H:i'),
            'start_time_label' => $s->start_time->format('g:i A'),
            'staff_id' => $s->staff_id,
            'staff_name' => optional($s->staff)->name,
        ];
    }

    public function payment(Appointment $appointment)
    {
        $appointment->load('customer', 'staff');
        $serviceItems = $this->appointmentServiceItems($appointment);
        $servicesTotal = array_sum(array_column($serviceItems, 'price'));
        $products = Product::orderBy('name')->get();

        return view('revenue.payment', compact('appointment', 'serviceItems', 'servicesTotal', 'products'));
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
        ]);

        $serviceItems = $this->appointmentServiceItems($appointment);
        $servicesTotal = array_sum(array_column($serviceItems, 'price'));

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

        $subtotal = $servicesTotal + $productsTotal;

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

        if (empty($payments) || abs($paidTotal - $totalAmount) > 0.01) {
            return redirect()->back()
                ->with('error', sprintf(
                    'Payment total (%.2f QAR) does not match the amount due (%.2f QAR).',
                    $paidTotal,
                    $totalAmount
                ))->withInput();
        }

        $customer = $appointment->customer_id
            ? $appointment->customer
            : $this->findOrCreateCustomer($appointment->phone, $appointment->customer_name);

        $sale = Sale::create([
            'appointment_id' => $appointment->id,
            'customer_id' => $customer->id,
            'staff_id' => $appointment->staff_id,
            'created_by' => auth()->id(),
            'branch' => $appointment->branch,
            'services_total' => $servicesTotal,
            'products_total' => $productsTotal,
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
                'name' => $item['name'],
                'price' => $item['price'],
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

        foreach ($payments as $method => $amount) {
            SalePayment::create([
                'sale_id' => $sale->id,
                'method' => $method,
                'amount' => round($amount, 2),
            ]);
        }

        $dominantMethod = array_search(max($payments), $payments);

        $appointment->update([
            'status' => 'completed',
            'price' => $servicesTotal,
            'payment_method' => $dominantMethod,
            'paid_at' => now(),
        ]);

        $pointsEarned = $customer->earnPointsForSale($sale);

        return redirect()->route('appointments.revenue.index')
            ->with('success', 'Payment recorded successfully.' . ($pointsEarned ? " {$customer->name} earned {$pointsEarned} loyalty points." : ''));
    }
}
