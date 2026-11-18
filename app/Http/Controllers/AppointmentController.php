<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Lead;
use App\Models\AdLeadEntry;
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
use App\Models\ClientFreebieCredit;
use App\Models\ServiceFreebiePackage;
use App\Models\LoyaltyTransaction;
use App\Services\ServiceFreebieLifecycleService;
use App\Support\BookingConflictValidator;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class AppointmentController extends Controller
{
    private ?BookingConflictValidator $conflictValidatorInstance = null;
    private ?ServiceFreebieLifecycleService $freebieLifecycleInstance = null;

    private function conflictValidator(): BookingConflictValidator
    {
        return $this->conflictValidatorInstance ??= new BookingConflictValidator();
    }

    private function freebieService(): ServiceFreebieLifecycleService
    {
        return $this->freebieLifecycleInstance ??= new ServiceFreebieLifecycleService($this->conflictValidator());
    }
    const BRANCH_LABELS = [
        'old_airport' => 'Old Airport',
        'wakrah' => 'Al Wakrah',
    ];

    /**
     * Normalize incoming branch values ('Al Wakrah', 'Wakrah', 'al_wakrah',
     * 'Old Airport', etc.) to canonical DB keys ('old_airport' or 'wakrah'),
     * while leaving unrecognized values untouched so validation still rejects
     * retired keys like 'home_service'.
     */
    private function normalizeRequestBranch(Request $request, bool $fallbackToActiveBranch = true): void
    {
        if ($request->filled('branch') && is_string($request->input('branch'))) {
            $normalized = Customer::normalizeBranch($request->input('branch'));
            if ($normalized !== null) {
                $request->merge(['branch' => $normalized]);
            }
            return;
        }

        if ($fallbackToActiveBranch && !$request->filled('branch')) {
            foreach (['active_branch', 'calendar_branch'] as $fallbackKey) {
                if ($request->filled($fallbackKey) && is_string($request->input($fallbackKey))) {
                    $normalized = Customer::normalizeBranch($request->input($fallbackKey));
                    if ($normalized !== null) {
                        $request->merge(['branch' => $normalized]);
                        return;
                    }
                }
            }
        }
    }

    /**
     * Check whether a staff member is assigned to the given canonical branch
     * ('both' or null/empty matches both branches).
     */
    private function staffMatchesBranch(?Staff $staff, string $branch): bool
    {
        if (!$staff || empty($staff->branch) || $staff->branch === 'both') {
            return true;
        }

        $staffBranch = Customer::normalizeBranch($staff->branch) ?? $staff->branch;

        return $staffBranch === $branch;
    }

    const PAID_BOOKING_LOCKED_MESSAGE = 'This booking is already paid and closed. Only a user with Finance edit access can void/cancel a paid sale before making changes.';
    const FINANCE_REVERSAL_FORBIDDEN_MESSAGE = 'Finance edit permission is required to cancel, reverse, or delete a paid booking.';
    const UNPICKED_COMBO_CHECKOUT_MESSAGE = "Please select the combo service(s) performed today before completing checkout, or confirm 'Package Pre-Purchase Only' if 0 services were performed today.";

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

    private function rejectPaidBookingMutation(Request $request, bool $alwaysJson = false)
    {
        return $this->conflictValidator()->toResponse($request, [
            'reason' => 'paid_locked',
            'field' => 'booking',
            'message' => self::PAID_BOOKING_LOCKED_MESSAGE,
        ], $alwaysJson);
    }

    private function guardFinancePermissionForPaidReversal(Request $request, Appointment $appointment)
    {
        if (!$appointment->sale()->exists()) {
            return null;
        }

        $user = $request->user() ?: auth()->user();
        if (!$user || !$user->canEdit('finance')) {
            if ($request->expectsJson() || $request->ajax()) {
                return response()->json([
                    'success' => false,
                    'reason' => 'forbidden',
                    'message' => self::FINANCE_REVERSAL_FORBIDDEN_MESSAGE,
                ], 403);
            }

            abort(403, self::FINANCE_REVERSAL_FORBIDDEN_MESSAGE);
        }

        return null;
    }

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
            'this_week' => [$now->copy()->startOfWeek(Carbon::SUNDAY), $now->copy()->endOfWeek(Carbon::SATURDAY)],
            'last_month' => [$now->copy()->subMonthNoOverflow()->startOfMonth(), $now->copy()->subMonthNoOverflow()->endOfMonth()],
            'all_time' => [
                ($earliest = Appointment::min('appointment_datetime'))
                    ? Carbon::parse($earliest)->startOfDay()
                    : $now->copy()->startOfMonth(),
                null,
            ],
            default => [$now->copy()->startOfMonth(), $now->copy()->endOfMonth()],
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

        $normalizedFilterBranch = $request->filled('branch') ? Customer::normalizeBranch((string) $request->input('branch')) : null;
        $branch = ($normalizedFilterBranch && array_key_exists($normalizedFilterBranch, self::BRANCH_LABELS))
            ? $normalizedFilterBranch
            : null;

        $query = Appointment::query()->with(['agent', 'staff', 'sale', 'clientPackages', 'appointmentServices']);
        if ($to !== null) {
            $query->whereBetween('appointment_datetime', [$from, $to]);
        } else {
            $query->where('appointment_datetime', '>=', $from);
        }

        if ($branch) {
            $query->whereIn('branch', Customer::branchMatchValues($branch));
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
        if ($to === null) {
            $latestInSet = $all->max('appointment_datetime');
            $to = $latestInSet ? Carbon::parse($latestInSet)->endOfDay() : now()->endOfMonth();
            if ($to->lt($from)) {
                $to = $from->copy()->endOfDay();
            }
        }
        $activeAppointments = $all->whereNotIn('status', ['cancelled', 'no_show']);

        $totalBookings = $all->count();
        $activeBookingsCount = $activeAppointments->count();
        $projectedRevenue = (float) $activeAppointments->sum('price');
        $collectedRevenue = (float) $all->sum(fn($a) => $a->sale
            ? max(0, (float) $a->sale->services_total + (float) $a->sale->products_total + (float) $a->sale->packages_total - (float) $a->sale->discount_amount)
            : 0.0);
        $totalRevenue = $projectedRevenue;
        $avgBookingValue = $activeBookingsCount ? $totalRevenue / $activeBookingsCount : 0;
        $uniqueCustomers = $all->pluck('phone')->filter()->unique()->count();

        $statusCounts = $all->groupBy('status')->map->count();
        $statusLabels = $statusCounts->keys()->map(fn($s) => ucwords(str_replace('_', ' ', $s)))->values();

        $branchStats = collect(self::BRANCH_LABELS)->map(function ($label, $key) use ($all) {
            $group = $all->where('branch', $key);
            $activeGroup = $group->whereNotIn('status', ['cancelled', 'no_show']);
            $collected = (float) $group->sum(fn($a) => $a->sale
                ? max(0, (float) $a->sale->services_total + (float) $a->sale->products_total + (float) $a->sale->packages_total - (float) $a->sale->discount_amount)
                : 0.0);
            return [
                'label' => $label,
                'count' => $group->count(),
                'active_count' => $activeGroup->count(),
                'revenue' => (float) $activeGroup->sum('price'),
                'collected_revenue' => $collected,
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
        $activeStaffs = Staff::active()->orderBy('name')->get(['id', 'name', 'branch']);

        return view('appointments.index', compact(
            'appointments',
            'period',
            'branch',
            'search',
            'services',
            'activeStaffs',
            'from',
            'to',
            'totalBookings',
            'activeBookingsCount',
            'totalRevenue',
            'projectedRevenue',
            'collectedRevenue',
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
        $this->normalizeRequestBranch($request);

        if ($request->filled('phone') && is_string($request->input('phone'))) {
            $sanitizedPhone = preg_replace('/[\s\-\(\)]+/', '', trim($request->input('phone')));
            if (str_starts_with($sanitizedPhone, '00')) {
                $sanitizedPhone = '+' . substr($sanitizedPhone, 2);
            }
            $request->merge(['phone' => $sanitizedPhone]);
        }

        $isUnscheduled = $request->boolean('unscheduled')
            || ($request->filled('booking_date') && !$request->filled('start_time') && !$request->filled('appointment_datetime'));

        if (!$isUnscheduled && !$request->filled('staff_id')) {
            $firstPerServiceStaffId = null;
            foreach ((array) $request->input('service_staff', []) as $candidateStaffId) {
                if (!empty($candidateStaffId)) {
                    $firstPerServiceStaffId = (int) $candidateStaffId;
                    break;
                }
            }
            if (!$firstPerServiceStaffId) {
                foreach ((array) $request->input('redeem_service_staff', []) as $candidateStaffId) {
                    if (!empty($candidateStaffId)) {
                        $firstPerServiceStaffId = (int) $candidateStaffId;
                        break;
                    }
                }
            }
            if (!$firstPerServiceStaffId) {
                foreach ((array) $request->input('packages', []) as $pkgRow) {
                    foreach ((array) ($pkgRow['service_staff'] ?? []) as $candidateStaffId) {
                        if (!empty($candidateStaffId)) {
                            $firstPerServiceStaffId = (int) $candidateStaffId;
                            break 2;
                        }
                    }
                }
            }
            if (!$firstPerServiceStaffId) {
                foreach ($this->freebieService()->extractAnchorPackageInputs($request) as $fPkgRow) {
                    if (!empty($fPkgRow['anchor_staff_id'])) {
                        $firstPerServiceStaffId = (int) $fPkgRow['anchor_staff_id'];
                        break;
                    }
                    foreach ((array) ($fPkgRow['slots'] ?? []) as $slotRow) {
                        if (is_array($slotRow) && !empty($slotRow['staff_id'])) {
                            $firstPerServiceStaffId = (int) $slotRow['staff_id'];
                            break 2;
                        }
                    }
                }
            }
            if (!$firstPerServiceStaffId) {
                foreach ($this->freebieService()->extractRedemptionInputs($request) as $fRedeemRow) {
                    if (!empty($fRedeemRow['staff_id'])) {
                        $firstPerServiceStaffId = (int) $fRedeemRow['staff_id'];
                        break;
                    }
                }
            }
            if ($firstPerServiceStaffId) {
                $request->merge(['staff_id' => $firstPerServiceStaffId]);
            }
        }

        $rules = [
            'customer_name' => 'required|string|max:255',
            'phone' => ['required', 'string', 'max:20', 'regex:/^\+?[1-9]\d{6,14}$/'],
            'appointment_datetime' => $isUnscheduled ? 'nullable|date' : 'required|date',
            'booking_date' => $isUnscheduled ? 'required_without:appointment_datetime|nullable|date' : 'nullable|date',
            'start_time' => 'nullable|string',
            'unscheduled' => 'nullable|boolean',
            'service_name' => 'nullable|array',
            'service_name.*' => 'string|max:255',
            'service_price' => 'nullable|array',
            'service_price.*' => 'nullable|numeric|min:0',
            'service_discount_reason' => 'nullable|array',
            'service_discount_reason.*' => 'nullable|string|max:255',
            'service_staff' => 'nullable|array',
            'service_staff.*' => 'nullable|exists:staff,id',
            'service_sort_order' => 'nullable|array',
            'service_sort_order.*' => 'nullable|integer|min:0',
            'service_is_parallel' => 'nullable|array',
            'branch' => 'required|in:old_airport,wakrah',
            'price' => 'nullable|numeric',
            'booking_agent_id' => ['nullable', Rule::exists('users', 'id')->where('role', 'agent')],
            'staff_id' => 'nullable|exists:staff,id',
            'decide_in_salon' => 'nullable|boolean',
            'packages' => 'nullable|array',
            'packages.*.combo_id' => 'required_with:packages|integer|exists:combos,id',
            'packages.*.immediate_service_ids' => 'nullable|array',
            'packages.*.immediate_service_ids.*' => 'integer|exists:services,id',
            'packages.*.pending_service_ids' => 'nullable|array',
            'packages.*.pending_service_ids.*' => 'integer|exists:services,id',
            'packages.*.service_staff' => 'nullable|array',
            'packages.*.service_sort_order' => 'nullable|array',
            'packages.*.service_parallel' => 'nullable|array',
            'redeem_service_ids' => 'nullable|array',
            'redeem_service_ids.*' => 'string',
            'redeem_service_staff' => 'nullable|array',
            'redeem_service_sort_order' => 'nullable|array',
            'redeem_service_parallel' => 'nullable|array',
            'freebie_package_id' => 'nullable|integer|exists:service_freebie_packages,id',
            'anchor_staff_id' => 'nullable|exists:staff,id',
            'freebie_slots' => 'nullable|array',
            'freebie_packages' => 'nullable|array',
            'freebie_packages.*.package_id' => 'required_with:freebie_packages|integer|exists:service_freebie_packages,id',
            'redeem_freebies' => 'nullable|array',
            'freebie_credit_id' => 'nullable|integer',
            'freebie_service_id' => 'nullable|integer',
            'freebie_staff_id' => 'nullable|exists:staff,id',
            'redeem_freebie_tokens' => 'nullable|array',
            'redeem_freebie_staff' => 'nullable|array',
            'redeem_freebie_sort_order' => 'nullable|array',
            'redeem_freebie_parallel' => 'nullable|array',
        ];

        $messages = [
            'customer_name.required' => 'Please select an existing client or add a new client.',
            'phone.required' => 'Please select an existing client or add a new client.',
            'appointment_datetime.required' => 'Appointment date and time is required.',
            'booking_date.required_without' => 'Appointment date is required.',
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

        $rawServiceNames = array_values(array_filter(
            array_map('trim', (array) $request->input('service_name', [])),
            fn ($n) => $n !== '' && !in_array(strtolower($n), ['choose in salon', 'decide in salon', 'services tbd in salon'], true)
        ));
        $rawFreebieAnchorInputs = $this->freebieService()->extractAnchorPackageInputs($request);
        $rawFreebieRedemptionInputs = $this->freebieService()->extractRedemptionInputs($request);

        // If 1-click single freebie redemption passed freebie_credit_id alongside service_name,
        // treat that service strictly as the 0.00 QAR freebie redemption (not an extra paid standalone line).
        if ($request->filled('freebie_credit_id') && count($rawFreebieRedemptionInputs) === 1 && count($rawServiceNames) === 1) {
            $rawServiceNames = [];
        }

        $hasRealServices = !empty($rawServiceNames);
        $hasRawPackages = !empty(array_filter((array) $request->input('packages', [])));
        $hasRawRedemptions = !empty(array_filter((array) $request->input('redeem_service_ids', [])));
        $hasFreebieAnchors = !empty($rawFreebieAnchorInputs);
        $hasFreebieRedemptions = !empty($rawFreebieRedemptionInputs);
        $isDecideInSalon = ($hasRealServices || $hasRawPackages || $hasRawRedemptions || $hasFreebieAnchors || $hasFreebieRedemptions)
            ? false
            : $request->boolean('decide_in_salon');

        if (!$hasRealServices && !$hasRawPackages && !$hasRawRedemptions && !$hasFreebieAnchors && !$hasFreebieRedemptions && !$isDecideInSalon) {
            return $this->conflictValidator()->toResponse($request, [
                'reason' => 'validation',
                'field' => 'service_name',
                'message' => 'At least one service, combo package, or Decide in Salon must be selected.',
            ]);
        }

        if ($isDecideInSalon && $request->input('then') === 'checkout') {
            return $this->conflictValidator()->toResponse($request, [
                'reason' => 'decide_in_salon',
                'field' => 'services',
                'message' => 'Please add the service(s) or combo performed before completing checkout.',
            ]);
        }

        $rawDateSource = $request->booking_date ?: $request->appointment_datetime;
        $appointmentDate = Carbon::parse($rawDateSource)->format('Y-m-d');
        $staffId = $request->staff_id ? (int) $request->staff_id : null;
        $phone = Customer::normalizePhone($request->phone);

        $existingCustomer = Customer::where('phone', $phone)
            ->orWhere('phone', $request->phone)
            ->first();

        if ($existingCustomer) {
            ClientPackage::expireDue($existingCustomer->id);
        }

        [$packagePlans, $packagePlanError, $packagePlanConflict] = $this->buildPackagePurchasePlans(
            $request->input('packages', []),
            (string) $request->branch
        );
        if ($packagePlanError) {
            return $this->conflictValidator()->toResponse($request, $packagePlanConflict ?? [
                'reason' => 'validation',
                'field' => 'packages',
                'message' => $packagePlanError,
            ]);
        }

        [$redemptionPlans, $redemptionPlanError, $redemptionPlanConflict] = $existingCustomer
            ? $this->buildPackageRedemptionPlans(
                $request->input('redeem_service_ids', []),
                $existingCustomer,
                null,
                is_array($request->input('redeem_service_staff')) ? $request->input('redeem_service_staff') : [],
                (string) $request->branch,
                is_array($request->input('redeem_service_sort_order')) ? $request->input('redeem_service_sort_order') : [],
                is_array($request->input('redeem_service_parallel')) ? $request->input('redeem_service_parallel') : []
            )
            : [[], (!empty($request->input('redeem_service_ids', [])) ? 'Client profile is required to redeem a package.' : null), null];
        if ($redemptionPlanError) {
            return $this->conflictValidator()->toResponse($request, $redemptionPlanConflict ?? [
                'reason' => 'validation',
                'field' => 'redeem_service_ids',
                'message' => $redemptionPlanError,
            ]);
        }

        [$freebieAnchorPlans, $freebieAnchorError, $freebieAnchorConflict] = $this->freebieService()->buildAnchorPurchasePlans(
            $rawFreebieAnchorInputs,
            (string) $request->branch,
            $staffId
        );
        if ($freebieAnchorError !== null) {
            return $this->conflictValidator()->toResponse($request, $freebieAnchorConflict ?? [
                'reason' => 'validation',
                'field' => 'freebie_package_id',
                'message' => $freebieAnchorError,
            ]);
        }

        [$freebieRedemptionPlans, $freebieRedeemError, $freebieRedeemConflict] = $this->freebieService()->buildFreebieRedemptionPlans(
            $rawFreebieRedemptionInputs,
            $existingCustomer,
            (string) $request->branch,
            Carbon::parse($appointmentDate, 'Asia/Qatar'),
            null,
            $staffId
        );
        if ($freebieRedeemError !== null) {
            return $this->conflictValidator()->toResponse($request, $freebieRedeemConflict ?? [
                'reason' => 'validation',
                'field' => 'redeem_freebies',
                'message' => $freebieRedeemError,
            ]);
        }

        $startTime = $isUnscheduled ? null : Carbon::parse($request->appointment_datetime);
        $serviceNames = $rawServiceNames;

        $rawStandaloneStaff = (array) $request->input('service_staff', []);
        $rawStandaloneSort = (array) $request->input('service_sort_order', []);
        $rawStandaloneParallel = (array) $request->input('service_is_parallel', []);

        $entryCatalog = !empty($serviceNames)
            ? Service::whereIn('name', $serviceNames)->get()->keyBy('name')
            : collect();

        $rawTimelineItems = [];
        foreach ($serviceNames as $idx => $name) {
            $svcModel = $entryCatalog->get($name);
            $rawAssigned = $rawStandaloneStaff[$idx] ?? null;
            $resolvedLineStaffId = ($rawAssigned !== null && $rawAssigned !== '')
                ? (int) $rawAssigned
                : $staffId;
            $rawTimelineItems[] = [
                'kind' => 'standalone',
                'standalone_idx' => $idx,
                'name' => $name,
                'service_id' => $svcModel?->id,
                'duration' => (int) ($svcModel?->duration ?? 30),
                'staff_id' => $resolvedLineStaffId,
                'sort_order' => ($rawStandaloneSort[$idx] ?? null) !== null && ($rawStandaloneSort[$idx] ?? '') !== ''
                    ? (int) $rawStandaloneSort[$idx]
                    : null,
                'is_parallel' => filter_var($rawStandaloneParallel[$idx] ?? false, FILTER_VALIDATE_BOOLEAN),
            ];
        }

        foreach ($freebieAnchorPlans as $faIdx => $faPlan) {
            $anchorSvc = $faPlan['anchor_service'];
            $anchorStaff = $faPlan['anchor_staff_id'] ?? $staffId;
            $rawTimelineItems[] = [
                'kind' => 'freebie_anchor',
                'plan_idx' => $faIdx,
                'name' => $anchorSvc->name,
                'service_id' => $anchorSvc->id,
                'duration' => (int) ($anchorSvc->duration ?: 60),
                'staff_id' => $anchorStaff ? (int) $anchorStaff : null,
                'sort_order' => $faPlan['anchor_sort_order'] ?? null,
                'is_parallel' => (bool) ($faPlan['anchor_is_parallel'] ?? false),
            ];

            foreach ($faPlan['slots'] as $slotRow) {
                if ($slotRow['mode'] === 'same_day' && $slotRow['service']) {
                    $slotSvc = $slotRow['service'];
                    $slotStaff = $slotRow['staff_id'] ?? $staffId;
                    $rawTimelineItems[] = [
                        'kind' => 'freebie_slot',
                        'plan_idx' => $faIdx,
                        'slot_number' => (int) $slotRow['slot_number'],
                        'name' => $slotSvc->name,
                        'service_id' => $slotSvc->id,
                        'duration' => (int) ($slotSvc->duration ?: 30),
                        'staff_id' => $slotStaff ? (int) $slotStaff : null,
                        'sort_order' => $slotRow['sort_order'] ?? null,
                        'is_parallel' => (bool) ($slotRow['is_parallel'] ?? false),
                    ];
                }
            }
        }

        foreach ($freebieRedemptionPlans as $frIdx => $frPlan) {
            $frStaff = $frPlan['staff_id'] ?? $staffId;
            $rawTimelineItems[] = [
                'kind' => 'freebie_redeem',
                'redeem_idx' => $frIdx,
                'name' => $frPlan['service']->name,
                'service_id' => $frPlan['service']->id,
                'duration' => (int) ($frPlan['service']->duration ?: 30),
                'staff_id' => $frStaff ? (int) $frStaff : null,
                'sort_order' => $frPlan['sort_order'] ?? null,
                'is_parallel' => (bool) ($frPlan['is_parallel'] ?? false),
            ];
        }

        foreach ($redemptionPlans as $rIdx => $plan) {
            $assigned = $plan['staff_id'] ?? $staffId;
            $rawTimelineItems[] = [
                'kind' => 'redemption',
                'redemption_idx' => $rIdx,
                'name' => $plan['service']->name,
                'service_id' => $plan['service']->id,
                'duration' => (int) ($plan['service']->duration ?: 30),
                'staff_id' => $assigned ? (int) $assigned : null,
                'sort_order' => $plan['sort_order'] ?? null,
                'is_parallel' => (bool) ($plan['is_parallel'] ?? false),
            ];
        }

        foreach ($packagePlans as $pIdx => $plan) {
            foreach ($plan['immediate_ids'] as $sid) {
                $svcObj = $plan['pool']->get($sid);
                $assigned = $plan['service_staff'][$sid] ?? $staffId;
                $rawTimelineItems[] = [
                    'kind' => 'combo',
                    'plan_idx' => $pIdx,
                    'service_id' => $sid,
                    'name' => $svcObj->name,
                    'duration' => (int) ($svcObj->duration ?: 30),
                    'staff_id' => $assigned ? (int) $assigned : null,
                    'sort_order' => $plan['service_sort_order'][$sid] ?? null,
                    'is_parallel' => (bool) ($plan['service_parallel'][$sid] ?? false),
                ];
            }
        }

        $timeline = BookingConflictValidator::computeSliceTimeline($startTime, $rawTimelineItems);
        $scheduleEntries = $timeline['slices'];

        if (!$staffId && !empty($scheduleEntries) && !empty($scheduleEntries[0]['staff_id'])) {
            $staffId = (int) $scheduleEntries[0]['staff_id'];
        }

        $validationSlices = [];
        if (empty($scheduleEntries)) {
            $holdDuration = !empty($packagePlans)
                ? max(array_map(fn ($p) => $p['combo']->effective_duration, $packagePlans))
                : ($isDecideInSalon ? 60 : 30);
            $validationSlices[] = [
                'staff_id' => $staffId,
                'start' => $startTime,
                'duration' => $holdDuration,
                'is_parallel' => false,
                'service_names' => [],
            ];
        } else {
            foreach ($scheduleEntries as $entry) {
                $validationSlices[] = [
                    'staff_id' => $entry['staff_id'],
                    'start' => $entry['start'],
                    'duration' => $entry['duration'],
                    'is_parallel' => (bool) ($entry['is_parallel'] ?? false),
                    'service_names' => [$entry['name']],
                ];
            }
        }

        $conflict = $this->conflictValidator()->validateBookingOperation(
            (string) $request->branch,
            Carbon::parse($appointmentDate),
            $validationSlices,
            'book',
            $isUnscheduled,
            null,
            null
        );

        if ($conflict !== null) {
            return $this->conflictValidator()->toResponse($request, $conflict);
        }

        $customer = $this->findOrCreateCustomer($phone, $request->customer_name);

        // Empty selection means the client hasn't chosen services yet - the
        // front desk saves the slot now and staff pick the real services
        // (via "Add service" on the appointment) once the client arrives.
        $allServiceNames = array_column($scheduleEntries, 'name');
        $services = !empty($allServiceNames)
            ? implode(', ', $allServiceNames)
            : (!empty($packagePlans) ? 'Choose in Salon' : 'Decide in Salon');

        $resolvedComboNames = collect($packagePlans)->map(fn ($p) => $p['combo']->name)
            ->merge(collect($redemptionPlans)->map(fn ($p) => $p['client_package']->combo_name))
            ->map(fn ($n) => trim((string) $n))
            ->filter()
            ->unique()
            ->values();
        $resolvedComboId = !empty($packagePlans)
            ? $packagePlans[0]['combo']->id
            : (!empty($redemptionPlans) ? $redemptionPlans[0]['client_package']->combo_id : null);
        $resolvedComboName = $resolvedComboNames->isNotEmpty() ? $resolvedComboNames->implode(', ') : null;

        $lifetimeRevenue = $this->calculateVerifiedLifetimeRevenue($customer?->id, $phone);

        $standaloneScheduleByIdx = [];
        $redemptionScheduleByIdx = [];
        $comboScheduleByPlanAndSvc = [];
        $freebieAnchorScheduleByPlan = [];
        $freebieSlotScheduleByPlanAndSlot = [];
        $freebieRedeemScheduleByIdx = [];
        foreach ($scheduleEntries as $slice) {
            if ($slice['kind'] === 'standalone') {
                $standaloneScheduleByIdx[$slice['standalone_idx']] = $slice;
            } elseif ($slice['kind'] === 'redemption') {
                $redemptionScheduleByIdx[$slice['redemption_idx']] = $slice;
            } elseif ($slice['kind'] === 'combo') {
                $comboScheduleByPlanAndSvc[$slice['plan_idx']][$slice['service_id']] = $slice;
            } elseif ($slice['kind'] === 'freebie_anchor') {
                $freebieAnchorScheduleByPlan[$slice['plan_idx']] = $slice;
            } elseif ($slice['kind'] === 'freebie_slot') {
                $freebieSlotScheduleByPlanAndSlot[$slice['plan_idx']][$slice['slot_number']] = $slice;
            } elseif ($slice['kind'] === 'freebie_redeem') {
                $freebieRedeemScheduleByIdx[$slice['redeem_idx']] = $slice;
            }
        }

        $isPureFreebieRedemptionBooking = !empty($freebieRedemptionPlans)
            && empty($serviceNames)
            && empty($packagePlans)
            && empty($redemptionPlans)
            && empty($freebieAnchorPlans);

        $appointment = DB::transaction(function () use (
            $request,
            $services,
            $resolvedComboId,
            $resolvedComboName,
            $phone,
            $customer,
            $lifetimeRevenue,
            $staffId,
            $startTime,
            $appointmentDate,
            $isUnscheduled,
            $serviceNames,
            $packagePlans,
            $redemptionPlans,
            $freebieAnchorPlans,
            $freebieRedemptionPlans,
            $isPureFreebieRedemptionBooking,
            $isDecideInSalon,
            $standaloneScheduleByIdx,
            $redemptionScheduleByIdx,
            $comboScheduleByPlanAndSvc,
            $freebieAnchorScheduleByPlan,
            $freebieSlotScheduleByPlanAndSlot,
            $freebieRedeemScheduleByIdx
        ) {
            $baseAttributes = $request->except([
                'freebie_package_id',
                'freebie_slots',
                'freebie_packages',
                'redeem_freebies',
                'freebie_credit_id',
                'freebie_service_id',
                'freebie_staff_id',
                'redeem_freebie_tokens',
                'redeem_freebie_staff',
                'redeem_freebie_sort_order',
                'redeem_freebie_parallel',
                'anchor_staff_id',
            ]);

            $appointment = Appointment::create(array_merge(
                $baseAttributes,
                [
                    'appointment_datetime' => $startTime ? $startTime : Carbon::parse($appointmentDate)->startOfDay(),
                    'booking_date' => $appointmentDate,
                    'start_time' => $startTime ? $startTime->format('H:i:s') : null,
                    'staff_id' => $staffId ? (int) $staffId : null,
                    'lifetime_revenue' => $lifetimeRevenue,
                    'service_name' => $services,
                    'combo_id' => $resolvedComboId,
                    'combo_name' => $resolvedComboName,
                    'decide_in_salon' => $isDecideInSalon,
                    'hold_duration' => $isDecideInSalon ? 60 : null,
                    'price' => ($isDecideInSalon || $isPureFreebieRedemptionBooking) ? 0 : ($request->price ?? 0),
                    'customer_name' => $request->customer_name,
                    'phone' => $phone,
                    'customer_id' => $customer?->id,
                    'created_by' => auth()->id(),
                ]
            ));

            $this->createServiceLineItems(
                $appointment,
                $serviceNames,
                $staffId ? (int) $staffId : null,
                $startTime,
                $request->service_price ?? [],
                $request->service_discount_reason ?? [],
                $standaloneScheduleByIdx
            );

            if (!empty($freebieAnchorPlans) && $customer) {
                $this->freebieService()->persistAnchorPurchasePlans(
                    $appointment,
                    $customer,
                    $freebieAnchorPlans,
                    $freebieAnchorScheduleByPlan,
                    $freebieSlotScheduleByPlanAndSlot,
                    $appointmentDate
                );
            }

            if (!empty($freebieRedemptionPlans)) {
                $this->freebieService()->persistFreebieRedemptionPlans(
                    $appointment,
                    $freebieRedemptionPlans,
                    $freebieRedeemScheduleByIdx,
                    $isPureFreebieRedemptionBooking
                );
            }

            if (!empty($redemptionPlans) || !empty($packagePlans)) {
                $lastLine = $appointment->appointmentServices()->where('is_pending', false)->whereNotNull('start_time')->orderByDesc('start_time')->first();
                $cursor = $startTime ? ($lastLine ? $lastLine->end_time : $startTime) : null;

                foreach ($redemptionPlans as $rIdx => $plan) {
                    $service = $plan['service'];
                    $package = $plan['client_package'];
                    $sliceMeta = $redemptionScheduleByIdx[$rIdx] ?? null;
                    $assignedStaffId = $sliceMeta['staff_id'] ?? $plan['staff_id'] ?? ($staffId ? (int) $staffId : null);
                    $sliceCursor = $sliceMeta ? $sliceMeta['start'] : $cursor;

                    $appointmentService = $this->addRedeemedServiceLine(
                        $appointment,
                        $service,
                        $package->combo_name,
                        $sliceCursor,
                        $assignedStaffId,
                        $package->combo_id,
                        $package->id,
                        $sliceMeta['sort_order'] ?? 0,
                        (bool) ($sliceMeta['is_parallel'] ?? false)
                    );

                    if (!$sliceMeta) {
                        $cursor = $sliceCursor;
                    }

                    $existingPendingRow = ClientPackageService::where('client_package_id', $package->id)
                        ->where('service_id', $service->id)
                        ->where('status', 'pending')
                        ->first();

                    if ($existingPendingRow) {
                        $existingPendingRow->update([
                            'service_name' => $service->name,
                            'status' => 'redeemed',
                            'redeemed_at' => now(),
                            'appointment_service_id' => $appointmentService->id,
                        ]);
                    } else {
                        ClientPackageService::create([
                            'client_package_id' => $package->id,
                            'service_id' => $service->id,
                            'service_name' => $service->name,
                            'status' => 'redeemed',
                            'redeemed_at' => now(),
                            'appointment_service_id' => $appointmentService->id,
                        ]);
                    }
                    $package->refreshStatus();
                }

                if (!empty($packagePlans) && $customer) {
                    $this->createPackagePurchases($appointment, $customer, $packagePlans, $cursor, $comboScheduleByPlanAndSvc);
                }
            }

            $createdLines = $appointment->appointmentServices()->get();
            $activeLines = $createdLines->where('is_pending', false)->values();
            if (
                $activeLines->isNotEmpty()
                && empty($packagePlans)
                && empty($redemptionPlans)
                && empty($freebieAnchorPlans)
                && empty($freebieRedemptionPlans)
            ) {
                $linesSum = (float) $activeLines->sum(fn ($s) => $s->final_price);
                if ($linesSum == 0.0 && $activeLines->count() === 1 && $request->filled('price') && (float) $request->price > 0) {
                    $firstLine = $activeLines->first();
                    $firstLine->update([
                        'price' => (float) $request->price,
                        'original_price' => (float) $request->price,
                    ]);
                }
            }

            if ($createdLines->isNotEmpty() || !empty($packagePlans) || !empty($redemptionPlans) || !empty($freebieAnchorPlans) || !empty($freebieRedemptionPlans)) {
                $appointment->syncFromServices();
            } elseif ($isDecideInSalon) {
                $appointment->update([
                    'price' => 0,
                    'decide_in_salon' => true,
                    'hold_duration' => 60,
                    'service_name' => 'Decide in Salon',
                ]);
            }

            if ($customer && !in_array(strtolower((string) ($appointment->status ?? 'pending')), ['cancelled', 'no_show'], true)) {
                $this->autoCloseOpenLeadsForBooking($appointment, $customer, $phone);
            }

            $this->syncAdLeadAttributionForAppointment($appointment);

            return $appointment;
        });

        if ($request->expectsJson() || $request->ajax()) {
            $appointment->refresh();

            return response()->json([
                'success' => true,
                'message' => $isUnscheduled ? 'Unscheduled booking added to queue.' : 'Appointment booked successfully.',
                'redirect' => $request->input('then') === 'checkout'
                    ? route('appointments.revenue.payment', $appointment->id)
                    : null,
                'appointment' => [
                    'id' => $appointment->id,
                    'customer_name' => $appointment->customer_name,
                    'service_name' => $appointment->service_name,
                    'combo_id' => $appointment->combo_id,
                    'combo_name' => $appointment->display_combo_name,
                    'freebie_package_id' => $appointment->freebie_package_id,
                    'is_freebie_redemption' => (bool) $appointment->is_freebie_redemption,
                    'is_combo_tbd' => $appointment->is_combo_tbd,
                    'decide_in_salon' => (bool) $appointment->decide_in_salon,
                    'hold_duration' => $appointment->effective_hold_duration,
                    'price' => (float) $appointment->price,
                    'branch' => $appointment->branch,
                    'branch_label' => $appointment->branch_label,
                    'staff_id' => $appointment->staff_id,
                    'status' => $appointment->status,
                ],
            ], 201);
        }

        if ($request->input('then') === 'checkout') {
            return redirect()->route('appointments.revenue.payment', $appointment->id);
        }

        return redirect()->back()->with('success', $isUnscheduled ? 'Unscheduled booking added to queue.' : 'Appointment booked successfully.');
    }

    /**
     * Compute verified paid client Lifetime Revenue (LTV) strictly from net paid Sale records,
     * excluding cancelled, no-show, or unpaid appointments and preventing edit double-counting.
     */
    private function calculateVerifiedLifetimeRevenue(?int $customerId, ?string $phone): float
    {
        $rawDigits = preg_replace('/\D/', '', (string) $phone);
        [$countryCode, $localNumber] = Lead::splitPhone($rawDigits);
        $normalizedPhone = Lead::normalizePhone($countryCode, $localNumber);
        $withQatarCode = strlen($normalizedPhone) === 8 ? '974' . $normalizedPhone : null;
        $phoneCandidates = array_values(array_unique(array_filter([
            $normalizedPhone,
            $rawDigits,
            $withQatarCode,
        ])));

        if (!$customerId && empty($phoneCandidates)) {
            return 0.0;
        }

        $matchedCustomerIds = collect($customerId ? [$customerId] : []);
        if (!empty($phoneCandidates)) {
            $matchedCustomerIds = $matchedCustomerIds->merge(
                Customer::whereIn('phone', $phoneCandidates)->pluck('id')
            )->unique()->values();
        }

        $sales = Sale::query()
            ->where(function ($q) use ($matchedCustomerIds, $phoneCandidates) {
                if ($matchedCustomerIds->isNotEmpty()) {
                    $q->whereIn('customer_id', $matchedCustomerIds);
                }
                if (!empty($phoneCandidates)) {
                    $q->orWhereHas('appointment', function ($aq) use ($phoneCandidates) {
                        $aq->whereNotIn('status', ['cancelled', 'no_show'])
                            ->where(function ($pq) use ($phoneCandidates) {
                                $pq->whereIn('phone', $phoneCandidates);
                                foreach ($phoneCandidates as $cand) {
                                    $pq->orWhereRaw("REPLACE(REPLACE(REPLACE(REPLACE(phone, '+', ''), ' ', ''), '(', ''), ')', '') = ?", [$cand]);
                                }
                            });
                    });
                }
            })
            ->where(function ($q) {
                $q->whereNull('appointment_id')
                    ->orWhereHas('appointment', fn ($aq) => $aq->whereNotIn('status', ['cancelled', 'no_show']));
            })
            ->get(['id', 'services_total', 'products_total', 'packages_total', 'discount_amount']);

        return round((float) $sales->unique('id')->sum(fn (Sale $sale) => AdLeadEntry::netSaleAmount($sale)), 2);
    }

    /**
     * Find a customer by phone, creating one if needed. Refreshes the stored
     * name so the directory reflects the latest booking's spelling. Returns
     * null when phone normalizes to empty (walk-in without a phone number).
     */
    private function findOrCreateCustomer(?string $phone, ?string $name): ?Customer
    {
        $customer = AdLeadEntry::resolveOrCreateCanonicalCustomer($phone, $name);
        if (!$customer) {
            return null;
        }

        $cleanName = trim((string) $name);
        if ($cleanName !== '' && $customer->name !== $cleanName) {
            $customer->name = $cleanName;
            $customer->save();
        }

        return $customer;
    }

    /**
     * Automatically close any open leads for a client once they book a
     * valid salon appointment, linking customer_id and appointment_id and
     * appending an audit note to customer_remarks. Enforces 1 open lead per
     * phone by merging any duplicate open rows into the latest lead.
     */
    private function autoCloseOpenLeadsForBooking(Appointment $appointment, Customer $customer, string $phone): void
    {
        [$countryCode, $localNumber] = Lead::splitPhone($phone);
        $normalizedPhone = Lead::normalizePhone($countryCode, $localNumber);
        $phoneCandidates = array_values(array_unique(array_filter([$normalizedPhone, $phone])));
        $resolvedCustomerId = $appointment->customer_id ?: $customer->id;

        $openLeads = Lead::where(function ($q) use ($resolvedCustomerId, $phoneCandidates) {
            if ($resolvedCustomerId) {
                $q->where('customer_id', $resolvedCustomerId);
            }
            if (!empty($phoneCandidates)) {
                $q->orWhereIn('phone', $phoneCandidates);
            }
        })
            ->where(function ($q) {
                $q->whereNull('needful_done')->orWhereNotIn('needful_done', ['booked', 'no']);
            })
            ->orderByDesc('id')
            ->get();

        if ($openLeads->isEmpty()) {
            return;
        }

        /** @var Lead $primaryLead */
        $primaryLead = $openLeads->first();
        $mergedServices = is_array($primaryLead->service_interest) ? $primaryLead->service_interest : [];
        $mergedCategories = $primaryLead->categories_list;
        $remarksParts = [];
        $resolvedAgentId = $primaryLead->assigned_agent_id ?: $appointment->booking_agent_id;

        foreach ($openLeads as $row) {
            if (is_array($row->service_interest)) {
                $mergedServices = array_merge($mergedServices, $row->service_interest);
            }
            $mergedCategories = array_merge($mergedCategories, $row->categories_list);
            $r = trim((string) $row->customer_remarks);
            if ($r !== '' && !in_array($r, $remarksParts, true)) {
                $remarksParts[] = $r;
            }
            if (!$resolvedAgentId && $row->assigned_agent_id) {
                $resolvedAgentId = $row->assigned_agent_id;
            }
        }

        // Defensively merge and remove any older duplicate open rows so 1 booking = 1 conversion
        foreach ($openLeads->slice(1) as $duplicateLead) {
            AdLeadEntry::where('lead_id', $duplicateLead->id)->update(['lead_id' => $primaryLead->id]);
            $duplicateLead->delete();
        }

        $mergedServices = array_values(array_unique(array_filter(array_map('trim', $mergedServices))));
        $normalizedCategories = Lead::normalizeCategories($mergedCategories);
        $primaryCategory = Lead::resolvePrimaryCategory($normalizedCategories) ?: $primaryLead->category;
        $existingRemarks = implode(' | ', $remarksParts);
        $autoCloseNote = '[Auto-closed: Booked on ' . now()->format('d M Y') . ']';
        $newRemarks = $existingRemarks !== ''
            ? (str_contains($existingRemarks, $autoCloseNote) ? $existingRemarks : $existingRemarks . ' ' . $autoCloseNote)
            : $autoCloseNote;

        $primaryLead->update([
            'needful_done' => 'booked',
            'category' => $primaryCategory,
            'categories' => !empty($normalizedCategories) ? $normalizedCategories : $primaryLead->categories,
            'customer_id' => $resolvedCustomerId ?: $primaryLead->customer_id,
            'appointment_id' => $appointment->id,
            'assigned_agent_id' => $resolvedAgentId,
            'next_followup_date' => null,
            'service_interest' => !empty($mergedServices) ? $mergedServices : $primaryLead->service_interest,
            'customer_remarks' => $newRemarks,
        ]);
    }

    public function update(Request $request, Appointment $appointment)
    {
        $this->normalizeRequestBranch($request);

        $alreadyPaid = $appointment->sale()->exists();
        $requestedTopStatus = $request->input('status') ?: $appointment->status;

        if ($alreadyPaid) {
            if (!in_array($requestedTopStatus, ['cancelled', 'no_show'], true)) {
                return $this->rejectPaidBookingMutation($request);
            }

            $forbidden = $this->guardFinancePermissionForPaidReversal($request, $appointment);
            if ($forbidden !== null) {
                return $forbidden;
            }

            $downstreamConflict = $this->downstreamPackageRedemptionConflict($appointment);
            if ($downstreamConflict !== null) {
                return $this->conflictValidator()->toResponse($request, [
                    'reason' => 'validation',
                    'field' => 'status',
                    'message' => $downstreamConflict,
                ]);
            }

            $oldApptDate = Carbon::parse($appointment->appointment_datetime ?: $appointment->booking_date ?: now());
            DB::transaction(function () use ($appointment, $requestedTopStatus) {
                $this->reverseAppointmentSale($appointment, true);
                $appointment->update(['status' => $requestedTopStatus]);
            });
            $appointment->refresh();
            $this->syncLeadCategoryFromAppointment($appointment, $requestedTopStatus === 'cancelled' ? 'cancel' : 'no_show');
            $this->syncAdLeadAttributionForAppointment($appointment, $oldApptDate);

            if ($request->expectsJson() || $request->ajax()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Paid booking cancelled and sale reversed.',
                ]);
            }

            return redirect()->back()->with('success', 'Paid booking cancelled and sale reversed.');
        }

        if ($request->filled('phone') && is_string($request->input('phone'))) {
            $sanitizedPhone = preg_replace('/[\s\-\(\)]+/', '', trim($request->input('phone')));
            if (str_starts_with($sanitizedPhone, '00')) {
                $sanitizedPhone = '+' . substr($sanitizedPhone, 2);
            }
            $request->merge(['phone' => $sanitizedPhone]);
        }

        $hasComboContext = ClientPackage::where('appointment_id', $appointment->id)->exists()
            || !empty($appointment->combo_name)
            || $appointment->appointmentServices()->whereNotNull('combo_name')->exists()
            || $request->filled('combo_packages');
        $hasFreebieContext = $appointment->freebie_package_id !== null
            || (bool) $appointment->is_freebie_redemption
            || $appointment->appointmentServices()->where(function ($q) {
                $q->whereNotNull('freebie_package_id')->orWhere('is_freebie_redemption', true);
            })->exists();

        $request->validate([
            'customer_name' => 'nullable|string|max:255',
            'phone' => ['nullable', 'string', 'max:20', 'regex:/^\+?[1-9]\d{6,14}$/'],
            'appointment_datetime' => 'required|date',
            'service_name' => 'nullable|array',
            'service_name.*' => 'string|max:255',
            'decide_in_salon' => 'nullable|boolean',
            'combo_packages' => 'nullable|array',
            'combo_packages.*.client_package_id' => 'required_with:combo_packages|integer|exists:client_packages,id',
            'combo_packages.*.today_service_ids' => 'nullable|array',
            'combo_packages.*.today_service_ids.*' => 'integer|exists:services,id',
            'combo_packages.*.pending_service_ids' => 'nullable|array',
            'combo_packages.*.pending_service_ids.*' => 'integer|exists:services,id',
            'combo_packages.*.service_staff' => 'nullable|array',
            'combo_packages.*.service_sort_order' => 'nullable|array',
            'combo_packages.*.service_parallel' => 'nullable|array',
            'redeem_service_ids' => 'nullable|array',
            'redeem_service_ids.*' => 'string',
            'redeem_service_staff' => 'nullable|array',
            'branch' => 'required|in:old_airport,wakrah',
            'price' => 'nullable|numeric',
            'booking_agent_id' => ['nullable', Rule::exists('users', 'id')->where('role', 'agent')],
            'staff_id' => 'nullable|exists:staff,id',
            'status' => 'nullable|in:pending,arrived,in_progress,completed,no_show,cancelled',
        ], [
            'phone.regex' => 'Enter a valid phone number with country code, e.g. +974XXXXXXXX.'
        ]);

        $rawServiceNamesInput = array_values(array_filter(
            array_map('trim', (array) $request->input('service_name', [])),
            fn ($n) => $n !== '' && !in_array(strtolower($n), ['choose in salon', 'decide in salon', 'services tbd in salon'], true)
        ));
        $requestedRedeemTokens = array_values(array_filter((array) $request->input('redeem_service_ids', [])));
        $hasRedeemRequest = !empty($requestedRedeemTokens);
        $hasExistingRedemptionLines = $appointment->appointmentServices()->where('is_pending', false)->get()->contains(fn ($l) => $l->isPackageRedemptionLine());
        $hasRealServices = !empty($rawServiceNamesInput) || $hasRedeemRequest || $hasExistingRedemptionLines;
        $isDecideInSalon = ($hasRealServices || $hasComboContext || $hasFreebieContext)
            ? false
            : ($request->exists('decide_in_salon') ? $request->boolean('decide_in_salon') : (bool) $appointment->decide_in_salon);

        if (!$hasRealServices && !$hasComboContext && !$hasFreebieContext && !$isDecideInSalon && !in_array($request->status, ['cancelled', 'no_show'], true)) {
            return $this->conflictValidator()->toResponse($request, [
                'reason' => 'validation',
                'field' => 'service_name',
                'message' => 'At least one service, combo package, or Decide in Salon must be selected.',
            ]);
        }

        $services = !empty($rawServiceNamesInput)
            ? implode(', ', $rawServiceNamesInput)
            : ($hasFreebieContext ? ($appointment->service_name ?: 'Service + Freebies') : ($hasComboContext ? 'Choose in Salon' : 'Decide in Salon'));
        $phone = $request->phone ? Customer::normalizePhone($request->phone) : '';

        $staffId = $request->exists('staff_id')
            ? ($request->filled('staff_id') ? (int) $request->input('staff_id') : null)
            : ($appointment->staff_id ? (int) $appointment->staff_id : null);
        $startTime = Carbon::parse($request->appointment_datetime);
        $isUnscheduled = $request->boolean('unscheduled');

        $oldStaffId = $appointment->staff_id;
        $oldStatus = $appointment->status;
        $oldApptDate = Carbon::parse($appointment->appointment_datetime ?: $appointment->booking_date ?: now());
        $hasSale = $appointment->sale()->exists();
        $requestedStatus = $request->status ?: $appointment->status;
        $redirectToCheckout = ($requestedStatus === 'completed' && !$hasSale);

        if ($redirectToCheckout && $isDecideInSalon) {
            return $this->conflictValidator()->toResponse($request, [
                'reason' => 'decide_in_salon',
                'field' => 'services',
                'message' => 'Please add the service(s) or combo performed before completing checkout.',
            ]);
        }

        // Do not allow flipping an unpaid appointment to 'completed' without checkout.
        $effectiveStatus = $redirectToCheckout
            ? (in_array($oldStatus, ['cancelled', 'no_show', 'completed'], true) ? 'in_progress' : ($oldStatus ?: 'pending'))
            : $requestedStatus;

        $submittedNames = $rawServiceNamesInput;

        if (in_array($effectiveStatus, ['cancelled', 'no_show'], true)) {
            $downstreamConflict = $this->downstreamPackageRedemptionConflict($appointment);
            if ($downstreamConflict !== null) {
                return $this->conflictValidator()->toResponse($request, [
                    'reason' => 'validation',
                    'field' => 'status',
                    'message' => $downstreamConflict,
                ]);
            }
        }

        $validatedComboSyncPlans = [];
        $validationMode = ($appointment->start_time === null || !$startTime->equalTo($appointment->appointment_date))
            ? 'reschedule'
            : 'book';

        if (!in_array($effectiveStatus, ['cancelled', 'no_show'], true)) {
            $validationSlices = [];
            $sliceCursor = $isUnscheduled ? null : $startTime->copy();

            if (!empty($rawServiceNamesInput)) {
                $standaloneCatalog = Service::whereIn('name', $rawServiceNamesInput)->get()->keyBy('name');
                $existingStandaloneLines = $appointment->appointmentServices()
                    ->where('is_pending', false)
                    ->whereNull('client_package_id')
                    ->whereNull('combo_id')
                    ->whereNull('combo_name')
                    ->orderBy('start_time')
                    ->orderBy('id')
                    ->get()
                    ->keyBy('id');

                foreach ($rawServiceNamesInput as $svcName) {
                    $matched = $existingStandaloneLines->first(fn ($l) => strcasecmp(trim((string) $l->name), $svcName) === 0);
                    if ($matched) {
                        $existingStandaloneLines->forget($matched->id);
                        $lineStaffId = ($matched->staff_id === null || (int) $matched->staff_id === (int) $oldStaffId)
                            ? $staffId
                            : (int) $matched->staff_id;
                        $lineDuration = (int) ($matched->duration ?: (optional($standaloneCatalog->get($svcName))->duration ?? 30));
                    } else {
                        $lineStaffId = $staffId;
                        $lineDuration = (int) (optional($standaloneCatalog->get($svcName))->duration ?? 30);
                    }

                    $validationSlices[] = [
                        'staff_id' => $lineStaffId,
                        'start' => $sliceCursor ? $sliceCursor->copy() : null,
                        'duration' => $lineDuration,
                        'service_names' => [$svcName],
                    ];

                    if ($sliceCursor) {
                        $sliceCursor->addMinutes($lineDuration);
                    }
                }
            }

            if ($request->filled('combo_packages')) {
                foreach ((array) $request->input('combo_packages', []) as $comboPkgPayload) {
                    $pkgModel = ClientPackage::with(['combo.services', 'services'])->find((int) ($comboPkgPayload['client_package_id'] ?? 0));
                    if (!$pkgModel) {
                        continue;
                    }
                    [$syncPlan, $syncConflict] = $this->validateComboPackageSelection(
                        $appointment,
                        $pkgModel,
                        (array) ($comboPkgPayload['today_service_ids'] ?? []),
                        (array) ($comboPkgPayload['pending_service_ids'] ?? []),
                        (array) ($comboPkgPayload['service_staff'] ?? []),
                        $staffId,
                        $request->branch,
                        $sliceCursor,
                        $validationMode,
                        $isUnscheduled,
                        (array) ($comboPkgPayload['service_sort_order'] ?? []),
                        (array) ($comboPkgPayload['service_parallel'] ?? [])
                    );
                    if ($syncConflict !== null) {
                        return $this->conflictValidator()->toResponse($request, $syncConflict);
                    }
                    $validatedComboSyncPlans[] = $syncPlan;

                    foreach ($syncPlan['computed_slices'] ?? [] as $cSlice) {
                        $validationSlices[] = [
                            'staff_id' => $cSlice['staff_id'] ?? $staffId,
                            'start' => $cSlice['start'],
                            'duration' => $cSlice['duration'],
                            'is_parallel' => (bool) ($cSlice['is_parallel'] ?? false),
                            'service_names' => [$cSlice['name']],
                        ];
                    }
                    if ($sliceCursor && !empty($syncPlan['max_end'])) {
                        $sliceCursor = $syncPlan['max_end']->copy();
                    }
                }
            } elseif ($hasComboContext) {
                $existingComboLines = $appointment->appointmentServices()
                    ->where('is_pending', false)
                    ->where(function ($q) {
                        $q->whereNotNull('combo_name')->orWhereNotNull('combo_id')->orWhereNotNull('client_package_id');
                    })
                    ->orderBy('sort_order')
                    ->orderBy('start_time')
                    ->orderBy('id')
                    ->get();

                $existingComboTimeline = BookingConflictValidator::computeSliceTimeline(
                    $sliceCursor,
                    $existingComboLines->map(fn ($cLine, $idx) => [
                        'line_id' => $cLine->id,
                        'name' => $cLine->name,
                        'duration' => (int) ($cLine->duration ?: 30),
                        'staff_id' => ($cLine->staff_id === null || (int) $cLine->staff_id === (int) $oldStaffId)
                            ? $staffId
                            : (int) $cLine->staff_id,
                        'sort_order' => (int) ($cLine->sort_order ?? $idx),
                        'is_parallel' => (bool) $cLine->is_parallel,
                    ])->all()
                );

                foreach ($existingComboTimeline['slices'] as $cSlice) {
                    $validationSlices[] = [
                        'staff_id' => $cSlice['staff_id'],
                        'start' => $cSlice['start'],
                        'duration' => $cSlice['duration'],
                        'is_parallel' => (bool) $cSlice['is_parallel'],
                        'service_names' => [$cSlice['name']],
                    ];
                }
                if ($sliceCursor && $existingComboTimeline['max_end']) {
                    $sliceCursor = $existingComboTimeline['max_end']->copy();
                }
            }

            if (empty($validationSlices)) {
                $holdDuration = $hasComboContext
                    ? (int) ($appointment->hold_duration ?: ($appointment->combo?->effective_duration ?? 60))
                    : ($isDecideInSalon ? $appointment->effective_hold_duration : 30);
                $validationSlices[] = [
                    'staff_id' => $staffId,
                    'start' => $isUnscheduled ? null : $startTime->copy(),
                    'duration' => $holdDuration,
                    'service_names' => [],
                ];
            }

            $conflict = $this->conflictValidator()->validateBookingOperation(
                (string) $request->branch,
                $startTime->copy()->startOfDay(),
                $validationSlices,
                $validationMode,
                $isUnscheduled,
                $appointment->id,
                null
            );

            if ($conflict !== null) {
                return $this->conflictValidator()->toResponse($request, $conflict);
            }
        } elseif ($request->filled('combo_packages')) {
            foreach ((array) $request->input('combo_packages', []) as $comboPkgPayload) {
                $pkgModel = ClientPackage::with(['combo.services', 'services'])->find((int) ($comboPkgPayload['client_package_id'] ?? 0));
                if (!$pkgModel) {
                    continue;
                }
                [$syncPlan, $syncConflict] = $this->validateComboPackageSelection(
                    $appointment,
                    $pkgModel,
                    (array) ($comboPkgPayload['today_service_ids'] ?? []),
                    (array) ($comboPkgPayload['pending_service_ids'] ?? []),
                    (array) ($comboPkgPayload['service_staff'] ?? []),
                    $staffId,
                    $request->branch,
                    null,
                    $validationMode,
                    true
                );
                if ($syncConflict !== null) {
                    return $this->conflictValidator()->toResponse($request, $syncConflict);
                }
                $validatedComboSyncPlans[] = $syncPlan;
            }
        }

        $customerId = $appointment->customer_id;
        if ($phone !== '') {
            $customer = $this->findOrCreateCustomer($phone, $request->customer_name);
            if ($customer) {
                $customerId = $customer->id;
            }
        }

        if (!empty($requestedRedeemTokens)) {
            $preCheckCustomer = $customerId ? Customer::find($customerId) : $appointment->customer;
            if (!$preCheckCustomer) {
                return $this->conflictValidator()->toResponse($request, [
                    'reason' => 'validation',
                    'field' => 'redeem_service_ids',
                    'message' => 'A client profile with a phone number is required to redeem a package.',
                ]);
            }
            [, $redeemCheckError] = $this->buildPackageRedemptionPlans(
                $requestedRedeemTokens,
                $preCheckCustomer,
                $appointment->id,
                is_array($request->input('redeem_service_staff')) ? $request->input('redeem_service_staff') : []
            );
            if ($redeemCheckError !== null) {
                return $this->conflictValidator()->toResponse($request, [
                    'reason' => 'validation',
                    'field' => 'redeem_service_ids',
                    'message' => $redeemCheckError,
                ]);
            }
        }

        // Calculate verified paid lifetime revenue strictly from paid Sale net amounts
        // (never double-counting the edited appointment or including cancelled/no-show/unpaid bookings).
        $lifetimeRevenue = $this->calculateVerifiedLifetimeRevenue($customerId ? (int) $customerId : null, $phone);

        DB::transaction(function () use (
            $request,
            $appointment,
            $startTime,
            $services,
            $lifetimeRevenue,
            $phone,
            $customerId,
            $staffId,
            $oldStaffId,
            $oldStatus,
            $effectiveStatus,
            $submittedNames,
            $hasComboContext,
            $hasFreebieContext,
            $validatedComboSyncPlans,
            $isDecideInSalon,
            $requestedRedeemTokens
        ) {
            if (in_array($effectiveStatus, ['cancelled', 'no_show'], true)) {
                $this->reverseAppointmentSale($appointment, true);
            } elseif (in_array(strtolower((string) $oldStatus), ['cancelled', 'no_show'], true)) {
                $this->restoreCancelledOriginatingPackages($appointment);
            }

            $resolvedBookingAgentId = $appointment->booking_agent_id;
            if ($request->exists('booking_agent_id')) {
                $actor = $request->user();
                $submittedAgentId = $request->filled('booking_agent_id') ? (int) $request->input('booking_agent_id') : null;
                $canAdminOrManagerEdit = $actor && ($actor->isAdmin() || $actor->role === 'manager');
                $canSelfAgentEdit = $actor && $actor->role === 'agent'
                    && ($appointment->booking_agent_id === null || (int) $appointment->booking_agent_id === (int) $actor->id)
                    && ($submittedAgentId === null || $submittedAgentId === (int) $actor->id);

                if ($canAdminOrManagerEdit || $canSelfAgentEdit) {
                    $resolvedBookingAgentId = $submittedAgentId;
                }
            }

            $payload = $request->all();
            unset($payload['booking_agent_id'], $payload['combo_packages'], $payload['redeem_service_ids'], $payload['redeem_service_staff']);
            if ($hasComboContext || $hasFreebieContext || $isDecideInSalon) {
                unset($payload['price']);
            }
            $payload['status'] = $effectiveStatus;
            $payload['staff_id'] = $staffId ? (int) $staffId : null;

            $extraFields = [
                'booking_agent_id' => $resolvedBookingAgentId,
                'booking_date' => $startTime->toDateString(),
                'start_time' => $startTime->format('H:i:s'),
                'service_name' => $services,
                'decide_in_salon' => $isDecideInSalon,
                'hold_duration' => $isDecideInSalon ? $appointment->effective_hold_duration : null,
                'lifetime_revenue' => $lifetimeRevenue,
                'customer_name' => $request->customer_name ?: 'Walk-in',
                'phone' => $phone,
                'customer_id' => $customerId,
            ];
            if ($isDecideInSalon && !$appointment->sale()->exists()) {
                $extraFields['price'] = 0;
            }

            $appointment->update(array_merge($payload, $extraFields));

            if (!empty($validatedComboSyncPlans)) {
                // Sync standalone non-combo lines first, then apply combo package selections
                $existingStandalone = $appointment->appointmentServices()
                    ->whereNull('client_package_id')
                    ->whereNull('combo_id')
                    ->whereNull('combo_name')
                    ->orderBy('start_time')
                    ->orderBy('id')
                    ->get();

                $catalog = Service::whereIn('name', $submittedNames)->get()->keyBy('name');
                $remainingStandalone = $existingStandalone->keyBy('id');
                $cursor = $startTime->copy();
                $keptStandaloneIds = [];

                foreach ($submittedNames as $name) {
                    $matchedLine = $remainingStandalone->first(fn ($l) => strcasecmp(trim((string) $l->name), $name) === 0);
                    if ($matchedLine) {
                        $remainingStandalone->forget($matchedLine->id);
                        $keptStandaloneIds[] = $matchedLine->id;
                        $newLineStaffId = ($matchedLine->staff_id === null || (int) $matchedLine->staff_id === (int) $oldStaffId)
                            ? ($staffId ? (int) $staffId : null)
                            : $matchedLine->staff_id;
                        $matchedLine->update([
                            'start_time' => $cursor->copy(),
                            'staff_id' => $newLineStaffId,
                        ]);
                        $cursor->addMinutes($matchedLine->duration ?: 30);
                    } else {
                        $svc = $catalog->get($name);
                        $duration = $svc->duration ?? 30;
                        $catalogPrice = (float) ($svc->price ?? 0);
                        $newLine = AppointmentService::create([
                            'appointment_id' => $appointment->id,
                            'service_id' => $svc->id ?? null,
                            'staff_id' => $staffId ? (int) $staffId : null,
                            'name' => $name,
                            'price' => $catalogPrice,
                            'original_price' => $catalogPrice,
                            'duration' => $duration,
                            'start_time' => $cursor->copy(),
                        ]);
                        $keptStandaloneIds[] = $newLine->id;
                        $cursor->addMinutes($duration);
                    }
                }

                $removedStandaloneIds = $existingStandalone->pluck('id')->diff($keptStandaloneIds);
                if ($removedStandaloneIds->isNotEmpty()) {
                    AppointmentService::whereIn('id', $removedStandaloneIds)->delete();
                }

                foreach ($validatedComboSyncPlans as $syncPlan) {
                    $this->persistComboPackageSelection($appointment, $syncPlan, $cursor);
                }
            } elseif (($hasComboContext || $hasFreebieContext) && empty($submittedNames)) {
                // Rescheduling/updating metadata on a Combo or Service + Freebies appointment without altering its services
                $comboActiveLines = $appointment->appointmentServices()
                    ->where('is_pending', false)
                    ->orderBy('sort_order')
                    ->orderBy('start_time')
                    ->orderBy('id')
                    ->get();
                $recomputed = BookingConflictValidator::computeSliceTimeline(
                    $startTime->copy(),
                    $comboActiveLines->map(fn ($line, $idx) => [
                        'line' => $line,
                        'duration' => (int) ($line->duration ?: 30),
                        'staff_id' => ($line->staff_id === null || (int) $line->staff_id === (int) $oldStaffId)
                            ? ($staffId ? (int) $staffId : null)
                            : $line->staff_id,
                        'sort_order' => (int) ($line->sort_order ?? $idx),
                        'is_parallel' => (bool) $line->is_parallel,
                    ])->all()
                );
                foreach ($recomputed['slices'] as $cSlice) {
                    $cSlice['line']->update([
                        'start_time' => $cSlice['start'],
                        'staff_id' => $cSlice['staff_id'],
                        'sort_order' => $cSlice['sort_order'],
                        'is_parallel' => $cSlice['is_parallel'],
                    ]);
                }
            } else {
                // Synchronize appointment_services rows so the Bookings Page and Enhanced Calendar stay 100% in sync.
                $existingLines = $appointment->appointmentServices()
                    ->where('is_pending', false)
                    ->orderBy('sort_order')
                    ->orderBy('start_time')
                    ->orderBy('id')
                    ->get();
                $existingNames = $existingLines->pluck('name')->map(fn ($n) => trim((string) $n))->values()->all();

                if ($existingLines->isNotEmpty() && $submittedNames === $existingNames) {
                    $recomputed = BookingConflictValidator::computeSliceTimeline(
                        $startTime->copy(),
                        $existingLines->map(fn ($line, $idx) => [
                            'line' => $line,
                            'duration' => (int) ($line->duration ?: 30),
                            'staff_id' => ($line->staff_id === null || (int) $line->staff_id === (int) $oldStaffId)
                                ? ($staffId ? (int) $staffId : null)
                                : $line->staff_id,
                            'sort_order' => (int) ($line->sort_order ?? $idx),
                            'is_parallel' => (bool) $line->is_parallel,
                        ])->all()
                    );
                    foreach ($recomputed['slices'] as $cSlice) {
                        $cSlice['line']->update([
                            'start_time' => $cSlice['start'],
                            'staff_id' => $cSlice['staff_id'],
                            'sort_order' => $cSlice['sort_order'],
                            'is_parallel' => $cSlice['is_parallel'],
                        ]);
                    }
                } else {
                    $catalog = Service::whereIn('name', $submittedNames)->get()->keyBy('name');
                    $remainingExisting = $existingLines->keyBy('id');
                    $cursor = $startTime->copy();
                    $keptIds = [];

                    foreach ($submittedNames as $name) {
                        $matchedLine = $remainingExisting->first(fn ($l) => strcasecmp(trim((string) $l->name), $name) === 0);
                        if ($matchedLine) {
                            $remainingExisting->forget($matchedLine->id);
                            $keptIds[] = $matchedLine->id;
                            $newLineStaffId = ($matchedLine->staff_id === null || (int) $matchedLine->staff_id === (int) $oldStaffId)
                                ? ($staffId ? (int) $staffId : null)
                                : $matchedLine->staff_id;
                            $matchedLine->update([
                                'start_time' => $cursor->copy(),
                                'staff_id' => $newLineStaffId,
                            ]);
                            $cursor->addMinutes($matchedLine->duration ?: 30);
                        } else {
                            $svc = $catalog->get($name);
                            $duration = $svc->duration ?? 30;
                            $catalogPrice = (float) ($svc->price ?? (count($submittedNames) === 1 && $request->filled('price') ? $request->price : 0));
                            $newLine = AppointmentService::create([
                                'appointment_id' => $appointment->id,
                                'service_id' => $svc->id ?? null,
                                'staff_id' => $staffId ? (int) $staffId : null,
                                'name' => $name,
                                'price' => $catalogPrice,
                                'original_price' => $catalogPrice,
                                'duration' => $duration,
                                'start_time' => $cursor->copy(),
                            ]);
                            $keptIds[] = $newLine->id;
                            $cursor->addMinutes($duration);
                        }
                    }

                    $removedIds = $existingLines->pluck('id')->diff($keptIds);
                    if ($removedIds->isNotEmpty()) {
                        $removedRedemptions = ClientPackageService::whereIn('appointment_service_id', $removedIds)->get();
                        $pkgIds = $removedRedemptions->pluck('client_package_id')->unique()->filter();
                        ClientPackageService::whereIn('id', $removedRedemptions->pluck('id'))->delete();
                        AppointmentService::whereIn('id', $removedIds)->delete();
                        foreach (ClientPackage::whereIn('id', $pkgIds)->get() as $pkg) {
                            $pkg->refreshStatus();
                        }
                    }
                }
            }

            $freshLines = $appointment->appointmentServices()->where('is_pending', false)->get();
            if (!$hasComboContext && !$hasFreebieContext && $freshLines->count() === 1 && (float) $freshLines->first()->final_price == 0.0 && $request->filled('price') && (float) $request->price > 0 && !$freshLines->first()->discount_type) {
                $freshLines->first()->update([
                    'price' => (float) $request->price,
                    'original_price' => (float) $request->price,
                ]);
            }

            if (!empty($requestedRedeemTokens)) {
                $redeemCustomer = $customerId ? Customer::find($customerId) : ($appointment->customer ?: $this->findOrCreateCustomer($phone, $request->customer_name));
                if ($redeemCustomer) {
                    [$redeemPlans] = $this->buildPackageRedemptionPlans(
                        $requestedRedeemTokens,
                        $redeemCustomer,
                        $appointment->id,
                        is_array($request->input('redeem_service_staff')) ? $request->input('redeem_service_staff') : []
                    );
                    $this->applyRedemptionPlansOntoAppointment($appointment, $redeemPlans);
                }
            }

            $appointment->syncFromServices();

            if (!in_array($effectiveStatus, ['cancelled', 'no_show'], true)) {
                $this->freebieService()->syncAnchorExpiryOnReschedule($appointment);
            }
        });

        $appointment->refresh();

        if (in_array($appointment->status, ['cancelled', 'no_show'], true)) {
            $leadCategory = $appointment->status === 'cancelled' ? 'cancel' : 'no_show';
            $this->syncLeadCategoryFromAppointment($appointment, $leadCategory);
        } elseif (in_array($appointment->status, ['pending', 'confirmed', 'scheduled', 'arrived', 'in_progress', 'completed'], true)) {
            $this->markAppointmentLeadsAsBooked($appointment);
        }

        $this->syncAdLeadAttributionForAppointment($appointment, $oldApptDate);

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'Appointment updated successfully.',
                'redirect' => $redirectToCheckout
                    ? route('appointments.revenue.payment', $appointment->id)
                    : null,
            ]);
        }

        if ($redirectToCheckout) {
            return redirect()->route('appointments.revenue.payment', $appointment->id)
                ->with('info', 'Please complete payment checkout to mark this appointment as completed.');
        }

        return redirect()->back()->with('success', 'Appointment updated successfully.');
    }


    public function destroy(Request $request, Appointment $appointment)
    {
        $forbidden = $this->guardFinancePermissionForPaidReversal($request, $appointment);
        if ($forbidden !== null) {
            return $forbidden;
        }

        $conflict = $this->downstreamPackageRedemptionConflict($appointment);
        if ($conflict !== null) {
            if ($request->expectsJson()) {
                return response()->json(['success' => false, 'message' => $conflict], 422);
            }

            return redirect()->back()->with('error', $conflict);
        }

        DB::transaction(function () use ($appointment) {
            $customer = $appointment->customer ?: ($appointment->customer_id ? Customer::find($appointment->customer_id) : null);
            $apptDate = $appointment->appointment_date->copy();
            $phone = $appointment->phone;
            $appointmentId = $appointment->id;

            $rawPhone = preg_replace('/\D/', '', (string) $phone);
            $normalizedPhone = Customer::normalizePhone((string) $phone);
            $phoneCandidates = array_values(array_unique(array_filter([$normalizedPhone, $rawPhone, $phone])));
            $customerId = $appointment->customer_id ?: $customer?->id;

            $this->reverseAppointmentSale($appointment);

            $reopenNote = '[Reopened: Booking deleted on ' . now()->format('d M Y') . ']';
            $linkedLeads = Lead::where('appointment_id', $appointmentId)->orderByDesc('id')->get();
            $primaryLinkedLead = $linkedLeads->first();
            $minConversionDateStr = ($primaryLinkedLead?->created_at
                ? Carbon::parse($primaryLinkedLead->created_at)->startOfDay()
                : today()->startOfDay()
            )->toDateString();

            $otherActiveAppt = null;
            if ($customerId || !empty($phoneCandidates)) {
                $otherActiveAppt = Appointment::query()
                    ->where('id', '!=', $appointmentId)
                    ->whereNotIn('status', ['cancelled', 'no_show'])
                    ->where(function ($q) use ($customerId, $phoneCandidates) {
                        if ($customerId) {
                            $q->orWhere('customer_id', $customerId);
                        }
                        if (!empty($phoneCandidates)) {
                            $q->orWhereIn('phone', $phoneCandidates);
                        }
                    })
                    ->orderByDesc('appointment_datetime')
                    ->orderByDesc('id')
                    ->get()
                    ->first(function (Appointment $cand) use ($minConversionDateStr) {
                        $candDateStr = $cand->appointment_date->toDateString();

                        return $candDateStr >= $minConversionDateStr || $candDateStr >= today()->toDateString();
                    });
            }

            if ($otherActiveAppt) {
                foreach ($linkedLeads as $lead) {
                    $lead->update([
                        'appointment_id' => $otherActiveAppt->id,
                        'needful_done' => 'booked',
                        'next_followup_date' => null,
                    ]);
                }
            } elseif ($linkedLeads->isNotEmpty()) {
                /** @var Lead $primaryLead */
                $primaryLead = $linkedLeads->first();

                $extraOpenLeads = collect();
                if ($customerId || !empty($phoneCandidates)) {
                    $extraOpenLeads = Lead::query()
                        ->where('id', '!=', $primaryLead->id)
                        ->where(function ($q) use ($customerId, $phoneCandidates, $appointmentId) {
                            $q->where('appointment_id', $appointmentId)
                                ->orWhere(function ($sub) use ($customerId, $phoneCandidates) {
                                    $sub->where(function ($openQ) {
                                        $openQ->whereNull('needful_done')->orWhereNotIn('needful_done', Lead::CLOSED_STATUSES);
                                    })->where(function ($matchQ) use ($customerId, $phoneCandidates) {
                                        if ($customerId) {
                                            $matchQ->orWhere('customer_id', $customerId);
                                        }
                                        if (!empty($phoneCandidates)) {
                                            $matchQ->orWhereIn('phone', $phoneCandidates);
                                        }
                                    });
                                });
                        })
                        ->get();
                }

                $mergedServices = is_array($primaryLead->service_interest) ? $primaryLead->service_interest : [];
                $mergedCategories = array_merge(array_values(array_diff($primaryLead->categories_list, ['follow_up'])), ['cancel']);
                $remarksParts = [];
                $baseRemark = trim((string) preg_replace('/\s*\[Auto-closed:[^\]]*\]/i', '', (string) $primaryLead->customer_remarks));
                if ($baseRemark !== '') {
                    $remarksParts[] = $baseRemark;
                }

                foreach ($extraOpenLeads as $extra) {
                    if (is_array($extra->service_interest)) {
                        $mergedServices = array_merge($mergedServices, $extra->service_interest);
                    }
                    $mergedCategories = array_merge($mergedCategories, $extra->categories_list);
                    $extraRemark = trim((string) preg_replace('/\s*\[Auto-closed:[^\]]*\]/i', '', (string) $extra->customer_remarks));
                    if ($extraRemark !== '' && !in_array($extraRemark, $remarksParts, true)) {
                        $remarksParts[] = $extraRemark;
                    }
                    AdLeadEntry::where('lead_id', $extra->id)->update(['lead_id' => $primaryLead->id]);
                    $extra->delete();
                }

                $mergedServices = array_values(array_unique(array_filter(array_map('trim', $mergedServices))));
                $normalizedCategories = Lead::normalizeCategories($mergedCategories);
                $primaryCategory = Lead::resolvePrimaryCategory($normalizedCategories) ?: 'cancel';
                $cleanedRemarks = implode(' | ', $remarksParts);
                $newRemarks = $cleanedRemarks !== ''
                    ? (str_contains($cleanedRemarks, $reopenNote) ? $cleanedRemarks : $cleanedRemarks . ' ' . $reopenNote)
                    : $reopenNote;

                $primaryLead->update([
                    'category' => $primaryCategory,
                    'categories' => $normalizedCategories,
                    'needful_done' => null,
                    'appointment_id' => null,
                    'next_followup_date' => now()->toDateString(),
                    'service_interest' => !empty($mergedServices) ? $mergedServices : $primaryLead->service_interest,
                    'customer_remarks' => $newRemarks,
                ]);
            }

            $appointment->delete();

            AdLeadEntry::syncFromCustomerActivity($customer, $apptDate, $phone);
        });

        return redirect()->back()->with('success', 'Booking successfully deleted.');
    }

    /**
     * Guard against cancelling, marking no-show, or deleting an appointment
     * whose sold ClientPackage already has downstream redemptions on another
     * active/completed appointment.
     */
    private function downstreamPackageRedemptionConflict(Appointment $appointment): ?string
    {
        $sale = $appointment->sale()->first();
        $packageQuery = ClientPackage::where('appointment_id', $appointment->id);
        if ($sale) {
            $packageQuery->orWhere('sale_id', $sale->id);
        }

        $purchasedPackages = $packageQuery->with('services.appointmentService.appointment')->get();
        foreach ($purchasedPackages as $pkg) {
            foreach ($pkg->services as $pkgSvc) {
                if ($pkgSvc->status !== 'redeemed') {
                    continue;
                }

                $linkedLine = $pkgSvc->appointmentService;
                if (!$linkedLine) {
                    continue;
                }

                if ((int) $linkedLine->appointment_id === (int) $appointment->id) {
                    continue;
                }

                $downstreamAppt = $linkedLine->appointment;
                if ($downstreamAppt && !in_array(strtolower((string) $downstreamAppt->status), ['cancelled', 'no_show'], true)) {
                    return "Cannot cancel or delete this appointment because services from \"{$pkg->combo_name}\" have already been redeemed on a subsequent visit. Cancel the downstream redemption appointment(s) first.";
                }
            }
        }

        return null;
    }

    /**
     * Reverse any completed Sale, SaleItems, SalePayments, LoyaltyPoints, and
     * ClientPackage purchases/redemptions when an appointment is cancelled,
     * marked no-show, or deleted so phantom revenue never remains in Finance.
     */
    private function reverseAppointmentSale(Appointment $appointment, bool $preserveOriginatingPackagesForRevival = false): void
    {
        $sale = $appointment->sale()->first();
        if ($sale && auth()->check() && !auth()->user()->canEdit('finance')) {
            abort(403, self::FINANCE_REVERSAL_FORBIDDEN_MESSAGE);
        }

        $originatingPackageQuery = ClientPackage::where('appointment_id', $appointment->id);
        if ($sale) {
            $originatingPackageQuery->orWhere('sale_id', $sale->id);
        }
        $originatingPackages = $originatingPackageQuery->get();
        $originatingPackageIds = $originatingPackages->pluck('id')->all();

        // 1. Reverse any ClientPackageService redemptions tied to this appointment's service lines
        // (for non-originating Visit 2+ redemptions, always restore the slot to the parent package;
        // for originating packages being preserved in 'cancelled' state, keep their service map intact for revival).
        $serviceLineIds = $appointment->appointmentServices()->pluck('id');
        if ($serviceLineIds->isNotEmpty()) {
            $redemptionsQuery = ClientPackageService::whereIn('appointment_service_id', $serviceLineIds);
            if ($preserveOriginatingPackagesForRevival && !empty($originatingPackageIds)) {
                $redemptionsQuery->whereNotIn('client_package_id', $originatingPackageIds);
            }
            $redemptions = $redemptionsQuery->get();
            if ($redemptions->isNotEmpty()) {
                $affectedPackageIds = $redemptions->pluck('client_package_id')->unique()->filter();
                ClientPackageService::whereIn('id', $redemptions->pluck('id'))->delete();
                foreach (ClientPackage::whereIn('id', $affectedPackageIds)->get() as $pkg) {
                    $pkg->refreshStatus();
                }
            }
        }

        // 2. Handle originating ClientPackage rows purchased on this appointment or its sale
        foreach ($originatingPackages as $purchasedPkg) {
            if ($preserveOriginatingPackagesForRevival) {
                $purchasedPkg->services()->update(['sale_id' => null]);
                $purchasedPkg->update([
                    'sale_id' => null,
                    'purchased_at' => null,
                    'expires_at' => null,
                    'status' => 'cancelled',
                ]);
            } else {
                $purchasedPkg->services()->delete();
                $purchasedPkg->delete();
            }
        }

        // 2b. Void linked Service + Freebies credits (if anchor) or release scheduled slot (if future redemption)
        $this->freebieService()->onAppointmentCancelledOrDeleted($appointment);

        // 3. If a Sale exists, reverse loyalty points (both earned and redeemed) and delete the Sale
        if ($sale) {
            $earnedPoints = (int) LoyaltyTransaction::where('sale_id', $sale->id)
                ->where('type', 'earn')
                ->sum('points');

            $redeemedPoints = (int) abs((int) LoyaltyTransaction::where('sale_id', $sale->id)
                ->where('type', 'redeem')
                ->sum('points'));

            if (($earnedPoints > 0 || $redeemedPoints > 0) && $sale->customer_id) {
                $customer = Customer::find($sale->customer_id);
                if ($customer) {
                    $customer->update([
                        'loyalty_points' => max(0, (int) $customer->loyalty_points + $redeemedPoints - $earnedPoints),
                    ]);
                }
            }

            LoyaltyTransaction::where('sale_id', $sale->id)->delete();

            // Restore inventory stock for any retail products sold on this appointment sale
            $productSaleItems = $sale->items()
                ->where('type', 'product')
                ->whereNotNull('product_id')
                ->get();

            foreach ($productSaleItems as $item) {
                $product = Product::find($item->product_id);
                if ($product) {
                    $product->update([
                        'stock_quantity' => max(0, (int) $product->stock_quantity) + max(1, (int) $item->quantity),
                    ]);
                }
            }

            $sale->items()->delete();
            $sale->payments()->delete();
            $sale->delete();
        }

        if ($appointment->paid_at !== null || $appointment->payment_method !== null) {
            $appointment->update([
                'paid_at' => null,
                'payment_method' => null,
            ]);
        }
    }

    /**
     * When a previously cancelled or no-show Combo appointment is rescheduled or
     * flipped back to an active status, reactivate its unpaid originating
     * ClientPackage (or recreate it from combo metadata if missing) so the
     * appointment and Checkout charge the full Combo QAR price — never 0.00 QAR.
     */
    private function restoreCancelledOriginatingPackages(Appointment $appointment): void
    {
        $cancelledPkgs = ClientPackage::where('appointment_id', $appointment->id)
            ->whereNull('sale_id')
            ->where('status', 'cancelled')
            ->get();

        if ($cancelledPkgs->isEmpty() && !empty($appointment->combo_name) && !ClientPackage::where('appointment_id', $appointment->id)->exists()) {
            $combo = $appointment->combo_id
                ? Combo::with('services')->find($appointment->combo_id)
                : Combo::with('services')->where('name', $appointment->combo_name)->first();

            $customerId = $appointment->customer_id;
            if (!$customerId && $appointment->phone) {
                $customerId = $this->findOrCreateCustomer($appointment->phone, $appointment->customer_name)?->id;
            }

            if ($combo && $customerId) {
                $recreated = ClientPackage::create([
                    'customer_id' => $customerId,
                    'combo_id' => $combo->id,
                    'appointment_id' => $appointment->id,
                    'combo_name' => $combo->name,
                    'price_paid' => $combo->price,
                    'quantity_included' => $combo->quantity_included ?: $combo->services->count(),
                    'validity_days' => max(1, (int) ($combo->validity_days ?: 7)),
                    'purchased_at' => null,
                    'expires_at' => null,
                    'status' => 'active',
                ]);
                $cancelledPkgs = collect([$recreated]);
            }
        }

        foreach ($cancelledPkgs as $pkg) {
            $pkg->status = 'active';
            $pkg->save();
            $pkg->refreshStatus();
        }

        if ($cancelledPkgs->isNotEmpty()) {
            $appointment->syncFromServices();
        }

        $this->freebieService()->restoreVoidedAnchorCreditsOnRevival($appointment);
    }

    public function calendar(Request $request)
    {
        $staffs = Staff::active()->with('services:id,name')->orderBy('name')->get();
        $activeStaffs = $staffs;
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
                'default_duration' => $c->effective_duration,
                'quantity_included' => $c->quantity_included ?? $c->services->count(),
                'services' => $c->services->map(fn($s) => ['id' => $s->id, 'name' => $s->name, 'duration' => $s->duration])->values(),
            ])
            ->filter(fn($c) => $c['services']->isNotEmpty())
            ->values();

        $freebiePackagesCatalog = \Illuminate\Support\Facades\Schema::hasTable('service_freebie_packages')
            ? ServiceFreebiePackage::with(['anchorService', 'eligibleServices'])
                ->active()
                ->orderBy('name')
                ->get()
                ->filter(fn (ServiceFreebiePackage $p) => $p->anchorService !== null && $p->eligibleServices->isNotEmpty())
                ->map(fn (ServiceFreebiePackage $p) => [
                    'id' => (int) $p->id,
                    'name' => $p->name,
                    'price' => (float) $p->price,
                    'anchor_service_id' => (int) $p->anchor_service_id,
                    'anchor_service_name' => $p->anchorService->name,
                    'anchor_duration' => (int) ($p->anchorService->duration ?: 60),
                    'free_choices_count' => (int) $p->effective_choices_count,
                    'validity_days' => (int) $p->effective_validity_days,
                    'eligible_services' => $p->eligibleServices->map(fn (Service $s) => [
                        'id' => (int) $s->id,
                        'name' => $s->name,
                        'duration' => (int) ($s->duration ?: 30),
                        'price' => (float) $s->price,
                    ])->values(),
                ])
                ->values()
            : collect();

        $anchor = $request->date ? Carbon::parse($request->date)->startOfDay() : now()->startOfDay();
        $pendingTimeBookings = $this->pendingTimeBookingsForDate($request, $anchor);

        $prefillCustomer = $request->filled('customer_id') ? Customer::find((int) $request->input('customer_id')) : null;
        $normalizedPrefillPhone = $request->filled('phone')
            ? Customer::normalizePhone((string) $request->input('phone'))
            : '';
        if (!$prefillCustomer && $request->filled('phone')) {
            $rawPhoneDigits = preg_replace('/\D+/', '', (string) $request->input('phone'));
            $prefillPhoneCandidates = array_values(array_unique(array_filter([$normalizedPrefillPhone, $rawPhoneDigits])));
            if (!empty($prefillPhoneCandidates)) {
                $prefillCustomer = Customer::whereIn('phone', $prefillPhoneCandidates)->first();
            }
        }

        $rawServiceIds = $request->input('service_ids', []);
        if (!is_array($rawServiceIds)) {
            $rawServiceIds = array_filter(array_map('intval', explode(',', (string) $rawServiceIds)));
        }

        $rawServiceNames = [];
        foreach (['services', 'service_name'] as $svcParam) {
            if ($request->filled($svcParam)) {
                $val = $request->input($svcParam);
                $items = is_array($val) ? $val : explode(',', (string) $val);
                foreach ($items as $item) {
                    $trimmed = trim((string) $item);
                    if ($trimmed !== '' && strcasecmp($trimmed, 'Decide in Salon') !== 0) {
                        $rawServiceNames[] = $trimmed;
                    }
                }
            }
        }
        $rawServiceNames = array_values(array_unique($rawServiceNames));

        $matchedServices = $services->filter(function (Service $s) use ($rawServiceIds, $rawServiceNames) {
            if (in_array((int) $s->id, $rawServiceIds, true)) {
                return true;
            }
            foreach ($rawServiceNames as $wantedName) {
                if (strcasecmp(trim((string) $s->name), $wantedName) === 0) {
                    return true;
                }
            }
            return false;
        })->values();

        $hasPrefillTrigger = $request->boolean('open_booking')
            || $request->filled('customer_id')
            || $request->filled('name')
            || $request->filled('customer_name')
            || $request->filled('phone')
            || $request->filled('redeem_freebie_credit_id')
            || !empty($rawServiceIds)
            || !empty($rawServiceNames);

        $prefillBranch = $request->filled('branch') ? Customer::normalizeBranch((string) $request->input('branch')) : null;

        $bookingPrefill = [
            'auto_open' => $hasPrefillTrigger,
            'customer_id' => $prefillCustomer?->id ?: ($request->filled('customer_id') ? (int) $request->input('customer_id') : null),
            'customer_name' => trim((string) ($request->input('name') ?: $request->input('customer_name') ?: $prefillCustomer?->name ?: '')),
            'phone' => $normalizedPrefillPhone !== ''
                ? $normalizedPrefillPhone
                : trim((string) ($request->input('phone') ?: $prefillCustomer?->phone ?: '')),
            'branch' => ($prefillBranch && array_key_exists($prefillBranch, self::BRANCH_LABELS))
                ? $prefillBranch
                : null,
            'staff_id' => $request->filled('staff_id') ? (int) $request->input('staff_id') : null,
            'redeem_freebie_credit_id' => $request->filled('redeem_freebie_credit_id') ? (int) $request->input('redeem_freebie_credit_id') : null,
            'service_ids' => $matchedServices->pluck('id')->values()->all(),
            'services' => $matchedServices->isNotEmpty()
                ? $matchedServices->pluck('name')->values()->all()
                : $rawServiceNames,
        ];

        return view('appointments.calendar', compact('staffs', 'activeStaffs', 'services', 'products', 'agents', 'servicesCatalog', 'productsCatalog', 'combosCatalog', 'freebiePackagesCatalog', 'pendingTimeBookings', 'bookingPrefill'));
    }

    /**
     * Bookings where booking_date equals the current viewed date, but
     * start_time is null (the "Unscheduled Queue" for the calendar sidebar).
     */
    private function pendingTimeBookingsForDate(Request $request, Carbon $date): array
    {
        $branch = $this->resolveBranch($request);
        $dateStr = $date->toDateString();

        $query = Appointment::query()
            ->whereNotIn('status', ['cancelled', 'no_show'])
            ->whereIn('branch', Customer::branchMatchValues($branch))
            ->where(function ($q) use ($dateStr) {
                $q->whereDate('booking_date', $dateStr)
                    ->orWhere(function ($sub) use ($dateStr) {
                        $sub->whereNull('booking_date')
                            ->whereDate('appointment_datetime', $dateStr);
                    });
            })
            ->whereNull('start_time');

        if ($request->filled('service_name')) {
            $query->where('service_name', 'like', '%' . $request->service_name . '%');
        }
        if ($request->filled('agent_id')) {
            $query->where('booking_agent_id', $request->agent_id);
        }

        return $query->with(['clientPackages', 'appointmentServices', 'combo'])
            ->orderBy('id', 'desc')
            ->get()
            ->map(function (Appointment $a) {
                $comboName = $a->display_combo_name;
                $isComboTbd = $a->is_combo_tbd;
                $isDecideInSalon = !$isComboTbd
                    && empty($comboName)
                    && ((bool) $a->decide_in_salon || strtolower(trim((string) $a->service_name)) === 'decide in salon')
                    && $a->appointmentServices->where('is_pending', false)->isEmpty();
                $duration = $isComboTbd
                    ? (int) ($a->hold_duration ?: ($a->combo?->effective_duration ?? 60))
                    : ($isDecideInSalon
                        ? $a->effective_hold_duration
                        : $this->totalServiceDuration(explode(',', (string) $a->service_name)));

                return [
                    'id' => $a->id,
                    'customer_name' => $a->customer_name ?: 'Walk-in',
                    'phone' => $a->phone,
                    'service_name' => $isComboTbd ? 'Services TBD in Salon' : ($isDecideInSalon ? 'Decide in Salon' : $a->service_name),
                    'combo_id' => $a->combo_id,
                    'combo_name' => $comboName,
                    'is_combo_tbd' => $isComboTbd,
                    'decide_in_salon' => $isDecideInSalon,
                    'duration' => $duration,
                    'price' => (float) $a->price,
                    'status' => $a->status,
                    'branch' => $a->branch,
                    'booking_date' => $a->booking_date ?: optional($a->appointment_datetime)->format('Y-m-d'),
                    'staff_id' => $a->staff_id,
                    'notes' => $a->notes,
                ];
            })
            ->values()
            ->all();
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
     * Determine whether a staff member is off on a given date/time, and why.
     * Enforces legacy availability_status/off_from/off_to, StaffTimeOff records,
     * weekly_off days, and active ShiftPattern / ShiftBlock schedules.
     */
    private function staffOffInfo(Staff $staff, Carbon $date, ?int $duration = null, bool $enforceShiftHours = false): array
    {
        return $this->conflictValidator()->staffOffInfo($staff, $date, $duration, $enforceShiftHours);
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

    /**
     * The calendar no longer offers an "All Locations" option - every view
     * is strictly scoped to one branch to prevent cross-branch booking
     * errors, so this always resolves to a concrete branch and never to
     * null/empty, even if a request omits the param entirely.
     */
    private function resolveBranch(Request $request): string
    {
        return Customer::normalizeBranch($request->input('branch')) ?? 'old_airport';
    }

    private function filteredAppointmentQuery(Request $request)
    {
        // Cancelled/no-show bookings no longer occupy a slot, so the
        // calendar (month/week/day) shouldn't render them at all - once a
        // booking is marked either, it drops off the timeline and the slot
        // is immediately free for a new booking. Unscheduled bookings
        // (start_time is null) live in the Unscheduled Queue sidebar instead.
        $query = Appointment::query()
            ->whereNotIn('status', ['cancelled', 'no_show'])
            ->whereNotNull('start_time');

        $query->whereIn('branch', Customer::branchMatchValues($this->resolveBranch($request)));
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
        $lines = $appointment->appointmentServices
            ->where('is_pending', false)
            ->sortBy(fn (AppointmentService $s) => sprintf(
                '%05d_%015d_%010d',
                (int) ($s->sort_order ?? 0),
                $s->start_time ? $s->start_time->getTimestamp() : 0,
                (int) $s->id
            ))
            ->values();
        $parentComboName = $appointment->display_combo_name;

        if ($lines->isEmpty()) {
            $isCombo = !empty($parentComboName);
            $rawSvc = trim((string) $appointment->service_name);
            $isTbd = $isCombo && in_array(strtolower($rawSvc), ['', 'decide in salon', 'choose in salon', 'services tbd in salon'], true);
            $isDecide = !$isCombo && ((bool) $appointment->decide_in_salon || strtolower($rawSvc) === 'decide in salon');

            return [[
                'appointment_service_id' => null,
                'staff_id' => $appointment->staff_id,
                'start' => Carbon::parse($appointment->appointment_datetime),
                'duration' => $isTbd
                    ? (int) ($appointment->hold_duration ?: ($appointment->combo?->effective_duration ?? 60))
                    : ($isDecide
                        ? $appointment->effective_hold_duration
                        : $this->totalServiceDuration(explode(',', (string) $appointment->service_name))),
                'sort_order' => 0,
                'is_parallel' => false,
                'slice_index' => 0,
                'slice_count' => 1,
                'is_split_booking' => false,
                'show_price_badge' => true,
                'service_name' => $isTbd ? 'Services TBD in Salon' : ($isDecide ? 'Decide in Salon' : $appointment->service_name),
                'combo_name' => $parentComboName,
                'is_combo_tbd' => $isTbd,
                'decide_in_salon' => $isDecide,
            ]];
        }

        $sliceCount = $lines->count();
        $distinctStaffCount = $lines->map(fn ($l) => $l->staff_id ?? $appointment->staff_id)->filter()->unique()->count();
        $isSplitBooking = $sliceCount > 1 || $distinctStaffCount > 1;

        return $lines->map(fn ($line, $idx) => [
            'appointment_service_id' => $line->id,
            'staff_id' => $line->staff_id ?? $appointment->staff_id,
            'start' => $line->start_time ? Carbon::parse($line->start_time) : Carbon::parse($appointment->appointment_datetime),
            'duration' => (int) ($line->duration ?: 30),
            'sort_order' => (int) ($line->sort_order ?? $idx),
            'is_parallel' => (bool) $line->is_parallel,
            'slice_index' => $idx,
            'slice_count' => $sliceCount,
            'is_split_booking' => $isSplitBooking,
            'show_price_badge' => ($idx === 0),
            'service_name' => $line->name,
            'combo_name' => $line->combo_name ?: $parentComboName,
            'is_freebie_redemption' => (bool) $line->is_freebie_redemption,
            'freebie_package_id' => $line->freebie_package_id,
            'is_combo_tbd' => false,
            'decide_in_salon' => false,
        ])->all();
    }

    public function calendarData(Request $request)
    {
        $view = in_array($request->view, ['week', '3day', 'month']) ? $request->view : 'day';
        $anchor = $request->date ? Carbon::parse($request->date)->startOfDay() : now()->startOfDay();
        $branch = $this->resolveBranch($request);

        if ($view === 'month') {
            $rangeStart = $anchor->copy()->startOfMonth()->startOfWeek(Carbon::SUNDAY);
            $rangeEnd = $anchor->copy()->endOfMonth()->endOfWeek(Carbon::SUNDAY)->endOfDay();
        } elseif ($view === 'week') {
            $rangeStart = $anchor->copy()->startOfWeek(Carbon::MONDAY);
            $rangeEnd = $rangeStart->copy()->endOfWeek(Carbon::SUNDAY)->endOfDay();
        } elseif ($view === '3day') {
            $rangeStart = $anchor->copy();
            $rangeEnd = $rangeStart->copy()->addDays(2)->endOfDay();
        } else {
            $rangeStart = $anchor->copy()->startOfDay();
            $rangeEnd = $anchor->copy()->endOfDay();
        }

        $branchMatchValues = Customer::branchMatchValues($branch);

        $bookedStaffIdsOnDate = Appointment::query()
            ->whereNotIn('status', ['cancelled', 'no_show'])
            ->whereNotNull('start_time')
            ->whereIn('branch', $branchMatchValues)
            ->whereBetween('appointment_datetime', [$rangeStart, $rangeEnd])
            ->pluck('staff_id')
            ->merge(
                AppointmentService::query()
                    ->where('is_pending', false)
                    ->whereHas('appointment', function ($q) use ($branchMatchValues, $rangeStart, $rangeEnd) {
                        $q->whereNotIn('status', ['cancelled', 'no_show'])
                            ->whereNotNull('start_time')
                            ->whereIn('branch', $branchMatchValues)
                            ->whereBetween('appointment_datetime', [$rangeStart, $rangeEnd]);
                    })
                    ->pluck('staff_id')
            )
            ->filter()
            ->unique()
            ->values();

        $staffQuery = Staff::select('id', 'name', 'branch', 'working_hours', 'weekly_off', 'availability_status', 'off_from', 'off_to', 'profile_picture', 'bookable', 'start_date', 'end_date')
            ->with(['services:id,name', 'shiftPatterns.blocks', 'timeOffs'])
            ->where(function ($q) use ($bookedStaffIdsOnDate) {
                $q->active();
                if ($bookedStaffIdsOnDate->isNotEmpty()) {
                    $q->orWhereIn('id', $bookedStaffIdsOnDate);
                }
            })
            ->orderBy('name');

        if ($request->filled('staff_id') && !$request->boolean('all_branch_staff')) {
            $staffQuery->where('id', $request->staff_id);
        }

        $staffQuery->where(function ($q) use ($branch) {
            $q->where('branch', $branch)->orWhere('branch', 'both');
        });

        $staffs = $staffQuery->get();

        $palette = ['#1abc9c', '#3498db', '#9b59b6', '#e67e22', '#e74c3c', '#16a085', '#2ecc71', '#8e44ad'];
        $staffColors = [];
        foreach ($staffs as $i => $staff) {
            $staffColors[$staff->id] = $palette[$i % count($palette)];
        }

        $staffPayload = $staffs->map(fn($s) => [
            'id' => $s->id,
            'name' => $s->name,
            'branch' => $s->branch ?: 'both',
            'color' => $staffColors[$s->id],
            'profile_picture' => $s->profile_picture
                ? asset(str_replace('\\', '/', $s->profile_picture))
                : asset('design/sneat-admin-template/assets/img/avatars/1.png'),
            'skills' => $s->services->pluck('name')->values(),
            'skill_ids' => $s->services->pluck('id')->map(fn ($id) => (int) $id)->values(),
            'shift_windows' => $this->conflictValidator()->staffShiftWindowsForDate($s, $anchor),
        ]);

        $pendingTimeBookings = $this->pendingTimeBookingsForDate($request, $anchor);

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
                ->with(['clientPackages', 'appointmentServices', 'combo'])
                ->orderBy('appointment_datetime')
                ->get()
                ->groupBy(fn($a) => Carbon::parse($a->appointment_datetime)->format('Y-m-d'))
                ->map(fn($list) => $list->map(fn($a) => [
                    'id' => $a->id,
                    'time' => Carbon::parse($a->appointment_datetime)->format('g:i A'),
                    'customer_name' => $a->customer_name,
                    'service_name' => $a->is_combo_tbd ? 'Services TBD in Salon' : ($a->decide_in_salon ? 'Decide in Salon' : $a->service_name),
                    'combo_id' => $a->combo_id,
                    'combo_name' => $a->display_combo_name,
                    'is_combo_tbd' => $a->is_combo_tbd,
                    'decide_in_salon' => (bool) $a->decide_in_salon,
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
                'pending_time_bookings' => $pendingTimeBookings,
                'pendingTimeBookings' => $pendingTimeBookings,
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
                ->with(['appointmentServices', 'clientPackages', 'combo'])
                ->get();
            $weekUpsellSummaries = $this->upsellSummaries($weekAppointments->pluck('id'));

            // Flatten each appointment into its own per-staff slices first -
            // same reasoning as the day view - so a combo split across
            // several team members shows up in each of their day cells
            // instead of only the appointment's own booked staff.
            $weekSlices = $weekAppointments->flatMap(fn($a) => collect($this->appointmentStaffSlices($a))
                ->map(fn($slice) => (object) [
                    'appointment' => $a,
                    'appointment_service_id' => $slice['appointment_service_id'] ?? null,
                    'staff_id' => $slice['staff_id'],
                    'start' => $slice['start'],
                    'duration' => $slice['duration'],
                    'sort_order' => $slice['sort_order'] ?? 0,
                    'is_parallel' => (bool) ($slice['is_parallel'] ?? false),
                    'slice_index' => $slice['slice_index'] ?? 0,
                    'slice_count' => $slice['slice_count'] ?? 1,
                    'is_split_booking' => (bool) ($slice['is_split_booking'] ?? false),
                    'show_price_badge' => (bool) ($slice['show_price_badge'] ?? true),
                    'service_name' => $slice['service_name'],
                    'combo_name' => $slice['combo_name'] ?? $a->display_combo_name,
                    'is_combo_tbd' => $slice['is_combo_tbd'] ?? $a->is_combo_tbd,
                    'decide_in_salon' => $slice['decide_in_salon'] ?? (bool) $a->decide_in_salon,
                ]));

            $appointments = $weekSlices
                ->groupBy([fn($s) => $s->staff_id, fn($s) => $s->start->format('Y-m-d')])
                ->map(fn($byDate) => $byDate->map(fn($list) => $list->map(function ($s) use ($weekUpsellSummaries) {
                    $a = $s->appointment;
                    $upsell = $weekUpsellSummaries->get($a->id);

                    return [
                        'id'            => $a->id,
                        'appointment_service_id' => $s->appointment_service_id,
                        'time'          => $s->start->format('g:i A'),
                        'duration'      => $s->duration,
                        'start_minutes' => $s->start->hour * 60 + $s->start->minute,
                        'sort_order'    => $s->sort_order,
                        'is_parallel'   => $s->is_parallel,
                        'slice_index'   => $s->slice_index,
                        'slice_count'   => $s->slice_count,
                        'is_split_booking' => $s->is_split_booking,
                        'show_price_badge' => $s->show_price_badge,
                        'customer_name' => $a->customer_name,
                        'service_name'  => $s->service_name,
                        'combo_id'      => $a->combo_id,
                        'combo_name'    => $s->combo_name,
                        'is_combo_tbd'  => $s->is_combo_tbd,
                        'decide_in_salon' => (bool) $s->decide_in_salon,
                        'status'        => $a->status,
                        'price'         => $s->show_price_badge ? $a->price : null,
                        'booking_total' => (float) $a->price,
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
                'pending_time_bookings' => $pendingTimeBookings,
                'pendingTimeBookings'   => $pendingTimeBookings,
            ]);
        }

        // ---- DAY VIEW ----
        $workingHours = $this->branchWorkingHours($branch, $anchor);
        $uiStart = Carbon::createFromFormat('H:i', $workingHours['start']);
        $uiEnd   = Carbon::createFromTime(22, 0);
        $dayEnd  = $anchor->copy()->setTimeFromTimeString($workingHours['end']);

        $dayAppointments = $this->filteredAppointmentQuery($request)
            ->whereDate('appointment_datetime', $anchor->toDateString())
            ->with(['appointmentServices', 'clientPackages', 'combo'])
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
                        'appointment_service_id' => $slice['appointment_service_id'] ?? null,
                        'start'         => $start->format('H:i'),
                        'end'           => $end->format('H:i'),
                        'duration'      => $slice['duration'],
                        'start_minutes' => $start->hour * 60 + $start->minute,
                        'end_minutes'   => $end->hour * 60 + $end->minute,
                        'sort_order'    => $slice['sort_order'] ?? 0,
                        'is_parallel'   => (bool) ($slice['is_parallel'] ?? false),
                        'slice_index'   => $slice['slice_index'] ?? 0,
                        'slice_count'   => $slice['slice_count'] ?? 1,
                        'is_split_booking' => (bool) ($slice['is_split_booking'] ?? false),
                        'show_price_badge' => (bool) ($slice['show_price_badge'] ?? true),
                        'service_name'  => $slice['service_name'],
                        'combo_id'      => $a->combo_id,
                        'combo_name'    => $slice['combo_name'] ?? $a->display_combo_name,
                        'is_combo_tbd'  => $slice['is_combo_tbd'] ?? $a->is_combo_tbd,
                        'decide_in_salon' => (bool) ($slice['decide_in_salon'] ?? $a->decide_in_salon),
                        'status'        => $a->status,
                        'customer_name' => $a->customer_name,
                        'phone'         => $a->phone,
                        'price'         => ($slice['show_price_badge'] ?? true) ? $a->price : null,
                        'booking_total' => (float) $a->price,
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
            'pending_time_bookings' => $pendingTimeBookings,
            'pendingTimeBookings'   => $pendingTimeBookings,
        ]);
    }


    function branchWorkingHours(string $branch, Carbon $date): array
    {
        $isFriday = $date->isFriday();

        return match ($branch) {
            'wakrah' => $isFriday ? ['start' => '12:00', 'end' => '22:00'] : ['start' => '11:00', 'end' => '21:00'],
            default => $isFriday ? ['start' => '12:00', 'end' => '22:00'] : ['start' => '10:00', 'end' => '22:00'],
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
        return $this->conflictValidator()->unskilledServices($staffId, $serviceNames);
    }

    private function staffHasTimeConflict(?int $staffId, Carbon $newStart, int $newDuration, ?int $ignoreAppointmentId = null, ?int $ignoreAppointmentServiceId = null)
    {
        return $this->conflictValidator()->staffHasAnyConflict($staffId, $newStart, $newDuration, $ignoreAppointmentId, $ignoreAppointmentServiceId);
    }


    public function customerProfile($phone)
    {
        // Normalize incoming phone (supports 8-digit local, 974..., +974..., and 00974...)
        $rawDigits = preg_replace('/\D/', '', (string) $phone);
        if ($rawDigits === '') {
            return response()->json(['message' => 'No records found for this customer'], 404);
        }

        [$countryCode, $localNumber] = Lead::splitPhone($rawDigits);
        $normalizedPhone = Lead::normalizePhone($countryCode, $localNumber);
        $withQatarCode = strlen($normalizedPhone) === 8 ? '974' . $normalizedPhone : null;
        $phoneCandidates = array_values(array_unique(array_filter([
            $normalizedPhone,
            $rawDigits,
            $withQatarCode,
        ])));
        $phone = $normalizedPhone !== '' ? $normalizedPhone : $rawDigits;

        $appointments = Appointment::with(['agent', 'clientPackages', 'appointmentServices'])
            ->where(function ($q) use ($phoneCandidates) {
                $q->whereIn('phone', $phoneCandidates);
                foreach ($phoneCandidates as $cand) {
                    $q->orWhereRaw("REPLACE(REPLACE(REPLACE(REPLACE(phone, '+', ''), ' ', ''), '(', ''), ')', '') = ?", [$cand]);
                }
            })
            ->orderBy('appointment_datetime', 'asc')->get();

        if ($appointments->isEmpty()) {
            return response()->json(['message' => 'No records found for this customer'], 404);
        }

        $completedVisits = $appointments->where('status', 'completed')->values();
        $customerName = $appointments->first()->customer_name;
        $totalVisits = $completedVisits->count();
        $firstVisit = $completedVisits->isNotEmpty()
            ? $completedVisits->first()->appointment_datetime->format('d M Y, h:i A')
            : '—';
        $lastVisit = $completedVisits->isNotEmpty()
            ? $completedVisits->last()->appointment_datetime->format('d M Y, h:i A')
            : '—';

        $servicesTaken = collect();
        foreach ($appointments as $a) {
            $servicesTaken = $servicesTaken->merge(
                collect(explode(', ', (string) $a->service_name))
                    ->map(fn ($s) => trim((string) $s))
                    ->filter(fn ($s) => $s !== '' && !in_array(strtolower($s), ['decide in salon', 'choose in salon', 'services tbd in salon'], true))
            );
        }
        $servicesTaken = $servicesTaken->unique()->implode(', ');
        $firstCustomerId = $appointments->pluck('customer_id')->filter()->first();
        $lifetimeRevenue = $this->calculateVerifiedLifetimeRevenue(
            $firstCustomerId ? (int) $firstCustomerId : null,
            $phone
        );

        $appointmentList = $appointments->map(function ($a) {
            return [
                'appointment_datetime' => $a->appointment_datetime->format('d M Y, h:i A'),
                'service_name' => $a->is_combo_tbd ? 'Choose in Salon' : ($a->decide_in_salon ? 'Decide in Salon' : $a->service_name),
                'combo_name' => $a->display_combo_name,
                'decide_in_salon' => (bool) $a->decide_in_salon,
                'price' => number_format($a->price, 2),
                'branch' => self::BRANCH_LABELS[Customer::normalizeBranch($a->branch) ?? ''] ?? ucwords(str_replace('_', ' ', (string) $a->branch)),
                'agent' => $a->agent->name ?? '—'
            ];
        });

        $matchedCustomer = $firstCustomerId
            ? Customer::find((int) $firstCustomerId)
            : Customer::whereIn('phone', $phoneCandidates)->first();

        return response()->json([
            'customer_id' => $appointments->first()->customer_id ?: $matchedCustomer?->id,
            'customer_name' => $customerName,
            'phone' => $appointments->first()->phone, // show original format
            'total_visits' => $totalVisits,
            'first_visit' => $firstVisit,
            'last_visit' => $lastVisit,
            'services_taken' => $servicesTaken,
            'lifetime_revenue' => $lifetimeRevenue,
            'available_freebie_credits' => $matchedCustomer ? $matchedCustomer->redeemableFreebieCreditsPayload() : [],
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
        $this->normalizeRequestBranch($request);

        $request->validate([
            // "Decide in Salon" bookings have no service chosen yet, so the
            // services list is only mandatory without that flag.
            'services' => 'required_without:decide_in_salon|array',
            'appointment_datetime' => 'required|date',
            'branch' => 'required|in:old_airport,wakrah',
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
        $concreteNames = $this->conflictValidator()->filterConcreteServiceNames($serviceNames);

        // Total duration of selected services (60-minute hold for Decide in Salon)
        $duration = ($decideInSalon && empty($concreteNames))
            ? (int) ($request->input('duration') ?: 60)
            : $this->totalServiceDuration($serviceNames);

        $workingHours = $this->branchWorkingHours($branch, $appointmentTime);
        $startBranch = Carbon::createFromFormat('H:i', $workingHours['start'])
            ->setDate($appointmentTime->year, $appointmentTime->month, $appointmentTime->day);
        $endBranch = Carbon::createFromFormat('H:i', $workingHours['end'])
            ->setDate($appointmentTime->year, $appointmentTime->month, $appointmentTime->day);
        $appointmentEnd = $appointmentTime->copy()->addMinutes($duration);

        if ($appointmentTime->lt($startBranch) || $appointmentTime->gt($endBranch) || $appointmentEnd->gt($endBranch)) {
            return response()->json([]);
        }

        $staffQuery = Staff::active()
            ->where('availability_status', 'present')
            ->with(['shiftPatterns.blocks', 'timeOffs']);

        if ($decideInSalon) {
            // No service picked yet - offer every present staff member for
            // the branch rather than filtering by a skill match that can't
            // be evaluated.
        } else {
            $mappedServiceIds = Service::whereIn('name', $serviceNames)
                ->get()
                ->filter(fn (Service $svc) => $this->conflictValidator()->branchHasMappedSkillForService($branch, (int) $svc->id))
                ->pluck('id')
                ->values();

            if ($mappedServiceIds->isNotEmpty()) {
                $eligibleStaffIds = \Illuminate\Support\Facades\DB::table('service_staff')
                    ->whereIn('service_id', $mappedServiceIds)
                    ->groupBy('staff_id')
                    ->havingRaw('COUNT(DISTINCT service_id) = ?', [$mappedServiceIds->count()])
                    ->pluck('staff_id');

                $staffQuery->whereIn('id', $eligibleStaffIds);
            }
        }

        $staffs = $staffQuery
            ->where(function ($q) use ($branch) {
                $q->where('branch', $branch)
                    ->orWhere('branch', 'both');
            })
            ->get()
            ->filter(function ($staff) use ($appointmentTime, $duration, $excludeAppointmentId) {
                // ❌ Off / Leave / TimeOff / Weekly Off / Scheduled Shift check
                $offInfo = $this->staffOffInfo($staff, $appointmentTime, $duration, true);
                if ($offInfo['off']) {
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
        $request->validate([
            'status' => 'required|in:pending,confirmed,scheduled,arrived,in_progress,completed,no_show,cancelled',
        ]);

        if ($request->status === 'completed' && !$appointment->sale()->exists()) {
            if ($this->isUnresolvedDecideInSalon($appointment)) {
                return $this->conflictValidator()->toResponse($request, [
                    'reason' => 'decide_in_salon',
                    'field' => 'services',
                    'message' => 'Please add the service(s) or combo performed before completing checkout.',
                ], true);
            }

            if ($this->hasUnpickedOriginatingCombo($appointment) && !$request->boolean('package_prepurchase_only')) {
                return $this->conflictValidator()->toResponse($request, [
                    'reason' => 'combo_tbd',
                    'field' => 'package_prepurchase_only',
                    'message' => self::UNPICKED_COMBO_CHECKOUT_MESSAGE,
                ], true);
            }

            // Pure 0.00 QAR freebie redemption appointments owe nothing and can complete cleanly.
            $isZeroDueFreebieRedemption = (bool) $appointment->is_freebie_redemption
                && (float) $appointment->price <= 0.009
                && !$appointment->upsells()->exists();

            if ($isZeroDueFreebieRedemption) {
                DB::transaction(function () use ($appointment) {
                    $appointment->update([
                        'status' => 'completed',
                        'paid_at' => now('Asia/Qatar'),
                    ]);
                    $this->freebieService()->onAppointmentPaidOrCompleted($appointment);
                    $this->markAppointmentLeadsAsBooked($appointment);
                    $this->syncAdLeadAttributionForAppointment($appointment);
                });

                return response()->json([
                    'success' => true,
                    'message' => 'Freebie redemption completed.',
                ]);
            }

            // Do NOT mutate status to 'completed' before payment is recorded.
            return response()->json([
                'success' => true,
                'redirect' => route('appointments.revenue.payment', $appointment->id),
            ]);
        }

        if (in_array($request->status, ['no_show', 'cancelled'], true)) {
            $forbidden = $this->guardFinancePermissionForPaidReversal($request, $appointment);
            if ($forbidden !== null) {
                return $forbidden;
            }

            $conflict = $this->downstreamPackageRedemptionConflict($appointment);
            if ($conflict !== null) {
                return response()->json([
                    'success' => false,
                    'message' => $conflict,
                ], 422);
            }
        }

        $previousStatus = strtolower((string) $appointment->status);

        DB::transaction(function () use ($request, $appointment, $previousStatus) {
            if (in_array($request->status, ['no_show', 'cancelled'], true)) {
                $this->reverseAppointmentSale($appointment, true);
            } elseif (in_array($previousStatus, ['no_show', 'cancelled'], true)) {
                $this->restoreCancelledOriginatingPackages($appointment);
            }

            $appointment->update(['status' => $request->status]);

            if ($request->status === 'completed') {
                $this->freebieService()->onAppointmentPaidOrCompleted($appointment);
            }

            if (in_array($request->status, ['no_show', 'cancelled'], true)) {
                $this->syncLeadCategoryFromAppointment($appointment, $request->status === 'no_show' ? 'no_show' : 'cancel');
            } else {
                $this->markAppointmentLeadsAsBooked($appointment);
            }

            $this->syncAdLeadAttributionForAppointment($appointment);
        });

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
     * When an appointment that was previously cancelled or no-show is moved
     * back to an active/completed status (confirmed, scheduled, arrived,
     * in_progress, completed), transition its linked or open Lead to 'booked'
     * and ensure customer_id is populated.
     */
    private function markAppointmentLeadsAsBooked(Appointment $appointment): void
    {
        $rawPhone = preg_replace('/\D/', '', (string) $appointment->phone);
        [$countryCode, $localNumber] = Lead::splitPhone($rawPhone);
        $normalizedPhone = Lead::normalizePhone($countryCode, $localNumber);
        $phoneCandidates = array_values(array_unique(array_filter([$normalizedPhone, $rawPhone])));

        if (!$appointment->customer_id && $rawPhone !== '') {
            $customer = $this->findOrCreateCustomer($rawPhone, $appointment->customer_name);
            if ($customer) {
                $appointment->update(['customer_id' => $customer->id]);
            }
        }

        $leads = Lead::where('appointment_id', $appointment->id)
            ->orWhere(function ($q) use ($appointment, $phoneCandidates) {
                $q->where(function ($sub) {
                    $sub->whereNull('needful_done')->orWhereNotIn('needful_done', ['booked', 'no']);
                })->where(function ($match) use ($appointment, $phoneCandidates) {
                    if ($appointment->customer_id) {
                        $match->where('customer_id', $appointment->customer_id);
                    }
                    if (!empty($phoneCandidates)) {
                        $match->orWhereIn('phone', $phoneCandidates);
                    }
                });
            })
            ->orderByDesc('id')
            ->get()
            ->filter(fn(Lead $l) => !($l->needful_done === 'no' && $l->appointment_id !== $appointment->id))
            ->values();

        if ($leads->isEmpty()) {
            return;
        }

        // Prefer the lead explicitly linked to this appointment if one exists, otherwise the latest open lead
        /** @var Lead $primaryLead */
        $primaryLead = $leads->firstWhere('appointment_id', $appointment->id) ?: $leads->first();
        $mergedServices = is_array($primaryLead->service_interest) ? $primaryLead->service_interest : [];
        $mergedCategories = $primaryLead->categories_list;
        $remarksParts = [];
        $resolvedAgentId = $primaryLead->assigned_agent_id ?: $appointment->booking_agent_id;

        foreach ($leads as $row) {
            if (is_array($row->service_interest)) {
                $mergedServices = array_merge($mergedServices, $row->service_interest);
            }
            $mergedCategories = array_merge($mergedCategories, $row->categories_list);
            $r = trim((string) $row->customer_remarks);
            if ($r !== '' && !in_array($r, $remarksParts, true)) {
                $remarksParts[] = $r;
            }
            if (!$resolvedAgentId && $row->assigned_agent_id) {
                $resolvedAgentId = $row->assigned_agent_id;
            }
        }

        foreach ($leads as $row) {
            if ($row->id !== $primaryLead->id && !in_array($row->needful_done, Lead::CLOSED_STATUSES, true)) {
                AdLeadEntry::where('lead_id', $row->id)->update(['lead_id' => $primaryLead->id]);
                $row->delete();
            }
        }

        $mergedServices = array_values(array_unique(array_filter(array_map('trim', $mergedServices))));
        $normalizedCategories = Lead::normalizeCategories($mergedCategories);
        $primaryCategory = Lead::resolvePrimaryCategory($normalizedCategories) ?: $primaryLead->category;
        $existingRemarks = implode(' | ', $remarksParts);
        $autoCloseNote = '[Auto-closed: Booked on ' . now()->format('d M Y') . ']';
        $newRemarks = $existingRemarks !== ''
            ? (str_contains($existingRemarks, $autoCloseNote) ? $existingRemarks : $existingRemarks . ' ' . $autoCloseNote)
            : $autoCloseNote;

        $primaryLead->update([
            'needful_done' => 'booked',
            'category' => $primaryCategory,
            'categories' => !empty($normalizedCategories) ? $normalizedCategories : $primaryLead->categories,
            'customer_id' => $appointment->customer_id ?: $primaryLead->customer_id,
            'appointment_id' => $appointment->id,
            'assigned_agent_id' => $resolvedAgentId,
            'next_followup_date' => null,
            'service_interest' => !empty($mergedServices) ? $mergedServices : $primaryLead->service_interest,
            'customer_remarks' => $newRemarks,
        ]);
    }

    /**
     * Synchronize or reopen a Lead when an appointment is marked Cancelled or
     * No-show. Checks if another active/completed Appointment exists for the
     * client first; if not, clears appointment_id & needful_done and sets
     * next_followup_date = today so the lead stays visible for re-booking.
     */
    private function syncLeadCategoryFromAppointment(Appointment $appointment, string $category): void
    {
        $rawPhone = preg_replace('/\D/', '', (string) $appointment->phone);
        [$countryCode, $localNumber] = Lead::splitPhone($rawPhone);
        $normalizedPhone = Lead::normalizePhone($countryCode, $localNumber);
        $phoneCandidates = array_values(array_unique(array_filter([$normalizedPhone, $rawPhone])));

        if (!$appointment->customer_id && $rawPhone !== '') {
            $customer = $this->findOrCreateCustomer($rawPhone, $appointment->customer_name);
            if ($customer) {
                $appointment->update(['customer_id' => $customer->id]);
            }
        }

        $incomingServices = $appointment->service_name
            ? array_values(array_filter(array_map('trim', explode(',', $appointment->service_name))))
            : [];

        // 1. Always check appointment_id FIRST so a 'booked' lead tied to this appointment is found
        $lead = Lead::where('appointment_id', $appointment->id)->first();

        // 2. Otherwise find any existing open lead for this phone/customer
        if (!$lead && ($normalizedPhone !== '' || $appointment->customer_id)) {
            $lead = Lead::where(function ($q) use ($appointment, $phoneCandidates) {
                if ($appointment->customer_id) {
                    $q->where('customer_id', $appointment->customer_id);
                }
                if (!empty($phoneCandidates)) {
                    $q->orWhereIn('phone', $phoneCandidates);
                }
            })
                ->where(function ($q) {
                    $q->whereNull('needful_done')->orWhereNotIn('needful_done', Lead::CLOSED_STATUSES);
                })
                ->orderByDesc('id')
                ->first();
        }

        // Check whether this customer/phone has ANOTHER active or completed Appointment
        // on/after this lead's creation date (or today), excluding historical visits prior to the lead
        $minConversionDateStr = ($lead?->created_at
            ? Carbon::parse($lead->created_at)->startOfDay()
            : today()->startOfDay()
        )->toDateString();

        $otherActiveAppt = null;
        if ($appointment->customer_id || !empty($phoneCandidates)) {
            $otherActiveAppt = Appointment::query()
                ->where('id', '!=', $appointment->id)
                ->whereNotIn('status', ['cancelled', 'no_show'])
                ->where(function ($q) use ($appointment, $phoneCandidates) {
                    if ($appointment->customer_id) {
                        $q->orWhere('customer_id', $appointment->customer_id);
                    }
                    if (!empty($phoneCandidates)) {
                        $q->orWhereIn('phone', $phoneCandidates);
                    }
                })
                ->orderByDesc('appointment_datetime')
                ->orderByDesc('id')
                ->get()
                ->first(function (Appointment $cand) use ($minConversionDateStr) {
                    $candDateStr = $cand->appointment_date->toDateString();

                    return $candDateStr >= $minConversionDateStr || $candDateStr >= today()->toDateString();
                });
        }

        if (!$lead && $otherActiveAppt) {
            $lead = Lead::where('appointment_id', $otherActiveAppt->id)->first();
        }

        // Resolve agent attribution (Bidirectional sync + inheritance from previous leads)
        $inheritedAgentId = $lead?->assigned_agent_id ?: $appointment->booking_agent_id;
        if (!$inheritedAgentId && ($appointment->customer_id || !empty($phoneCandidates))) {
            $inheritedAgentId = Lead::where(function ($q) use ($appointment, $phoneCandidates) {
                if ($appointment->customer_id) {
                    $q->where('customer_id', $appointment->customer_id);
                }
                if (!empty($phoneCandidates)) {
                    $q->orWhereIn('phone', $phoneCandidates);
                }
            })
                ->whereNotNull('assigned_agent_id')
                ->orderByDesc('id')
                ->value('assigned_agent_id');
        }

        if ($lead) {
            $extraOpenLeads = collect();
            if ($normalizedPhone !== '' || $appointment->customer_id) {
                $extraOpenLeads = Lead::where('id', '!=', $lead->id)
                    ->where(function ($q) use ($appointment, $phoneCandidates) {
                        if ($appointment->customer_id) {
                            $q->where('customer_id', $appointment->customer_id);
                        }
                        if (!empty($phoneCandidates)) {
                            $q->orWhereIn('phone', $phoneCandidates);
                        }
                    })
                    ->where(function ($q) {
                        $q->whereNull('needful_done')->orWhereNotIn('needful_done', Lead::CLOSED_STATUSES);
                    })
                    ->get();
            }

            $existingServices = is_array($lead->service_interest) ? $lead->service_interest : [];
            $mergedCategories = in_array($category, ['cancel', 'no_show'], true)
                ? array_values(array_diff($lead->categories_list, ['follow_up']))
                : $lead->categories_list;
            if ($lead->appointment_id === $appointment->id && in_array($category, ['cancel', 'no_show'], true)) {
                $oppositeMissed = $category === 'cancel' ? 'no_show' : 'cancel';
                if (count($mergedCategories) === 1 && $mergedCategories[0] === $oppositeMissed) {
                    $mergedCategories = [];
                }
            }
            $mergedCategories[] = $category;

            $remarksParts = [];
            $baseRemark = trim((string) preg_replace('/\s*\[Auto-closed:[^\]]*\]/i', '', (string) $lead->customer_remarks));
            if ($baseRemark !== '') {
                $remarksParts[] = $baseRemark;
            }

            foreach ($extraOpenLeads as $extra) {
                if (is_array($extra->service_interest)) {
                    $existingServices = array_merge($existingServices, $extra->service_interest);
                }
                $mergedCategories = array_merge($mergedCategories, $extra->categories_list);
                $extraRemark = trim((string) preg_replace('/\s*\[Auto-closed:[^\]]*\]/i', '', (string) $extra->customer_remarks));
                if ($extraRemark !== '' && !in_array($extraRemark, $remarksParts, true)) {
                    $remarksParts[] = $extraRemark;
                }
                AdLeadEntry::where('lead_id', $extra->id)->update(['lead_id' => $lead->id]);
                $extra->delete();
            }

            $mergedServices = array_values(array_unique(array_filter(array_map('trim', array_merge($existingServices, $incomingServices)))));
            $normalizedCategories = Lead::normalizeCategories($mergedCategories);
            $primaryCategory = Lead::resolvePrimaryCategory($normalizedCategories) ?: $category;

            if ($otherActiveAppt) {
                $autoCloseNote = '[Auto-closed: Booked on ' . now()->format('d M Y') . ']';
                $cleanedRemarks = implode(' | ', $remarksParts);
                $newRemarks = $cleanedRemarks !== ''
                    ? (str_contains($cleanedRemarks, $autoCloseNote) ? $cleanedRemarks : $cleanedRemarks . ' ' . $autoCloseNote)
                    : $autoCloseNote;

                $lead->update([
                    'category' => $primaryCategory,
                    'categories' => $normalizedCategories,
                    'needful_done' => 'booked',
                    'next_followup_date' => null,
                    'service_interest' => !empty($mergedServices) ? $mergedServices : $lead->service_interest,
                    'appointment_id' => $otherActiveAppt->id,
                    'customer_id' => $lead->customer_id ?: $appointment->customer_id,
                    'assigned_agent_id' => $inheritedAgentId,
                    'customer_remarks' => $newRemarks,
                ]);
                $lead->touch();

                return;
            }

            $reopenNote = '[Reopened: ' . ucfirst($category) . ' on ' . now()->format('d M Y') . ']';
            $cleanedRemarks = implode(' | ', $remarksParts);
            $newRemarks = $cleanedRemarks !== ''
                ? (str_contains($cleanedRemarks, $reopenNote) ? $cleanedRemarks : $cleanedRemarks . ' ' . $reopenNote)
                : $reopenNote;

            $lead->update([
                'category' => $primaryCategory,
                'categories' => $normalizedCategories,
                'needful_done' => null,
                'next_followup_date' => now()->toDateString(),
                'service_interest' => !empty($mergedServices) ? $mergedServices : $lead->service_interest,
                'appointment_id' => null,
                'customer_id' => $lead->customer_id ?: $appointment->customer_id,
                'assigned_agent_id' => $inheritedAgentId,
                'customer_remarks' => $newRemarks,
            ]);
            $lead->touch();

            return;
        }

        // Never create a ghost lead with an empty phone number (e.g. a walk-in booking without phone)
        $leadPhone = $normalizedPhone !== '' ? $normalizedPhone : trim((string) $appointment->phone);
        if ($leadPhone === '') {
            return;
        }

        if ($otherActiveAppt) {
            Lead::create([
                'phone' => $leadPhone,
                'customer_id' => $appointment->customer_id,
                'appointment_id' => $otherActiveAppt->id,
                'assigned_agent_id' => $inheritedAgentId,
                'category' => $category,
                'categories' => [$category],
                'service_interest' => !empty($incomingServices) ? $incomingServices : null,
                'needful_done' => 'booked',
                'next_followup_date' => null,
            ]);

            return;
        }

        Lead::create([
            'phone' => $leadPhone,
            'customer_id' => $appointment->customer_id,
            'appointment_id' => null,
            'assigned_agent_id' => $inheritedAgentId,
            'category' => $category,
            'categories' => [$category],
            'service_interest' => !empty($incomingServices) ? $incomingServices : null,
            'needful_done' => null,
            'next_followup_date' => now()->toDateString(),
        ]);
    }

    /**
     * JSON detail payload for the appointment drawer.
     */
    public function show(Appointment $appointment)
    {
        $appointment->load('customer', 'staff', 'agent', 'combo', 'clientPackages.combo.services', 'clientPackages.services', 'appointmentServices.staff', 'upsells.staff');
        $activeLines = $appointment->appointmentServices->where('is_pending', false)->values();
        $pendingLines = $appointment->appointmentServices->where('is_pending', true)->values();
        $serviceLines = $activeLines->map(fn ($s) => $this->formatServiceLine($s))->values()->all();
        $pendingServiceLines = $pendingLines->map(fn ($s) => $this->formatServiceLine($s))->values()->all();
        $comboPackages = $this->buildAppointmentComboPackagesPayload($appointment);
        $upsellLines = $appointment->upsells->map(fn ($u) => $this->formatUpsellLine($u))->values()->all();

        $customer = $appointment->customer;
        if (!$customer && $appointment->phone) {
            $normalizedPhone = Customer::normalizePhone($appointment->phone);
            $customer = Customer::where('phone', $normalizedPhone)
                ->orWhere('phone', $appointment->phone)
                ->first();
            if ($customer && !$appointment->customer_id) {
                $appointment->update(['customer_id' => $customer->id]);
            }
        }
        $recentVisits = [];
        if ($customer) {
            $recentVisits = $customer->appointments()
                ->where('id', '!=', $appointment->id)
                ->with(['clientPackages', 'appointmentServices'])
                ->orderByDesc('appointment_datetime')
                ->limit(3)
                ->get(['id', 'appointment_datetime', 'service_name', 'combo_id', 'combo_name', 'status'])
                ->map(fn ($a) => [
                    'date' => $a->appointment_datetime->format('d M Y'),
                    'service_name' => $a->is_combo_tbd ? 'Choose in Salon' : $a->service_name,
                    'combo_name' => $a->display_combo_name,
                    'status' => $a->status,
                ])->all();
        }

        $isUnscheduled = $appointment->start_time === null;
        $dateObj = Carbon::parse($appointment->booking_date ?: $appointment->appointment_datetime);
        $canonicalBranch = Customer::normalizeBranch($appointment->branch) ?? $appointment->branch;

        $isPaid = (bool) ($appointment->paid_at || $appointment->sale()->exists());
        $redeemablePackageServices = (!$isPaid && $customer)
            ? $customer->redeemablePackageServices(null)->values()->all()
            : [];
        $redeemableFreebieCredits = (!$isPaid && $customer)
            ? $customer->redeemableFreebieCreditsPayload($appointment->id)
            : [];

        return response()->json([
            'id' => $appointment->id,
            'customer_name' => $appointment->customer_name,
            'phone' => $appointment->phone,
            'customer_id' => $appointment->customer_id,
            'customer_visits' => $customer ? $customer->appointments()->where('status', 'completed')->count() : null,
            'customer_allergies' => $customer->allergies ?? null,
            'customer_notes' => $customer->notes ?? null,
            'recent_visits' => $recentVisits,
            'appointment_datetime' => $isUnscheduled
                ? $dateObj->format('D, d M Y') . ' (Unscheduled)'
                : $appointment->appointment_datetime->format('D, d M Y \a\t h:i A'),
            'date' => $dateObj->format('Y-m-d'),
            'time' => $isUnscheduled ? '' : $appointment->appointment_datetime->format('H:i'),
            'is_unscheduled' => $isUnscheduled,
            'branch' => self::BRANCH_LABELS[$canonicalBranch] ?? ucwords(str_replace('_', ' ', (string) $appointment->branch)),
            'branch_raw' => $canonicalBranch,
            'staff_id' => $appointment->staff_id,
            'staff_name' => $appointment->staff->name ?? 'Unassigned',
            'agent_name' => $appointment->agent->name ?? null,
            'status' => $appointment->status,
            'is_paid' => $isPaid,
            'price' => (float) $appointment->price,
            'service_name' => $appointment->service_name,
            'combo_id' => $appointment->combo_id,
            'combo_name' => $appointment->display_combo_name,
            'is_combo_tbd' => $appointment->is_combo_tbd,
            'decide_in_salon' => (bool) $appointment->decide_in_salon,
            'hold_duration' => $appointment->effective_hold_duration,
            'visit_span_minutes' => $appointment->visit_span_minutes,
            'combo_packages' => $comboPackages,
            'redeemable_package_services' => $redeemablePackageServices,
            'redeemable_freebie_credits' => $redeemableFreebieCredits,
            'freebie_package_id' => $appointment->freebie_package_id,
            'is_freebie_redemption' => (bool) $appointment->is_freebie_redemption,
            'notes' => $appointment->notes,
            'services' => $serviceLines,
            'pending_services' => $pendingServiceLines,
            'upsells' => $upsellLines,
            'upsell_total' => array_sum(array_column($upsellLines, 'amount')),
            'payment_url' => route('appointments.revenue.payment', $appointment->id),
            'profile_url' => $appointment->customer_id ? route('customers.show', $appointment->customer_id) : null,
        ]);
    }

    /**
     * Move an appointment to a new date/time and, optionally, a different
     * staff member. Used by both calendar drag-and-drop (including dragging
     * from the Unscheduled Queue sidebar) and the drawer's manual
     * "Reschedule" quick action.
     */
    public function reschedule(Request $request, Appointment $appointment)
    {
        if ($appointment->sale()->exists()) {
            return $this->rejectPaidBookingMutation($request, true);
        }

        $this->normalizeRequestBranch($request, false);

        if (!$request->filled('booking_date') && $request->filled('date')) {
            $request->merge(['booking_date' => $request->input('date')]);
        }
        if (!$request->filled('start_time') && $request->filled('time')) {
            $request->merge(['start_time' => $request->input('time')]);
        }

        $request->validate([
            'appointment_datetime' => 'required_without:start_time|nullable|date',
            'start_time' => 'required_without:appointment_datetime|nullable|string',
            'booking_date' => 'nullable|date',
            'staff_id' => 'nullable|exists:staff,id',
            'branch' => 'nullable|in:old_airport,wakrah',
            'duration' => 'nullable|integer|min:5|max:720',
            'appointment_service_id' => 'nullable|integer|exists:appointment_services,id',
        ]);

        $wasUnscheduled = $appointment->start_time === null;
        $previousStaffId = $appointment->staff_id;
        $oldStart = $appointment->appointment_date->copy();

        if ($request->filled('appointment_datetime')) {
            $newStart = Carbon::parse($request->appointment_datetime);
        } else {
            $baseDate = $request->booking_date
                ?: ($appointment->booking_date ?: $appointment->appointment_date->toDateString());
            $newStart = Carbon::parse($baseDate . ' ' . $request->start_time);
        }

        $staffId = $request->exists('staff_id')
            ? ($request->filled('staff_id') ? (int) $request->input('staff_id') : null)
            : ($appointment->staff_id ? (int) $appointment->staff_id : null);
        $branch = Customer::normalizeBranch($request->branch ?: $appointment->branch) ?? 'old_airport';
        $requestedDuration = $request->filled('duration') ? (int) $request->input('duration') : null;
        $targetServiceLineId = $request->filled('appointment_service_id')
            ? (int) $request->input('appointment_service_id')
            : null;

        $serviceLines = $appointment->appointmentServices()
            ->where('is_pending', false)
            ->orderBy('sort_order')
            ->orderBy('start_time')
            ->orderBy('id')
            ->get();

        // Single-slice drag / resize on a multi-slice booking: update ONLY that slice
        // without corrupting sibling slices or resetting the locked package price.
        if ($targetServiceLineId !== null && $serviceLines->count() > 1) {
            /** @var ?AppointmentService $targetLine */
            $targetLine = $serviceLines->firstWhere('id', $targetServiceLineId);
            if (!$targetLine) {
                return response()->json([
                    'success' => false,
                    'reason' => 'validation',
                    'message' => 'Selected service slice was not found on this booking.',
                ], 422);
            }

            $targetDuration = $requestedDuration ?? (int) ($targetLine->duration ?: 30);
            $targetStaffId = $request->exists('staff_id')
                ? $staffId
                : ($targetLine->staff_id ? (int) $targetLine->staff_id : $staffId);

            $singleSliceConflict = $this->conflictValidator()->validateBookingOperation(
                $branch,
                $newStart->copy()->startOfDay(),
                [[
                    'staff_id' => $targetStaffId,
                    'start' => $newStart->copy(),
                    'duration' => $targetDuration,
                    'is_parallel' => false,
                    'service_names' => $targetLine->name ? [$targetLine->name] : [],
                ]],
                'reschedule',
                false,
                null,
                $targetLine->id
            );

            if ($singleSliceConflict !== null) {
                return $this->conflictValidator()->toResponse($request, $singleSliceConflict, true);
            }

            DB::transaction(function () use ($appointment, $targetLine, $newStart, $targetDuration, $targetStaffId, $branch, $oldStart) {
                $targetLine->update([
                    'start_time' => $newStart->copy(),
                    'duration' => $targetDuration,
                    'staff_id' => $targetStaffId,
                ]);

                $orderedActive = $appointment->appointmentServices()
                    ->where('is_pending', false)
                    ->get()
                    ->sortBy(fn (AppointmentService $s) => sprintf(
                        '%015d_%05d_%010d',
                        $s->start_time ? $s->start_time->getTimestamp() : 0,
                        (int) ($s->sort_order ?? 0),
                        (int) $s->id
                    ))
                    ->values();

                $prevStartTs = null;
                $prevStaffId = null;
                foreach ($orderedActive as $idx => $sliceLine) {
                    $curStartTs = $sliceLine->start_time ? $sliceLine->start_time->getTimestamp() : null;
                    $curStaffId = $sliceLine->staff_id ? (int) $sliceLine->staff_id : null;
                    $isPar = $idx > 0
                        && $curStartTs !== null
                        && $prevStartTs !== null
                        && $curStartTs === $prevStartTs
                        && ($curStaffId === null || $curStaffId !== $prevStaffId);

                    if ((int) $sliceLine->sort_order !== $idx || (bool) $sliceLine->is_parallel !== $isPar) {
                        $sliceLine->update([
                            'sort_order' => $idx,
                            'is_parallel' => $isPar,
                        ]);
                    }
                    $prevStartTs = $curStartTs;
                    $prevStaffId = $curStaffId;
                }

                if ($appointment->branch !== $branch) {
                    $appointment->branch = $branch;
                }
                $appointment->syncFromServices();
                $this->freebieService()->syncAnchorExpiryOnReschedule($appointment);
                $this->markAppointmentLeadsAsBooked($appointment);
                $this->syncAdLeadAttributionForAppointment($appointment, $oldStart);
            });

            return response()->json([
                'success' => true,
                'message' => 'Service slice rescheduled',
            ]);
        }

        $isMultiService = $serviceLines->count() > 1;
        $rawApptServiceName = trim((string) $appointment->service_name);
        $isPlaceholderService = in_array(strtolower($rawApptServiceName), ['', 'decide in salon', 'choose in salon', 'services tbd in salon'], true);

        $minuteDelta = $oldStart->diffInMinutes($newStart, false);
        $plannedLineUpdates = [];
        $validationSlices = [];

        if ($serviceLines->isNotEmpty()) {
            $lineCount = $serviceLines->count();
            $otherLinesDurationSum = ($requestedDuration !== null && $lineCount > 1)
                ? (int) $serviceLines->slice(0, $lineCount - 1)->sum(fn ($l) => $l->duration ?: 30)
                : 0;

            if ($wasUnscheduled) {
                $unschedTimeline = BookingConflictValidator::computeSliceTimeline(
                    $newStart->copy(),
                    $serviceLines->values()->map(function (AppointmentService $line, int $idx) use ($isMultiService, $previousStaffId, $staffId, $requestedDuration, $lineCount, $otherLinesDurationSum) {
                        $isLastLine = ($idx === $lineCount - 1);
                        $dur = $requestedDuration !== null
                            ? ($lineCount === 1 ? $requestedDuration : ($isLastLine ? max(5, $requestedDuration - $otherLinesDurationSum) : (int) ($line->duration ?: 30)))
                            : (int) ($line->duration ?: 30);
                        $preserveSecondaryStylist = $isMultiService
                            && $line->staff_id !== null
                            && ($previousStaffId === null || (int) $line->staff_id !== (int) $previousStaffId);
                        $effStaff = $preserveSecondaryStylist ? (int) $line->staff_id : $staffId;

                        return [
                            'line' => $line,
                            'duration' => $dur,
                            'staff_id' => $effStaff,
                            'sort_order' => (int) ($line->sort_order ?? $idx),
                            'is_parallel' => (bool) $line->is_parallel && ($idx > 0),
                        ];
                    })->all()
                );

                foreach ($unschedTimeline['slices'] as $cSlice) {
                    $line = $cSlice['line'];
                    $validationSlices[] = [
                        'staff_id' => $cSlice['staff_id'],
                        'start' => $cSlice['start'],
                        'duration' => $cSlice['duration'],
                        'is_parallel' => (bool) $cSlice['is_parallel'],
                        'service_names' => $line->name ? [$line->name] : [],
                    ];
                    $plannedLineUpdates[] = [
                        'line' => $line,
                        'start_time' => $cSlice['start'],
                        'duration' => $cSlice['duration'],
                        'staff_id' => $cSlice['staff_id'],
                    ];
                }
            } else {
                $cursor = $newStart->copy();
                foreach ($serviceLines->values() as $idx => $line) {
                    $isLastLine = ($idx === $lineCount - 1);
                    if ($requestedDuration !== null) {
                        $lineStart = $cursor->copy();
                        $lineDuration = $lineCount === 1
                            ? $requestedDuration
                            : ($isLastLine ? max(5, $requestedDuration - $otherLinesDurationSum) : (int) ($line->duration ?: 30));
                    } else {
                        $lineStart = $line->start_time
                            ? Carbon::parse($line->start_time)->addMinutes($minuteDelta)
                            : $cursor->copy();
                        $lineDuration = (int) ($line->duration ?: 30);
                    }

                    $preserveSecondaryStylist = $isMultiService
                        && $line->staff_id !== null
                        && ($previousStaffId === null || (int) $line->staff_id !== (int) $previousStaffId);

                    $effectiveLineStaffId = $preserveSecondaryStylist
                        ? (int) $line->staff_id
                        : $staffId;

                    $validationSlices[] = [
                        'staff_id' => $effectiveLineStaffId,
                        'start' => $lineStart->copy(),
                        'duration' => $lineDuration,
                        'is_parallel' => (bool) $line->is_parallel,
                        'service_names' => $line->name ? [$line->name] : [],
                    ];

                    $plannedLineUpdates[] = [
                        'line' => $line,
                        'start_time' => $lineStart,
                        'duration' => $lineDuration,
                        'staff_id' => $effectiveLineStaffId,
                    ];

                    $cursor = $lineStart->copy()->addMinutes($lineDuration);
                }
            }
        } else {
            $isComboHold = ($appointment->combo_id || $appointment->display_combo_name) && $isPlaceholderService;
            $isDecideHold = !$isComboHold && ((bool) $appointment->decide_in_salon || strtolower($rawApptServiceName) === 'decide in salon');

            $duration = $requestedDuration
                ?? ($isComboHold
                    ? (int) ($appointment->hold_duration ?: ($appointment->combo?->effective_duration ?? 60))
                    : ($isDecideHold
                        ? $appointment->effective_hold_duration
                        : $this->totalServiceDuration(explode(',', (string) $appointment->service_name))));

            $validationSlices[] = [
                'staff_id' => $staffId,
                'start' => $newStart->copy(),
                'duration' => (int) $duration,
                'service_names' => ($isPlaceholderService || $isDecideHold) ? [] : explode(',', (string) $appointment->service_name),
            ];
        }

        $conflict = $this->conflictValidator()->validateBookingOperation(
            $branch,
            $newStart->copy()->startOfDay(),
            $validationSlices,
            'reschedule',
            false,
            $appointment->id,
            null
        );

        if ($conflict !== null) {
            return $this->conflictValidator()->toResponse($request, $conflict, true);
        }

        DB::transaction(function () use ($appointment, $newStart, $staffId, $branch, $plannedLineUpdates, $oldStart, $requestedDuration) {
            $updatePayload = [
                'appointment_datetime' => $newStart,
                'booking_date' => $newStart->toDateString(),
                'start_time' => $newStart->format('H:i:s'),
                'staff_id' => $staffId,
                'branch' => $branch,
            ];

            if ($requestedDuration !== null && empty($plannedLineUpdates)) {
                $updatePayload['hold_duration'] = $requestedDuration;
            }

            $wasCancelledOrNoShow = in_array(strtolower((string) ($appointment->status ?? '')), ['cancelled', 'no_show'], true);
            if ($wasCancelledOrNoShow) {
                $updatePayload['status'] = 'pending';
            }

            $appointment->update($updatePayload);

            foreach ($plannedLineUpdates as $item) {
                /** @var AppointmentService $line */
                $line = $item['line'];
                $linePayload = [
                    'start_time' => $item['start_time'],
                    'staff_id' => $item['staff_id'],
                ];
                if ($requestedDuration !== null) {
                    $linePayload['duration'] = $item['duration'];
                }
                $line->update($linePayload);
            }

            if ($wasCancelledOrNoShow) {
                $this->restoreCancelledOriginatingPackages($appointment);
            }

            if ($requestedDuration !== null && !empty($plannedLineUpdates)) {
                $appointment->syncFromServices();
            }

            $this->freebieService()->syncAnchorExpiryOnReschedule($appointment);
            $this->markAppointmentLeadsAsBooked($appointment);
            $this->syncAdLeadAttributionForAppointment($appointment, $oldStart);
        });

        return response()->json([
            'success' => true,
            'message' => $wasUnscheduled ? 'Booking scheduled onto calendar' : 'Appointment rescheduled',
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
        $appointment->loadMissing('appointmentServices.clientPackageService.clientPackage');
        $allLines = $appointment->appointmentServices;
        $lines = $allLines->where('is_pending', false)->values();

        if ($lines->isEmpty()) {
            if ($allLines->isNotEmpty() || $appointment->combo_id || $appointment->display_combo_name) {
                return [];
            }

            // Fallback for any row that somehow has no line items yet.
            $names = array_values(array_filter(
                array_map('trim', explode(',', (string) $appointment->service_name)),
                fn ($n) => $n !== '' && !in_array(strtolower($n), ['decide in salon', 'choose in salon', 'services tbd in salon'], true)
            ));
            if (empty($names)) {
                return [];
            }

            $catalog = Service::whereIn('name', $names)->get()->keyBy('name');

            return collect($names)->map(fn ($name) => [
                'id' => null,
                'service_id' => $catalog->get($name)->id ?? null,
                'name' => $name,
                'combo_name' => null,
                'price' => $catalog->get($name)->price ?? 0,
                'original_price' => $catalog->get($name)->price ?? 0,
                'discount_amount' => 0,
                'discount_reason' => null,
                'duration' => $catalog->get($name)->duration ?? 0,
                'staff_id' => $appointment->staff_id,
                'is_package_redemption' => false,
                'client_package_service_id' => null,
            ])->values()->all();
        }

        return $lines->map(function ($s) {
            $isPkg = $s->isPackageRedemptionLine();
            $resolvedComboName = $s->combo_name
                ?: $s->clientPackageService?->clientPackage?->combo_name
                ?: ((is_string($s->discount_reason) && preg_match('/^Redeemed from (.+) package$/i', trim($s->discount_reason), $m)) ? trim($m[1]) : null);

            return [
                'id' => $s->id,
                'service_id' => $s->service_id,
                'name' => $s->name,
                'combo_name' => $resolvedComboName,
                'price' => $s->final_price,
                'original_price' => (float) ($s->original_price ?? $s->price),
                'discount_amount' => $isPkg ? 0.0 : (float) $s->discount_amount,
                'discount_reason' => $s->discount_reason,
                'duration' => $s->duration,
                'staff_id' => $s->staff_id,
                'is_package_redemption' => $isPkg,
                'client_package_service_id' => $s->clientPackageService?->id,
            ];
        })->values()->all();
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
    private function createServiceLineItems(Appointment $appointment, array $serviceNames, ?int $staffId, ?Carbon $startTime, array $customPrices = [], array $discountReasons = [], array $scheduleByIdx = []): void
    {
        $catalog = Service::whereIn('name', $serviceNames)->get()->keyBy('name');
        $cursor = $startTime ? $startTime->copy() : null;

        foreach ($serviceNames as $index => $name) {
            $service = $catalog->get($name);
            $duration = $service->duration ?? 30;
            $catalogPrice = (float) ($service->price ?? 0);

            $customPrice = $customPrices[$index] ?? null;
            $price = ($customPrice !== null && $customPrice !== '') ? (float) $customPrice : $catalogPrice;

            $sliceMeta = $scheduleByIdx[$index] ?? null;
            $lineStart = $sliceMeta
                ? $sliceMeta['start']
                : ($cursor ? $cursor->copy() : null);
            $lineStaffId = $sliceMeta
                ? ($sliceMeta['staff_id'] ?? $staffId)
                : $staffId;
            $lineSortOrder = $sliceMeta ? (int) ($sliceMeta['sort_order'] ?? $index) : $index;
            $lineParallel = $sliceMeta ? (bool) ($sliceMeta['is_parallel'] ?? false) : false;

            $line = AppointmentService::create([
                'appointment_id' => $appointment->id,
                'service_id' => $service->id ?? null,
                'staff_id' => $lineStaffId,
                'sort_order' => $lineSortOrder,
                'is_parallel' => $lineParallel,
                'name' => $name,
                'price' => $price,
                'original_price' => $catalogPrice,
                'duration' => $duration,
                'start_time' => $lineStart,
                'discount_reason' => $discountReasons[$index] ?? null,
            ]);

            $this->logPriceOverrideIfDiscounted($line);

            if (!$sliceMeta && $cursor) {
                $cursor->addMinutes($duration);
            }
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
        if ($line->isPackageRedemptionLine() || $line->discount_amount <= 0) {
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
        if ($appointment->sale()->exists()) {
            return $this->rejectPaidBookingMutation($request, true);
        }

        $request->validate([
            'service_id' => 'required|exists:services,id',
            'staff_id' => 'nullable|exists:staff,id',
        ]);

        $service = Service::find($request->service_id);
        $assignedStaffId = $request->staff_id ? (int) $request->staff_id : ($appointment->staff_id ? (int) $appointment->staff_id : null);
        $duration = (int) ($service->duration ?: 30);
        $lastLine = $appointment->appointmentServices()->whereNotNull('start_time')->orderByDesc('start_time')->first();
        $startTime = $appointment->start_time !== null
            ? ($lastLine ? Carbon::parse($lastLine->end_time) : Carbon::parse($appointment->appointment_datetime))
            : null;

        $isUnscheduledAppt = ($startTime === null);
        $bookingDateForAdd = Carbon::parse($appointment->booking_date ?: $appointment->appointment_datetime)->startOfDay();
        $addConflict = $this->conflictValidator()->validateBookingOperation(
            (string) $appointment->branch,
            $bookingDateForAdd,
            [[
                'staff_id' => $assignedStaffId,
                'start' => $startTime,
                'duration' => $duration,
                'service_names' => [$service->name],
            ]],
            'book',
            $isUnscheduledAppt,
            $lastLine ? null : $appointment->id,
            null
        );

        if ($addConflict !== null) {
            return $this->conflictValidator()->toResponse($request, $addConflict, true);
        }

        $line = AppointmentService::create([
            'appointment_id' => $appointment->id,
            'service_id' => $service->id,
            'staff_id' => $assignedStaffId,
            'name' => $service->name,
            'price' => $service->price,
            'original_price' => $service->price,
            'duration' => $duration,
            'start_time' => $startTime,
        ]);

        $appointment->syncFromServices();
        $this->syncAdLeadAttributionForAppointment($appointment);

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
        if ($appointment->sale()->exists()) {
            return $this->rejectPaidBookingMutation($request, true);
        }

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

        $newStart = Carbon::parse($request->start_time);
        $newDuration = (int) $request->duration;
        $assignedStaffId = $request->staff_id ? (int) $request->staff_id : null;

        $data = [
            'price' => $request->price,
            'duration' => $newDuration,
            'start_time' => $newStart,
            'staff_id' => $assignedStaffId,
            'discount_type' => $request->discount_type ?: null,
            'discount_value' => $request->discount_value ?: 0,
            'discount_reason' => $request->discount_reason ?: null,
        ];

        $redemption = ClientPackageService::with(['clientPackage.combo.services', 'clientPackage.redeemedServices'])
            ->where('appointment_service_id', $appointmentService->id)
            ->first();

        $serviceName = $appointmentService->name;
        if ($request->filled('service_id')) {
            $service = Service::find($request->service_id);

            if ($redemption && (int) $service->id !== (int) $appointmentService->service_id) {
                $pkg = $redemption->clientPackage;
                $pool = $pkg?->combo?->services ?? collect();

                if ($pool->isEmpty() || !$pool->contains('id', $service->id)) {
                    $comboLabel = $pkg?->combo_name ?: 'this package';
                    return response()->json([
                        'success' => false,
                        'message' => "\"{$service->name}\" is not part of {$comboLabel}.",
                    ], 422);
                }

                $alreadyRedeemed = $pkg->redeemedServices
                    ->where('id', '!=', $redemption->id)
                    ->contains('service_id', $service->id);
                if ($alreadyRedeemed) {
                    return response()->json([
                        'success' => false,
                        'message' => "\"{$service->name}\" has already been redeemed from {$pkg->combo_name}.",
                    ], 422);
                }
            }

            $data['service_id'] = $service->id;
            $data['name'] = $service->name;
            $serviceName = $service->name;

            // Swapping to a different catalog service resets the baseline
            // it's discounted against; otherwise the line keeps whatever
            // original_price it was first booked/added at.
            if ((int) $request->service_id !== (int) $appointmentService->service_id) {
                $data['original_price'] = $service->price;
                if ($redemption) {
                    $data['price'] = $service->price;
                }
            }
        }

        if ($redemption) {
            $comboLabel = $redemption->clientPackage?->combo_name ?: 'Package';
            $data['discount_type'] = 'percent';
            $data['discount_value'] = 100;
            $data['discount_reason'] = "Redeemed from {$comboLabel} package";
        }

        $updateSvcConflict = $this->conflictValidator()->validateBookingOperation(
            (string) $appointment->branch,
            $newStart->copy()->startOfDay(),
            [[
                'staff_id' => $assignedStaffId,
                'start' => $newStart,
                'duration' => $newDuration,
                'service_names' => $serviceName ? [$serviceName] : [],
            ]],
            'reschedule',
            false,
            null,
            $appointmentService->id
        );

        if ($updateSvcConflict !== null) {
            return $this->conflictValidator()->toResponse($request, $updateSvcConflict, true);
        }

        $previousDate = $appointment->appointment_date->copy();
        $appointmentService->update($data);

        if ($redemption && ((int) $redemption->service_id !== (int) $appointmentService->service_id || $redemption->service_name !== $appointmentService->name)) {
            $redemption->update([
                'service_id' => $appointmentService->service_id,
                'service_name' => $appointmentService->name,
            ]);
        }

        $appointment->syncFromServices();
        $this->syncAdLeadAttributionForAppointment($appointment, $previousDate);

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
    public function destroyService(Request $request, Appointment $appointment, AppointmentService $appointmentService)
    {
        if ($appointment->sale()->exists()) {
            return $this->rejectPaidBookingMutation($request, true);
        }

        if ($appointmentService->appointment_id !== $appointment->id) {
            abort(404);
        }

        $hasAttachedCombo = ClientPackage::where('appointment_id', $appointment->id)->exists() || !empty($appointment->combo_name);
        if (!$hasAttachedCombo && $appointment->appointmentServices()->where('is_pending', false)->count() <= 1) {
            return response()->json([
                'success' => false,
                'message' => 'An appointment must have at least one service. Cancel the appointment instead.',
            ], 422);
        }

        $redemptions = ClientPackageService::where('appointment_service_id', $appointmentService->id)->get();
        $affectedPackageIds = $redemptions->pluck('client_package_id')->unique()->filter();
        if ($redemptions->isNotEmpty()) {
            ClientPackageService::whereIn('id', $redemptions->pluck('id'))->delete();
        }

        $previousDate = $appointment->appointment_date->copy();
        $appointmentService->delete();

        foreach (ClientPackage::whereIn('id', $affectedPackageIds)->get() as $pkg) {
            $pkg->refreshStatus();
        }

        $appointment->syncFromServices();
        $this->syncAdLeadAttributionForAppointment($appointment, $previousDate);

        return response()->json(['success' => true, 'message' => 'Service removed.']);
    }

    private function formatServiceLine(AppointmentService $s): array
    {
        $isPkg = $s->isPackageRedemptionLine();
        $resolvedComboName = $s->combo_name
            ?: ((is_string($s->discount_reason) && preg_match('/^(?:Redeemed|Pending) from (.+) package$/i', trim($s->discount_reason), $m)) ? trim($m[1]) : null);

        $endObj = $s->end_time;

        return [
            'id' => $s->id,
            'service_id' => $s->service_id,
            'combo_id' => $s->combo_id,
            'combo_name' => $resolvedComboName,
            'client_package_id' => $s->client_package_id,
            'status' => $s->is_pending ? 'pending' : ($s->status ?: 'scheduled'),
            'is_pending' => (bool) $s->is_pending,
            'sort_order' => (int) ($s->sort_order ?? 0),
            'is_parallel' => (bool) $s->is_parallel,
            'name' => $s->name,
            'price' => (float) $s->price,
            'original_price' => (float) ($s->original_price ?? $s->price),
            'final_price' => $s->final_price,
            'duration' => $s->duration,
            'discount_type' => $s->discount_type,
            'discount_value' => (float) $s->discount_value,
            'discount_amount' => $isPkg ? 0.0 : (float) $s->discount_amount,
            'discount_reason' => $s->discount_reason,
            'is_package_redemption' => $isPkg,
            'is_freebie_redemption' => (bool) $s->is_freebie_redemption,
            'freebie_package_id' => $s->freebie_package_id,
            'freebie_credit_id' => $s->freebie_credit_id,
            'start_time' => $s->start_time ? $s->start_time->format('H:i') : '',
            'end_time' => $endObj ? $endObj->format('H:i') : '',
            'time_range_label' => (!$s->is_pending && $s->start_time && $endObj)
                ? $s->start_time->format('H:i') . '–' . $endObj->format('H:i')
                : ($s->is_pending ? 'Pending' : 'Pending time'),
            'start_time_label' => $s->is_pending
                ? 'Pending (Combo Balance)'
                : ($s->start_time ? $s->start_time->format('g:i A') : 'Pending time'),
            'staff_id' => $s->staff_id,
            'staff_name' => optional($s->staff)->name,
        ];
    }

    /**
     * In-salon Combo service selection & Pending toggle endpoint.
     * Allows staff/managers to choose, swap, or defer sub-services inside a
     * booked Combo ("Scheduled / Today" vs. "Pending") from Enhanced Calendar
     * or Bookings without inflating or corrupting the locked Combo QAR price.
     */
    public function updateComboServices(Request $request, Appointment $appointment)
    {
        $request->validate([
            'client_package_id' => 'nullable|integer|exists:client_packages,id',
            'today_service_ids' => 'nullable|array',
            'today_service_ids.*' => 'integer|exists:services,id',
            'pending_service_ids' => 'nullable|array',
            'pending_service_ids.*' => 'integer|exists:services,id',
            'service_staff' => 'nullable|array',
            'service_sort_order' => 'nullable|array',
            'service_parallel' => 'nullable|array',
        ]);

        if ($appointment->sale()->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'This appointment has already been checked out.',
            ], 422);
        }

        $clientPackage = null;
        if ($request->filled('client_package_id')) {
            $clientPackage = ClientPackage::with(['combo.services', 'services'])->find((int) $request->input('client_package_id'));
        } else {
            $clientPackage = ClientPackage::with(['combo.services', 'services'])
                ->where('appointment_id', $appointment->id)
                ->orderBy('id')
                ->first();
            if (!$clientPackage) {
                $firstLinkedPkgId = $appointment->appointmentServices()
                    ->whereNotNull('client_package_id')
                    ->value('client_package_id');
                if ($firstLinkedPkgId) {
                    $clientPackage = ClientPackage::with(['combo.services', 'services'])->find((int) $firstLinkedPkgId);
                }
            }
        }

        if (!$clientPackage) {
            return response()->json([
                'success' => false,
                'message' => 'No combo package is attached to this booking.',
            ], 422);
        }

        $standaloneOffsetCursor = null;
        if ($appointment->start_time !== null) {
            $standaloneOffsetCursor = Carbon::parse($appointment->appointment_datetime);
            $standaloneDurationSum = (int) $appointment->appointmentServices()
                ->where('is_pending', false)
                ->whereNull('client_package_id')
                ->where(fn ($q) => $q->whereNull('combo_id')->orWhere('combo_id', '!=', $clientPackage->combo_id))
                ->where(fn ($q) => $q->whereNull('combo_name')->orWhere('combo_name', '!=', $clientPackage->combo_name))
                ->get()
                ->sum(fn ($l) => $l->duration ?: 30);
            if ($standaloneDurationSum > 0) {
                $standaloneOffsetCursor->addMinutes($standaloneDurationSum);
            }
        }

        [$syncPlan, $syncConflict] = $this->validateComboPackageSelection(
            $appointment,
            $clientPackage,
            (array) $request->input('today_service_ids', []),
            (array) $request->input('pending_service_ids', []),
            (array) $request->input('service_staff', []),
            $appointment->staff_id ? (int) $appointment->staff_id : null,
            Customer::normalizeBranch($appointment->branch) ?? 'old_airport',
            $standaloneOffsetCursor,
            'book',
            $appointment->start_time === null,
            (array) $request->input('service_sort_order', []),
            (array) $request->input('service_parallel', [])
        );

        if ($syncConflict !== null) {
            return $this->conflictValidator()->toResponse($request, $syncConflict, true);
        }

        $previousDate = $appointment->appointment_date->copy();

        DB::transaction(function () use ($appointment, $syncPlan, $previousDate) {
            $standaloneLines = $appointment->appointmentServices()
                ->where('is_pending', false)
                ->where(function ($q) use ($syncPlan) {
                    $pkg = $syncPlan['client_package'];
                    $q->whereNull('client_package_id')
                        ->where(function ($sub) use ($pkg) {
                            $sub->whereNull('combo_id')->orWhere('combo_id', '!=', $pkg->combo_id);
                        })
                        ->where(function ($sub) use ($pkg) {
                            $sub->whereNull('combo_name')->orWhere('combo_name', '!=', $pkg->combo_name);
                        });
                })
                ->orderBy('sort_order')
                ->orderBy('start_time')
                ->orderBy('id')
                ->get();

            $cursor = $appointment->start_time !== null
                ? Carbon::parse($appointment->appointment_datetime)
                : null;

            if ($cursor !== null && $standaloneLines->isNotEmpty()) {
                $standaloneTimeline = BookingConflictValidator::computeSliceTimeline(
                    $cursor,
                    $standaloneLines->map(fn ($line, $idx) => [
                        'line' => $line,
                        'duration' => (int) ($line->duration ?: 30),
                        'staff_id' => $line->staff_id,
                        'sort_order' => (int) ($line->sort_order ?? $idx),
                        'is_parallel' => (bool) $line->is_parallel,
                    ])->all()
                );
                foreach ($standaloneTimeline['slices'] as $sSlice) {
                    $sSlice['line']->update([
                        'start_time' => $sSlice['start'],
                        'sort_order' => $sSlice['sort_order'],
                        'is_parallel' => $sSlice['is_parallel'],
                    ]);
                }
                $cursor = $standaloneTimeline['max_end'] ? $standaloneTimeline['max_end']->copy() : $cursor;
            }

            $this->persistComboPackageSelection($appointment, $syncPlan, $cursor, $standaloneLines->count());
            $appointment->syncFromServices();
            $this->syncAdLeadAttributionForAppointment($appointment, $previousDate);
        });

        $appointment->refresh()->load('customer', 'staff', 'agent', 'combo', 'clientPackages.combo.services', 'clientPackages.services', 'appointmentServices.staff', 'upsells.staff');

        return response()->json([
            'success' => true,
            'message' => 'Combo services updated.',
            'price' => (float) $appointment->price,
            'visit_span_minutes' => $appointment->visit_span_minutes,
            'service_name' => $appointment->service_name,
            'combo_name' => $appointment->display_combo_name,
            'is_combo_tbd' => $appointment->is_combo_tbd,
            'combo_packages' => $this->buildAppointmentComboPackagesPayload($appointment),
            'services' => $appointment->appointmentServices->where('is_pending', false)->map(fn ($s) => $this->formatServiceLine($s))->values()->all(),
            'pending_services' => $appointment->appointmentServices->where('is_pending', true)->map(fn ($s) => $this->formatServiceLine($s))->values()->all(),
        ]);
    }

    /**
     * Synchronize per-service staff_id, sort_order, and is_parallel across active slices
     * of an existing appointment directly from the Enhanced Calendar drawer.
     */
    public function syncSlices(Request $request, Appointment $appointment)
    {
        if ($appointment->sale()->exists()) {
            return $this->rejectPaidBookingMutation($request, true);
        }

        $request->validate([
            'slices' => 'required|array|min:1',
            'slices.*.id' => 'required|integer|exists:appointment_services,id',
            'slices.*.staff_id' => 'nullable|exists:staff,id',
            'slices.*.sort_order' => 'nullable|integer|min:0',
            'slices.*.is_parallel' => 'nullable|boolean',
        ]);

        $activeLinesById = $appointment->appointmentServices()
            ->where('is_pending', false)
            ->get()
            ->keyBy('id');

        if ($activeLinesById->isEmpty()) {
            return response()->json([
                'success' => false,
                'reason' => 'validation',
                'message' => 'No active service slices exist on this booking.',
            ], 422);
        }

        $submittedById = collect($request->input('slices', []))->keyBy(fn ($r) => (int) ($r['id'] ?? 0));
        $rawTimelineItems = [];

        foreach ($activeLinesById->values() as $idx => $line) {
            $sub = $submittedById->get($line->id);
            $resolvedStaffId = $sub !== null && array_key_exists('staff_id', $sub)
                ? ($sub['staff_id'] ? (int) $sub['staff_id'] : null)
                : ($line->staff_id ? (int) $line->staff_id : null);
            $resolvedSort = $sub !== null && isset($sub['sort_order']) && $sub['sort_order'] !== ''
                ? (int) $sub['sort_order']
                : ((int) ($line->sort_order ?? $idx));
            $resolvedParallel = $sub !== null && array_key_exists('is_parallel', $sub)
                ? filter_var($sub['is_parallel'], FILTER_VALIDATE_BOOLEAN)
                : (bool) $line->is_parallel;

            $rawTimelineItems[] = [
                'line' => $line,
                'name' => $line->name,
                'duration' => (int) ($line->duration ?: 30),
                'staff_id' => $resolvedStaffId,
                'sort_order' => $resolvedSort,
                'is_parallel' => $resolvedParallel,
            ];
        }

        $isUnscheduled = $appointment->start_time === null;
        $bookingStart = $isUnscheduled ? null : Carbon::parse($appointment->appointment_datetime);
        $timeline = BookingConflictValidator::computeSliceTimeline($bookingStart, $rawTimelineItems);

        $validationSlices = array_map(static fn (array $s) => [
            'staff_id' => $s['staff_id'],
            'start' => $s['start'],
            'duration' => $s['duration'],
            'is_parallel' => (bool) $s['is_parallel'],
            'service_names' => $s['name'] ? [$s['name']] : [],
        ], $timeline['slices']);

        $resolvedBranch = Customer::normalizeBranch($appointment->branch) ?? 'old_airport';
        $bookingDate = Carbon::parse($appointment->booking_date ?: $appointment->appointment_datetime)->startOfDay();

        $conflict = $this->conflictValidator()->validateBookingOperation(
            $resolvedBranch,
            $bookingDate,
            $validationSlices,
            'book',
            $isUnscheduled,
            $appointment->id,
            null
        );

        if ($conflict !== null) {
            return $this->conflictValidator()->toResponse($request, $conflict, true);
        }

        $previousDate = $appointment->appointment_date->copy();

        DB::transaction(function () use ($appointment, $timeline, $previousDate) {
            foreach ($timeline['slices'] as $cSlice) {
                /** @var AppointmentService $line */
                $line = $cSlice['line'];
                $line->update([
                    'staff_id' => $cSlice['staff_id'],
                    'sort_order' => $cSlice['sort_order'],
                    'is_parallel' => $cSlice['is_parallel'],
                    'start_time' => $cSlice['start'],
                ]);
            }
            $appointment->syncFromServices();
            $this->syncAdLeadAttributionForAppointment($appointment, $previousDate);
        });

        $appointment->refresh()->load('customer', 'staff', 'agent', 'combo', 'clientPackages.combo.services', 'clientPackages.services', 'appointmentServices.staff', 'upsells.staff');

        return response()->json([
            'success' => true,
            'message' => 'Service schedule updated.',
            'price' => (float) $appointment->price,
            'visit_span_minutes' => $appointment->visit_span_minutes,
            'combo_packages' => $this->buildAppointmentComboPackagesPayload($appointment),
            'services' => $appointment->appointmentServices->where('is_pending', false)->map(fn ($s) => $this->formatServiceLine($s))->values()->all(),
            'pending_services' => $appointment->appointmentServices->where('is_pending', true)->map(fn ($s) => $this->formatServiceLine($s))->values()->all(),
        ]);
    }

    /**
     * Validate an in-salon Today vs Pending selection for a ClientPackage on an Appointment.
     */
    private function validateComboPackageSelection(
        Appointment $appointment,
        ClientPackage $clientPackage,
        array $rawTodayIds,
        array $rawPendingIds,
        array $rawServiceStaff = [],
        ?int $fallbackStaffId = null,
        ?string $branch = null,
        ?Carbon $startCursor = null,
        string $mode = 'book',
        bool $isUnscheduled = false,
        array $rawServiceSort = [],
        array $rawServiceParallel = []
    ): array {
        $vError = static fn (string $msg, string $field = 'combo_packages'): array => [
            null,
            ['reason' => 'validation', 'field' => $field, 'message' => $msg],
        ];

        $isOriginating = ((int) $clientPackage->appointment_id === (int) $appointment->id);
        if (!$isOriginating && $appointment->customer_id && (int) $clientPackage->customer_id !== (int) $appointment->customer_id) {
            return $vError('Selected combo package does not belong to this client.');
        }

        if ($clientPackage->is_expired || $clientPackage->status === 'expired') {
            return $vError("\"{$clientPackage->combo_name}\" has expired.");
        }

        $combo = $clientPackage->combo ?: Combo::with('services')->find($clientPackage->combo_id);
        if (!$combo) {
            return $vError('Combo catalog definition was not found.');
        }
        $combo->loadMissing('services');
        $pool = $combo->services->keyBy('id');
        $qtyIncluded = $clientPackage->effective_quantity_included;

        $todayIds = array_values(array_unique(array_filter(array_map('intval', $rawTodayIds))));
        if (!empty($rawServiceSort)) {
            $origPos = array_flip($todayIds);
            usort($todayIds, static function (int $a, int $b) use ($rawServiceSort, $origPos): int {
                $sa = $rawServiceSort[$a] ?? ($rawServiceSort[(string) $a] ?? null);
                $sb = $rawServiceSort[$b] ?? ($rawServiceSort[(string) $b] ?? null);
                $ka = ($sa !== null && $sa !== '') ? (int) $sa : ($origPos[$a] ?? 0);
                $kb = ($sb !== null && $sb !== '') ? (int) $sb : ($origPos[$b] ?? 0);
                if ($ka !== $kb) {
                    return $ka <=> $kb;
                }

                return ($origPos[$a] ?? 0) <=> ($origPos[$b] ?? 0);
            });
        }

        $pendingIds = array_values(array_diff(
            array_values(array_unique(array_filter(array_map('intval', $rawPendingIds)))),
            $todayIds
        ));

        if (array_diff($todayIds, $pool->keys()->all()) || array_diff($pendingIds, $pool->keys()->all())) {
            return $vError("Selected services are not part of \"{$clientPackage->combo_name}\".");
        }

        $apptLineIds = $appointment->appointmentServices()->pluck('id')->all();
        $redeemedElsewhere = $clientPackage->services()
            ->where('status', 'redeemed')
            ->when(!empty($apptLineIds), fn ($q) => $q->whereNotIn('appointment_service_id', $apptLineIds))
            ->get();
        $redeemedElsewhereIds = $redeemedElsewhere->pluck('service_id')->map(fn ($id) => (int) $id)->all();

        $duplicateRedeemed = array_intersect($todayIds, $redeemedElsewhereIds);
        if (!empty($duplicateRedeemed)) {
            $dupName = $pool->get(reset($duplicateRedeemed))?->name ?? 'Service';
            return $vError("\"{$dupName}\" was already redeemed on another visit.");
        }

        $maxAvailableHere = max(0, $qtyIncluded - count($redeemedElsewhereIds));
        if (count($todayIds) + count($pendingIds) > $maxAvailableHere) {
            return $vError("\"{$clientPackage->combo_name}\" only includes {$maxAvailableHere} available service slot(s) across Today and Pending.");
        }

        $resolvedBranch = Customer::normalizeBranch($branch ?: $appointment->branch) ?? 'old_airport';
        $defaultStaffId = $fallbackStaffId;
        $serviceStaff = [];
        $rawTimelineItems = [];

        $cursor = (!$isUnscheduled && $appointment->start_time !== null)
            ? ($startCursor ? $startCursor->copy() : Carbon::parse($appointment->appointment_datetime))
            : ($startCursor ? $startCursor->copy() : null);

        foreach ($todayIds as $seqIdx => $sid) {
            $svc = $pool->get($sid);
            $rawAssigned = $rawServiceStaff[$sid] ?? ($rawServiceStaff[(string) $sid] ?? null);
            $assignedStaffId = ($rawAssigned !== null && $rawAssigned !== '')
                ? (int) $rawAssigned
                : $defaultStaffId;

            if ($assignedStaffId && !Staff::whereKey($assignedStaffId)->exists()) {
                return $vError("Selected staff member for \"{$svc->name}\" no longer exists.", 'staff_id');
            }

            $rawSortVal = $rawServiceSort[$sid] ?? ($rawServiceSort[(string) $sid] ?? null);
            $rawParVal = $rawServiceParallel[$sid] ?? ($rawServiceParallel[(string) $sid] ?? false);

            $rawTimelineItems[] = [
                'service_id' => $sid,
                'name' => $svc->name,
                'duration' => (int) ($svc->duration ?: 30),
                'staff_id' => $assignedStaffId,
                'sort_order' => ($rawSortVal !== null && $rawSortVal !== '') ? (int) $rawSortVal : $seqIdx,
                'is_parallel' => filter_var($rawParVal, FILTER_VALIDATE_BOOLEAN),
            ];

            $serviceStaff[$sid] = $assignedStaffId;
        }

        $timeline = BookingConflictValidator::computeSliceTimeline($cursor, $rawTimelineItems);
        $computedSlices = $timeline['slices'];

        $validationSlices = array_map(static fn (array $s) => [
            'staff_id' => $s['staff_id'],
            'start' => $s['start'],
            'duration' => $s['duration'],
            'is_parallel' => (bool) $s['is_parallel'],
            'service_names' => [$s['name']],
        ], $computedSlices);

        if (!empty($validationSlices)) {
            $bookingDate = Carbon::parse($appointment->booking_date ?: $appointment->appointment_datetime)->startOfDay();
            $conflict = $this->conflictValidator()->validateBookingOperation(
                $resolvedBranch,
                $bookingDate,
                $validationSlices,
                $mode,
                $isUnscheduled,
                $appointment->id,
                null
            );

            if ($conflict !== null) {
                return [null, $conflict];
            }
        }

        return [[
            'client_package' => $clientPackage,
            'combo' => $combo,
            'pool' => $pool,
            'today_ids' => array_map(static fn (array $s) => (int) $s['service_id'], $computedSlices),
            'pending_ids' => $pendingIds,
            'service_staff' => $serviceStaff,
            'computed_slices' => $computedSlices,
            'max_end' => $timeline['max_end'],
            'redeemed_elsewhere_ids' => $redeemedElsewhereIds,
        ], null];
    }

    /**
     * Persist validated Today + Pending combo selections on an Appointment & ClientPackage.
     */
    private function persistComboPackageSelection(Appointment $appointment, array $syncPlan, ?Carbon &$cursor, int $sortOffset = 0): void
    {
        /** @var ClientPackage $clientPackage */
        $clientPackage = $syncPlan['client_package'];
        $pool = $syncPlan['pool'];
        $todayIds = $syncPlan['today_ids'];
        $pendingIds = $syncPlan['pending_ids'];
        $serviceStaff = $syncPlan['service_staff'];
        $computedBySvcId = collect($syncPlan['computed_slices'] ?? [])->keyBy('service_id');

        $existingPkgLineIds = $appointment->appointmentServices()
            ->where(function ($q) use ($clientPackage) {
                $q->where('client_package_id', $clientPackage->id)
                    ->orWhere(function ($sub) use ($clientPackage) {
                        $sub->whereNull('client_package_id')
                            ->where('combo_id', $clientPackage->combo_id);
                    })
                    ->orWhere(function ($sub) use ($clientPackage) {
                        $sub->whereNull('client_package_id')
                            ->where('discount_reason', "Redeemed from {$clientPackage->combo_name} package");
                    });
            })
            ->pluck('id')
            ->all();

        if (!empty($existingPkgLineIds)) {
            ClientPackageService::where('client_package_id', $clientPackage->id)
                ->whereIn('appointment_service_id', $existingPkgLineIds)
                ->delete();
            AppointmentService::whereIn('id', $existingPkgLineIds)->delete();
        }

        if ((int) $clientPackage->appointment_id === (int) $appointment->id) {
            ClientPackageService::where('client_package_id', $clientPackage->id)
                ->where('status', 'pending')
                ->whereNull('appointment_service_id')
                ->delete();
        }

        foreach ($todayIds as $seqIdx => $sid) {
            $svc = $pool->get($sid);
            $cSlice = $computedBySvcId->get($sid);
            $assignedStaffId = $cSlice['staff_id'] ?? ($serviceStaff[$sid] ?? $appointment->staff_id);
            $sliceCursor = $cSlice ? $cSlice['start'] : $cursor;

            $activeLine = $this->addRedeemedServiceLine(
                $appointment,
                $svc,
                $clientPackage->combo_name,
                $sliceCursor,
                $assignedStaffId ? (int) $assignedStaffId : null,
                $clientPackage->combo_id,
                $clientPackage->id,
                $sortOffset + ($cSlice ? (int) $cSlice['sort_order'] : $seqIdx),
                $cSlice ? (bool) $cSlice['is_parallel'] : false
            );

            if (!$cSlice) {
                $cursor = $sliceCursor;
            }

            $existingPendingOnPkg = ClientPackageService::where('client_package_id', $clientPackage->id)
                ->where('service_id', $sid)
                ->where('status', 'pending')
                ->first();

            if ($existingPendingOnPkg) {
                $existingPendingOnPkg->update([
                    'service_name' => $svc->name,
                    'status' => 'redeemed',
                    'redeemed_at' => now(),
                    'appointment_service_id' => $activeLine->id,
                ]);
            } else {
                ClientPackageService::create([
                    'client_package_id' => $clientPackage->id,
                    'service_id' => $sid,
                    'service_name' => $svc->name,
                    'status' => 'redeemed',
                    'redeemed_at' => now(),
                    'appointment_service_id' => $activeLine->id,
                ]);
            }
        }

        if (!empty($syncPlan['max_end'])) {
            $cursor = $syncPlan['max_end']->copy();
        }

        foreach ($pendingIds as $pIdx => $sid) {
            $svc = $pool->get($sid);
            $pendingLine = AppointmentService::create([
                'appointment_id' => $appointment->id,
                'service_id' => $svc->id,
                'combo_id' => $clientPackage->combo_id,
                'combo_name' => $clientPackage->combo_name,
                'client_package_id' => $clientPackage->id,
                'staff_id' => null,
                'sort_order' => 100 + $pIdx,
                'is_parallel' => false,
                'name' => $svc->name,
                'price' => 0,
                'original_price' => 0,
                'duration' => $svc->duration,
                'start_time' => null,
                'status' => 'pending',
                'is_pending' => true,
                'discount_type' => 'percent',
                'discount_value' => 100,
                'discount_reason' => "Pending from {$clientPackage->combo_name} package",
            ]);

            $existingPendingOnPkg = ClientPackageService::where('client_package_id', $clientPackage->id)
                ->where('service_id', $sid)
                ->where('status', 'pending')
                ->first();

            if ($existingPendingOnPkg) {
                $existingPendingOnPkg->update([
                    'service_name' => $svc->name,
                    'appointment_service_id' => $pendingLine->id,
                ]);
            } else {
                ClientPackageService::create([
                    'client_package_id' => $clientPackage->id,
                    'service_id' => $sid,
                    'service_name' => $svc->name,
                    'status' => 'pending',
                    'redeemed_at' => null,
                    'appointment_service_id' => $pendingLine->id,
                ]);
            }
        }

        $clientPackage->refreshStatus();
    }

    /**
     * Build structured Combo Package cards for the Booking Edit Drawer & Modal.
     */
    private function buildAppointmentComboPackagesPayload(Appointment $appointment): array
    {
        $appointment->loadMissing('clientPackages.combo.services', 'clientPackages.services', 'appointmentServices.staff');

        $packagesById = $appointment->clientPackages->keyBy('id');

        $linkedPackageIds = $appointment->appointmentServices
            ->pluck('client_package_id')
            ->filter()
            ->unique()
            ->diff($packagesById->keys())
            ->values();

        if ($linkedPackageIds->isNotEmpty()) {
            $extraPackages = ClientPackage::with(['combo.services', 'services'])
                ->whereIn('id', $linkedPackageIds)
                ->get();
            foreach ($extraPackages as $ep) {
                $packagesById->put($ep->id, $ep);
            }
        }

        // Legacy fallback via ClientPackageService.appointment_service_id
        if ($packagesById->isEmpty() && $appointment->appointmentServices->isNotEmpty()) {
            $lineIds = $appointment->appointmentServices->pluck('id')->all();
            $legacyPkgIds = ClientPackageService::whereIn('appointment_service_id', $lineIds)
                ->pluck('client_package_id')
                ->unique()
                ->filter()
                ->values();
            if ($legacyPkgIds->isNotEmpty()) {
                foreach (ClientPackage::with(['combo.services', 'services'])->whereIn('id', $legacyPkgIds)->get() as $lp) {
                    $packagesById->put($lp->id, $lp);
                }
            }
        }

        $apptLineIds = $appointment->appointmentServices->pluck('id')->all();
        $result = [];

        foreach ($packagesById->values() as $pkg) {
            $pool = $pkg->combo?->services ?? collect();
            $qtyIncluded = $pkg->effective_quantity_included;
            $isOriginating = ((int) $pkg->appointment_id === (int) $appointment->id);

            $pkgApptLines = $appointment->appointmentServices->filter(function (AppointmentService $l) use ($pkg) {
                if ($l->client_package_id !== null) {
                    return (int) $l->client_package_id === (int) $pkg->id;
                }
                if ($l->combo_id !== null && (int) $l->combo_id === (int) $pkg->combo_id) {
                    return true;
                }

                return $l->combo_name === $pkg->combo_name
                    || $l->discount_reason === "Redeemed from {$pkg->combo_name} package";
            });

            $todayLinesOrdered = $pkgApptLines
                ->where('is_pending', false)
                ->sortBy(fn (AppointmentService $s) => sprintf(
                    '%05d_%015d_%010d',
                    (int) ($s->sort_order ?? 0),
                    $s->start_time ? $s->start_time->getTimestamp() : 0,
                    (int) $s->id
                ))
                ->values();
            $todayLinesBySvc = $todayLinesOrdered->keyBy('service_id');
            $pendingLinesBySvc = $pkgApptLines->where('is_pending', true)->keyBy('service_id');

            $redeemedElsewhereIds = $pkg->services
                ->where('status', 'redeemed')
                ->filter(fn ($cps) => !in_array($cps->appointment_service_id, $apptLineIds, true))
                ->pluck('service_id')
                ->map(fn ($id) => (int) $id)
                ->all();

            $pkgPendingCpsIds = $pkg->services
                ->where('status', 'pending')
                ->pluck('service_id')
                ->map(fn ($id) => (int) $id)
                ->all();

            $activeCount = $todayLinesBySvc->count();
            $redeemedTotal = $pkg->services->where('status', 'redeemed')->count();
            $remainingPendingCount = max(0, $qtyIncluded - $redeemedTotal);

            $counterLabel = ($activeCount === 0 && $redeemedTotal === 0 && $pendingLinesBySvc->isEmpty())
                ? "0 / {$qtyIncluded} Chosen — Decide in Salon"
                : "{$activeCount} Active · {$remainingPendingCount} Pending";

            $poolRows = $pool->map(function (Service $svc) use ($todayLinesBySvc, $pendingLinesBySvc, $redeemedElsewhereIds, $pkgPendingCpsIds) {
                $sid = (int) $svc->id;
                $todayLine = $todayLinesBySvc->get($sid);
                $pendingLine = $pendingLinesBySvc->get($sid);

                if ($todayLine) {
                    $state = 'today';
                } elseif (in_array($sid, $redeemedElsewhereIds, true)) {
                    $state = 'redeemed_elsewhere';
                } elseif ($pendingLine || in_array($sid, $pkgPendingCpsIds, true)) {
                    $state = 'pending';
                } else {
                    $state = 'unselected';
                }

                $lineEnd = $todayLine?->end_time;

                return [
                    'id' => $sid,
                    'service_id' => $sid,
                    'name' => $svc->name,
                    'duration' => (int) ($todayLine?->duration ?: $svc->duration),
                    'catalog_price' => (float) $svc->price,
                    'state' => $state,
                    'staff_id' => $todayLine?->staff_id,
                    'staff_name' => $todayLine?->staff?->name,
                    'sort_order' => $todayLine ? (int) ($todayLine->sort_order ?? 0) : null,
                    'is_parallel' => $todayLine ? (bool) $todayLine->is_parallel : false,
                    'start_time' => $todayLine?->start_time ? $todayLine->start_time->format('H:i') : '',
                    'end_time' => $lineEnd ? $lineEnd->format('H:i') : '',
                    'time_range_label' => ($todayLine?->start_time && $lineEnd)
                        ? $todayLine->start_time->format('H:i') . '–' . $lineEnd->format('H:i')
                        : '',
                    'appointment_service_id' => $todayLine?->id ?? $pendingLine?->id,
                ];
            })->values()->all();

            $pendingNamedCount = count(array_filter($poolRows, static fn (array $r) => $r['state'] === 'pending'));

            $result[] = [
                'client_package_id' => $pkg->id,
                'combo_id' => $pkg->combo_id,
                'combo_name' => $pkg->combo_name,
                'price_paid' => (float) $pkg->price_paid,
                'quantity_included' => $qtyIncluded,
                'active_count' => $activeCount,
                'pending_count' => $remainingPendingCount,
                'remaining_count' => $remainingPendingCount,
                'pending_named_count' => $pendingNamedCount,
                'redeemed_elsewhere_count' => count($redeemedElsewhereIds),
                'max_selectable_here' => max(0, $qtyIncluded - count($redeemedElsewhereIds)),
                'counter_label' => $counterLabel,
                'is_originating' => $isOriginating,
                'is_paid' => $pkg->sale_id !== null,
                'ordered_today_ids' => $todayLinesOrdered->pluck('service_id')->map(fn ($id) => (int) $id)->values()->all(),
                'pool_services' => $poolRows,
            ];
        }

        return $result;
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
        if ($appointment->sale()->exists()) {
            return $this->rejectPaidBookingMutation($request, true);
        }

        $item = $this->validatedUpsellCatalogItem($request);

        $upsell = AppointmentUpsell::create($item + [
            'appointment_id' => $appointment->id,
            'staff_id' => $request->staff_id,
            'amount' => $request->amount,
            'created_by' => auth()->id(),
        ]);

        $this->syncAdLeadAttributionForAppointment($appointment);

        return response()->json([
            'success' => true,
            'message' => 'Upsell added.',
            'upsell' => $this->formatUpsellLine($upsell->fresh('staff')),
        ]);
    }

    public function updateUpsell(Request $request, Appointment $appointment, AppointmentUpsell $appointmentUpsell)
    {
        if ($appointment->sale()->exists()) {
            return $this->rejectPaidBookingMutation($request, true);
        }

        if ($appointmentUpsell->appointment_id !== $appointment->id) {
            abort(404);
        }

        $item = $this->validatedUpsellCatalogItem($request);

        $appointmentUpsell->update($item + [
            'staff_id' => $request->staff_id,
            'amount' => $request->amount,
        ]);

        $this->syncAdLeadAttributionForAppointment($appointment);

        return response()->json([
            'success' => true,
            'message' => 'Upsell updated.',
            'upsell' => $this->formatUpsellLine($appointmentUpsell->fresh('staff')),
        ]);
    }

    public function destroyUpsell(Request $request, Appointment $appointment, AppointmentUpsell $appointmentUpsell)
    {
        if ($appointment->sale()->exists()) {
            return $this->rejectPaidBookingMutation($request, true);
        }

        if ($appointmentUpsell->appointment_id !== $appointment->id) {
            abort(404);
        }

        $appointmentUpsell->delete();
        $this->syncAdLeadAttributionForAppointment($appointment);

        return response()->json(['success' => true, 'message' => 'Upsell removed.']);
    }

    /**
     * Attach 0.00 QAR package service redemption(s) directly onto an existing or
     * Decide-in-Salon appointment from the Enhanced Calendar drawer or Bookings modal.
     */
    public function redeemPackageServices(Request $request, Appointment $appointment)
    {
        if ($appointment->sale()->exists()) {
            return $this->rejectPaidBookingMutation($request, true);
        }

        $request->validate([
            'redeem_service_ids' => 'required|array|min:1',
            'redeem_service_ids.*' => 'string',
            'redeem_service_staff' => 'nullable|array',
        ]);

        $customer = $appointment->customer
            ?: ($appointment->customer_id ? Customer::find($appointment->customer_id) : null)
            ?: $this->findOrCreateCustomer($appointment->phone, $appointment->customer_name);

        if (!$customer) {
            return response()->json([
                'success' => false,
                'reason' => 'validation',
                'message' => 'A client profile with a phone number is required to redeem a package.',
            ], 422);
        }

        if (!$appointment->customer_id) {
            $appointment->update(['customer_id' => $customer->id]);
        }

        ClientPackage::expireDue($customer->id);

        $rawRedeemStaff = is_array($request->input('redeem_service_staff'))
            ? $request->input('redeem_service_staff')
            : [];

        [$redemptionPlans, $redemptionError] = $this->buildPackageRedemptionPlans(
            (array) $request->input('redeem_service_ids', []),
            $customer,
            $appointment->id,
            $rawRedeemStaff
        );

        if ($redemptionError !== null) {
            return response()->json([
                'success' => false,
                'reason' => 'validation',
                'message' => $redemptionError,
            ], 422);
        }

        $isUnscheduled = $appointment->start_time === null;
        if (!$isUnscheduled) {
            $lastLine = $appointment->appointmentServices()
                ->where('is_pending', false)
                ->whereNotNull('start_time')
                ->orderByDesc('start_time')
                ->first();
            $checkCursor = $lastLine
                ? Carbon::parse($lastLine->end_time)
                : Carbon::parse($appointment->appointment_datetime);

            $validationSlices = [];
            foreach ($redemptionPlans as $plan) {
                $svc = $plan['service'];
                $dur = (int) ($svc->duration ?: 30);
                $validationSlices[] = [
                    'staff_id' => $plan['staff_id'] ?? ($appointment->staff_id ? (int) $appointment->staff_id : null),
                    'start' => $checkCursor->copy(),
                    'duration' => $dur,
                    'service_names' => [$svc->name],
                ];
                $checkCursor->addMinutes($dur);
            }

            $conflict = $this->conflictValidator()->validateBookingOperation(
                (string) $appointment->branch,
                Carbon::parse($appointment->booking_date ?: $appointment->appointment_datetime)->startOfDay(),
                $validationSlices,
                'book',
                false,
                $appointment->id,
                null
            );

            if ($conflict !== null) {
                return $this->conflictValidator()->toResponse($request, $conflict, true);
            }
        }

        DB::transaction(function () use ($appointment, $redemptionPlans) {
            $this->applyRedemptionPlansOntoAppointment($appointment, $redemptionPlans);
            $appointment->decide_in_salon = false;
            $appointment->hold_duration = null;
            $appointment->syncFromServices();
            $this->syncAdLeadAttributionForAppointment($appointment);
        });

        return response()->json([
            'success' => true,
            'message' => 'Package service(s) redeemed onto booking.',
            'appointment_id' => $appointment->id,
        ]);
    }

    /**
     * Convert matching unpaid standalone lines on $appointment into 0.00 QAR
     * package redemptions (preventing Visit 2 double-charging) or append new
     * 0.00 QAR redeemed lines, and clean up any stale Visit 1 is_pending rows.
     */
    private function applyRedemptionPlansOntoAppointment(Appointment $appointment, array $redemptionPlans, ?Carbon &$cursor = null): void
    {
        if (empty($redemptionPlans)) {
            return;
        }

        if ($cursor === null && $appointment->start_time !== null) {
            $lastLine = $appointment->appointmentServices()
                ->where('is_pending', false)
                ->whereNotNull('start_time')
                ->orderByDesc('start_time')
                ->first();
            $cursor = $lastLine
                ? Carbon::parse($lastLine->end_time)
                : Carbon::parse($appointment->appointment_datetime);
        }

        $unmatchedStandalone = $appointment->appointmentServices()
            ->where('is_pending', false)
            ->whereNull('client_package_id')
            ->whereNull('combo_id')
            ->whereNull('combo_name')
            ->orderBy('start_time')
            ->orderBy('id')
            ->get()
            ->filter(fn (AppointmentService $l) => !$l->isPackageRedemptionLine())
            ->keyBy('id');

        foreach ($redemptionPlans as $plan) {
            /** @var Service $service */
            $service = $plan['service'];
            /** @var ClientPackage $package */
            $package = $plan['client_package'];

            $matchedStandalone = $unmatchedStandalone->first(function (AppointmentService $line) use ($service): bool {
                if ($line->service_id !== null && (int) $line->service_id === (int) $service->id) {
                    return true;
                }

                return strcasecmp(trim((string) $line->name), trim((string) $service->name)) === 0;
            });

            if ($matchedStandalone) {
                $unmatchedStandalone->forget($matchedStandalone->id);
                $matchedStandalone->update([
                    'service_id' => $service->id,
                    'combo_id' => $package->combo_id,
                    'combo_name' => $package->combo_name,
                    'client_package_id' => $package->id,
                    'staff_id' => $plan['staff_id'] ?? $matchedStandalone->staff_id ?? $appointment->staff_id,
                    'name' => $service->name,
                    'price' => $service->price,
                    'original_price' => $matchedStandalone->original_price ?: $service->price,
                    'discount_type' => 'percent',
                    'discount_value' => 100,
                    'discount_reason' => "Redeemed from {$package->combo_name} package",
                ]);
                $appointmentService = $matchedStandalone->fresh();
            } else {
                $appointmentService = $this->addRedeemedServiceLine(
                    $appointment,
                    $service,
                    $package->combo_name,
                    $cursor,
                    $plan['staff_id'] ?? null,
                    $package->combo_id,
                    $package->id
                );
            }

            $existingPendingRow = ClientPackageService::where('client_package_id', $package->id)
                ->where('service_id', $service->id)
                ->where('status', 'pending')
                ->first();

            if ($existingPendingRow) {
                if ($existingPendingRow->appointment_service_id) {
                    AppointmentService::whereKey($existingPendingRow->appointment_service_id)
                        ->where('is_pending', true)
                        ->delete();
                }
                $existingPendingRow->update([
                    'service_name' => $service->name,
                    'status' => 'redeemed',
                    'redeemed_at' => now(),
                    'appointment_service_id' => $appointmentService->id,
                ]);
            } else {
                ClientPackageService::create([
                    'client_package_id' => $package->id,
                    'service_id' => $service->id,
                    'service_name' => $service->name,
                    'status' => 'redeemed',
                    'redeemed_at' => now(),
                    'appointment_service_id' => $appointmentService->id,
                ]);
            }

            $package->refreshStatus();
        }
    }

    /**
     * Returns true when an appointment has an unpaid originating Combo ("Choose in Salon" / 0 Today services)
     * and no immediate combo services or redemptions have been selected yet for today's visit.
     */
    private function hasUnpickedOriginatingCombo(Appointment $appointment, array $newPackagePlans = [], array $redemptionPlans = []): bool
    {
        $unpaidOriginating = ClientPackage::where('appointment_id', $appointment->id)
            ->whereNull('sale_id')
            ->where('status', '!=', 'cancelled')
            ->get();

        if ($unpaidOriginating->isEmpty()) {
            return false;
        }

        $activeLines = $appointment->appointmentServices()->where('is_pending', false)->get();
        if ($activeLines->isNotEmpty()) {
            return false;
        }

        foreach ($newPackagePlans as $plan) {
            if (!empty($plan['immediate_ids'])) {
                return false;
            }
        }

        if (!empty($redemptionPlans)) {
            return false;
        }

        return true;
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

    private function isUnresolvedDecideInSalon(Appointment $appointment): bool
    {
        if (!(bool) $appointment->decide_in_salon) {
            return false;
        }

        if (!empty($appointment->combo_name)) {
            return false;
        }

        if ($appointment->appointmentServices()->where('is_pending', false)->exists()) {
            return false;
        }

        if (ClientPackage::where('appointment_id', $appointment->id)->exists()) {
            return false;
        }

        return true;
    }

    public function payment(Request $request, Appointment $appointment)
    {
        if ($appointment->sale()->exists()) {
            return redirect()->route('appointments.revenue.index')
                ->with('error', 'This appointment has already been checked out.');
        }

        if ($this->isUnresolvedDecideInSalon($appointment)) {
            return $this->conflictValidator()->toResponse($request, [
                'reason' => 'decide_in_salon',
                'field' => 'services',
                'message' => 'Please add the service(s) or combo performed before completing checkout.',
            ]);
        }

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
            ? $appointment->customer->redeemablePackageServices($appointment->id)
            : [];

        // A combo can already have been sold at booking time (via the
        // calendar drawer's own "+ Add Combo") - it's just never been paid
        // for yet. Surface it here so the checkout total/summary accounts
        // for it from the moment the page loads, not only after this
        // checkout's own "sell a package" picker is used.
        $unpaidPackages = ClientPackage::where('appointment_id', $appointment->id)
            ->whereNull('sale_id')
            ->where('status', '!=', 'cancelled')
            ->get(['id', 'combo_name', 'price_paid']);
        $unpaidPackagesTotal = (float) $unpaidPackages->sum('price_paid');
        $hasUnpickedOriginatingCombo = $this->hasUnpickedOriginatingCombo($appointment);

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
            'unpaidPackagesTotal',
            'hasUnpickedOriginatingCombo'
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
            'products.*.staff_id' => 'nullable|exists:staff,id',
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
            'packages.*.pending_service_ids' => 'nullable|array',
            'packages.*.pending_service_ids.*' => 'integer|exists:services,id',
            'packages.*.service_staff' => 'nullable|array',
            // Previously-purchased pending balance being redeemed today -
            // each a "{client_package_id}:{service_id}" token, fully
            // re-validated in buildPackageRedemptionPlans().
            'redeem_service_ids' => 'nullable|array',
            'redeem_service_ids.*' => 'string',
            'redeem_service_staff' => 'nullable|array',
            'redeem_points' => 'nullable|integer|min:0',
            'package_prepurchase_only' => 'nullable|boolean',
        ]);

        $alreadyCheckedOut = false;
        $validationError = null;
        $decideInSalonCheckoutError = null;
        $unpickedComboCheckoutError = null;
        $purchasedPackages = [];
        $pointsEarned = 0;
        $customer = null;

        try {
            DB::transaction(function () use (
                $request,
                $appointment,
                &$alreadyCheckedOut,
                &$validationError,
                &$decideInSalonCheckoutError,
                &$unpickedComboCheckoutError,
                &$purchasedPackages,
                &$pointsEarned,
                &$customer
            ) {
                $lockedAppointment = Appointment::whereKey($appointment->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                if ($lockedAppointment->sale()->exists()) {
                    $alreadyCheckedOut = true;
                    return;
                }

                $customer = $lockedAppointment->customer_id
                    ? Customer::whereKey($lockedAppointment->customer_id)->lockForUpdate()->first()
                    : $this->findOrCreateCustomer($lockedAppointment->phone, $lockedAppointment->customer_name);

                // The expiration engine: reconcile this client's packages against
                // real time before trusting anything about what's still redeemable.
                if ($customer) {
                    ClientPackage::expireDue($customer->id);
                }

                $requestedRedeemPoints = (int) ($request->input('redeem_points', 0));
                if ($requestedRedeemPoints > 0) {
                    if (!$customer || $requestedRedeemPoints > (int) $customer->loyalty_points) {
                        $validationError = 'Insufficient loyalty points to redeem.';
                        return;
                    }
                }

                [$packagePlans, $packagePlanError] = $this->buildPackagePurchasePlans($request->input('packages', []));
                if ($packagePlanError) {
                    $validationError = $packagePlanError;
                    return;
                }
                if (!empty($packagePlans) && !$customer) {
                    $validationError = 'A client profile with a phone number is required to purchase a package.';
                    return;
                }

                $rawRedeemStaff = is_array($request->input('redeem_service_staff'))
                    ? $request->input('redeem_service_staff')
                    : [];
                [$redemptionPlans, $redemptionPlanError] = $customer
                    ? $this->buildPackageRedemptionPlans($request->input('redeem_service_ids', []), $customer, $lockedAppointment->id, $rawRedeemStaff)
                    : [[], (!empty($request->input('redeem_service_ids', [])) ? 'A client profile with a phone number is required to redeem a package.' : null)];
                if ($redemptionPlanError) {
                    $validationError = $redemptionPlanError;
                    return;
                }

                $serviceItems = $this->appointmentServiceItems($lockedAppointment);

                // Smart Coverage (P1-1): If an unpaid standalone service line on this visit
                // matches a package slot being redeemed right now, cover its price at 0.00 QAR
                // instead of charging full catalog price + burning the package slot.
                $remainingCoverableIndices = [];
                foreach ($serviceItems as $idx => $item) {
                    if (empty($item['is_package_redemption']) && empty($item['combo_name']) && (float) ($item['price'] ?? 0) > 0) {
                        $remainingCoverableIndices[] = $idx;
                    }
                }

                $coveredStandaloneAmount = 0.0;
                foreach ($redemptionPlans as $plan) {
                    $redeemSvc = $plan['service'];
                    $matchedPos = null;
                    foreach ($remainingCoverableIndices as $pos => $itemIdx) {
                        $candItem = $serviceItems[$itemIdx];
                        $idMatch = !empty($candItem['service_id']) && (int) $candItem['service_id'] === (int) $redeemSvc->id;
                        $nameMatch = strcasecmp(trim((string) ($candItem['name'] ?? '')), trim((string) $redeemSvc->name)) === 0;
                        if ($idMatch || $nameMatch) {
                            $matchedPos = $pos;
                            $coveredStandaloneAmount += (float) $candItem['price'];
                            break;
                        }
                    }
                    if ($matchedPos !== null) {
                        unset($remainingCoverableIndices[$matchedPos]);
                    }
                }

                $servicesTotal = max(0.0, round(array_sum(array_column($serviceItems, 'price')) - $coveredStandaloneAmount, 2));

                // A combo bought at booking time already has its ClientPackage row
                // (and any immediate services already added as 0-cost lines) - it's
                // just never been paid for yet. Charge for it here alongside any
                // brand-new combo being sold at this checkout.
                $unpaidBookingPackages = ClientPackage::where('appointment_id', $lockedAppointment->id)
                    ->whereNull('sale_id')
                    ->where('status', '!=', 'cancelled')
                    ->lockForUpdate()
                    ->get();

                if (
                    $lockedAppointment->decide_in_salon
                    && empty($serviceItems)
                    && empty($packagePlans)
                    && $unpaidBookingPackages->isEmpty()
                    && empty($redemptionPlans)
                ) {
                    $decideInSalonCheckoutError = 'Please add the service(s) or combo performed before completing checkout.';
                    return;
                }

                if (
                    $this->hasUnpickedOriginatingCombo($lockedAppointment, $packagePlans, $redemptionPlans)
                    && !$request->boolean('package_prepurchase_only')
                ) {
                    $unpickedComboCheckoutError = self::UNPICKED_COMBO_CHECKOUT_MESSAGE;
                    return;
                }

                $packagesTotal = array_reduce($packagePlans, fn($sum, $plan) => $sum + (float) $plan['combo']->price, 0)
                    + (float) $unpaidBookingPackages->sum('price_paid');

                $productLines = [];
                $productsTotal = 0;
                foreach ($request->input('products', []) as $line) {
                    $product = Product::whereKey($line['product_id'])->lockForUpdate()->first();
                    if (!$product) continue;
                    $qty = (int) $line['quantity'];
                    $lineTotal = $product->price * $qty;
                    $productsTotal += $lineTotal;
                    $lineStaffId = !empty($line['staff_id']) ? (int) $line['staff_id'] : $lockedAppointment->staff_id;
                    $productLines[] = [
                        'product' => $product,
                        'product_id' => $product->id,
                        'staff_id' => $lineStaffId,
                        'name' => $product->name,
                        'price' => $product->price,
                        'quantity' => $qty,
                        'total' => $lineTotal,
                    ];
                }

                // Upsells logged from the calendar drawer are charged at checkout too,
                // folded into services/products revenue by their own type.
                $upsells = $lockedAppointment->upsells()->get();
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

                $effectiveRedeemPoints = 0;
                if ($requestedRedeemPoints > 0 && $customer) {
                    $maxRedeemableAmount = max(0.0, $subtotal - $discountAmount);
                    $effectiveRedeemPoints = (int) min($requestedRedeemPoints, floor($maxRedeemableAmount));
                    if ($effectiveRedeemPoints > 0) {
                        $discountAmount = round($discountAmount + $effectiveRedeemPoints, 2);
                        if (!$discountType) {
                            $discountType = 'flat';
                            $discountValue = $discountAmount;
                        }
                    }
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
                    $validationError = sprintf(
                        'Payment total (%.2f QAR) does not match the amount due (%.2f QAR).',
                        $paidTotal,
                        $totalAmount
                    );
                    return;
                }

                // Seeded with any packages already created at booking time - they
                // still need a sale_id and a SaleItem now that they're being paid for.
                $purchasedPackages = $unpaidBookingPackages->all();

                $lastLine = $lockedAppointment->appointmentServices()
                    ->where('is_pending', false)
                    ->whereNotNull('start_time')
                    ->orderByDesc('start_time')
                    ->first();
                $cursor = $lastLine ? $lastLine->end_time : $lockedAppointment->appointment_datetime;

                if (!empty($redemptionPlans)) {
                    $this->applyRedemptionPlansOntoAppointment($lockedAppointment, $redemptionPlans, $cursor);
                }

                if ($customer && !empty($packagePlans)) {
                    $purchasedPackages = array_merge(
                        $purchasedPackages,
                        $this->createPackagePurchases($lockedAppointment, $customer, $packagePlans, $cursor)
                    );
                }

                // Re-fetch now that the redemption/immediate lines above actually
                // exist, so the sale's service line items include them (still at
                // their 0 price - $servicesTotal itself doesn't change). Also
                // reload clientPackageService so SaleItem.client_package_service_id
                // links every performed package slot to its sale line.
                if ($redemptionPlans || $packagePlans) {
                    $lockedAppointment->syncFromServices();
                }
                $serviceItems = $this->appointmentServiceItems(
                    $lockedAppointment->load('appointmentServices.clientPackageService')
                );

                $resolvedBranch = $lockedAppointment->branch ?: ($request->input('branch') ?: 'old_airport');

                $sale = Sale::create([
                    'appointment_id' => $lockedAppointment->id,
                    'customer_id' => $customer?->id,
                    'staff_id' => $lockedAppointment->staff_id,
                    'created_by' => auth()->id(),
                    'branch' => $resolvedBranch,
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
                        'client_package_service_id' => $item['client_package_service_id'] ?? null,
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
                        'staff_id' => $line['staff_id'] ?? $lockedAppointment->staff_id,
                        'name' => $line['name'],
                        'price' => $line['price'],
                        'quantity' => $line['quantity'],
                        'total' => $line['total'],
                    ]);

                    /** @var Product $productModel */
                    $productModel = $line['product'];
                    $productModel->update([
                        'stock_quantity' => max(0, (int) $productModel->stock_quantity - (int) $line['quantity']),
                    ]);
                }

                foreach ($upsells as $upsell) {
                    SaleItem::create([
                        'sale_id' => $sale->id,
                        'type' => $upsell->type,
                        'product_id' => $upsell->product_id,
                        'staff_id' => $upsell->staff_id ?? $lockedAppointment->staff_id,
                        'name' => $upsell->name . ' (Upsell)',
                        'price' => $upsell->amount,
                        'quantity' => 1,
                        'total' => $upsell->amount,
                    ]);

                    if ($upsell->type === 'product' && $upsell->product_id) {
                        $upsellProduct = Product::whereKey($upsell->product_id)->lockForUpdate()->first();
                        if ($upsellProduct) {
                            $upsellProduct->update([
                                'stock_quantity' => max(0, (int) $upsellProduct->stock_quantity - 1),
                            ]);
                        }
                    }
                }

                $paidAt = now('Asia/Qatar');

                foreach ($purchasedPackages as $pkg) {
                    $pkg->loadMissing('combo');
                    $validityDays = (int) ($pkg->validity_days ?: ($pkg->combo?->validity_days ?: 7));
                    if ($validityDays <= 0) {
                        $validityDays = 7;
                    }

                    $pkg->update([
                        'sale_id' => $sale->id,
                        'validity_days' => $validityDays,
                        'purchased_at' => $paidAt,
                        'expires_at' => $paidAt->copy()->addDays($validityDays)->endOfDay(),
                    ]);
                    $pkg->refreshStatus();

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

                $activeAppointmentServiceIds = $lockedAppointment->appointmentServices
                    ->where('is_pending', false)
                    ->pluck('id')
                    ->all();
                if (!empty($activeAppointmentServiceIds)) {
                    ClientPackageService::whereIn('appointment_service_id', $activeAppointmentServiceIds)
                        ->where('status', 'redeemed')
                        ->update(['sale_id' => $sale->id]);
                }

                foreach ($payments as $method => $amount) {
                    SalePayment::create([
                        'sale_id' => $sale->id,
                        'method' => $method,
                        'amount' => round($amount, 2),
                    ]);
                }

                $dominantMethod = $payments ? array_search(max($payments), $payments) : null;
                $netSalePrice = AdLeadEntry::netSaleAmount($sale);
                $updatedLifetimeRevenue = $this->calculateVerifiedLifetimeRevenue(
                    $customer?->id ?: ($lockedAppointment->customer_id ? (int) $lockedAppointment->customer_id : null),
                    $lockedAppointment->phone
                );

                $lockedAppointment->update([
                    'branch' => $resolvedBranch,
                    'status' => 'completed',
                    'decide_in_salon' => false,
                    'hold_duration' => null,
                    'price' => $netSalePrice,
                    'lifetime_revenue' => $updatedLifetimeRevenue,
                    'payment_method' => $dominantMethod,
                    'paid_at' => now(),
                ]);

                $this->freebieService()->onAppointmentPaidOrCompleted($lockedAppointment);

                $this->markAppointmentLeadsAsBooked($lockedAppointment);
                $this->syncAdLeadAttributionForAppointment($lockedAppointment);

                if ($customer && $effectiveRedeemPoints > 0) {
                    LoyaltyTransaction::create([
                        'customer_id' => $customer->id,
                        'sale_id' => $sale->id,
                        'type' => 'redeem',
                        'points' => -$effectiveRedeemPoints,
                        'description' => 'Redeemed at checkout #' . $sale->id,
                        'created_by' => auth()->id(),
                    ]);
                    $customer->decrement('loyalty_points', $effectiveRedeemPoints);
                }

                if ($customer) {
                    $pointsEarned = $customer->earnPointsForSale($sale);
                }
            });
        } catch (UniqueConstraintViolationException) {
            return redirect()->route('appointments.revenue.index')
                ->with('error', 'This appointment has already been checked out.');
        }

        if ($alreadyCheckedOut) {
            return redirect()->route('appointments.revenue.index')
                ->with('error', 'This appointment has already been checked out.');
        }

        if ($decideInSalonCheckoutError !== null) {
            return $this->conflictValidator()->toResponse($request, [
                'reason' => 'decide_in_salon',
                'field' => 'services',
                'message' => $decideInSalonCheckoutError,
            ]);
        }

        if ($unpickedComboCheckoutError !== null) {
            return $this->conflictValidator()->toResponse($request, [
                'reason' => 'combo_tbd',
                'field' => 'package_prepurchase_only',
                'message' => $unpickedComboCheckoutError,
            ]);
        }

        if ($validationError !== null) {
            return redirect()->back()->withInput()->with('error', $validationError);
        }

        $packageNote = count($purchasedPackages)
            ? ' ' . count($purchasedPackages) . ' package' . (count($purchasedPackages) === 1 ? '' : 's') . ' sold.'
            : '';

        return redirect()->route('appointments.revenue.index')
            ->with('success', 'Payment recorded successfully.' . $packageNote . ($pointsEarned && $customer ? " {$customer->name} earned {$pointsEarned} loyalty points." : ''));
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
    private function buildPackagePurchasePlans(array $packages, ?string $branch = null): array
    {
        $plans = [];
        $canonicalBranch = $branch ? (Customer::normalizeBranch($branch) ?? $branch) : null;

        foreach ($packages as $pkg) {
            $rawImmediateIds = array_values(array_unique(array_map('intval', $pkg['immediate_service_ids'] ?? [])));
            $rawPendingIds = array_values(array_diff(
                array_unique(array_map('intval', $pkg['pending_service_ids'] ?? [])),
                $rawImmediateIds
            ));
            $rawServiceStaff = is_array($pkg['service_staff'] ?? null) ? $pkg['service_staff'] : [];
            $rawServiceSort = is_array($pkg['service_sort_order'] ?? null) ? $pkg['service_sort_order'] : [];
            $rawServiceParallel = is_array($pkg['service_parallel'] ?? null) ? $pkg['service_parallel'] : [];

            if (!empty($rawServiceSort)) {
                $origPos = array_flip($rawImmediateIds);
                usort($rawImmediateIds, static function (int $a, int $b) use ($rawServiceSort, $origPos): int {
                    $sa = $rawServiceSort[$a] ?? ($rawServiceSort[(string) $a] ?? null);
                    $sb = $rawServiceSort[$b] ?? ($rawServiceSort[(string) $b] ?? null);
                    $ka = ($sa !== null && $sa !== '') ? (int) $sa : ($origPos[$a] ?? 0);
                    $kb = ($sb !== null && $sb !== '') ? (int) $sb : ($origPos[$b] ?? 0);
                    if ($ka !== $kb) {
                        return $ka <=> $kb;
                    }

                    return ($origPos[$a] ?? 0) <=> ($origPos[$b] ?? 0);
                });
            }

            $combo = Combo::with('services')->find($pkg['combo_id'] ?? null);
            if (!$combo) {
                return [[], 'Selected combo package no longer exists.', null];
            }

            $poolServices = $combo->services->keyBy('id');
            $quantityIncluded = $combo->quantity_included ?? $poolServices->count();

            if (array_diff($rawImmediateIds, $poolServices->keys()->all()) || array_diff($rawPendingIds, $poolServices->keys()->all())) {
                return [[], "Selected services are not part of the \"{$combo->name}\" package.", null];
            }

            if (count($rawImmediateIds) + count($rawPendingIds) > $quantityIncluded) {
                return [[], "\"{$combo->name}\" only includes {$quantityIncluded} service(s) across Today and Pending.", null];
            }

            $serviceStaff = [];
            $serviceSortOrder = [];
            $serviceParallel = [];

            foreach ($rawImmediateIds as $seqIdx => $sid) {
                $rawSortVal = $rawServiceSort[$sid] ?? ($rawServiceSort[(string) $sid] ?? null);
                if ($rawSortVal !== null && $rawSortVal !== '') {
                    $serviceSortOrder[$sid] = (int) $rawSortVal;
                }
                $rawParVal = $rawServiceParallel[$sid] ?? ($rawServiceParallel[(string) $sid] ?? false);
                $serviceParallel[$sid] = filter_var($rawParVal, FILTER_VALIDATE_BOOLEAN);

                $rawAssigned = $rawServiceStaff[$sid] ?? ($rawServiceStaff[(string) $sid] ?? null);
                if ($rawAssigned === null || $rawAssigned === '') {
                    continue;
                }

                $assignedStaffId = (int) $rawAssigned;
                $service = $poolServices->get($sid);
                $assignedStaff = \App\Models\Staff::find($assignedStaffId);

                if (!$assignedStaff) {
                    return [[], "Selected staff member for \"{$service->name}\" no longer exists.", [
                        'reason' => 'validation',
                        'field' => 'staff_id',
                        'message' => "Selected staff member for \"{$service->name}\" no longer exists.",
                    ]];
                }

                $unskilled = $this->conflictValidator()->unskilledServices($assignedStaffId, [$service->name], $canonicalBranch);
                if (!empty($unskilled)) {
                    $eligibleNames = $canonicalBranch
                        ? $this->conflictValidator()->eligibleBranchStaffNames($canonicalBranch, $unskilled, $assignedStaffId)
                        : [];
                    $eligibleSuffix = !empty($eligibleNames) ? ' Eligible staff: ' . implode(', ', $eligibleNames) . '.' : '';
                    $msg = "Cannot book: {$assignedStaff->name} does not have the required skill for {$service->name}.{$eligibleSuffix}";

                    return [[], $msg, [
                        'reason' => BookingConflictValidator::REASON_MISSING_SKILL,
                        'field' => 'staff_id',
                        'message' => $msg,
                    ]];
                }

                $serviceStaff[$sid] = $assignedStaffId;
            }

            $plans[] = [
                'combo' => $combo,
                'immediate_ids' => $rawImmediateIds,
                'pending_ids' => $rawPendingIds,
                'service_staff' => $serviceStaff,
                'service_sort_order' => $serviceSortOrder,
                'service_parallel' => $serviceParallel,
                'pool' => $poolServices,
                'quantity_included' => $quantityIncluded,
            ];
        }

        return [$plans, null, null];
    }

    private function buildPackageRedemptionPlans(
        array $tokens,
        Customer $customer,
        ?int $forAppointmentId = null,
        array $redeemServiceStaff = [],
        ?string $branch = null,
        array $redeemServiceSort = [],
        array $redeemServiceParallel = []
    ): array {
        $plans = [];
        $claimedPerPackage = [];
        $canonicalBranch = $branch ? (Customer::normalizeBranch($branch) ?? $branch) : null;

        foreach ($tokens as $token) {
            $parts = explode(':', (string) $token, 2);
            $package = ClientPackage::with('redeemedServices')->find((int) ($parts[0] ?? 0));
            $service = Service::find((int) ($parts[1] ?? 0));

            if (!$package || !$service) {
                return [[], 'One of the selected package services is no longer available to redeem.', null];
            }

            if ((int) $package->customer_id !== (int) $customer->id) {
                return [[], 'That package does not belong to this client.', null];
            }

            if (!$package->canRedeem($forAppointmentId)) {
                $package->refreshStatus();
                return [[], "{$package->combo_name} has expired or has no remaining balance to redeem.", null];
            }

            $combo = Combo::with('services')->find($package->combo_id);
            if (!$combo || !$combo->services->contains('id', $service->id)) {
                return [[], "\"{$service->name}\" is not part of {$package->combo_name}.", null];
            }

            if ($package->redeemedServices->contains('service_id', $service->id)) {
                return [[], "\"{$service->name}\" has already been redeemed from {$package->combo_name}.", null];
            }

            $claimedPerPackage[$package->id] = ($claimedPerPackage[$package->id] ?? 0) + 1;
            if ($package->redeemed_count + $claimedPerPackage[$package->id] > $package->quantity_included) {
                return [[], "{$package->combo_name} doesn't have enough remaining balance for this selection.", null];
            }

            $canonicalToken = "{$package->id}:{$service->id}";
            $rawAssigned = $redeemServiceStaff[(string) $token] ?? ($redeemServiceStaff[$canonicalToken] ?? null);
            $assignedStaffId = null;
            if ($rawAssigned !== null && $rawAssigned !== '') {
                $assignedStaffId = (int) $rawAssigned;
                $assignedStaff = Staff::find($assignedStaffId);
                if (!$assignedStaff) {
                    return [[], "Selected staff member for \"{$service->name}\" no longer exists.", null];
                }
                $unskilled = $this->conflictValidator()->unskilledServices($assignedStaffId, [$service->name], $canonicalBranch);
                if (!empty($unskilled)) {
                    $eligibleNames = $canonicalBranch
                        ? $this->conflictValidator()->eligibleBranchStaffNames($canonicalBranch, $unskilled, $assignedStaffId)
                        : [];
                    $eligibleSuffix = !empty($eligibleNames) ? ' Eligible staff: ' . implode(', ', $eligibleNames) . '.' : '';
                    $msg = "Cannot book: {$assignedStaff->name} does not have the required skill for {$service->name}.{$eligibleSuffix}";

                    return [[], $msg, [
                        'reason' => BookingConflictValidator::REASON_MISSING_SKILL,
                        'field' => 'staff_id',
                        'message' => $msg,
                    ]];
                }
            }

            $rawSort = $redeemServiceSort[(string) $token] ?? ($redeemServiceSort[$canonicalToken] ?? null);
            $rawPar = $redeemServiceParallel[(string) $token] ?? ($redeemServiceParallel[$canonicalToken] ?? false);

            $plans[] = [
                'client_package' => $package,
                'service' => $service,
                'staff_id' => $assignedStaffId,
                'sort_order' => ($rawSort !== null && $rawSort !== '') ? (int) $rawSort : null,
                'is_parallel' => filter_var($rawPar, FILTER_VALIDATE_BOOLEAN),
            ];
        }

        return [$plans, null, null];
    }

    /**
     * Appends a service line performed today but already paid for via a
     * combo package - priced at 0 through the same discount mechanism used
     * for manual price overrides.
     */
    private function addRedeemedServiceLine(
        Appointment $appointment,
        Service $service,
        string $comboName,
        ?Carbon &$cursor,
        ?int $staffId = null,
        ?int $comboId = null,
        ?int $clientPackageId = null,
        int $sortOrder = 0,
        bool $isParallel = false
    ): AppointmentService {
        $line = AppointmentService::create([
            'appointment_id' => $appointment->id,
            'service_id' => $service->id,
            'combo_id' => $comboId,
            'combo_name' => $comboName,
            'client_package_id' => $clientPackageId,
            'staff_id' => $staffId ?? $appointment->staff_id,
            'sort_order' => $sortOrder,
            'is_parallel' => $isParallel,
            'name' => $service->name,
            'price' => $service->price,
            'original_price' => $service->price,
            'duration' => $service->duration,
            'start_time' => $cursor ? $cursor->copy() : null,
            'status' => 'scheduled',
            'is_pending' => false,
            'discount_type' => 'percent',
            'discount_value' => 100,
            'discount_reason' => "Redeemed from {$comboName} package",
        ]);

        if ($cursor) {
            $cursor = $cursor->copy()->addMinutes($service->duration);
        }

        return $line;
    }

    private function createPackagePurchases(
        Appointment $appointment,
        Customer $customer,
        array $packagePlans,
        ?Carbon &$cursor,
        array $scheduleByPlanAndSvc = []
    ): array {
        $purchased = [];

        foreach ($packagePlans as $planIdx => $plan) {
            $combo = $plan['combo'];
            $validityDays = (int) ($combo->validity_days ?: 7);
            if ($validityDays <= 0) {
                $validityDays = 7;
            }

            $clientPackage = ClientPackage::create([
                'customer_id' => $customer->id,
                'combo_id' => $combo->id,
                'appointment_id' => $appointment->id,
                'combo_name' => $combo->name,
                'price_paid' => $combo->price,
                'quantity_included' => $plan['quantity_included'],
                'validity_days' => $validityDays,
                'purchased_at' => null,
                'expires_at' => null,
                'status' => 'active',
            ]);

            foreach ($plan['immediate_ids'] as $seqIdx => $sid) {
                $service = $plan['pool']->get($sid);
                $sliceMeta = $scheduleByPlanAndSvc[$planIdx][$sid] ?? null;
                $assignedStaffId = $sliceMeta['staff_id'] ?? ($plan['service_staff'][$sid] ?? null);
                $sliceCursor = $sliceMeta ? $sliceMeta['start'] : $cursor;

                $appointmentService = $this->addRedeemedServiceLine(
                    $appointment,
                    $service,
                    $combo->name,
                    $sliceCursor,
                    $assignedStaffId,
                    $combo->id,
                    $clientPackage->id,
                    $sliceMeta ? (int) $sliceMeta['sort_order'] : $seqIdx,
                    $sliceMeta ? (bool) $sliceMeta['is_parallel'] : false
                );

                if (!$sliceMeta) {
                    $cursor = $sliceCursor;
                }

                ClientPackageService::create([
                    'client_package_id' => $clientPackage->id,
                    'service_id' => $sid,
                    'service_name' => $service->name,
                    'status' => 'redeemed',
                    'redeemed_at' => now(),
                    'appointment_service_id' => $appointmentService->id,
                ]);
            }

            foreach ($plan['pending_ids'] ?? [] as $pIdx => $sid) {
                $service = $plan['pool']->get($sid);
                if (!$service) {
                    continue;
                }

                $pendingApptService = AppointmentService::create([
                    'appointment_id' => $appointment->id,
                    'service_id' => $service->id,
                    'combo_id' => $combo->id,
                    'combo_name' => $combo->name,
                    'client_package_id' => $clientPackage->id,
                    'staff_id' => null,
                    'sort_order' => 100 + $pIdx,
                    'is_parallel' => false,
                    'name' => $service->name,
                    'price' => 0,
                    'original_price' => $service->price,
                    'duration' => $service->duration,
                    'start_time' => null,
                    'status' => 'pending',
                    'is_pending' => true,
                    'discount_type' => null,
                    'discount_value' => 0,
                    'discount_reason' => "Pending from {$combo->name} package",
                ]);

                ClientPackageService::create([
                    'client_package_id' => $clientPackage->id,
                    'service_id' => $sid,
                    'service_name' => $service->name,
                    'status' => 'pending',
                    'redeemed_at' => null,
                    'appointment_service_id' => $pendingApptService->id,
                ]);
            }

            $clientPackage->refreshStatus();
            $purchased[] = $clientPackage;
        }

        return $purchased;
    }

    /**
     * Synchronize the client's matching AdLeadEntry in the SAME calendar month
     * as the appointment (or its previous month if rescheduled across months).
     */
    private function syncAdLeadAttributionForAppointment(Appointment $appointment, ?Carbon $previousDate = null): void
    {
        $appointment->refresh();
        $customer = $appointment->customer ?: ($appointment->customer_id ? Customer::find($appointment->customer_id) : null);
        $apptDate = Carbon::parse($appointment->appointment_datetime ?: $appointment->booking_date ?: now());

        if ($previousDate && ($previousDate->year !== $apptDate->year || $previousDate->month !== $apptDate->month || $previousDate->toDateString() !== $apptDate->toDateString())) {
            AdLeadEntry::syncFromCustomerActivity($customer, $previousDate, $appointment->phone, $appointment->id);
        }

        AdLeadEntry::syncFromCustomerActivity($customer, $apptDate, $appointment->phone, $appointment->id);
    }
}
