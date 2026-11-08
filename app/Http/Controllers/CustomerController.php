<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Models\ClientPackage;
use App\Models\Combo;
use App\Models\Customer;
use App\Models\Lead;
use App\Models\Sale;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\StaffComplaint;
use App\Support\ClientMaintenancePlanner;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

class CustomerController extends Controller
{
    public function index(Request $request)
    {
        $segments = $this->computeRetentionSegments();
        $rawSegment = strtolower(trim((string) $request->query('segment', 'all')));
        if ($rawSegment === 'second_visit_zone') {
            $rawSegment = 'due_for_refill';
        }
        $allowedSegments = ['all', 'ltv', 'due_for_refill', 'package_renewal', 'bleeding_vips'];
        $activeSegment = in_array($rawSegment, $allowedSegments, true) ? $rawSegment : 'all';

        $netSaleExpr = 'CASE WHEN (COALESCE(services_total, 0) + COALESCE(products_total, 0) + COALESCE(packages_total, 0) - COALESCE(discount_amount, 0)) > 0 THEN (COALESCE(services_total, 0) + COALESCE(products_total, 0) + COALESCE(packages_total, 0) - COALESCE(discount_amount, 0)) ELSE 0 END';

        $query = Customer::query()
            ->select('customers.*')
            ->selectSub(
                Sale::query()
                    ->selectRaw("COALESCE(SUM({$netSaleExpr}), 0)")
                    ->whereColumn('sales.customer_id', 'customers.id'),
                'computed_net_ltv'
            )
            ->withCount([
                'appointments' => fn ($q) => $q->where('status', 'completed'),
            ]);

        if (!in_array($activeSegment, ['all', 'ltv'], true)) {
            $query->whereIn('id', $segments[$activeSegment] ?? []);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $digits = preg_replace('/\D/', '', $search);

            $query->where(function ($q) use ($search, $digits) {
                $q->where('name', 'like', '%' . $search . '%');
                if ($digits !== '') {
                    $q->orWhere('phone', 'like', '%' . $digits . '%');
                }
            });
        }

        if ($activeSegment === 'ltv') {
            $query->orderByDesc('computed_net_ltv')
                ->orderByDesc('appointments_count')
                ->orderBy('name');
        } else {
            $query->orderBy('name');
        }

        $customers = $query->paginate(20)->withQueryString();

        // LTV + last visit, computed in bulk to avoid N+1 per-row queries.
        $customerIds = $customers->pluck('id');

        $ltv = Sale::whereIn('customer_id', $customerIds)
            ->get(['customer_id', 'services_total', 'products_total', 'packages_total', 'discount_amount'])
            ->groupBy('customer_id')
            ->map(fn ($sales) => round((float) $sales->sum(fn ($s) => max(0, (float) $s->services_total + (float) $s->products_total + (float) $s->packages_total - (float) $s->discount_amount)), 2));

        $lastCompletedAppts = Appointment::with('staff:id,name')
            ->whereIn('customer_id', $customerIds)
            ->where('status', 'completed')
            ->orderByDesc('appointment_datetime')
            ->get(['id', 'customer_id', 'staff_id', 'branch', 'service_name', 'appointment_datetime'])
            ->groupBy('customer_id')
            ->map(fn ($group) => $group->first());

        $lastVisit = $lastCompletedAppts->map(fn ($appt) => $appt?->appointment_datetime);

        $dueCustomerIds = collect($segments['due_for_refill'] ?? []);
        $dueDetailsMap = $segments['due_for_refill_details'] ?? [];

        $segmentCounts = [
            'all' => $segments['all_count'],
            'ltv' => $segments['all_count'],
            'due_for_refill' => count($segments['due_for_refill']),
            'package_renewal' => count($segments['package_renewal']),
            'bleeding_vips' => count($segments['bleeding_vips']),
        ];

        $dueForRefillLookup = array_flip($segments['due_for_refill']);
        $packageRenewalLookup = array_flip($segments['package_renewal']);
        $bleedingVipsLookup = array_flip($segments['bleeding_vips']);

        $customerTiers = [];
        foreach ($customers as $c) {
            $cid = (int) $c->id;
            $cLtv = (float) ($ltv[$cid] ?? 0);
            $isVip = ((int) $c->appointments_count >= 2) || ($cLtv >= 500);
            $badges = [];
            $dueDetail = $dueDetailsMap[$cid] ?? null;

            if (isset($dueForRefillLookup[$cid]) && $dueDetail) {
                $isOverdue = ($dueDetail['urgency'] ?? '') === 'overdue';
                $svcLabel = $dueDetail['service_name'] ?? 'Service';
                $daysOverdue = (int) ($dueDetail['days_overdue'] ?? 0);
                $daysUntil = (int) ($dueDetail['days_until'] ?? 0);

                $badges[] = [
                    'label' => ($isOverdue ? '⏰ Overdue: ' : '🔄 Due: ') . $svcLabel,
                    'class' => $isOverdue ? 'status-pill-slipping' : 'status-pill-refill',
                    'tooltip' => $isOverdue
                        ? "Overdue by {$daysOverdue} day(s) for {$svcLabel}."
                        : ($daysUntil === 0 ? "Due today for {$svcLabel}." : "Due in {$daysUntil} day(s) for {$svcLabel}."),
                ];

                if ((int) ($dueDetail['completed_visits_count'] ?? $c->appointments_count) === 1) {
                    $badges[] = [
                        'label' => '🔥 1st Visit',
                        'class' => 'status-pill-second',
                        'tooltip' => 'Completed 1st visit — lock in their 2nd visit now.',
                    ];
                }
            }

            if ($isVip) {
                $badges[] = [
                    'label' => '👑 VIP',
                    'class' => 'status-pill-vip',
                    'tooltip' => '2+ completed visits or 500+ QAR spent.',
                ];
            }

            if (isset($packageRenewalLookup[$cid])) {
                $badges[] = [
                    'label' => '🎟️ Package Low',
                    'class' => 'status-pill-package',
                    'tooltip' => '1 session left or package expires within 3 weeks.',
                ];
            } elseif (isset($bleedingVipsLookup[$cid])) {
                $badges[] = [
                    'label' => '⚠️ 45d+ Away',
                    'class' => 'status-pill-slipping',
                    'tooltip' => 'No visit in over 45 days and no upcoming booking.',
                ];
            }

            // Cap at max 2 compact badges so the status cell never wraps or stacks
            $badges = array_slice($badges, 0, 2);

            $rawDigits = preg_replace('/\D+/', '', (string) $c->phone);
            if ($rawDigits !== '' && strlen($rawDigits) <= 8) {
                $rawDigits = '974' . $rawDigits;
            }

            $lastAppt = $lastCompletedAppts->get($cid);
            $primaryService = $dueDetail['service_name']
                ?? ($lastAppt && $lastAppt->service_name
                    ? trim(explode(',', (string) $lastAppt->service_name)[0])
                    : 'Signature Salon Service');
            $daysSince = isset($dueDetail['days_overdue']) && $dueDetail['days_overdue'] > 0
                ? (int) $dueDetail['days_overdue']
                : (($lastAppt && $lastAppt->appointment_datetime)
                    ? max(0, (int) floor((now()->startOfDay()->timestamp - Carbon::parse($lastAppt->appointment_datetime)->startOfDay()->timestamp) / 86400))
                    : 0);

            $customerTiers[$cid] = [
                'is_vip' => $isVip,
                'badges' => $badges,
                'wa_phone' => $rawDigits,
                'last_service' => $primaryService,
                'last_staff' => ($dueDetail['last_staff_name'] ?? '') ?: ($lastAppt?->staff?->name ?? ''),
                'last_branch' => ($dueDetail['branch'] ?? '') ?: ($lastAppt?->branch ?? ''),
                'days_since' => $daysSince,
                'due_detail' => $dueDetail,
            ];
        }

        return view('customers.index', compact(
            'customers',
            'ltv',
            'lastVisit',
            'dueCustomerIds',
            'activeSegment',
            'segmentCounts',
            'customerTiers',
            'segments'
        ));
    }

    /**
     * Compute the 3 Zero-Overlap Outreach Tabs across all salon clients:
     * 1. due_for_refill (highest priority — includes explicit service intervals + 28-day fallback for 1st-visit retention)
     * 2. package_renewal (excluding due_for_refill)
     * 3. bleeding_vips (excluding due_for_refill & package_renewal)
     *
     * Excludes clients who have an upcoming active appointment on or after today
     * OR who were contacted within the last 14 days (last_retention_contacted_at).
     */
    public function computeRetentionSegments(): array
    {
        ClientPackage::expireDue();

        $todayStart = now()->startOfDay();
        $cooldownCutoff = now()->subDays(Customer::RETENTION_COOLDOWN_DAYS);
        $allCustomers = Customer::query()->get(['id', 'loyalty_points', 'last_retention_contacted_at']);

        $cooldownCustomerIds = $allCustomers
            ->filter(fn (Customer $c) => $c->last_retention_contacted_at !== null && $c->last_retention_contacted_at->gte($cooldownCutoff))
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->flip();

        $upcomingActiveCustomerIds = Appointment::query()
            ->whereNotNull('customer_id')
            ->whereIn('status', ['pending', 'confirmed', 'scheduled', 'arrived', 'in_progress'])
            ->where(function ($q) use ($todayStart) {
                $q->whereDate('appointment_datetime', '>=', $todayStart->toDateString())
                    ->orWhere(function ($sub) use ($todayStart) {
                        $sub->whereNull('appointment_datetime')
                            ->whereDate('booking_date', '>=', $todayStart->toDateString());
                    });
            })
            ->pluck('customer_id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->flip();

        $completedStats = Appointment::query()
            ->whereNotNull('customer_id')
            ->where('status', 'completed')
            ->selectRaw('customer_id, COUNT(*) as completed_count, MAX(appointment_datetime) as last_visit, SUM(COALESCE(price, 0)) as price_sum')
            ->groupBy('customer_id')
            ->get()
            ->keyBy('customer_id');

        $salesLtv = Sale::query()
            ->whereNotNull('customer_id')
            ->get(['customer_id', 'services_total', 'products_total', 'packages_total', 'discount_amount'])
            ->groupBy('customer_id')
            ->map(fn ($sales) => round((float) $sales->sum(fn ($s) => max(0, (float) $s->services_total + (float) $s->products_total + (float) $s->packages_total - (float) $s->discount_amount)), 2));

        // Priority 1: due_for_refill (Clients flagged as overdue or due_soon by ClientMaintenancePlanner)
        $dueQueue = (new ClientMaintenancePlanner())->dueQueue();
        $dueForRefillMap = [];
        $dueForRefillDetails = [];

        foreach ($dueQueue as $row) {
            $cid = (int) $row->customer_id;
            if ($upcomingActiveCustomerIds->has($cid) || $cooldownCustomerIds->has($cid)) {
                continue;
            }

            $stat = $completedStats->get($cid);
            $completedCount = $stat ? (int) $stat->completed_count : (int) ($row->completed_visits_count ?? 1);
            $lastVisitAt = ($stat && $stat->last_visit) ? Carbon::parse($stat->last_visit) : null;
            $daysSinceLastVisit = $lastVisitAt
                ? (int) floor(($todayStart->timestamp - $lastVisitAt->copy()->startOfDay()->timestamp) / 86400)
                : (int) ($row->days_since_visit ?? 0);
            $netLtv = $salesLtv->has($cid)
                ? (float) $salesLtv->get($cid)
                : ($stat ? (float) $stat->price_sum : 0.0);

            // If this is a 2+ visit VIP who is > 45 days away AND the service only used the generic 28d fallback
            // (no explicit rebooking_interval_days configured), let them route to Missing VIPs (bleeding_vips).
            $hasExplicitInterval = (bool) ($row->has_explicit_interval ?? true);
            if (!$hasExplicitInterval && ($completedCount >= 2 || $netLtv >= 500) && $daysSinceLastVisit > 45) {
                continue;
            }

            $dueForRefillMap[$cid] = true;
            if (!isset($dueForRefillDetails[$cid])) {
                $daysUntil = (int) $row->days_until;
                $dueForRefillDetails[$cid] = [
                    'service_name' => $row->service_name,
                    'urgency' => $row->urgency,
                    'days_until' => $daysUntil,
                    'days_overdue' => $daysUntil < 0 ? abs($daysUntil) : 0,
                    'last_staff_name' => $row->last_staff_name ?? '',
                    'branch' => $row->branch ?? '',
                    'completed_visits_count' => $completedCount,
                ];
            }
        }

        // Priority 2: package_renewal (excluding due_for_refill)
        $activePackages = ClientPackage::query()
            ->withCount(['services as redeemed_services_count' => fn ($q) => $q->where('status', 'redeemed')])
            ->where('status', 'active')
            ->where('expires_at', '>=', now())
            ->where(function ($q) {
                $q->whereNotNull('sale_id')->orWhereNull('appointment_id');
            })
            ->get();

        $packageRenewalMap = [];
        $packageRenewalDetails = [];
        $expiryWindowEnd = now()->addDays(21)->endOfDay();
        foreach ($activePackages as $pkg) {
            $cid = (int) $pkg->customer_id;
            if ($upcomingActiveCustomerIds->has($cid) || $cooldownCustomerIds->has($cid) || isset($dueForRefillMap[$cid])) {
                continue;
            }
            $remaining = max(0, (int) $pkg->quantity_included - (int) $pkg->redeemed_services_count);
            if ($remaining <= 1 || ($pkg->expires_at && $pkg->expires_at->lte($expiryWindowEnd))) {
                $packageRenewalMap[$cid] = true;
                if (!isset($packageRenewalDetails[$cid])) {
                    $packageRenewalDetails[$cid] = [
                        'remaining' => $remaining,
                        'combo_name' => $pkg->combo_name ?: 'Package',
                    ];
                }
            }
        }

        $bleedingVips = [];

        foreach ($allCustomers as $customer) {
            $cid = (int) $customer->id;
            if ($upcomingActiveCustomerIds->has($cid) || $cooldownCustomerIds->has($cid)) {
                continue;
            }
            // Strict priority de-duplication: skip if already in due_for_refill or package_renewal
            if (isset($dueForRefillMap[$cid]) || isset($packageRenewalMap[$cid])) {
                continue;
            }

            $stat = $completedStats->get($cid);
            $completedCount = $stat ? (int) $stat->completed_count : 0;
            $lastVisitAt = ($stat && $stat->last_visit) ? Carbon::parse($stat->last_visit) : null;
            $daysSinceLastVisit = $lastVisitAt
                ? (int) floor(($todayStart->timestamp - $lastVisitAt->copy()->startOfDay()->timestamp) / 86400)
                : null;

            $netLtv = $salesLtv->has($cid)
                ? (float) $salesLtv->get($cid)
                : ($stat ? (float) $stat->price_sum : 0.0);

            // Priority 3: bleeding_vips (2+ completed visits or Net LTV >= 500 QAR, > 45 days ago)
            if (($completedCount >= 2 || $netLtv >= 500) && $daysSinceLastVisit !== null && $daysSinceLastVisit > 45) {
                $bleedingVips[] = $cid;
            }
        }

        return [
            'all_count' => $allCustomers->count(),
            'due_for_refill' => array_keys($dueForRefillMap),
            'due_for_refill_details' => $dueForRefillDetails,
            'package_renewal' => array_keys($packageRenewalMap),
            'package_renewal_details' => $packageRenewalDetails,
            'bleeding_vips' => array_values(array_unique($bleedingVips)),
            'upcoming_active_ids' => $upcomingActiveCustomerIds->keys()->all(),
            'cooldown_ids' => $cooldownCustomerIds->keys()->all(),
        ];
    }

    /**
     * Redirect old Beauty Planning bookmarks directly to the unified Due for Refill tab.
     */
    public function beautyPlanning()
    {
        return redirect()->route('customers.index', ['segment' => 'due_for_refill']);
    }

    public function show(Customer $customer)
    {
        $customer->loadCount([
            'appointments' => fn ($q) => $q->where('status', 'completed'),
        ]);

        $appointments = $customer->appointments()
            ->with(['staff', 'appointmentServices.service.category'])
            ->orderByDesc('appointment_datetime')
            ->get();

        $upcoming = $appointments->filter(fn($a) => $a->appointment_datetime->isFuture() && $a->status === 'pending')->sortBy('appointment_datetime');
        $past = $appointments->filter(fn($a) => !$a->appointment_datetime->isFuture() || $a->status !== 'pending');

        $lifetimeValue = round((float) Sale::where('customer_id', $customer->id)
            ->get(['services_total', 'products_total', 'packages_total', 'discount_amount'])
            ->sum(fn ($s) => max(0, (float) $s->services_total + (float) $s->products_total + (float) $s->packages_total - (float) $s->discount_amount)), 2);

        $completedAppointments = $appointments->where('status', 'completed');

        $favoriteServices = $completedAppointments
            ->flatMap(fn($a) => array_map('trim', explode(',', (string) $a->service_name)))
            ->filter(fn($name) => $name !== '' && strcasecmp($name, 'Decide in Salon') !== 0)
            ->countBy()
            ->sortDesc()
            ->take(5);

        $loyaltyHistory = $customer->loyaltyTransactions()->take(10)->get();

        $maintenanceSchedule = (new ClientMaintenancePlanner())->scheduleForCustomer($customer);

        // Reconcile against real time before displaying, so an expired
        // package never shows stale "pending" services here.
        ClientPackage::expireDue($customer->id);
        $clientPackages = $customer->clientPackages()->with('combo.services', 'services', 'redeemedServices')->get();

        // ---- Hormozi LTV & Ascension Playbook Intelligence ----
        $catalogServices = Service::with('category')->get();
        $catalogByName = $catalogServices->keyBy(fn ($s) => strtolower(trim((string) $s->name)));
        $catalogById = $catalogServices->keyBy('id');

        // Count completed visits per service (by canonical service name)
        $completedServiceCounts = [];
        $capturedCategoryIds = [];
        $capturedCategoryNames = [];

        foreach ($completedAppointments as $appt) {
            $perApptServiceNames = [];

            foreach ($appt->appointmentServices as $line) {
                $svcModel = $line->service_id ? $catalogById->get((int) $line->service_id) : null;
                $lineName = trim((string) ($svcModel?->name ?: $line->name));
                if ($lineName !== '' && strcasecmp($lineName, 'Decide in Salon') !== 0) {
                    $perApptServiceNames[strtolower($lineName)] = $lineName;
                }
                if (!$svcModel && $lineName !== '') {
                    $svcModel = $catalogByName->get(strtolower($lineName));
                }
                if ($svcModel && $svcModel->category_id) {
                    $capturedCategoryIds[] = (int) $svcModel->category_id;
                    if ($svcModel->category) {
                        $capturedCategoryNames[] = strtolower(trim((string) $svcModel->category->name));
                    }
                }
            }

            $flatNames = array_filter(array_map('trim', explode(',', (string) $appt->service_name)));
            foreach ($flatNames as $flatName) {
                if ($flatName === '' || strcasecmp($flatName, 'Decide in Salon') === 0) {
                    continue;
                }
                $key = strtolower($flatName);
                $svcModel = $catalogByName->get($key);
                $canonicalName = $svcModel ? $svcModel->name : $flatName;
                $perApptServiceNames[strtolower($canonicalName)] = $canonicalName;

                if ($svcModel && $svcModel->category_id) {
                    $capturedCategoryIds[] = (int) $svcModel->category_id;
                    if ($svcModel->category) {
                        $capturedCategoryNames[] = strtolower(trim((string) $svcModel->category->name));
                    }
                }
            }

            foreach ($perApptServiceNames as $lowerKey => $canonical) {
                if (!isset($completedServiceCounts[$lowerKey])) {
                    $completedServiceCounts[$lowerKey] = ['name' => $canonical, 'count' => 0];
                }
                $completedServiceCounts[$lowerKey]['count']++;
            }
        }

        // Determine services already covered by an active ClientPackage with remaining sessions
        $coveredServiceKeys = [];
        foreach ($clientPackages as $pkg) {
            if ($pkg->status === 'active' && !$pkg->is_expired && $pkg->remaining_count > 0) {
                $redeemedIds = $pkg->redeemedServices->pluck('service_id')->map(fn ($id) => (int) $id)->all();
                if ($pkg->combo && $pkg->combo->services) {
                    foreach ($pkg->combo->services as $cSvc) {
                        if (!in_array((int) $cSvc->id, $redeemedIds, true)) {
                            $coveredServiceKeys[strtolower(trim((string) $cSvc->name))] = true;
                        }
                    }
                }
                foreach ($pkg->services as $pSvc) {
                    if ($pSvc->status === 'pending') {
                        $coveredServiceKeys[strtolower(trim((string) $pSvc->service_name))] = true;
                    }
                }
            }
        }

        $activeCombos = Combo::where('status', 'active')->with('services')->get();
        $ascensionOpportunities = collect();

        foreach ($completedServiceCounts as $lowerKey => $info) {
            if ($info['count'] < 2 || isset($coveredServiceKeys[$lowerKey])) {
                continue;
            }

            foreach ($activeCombos as $combo) {
                $matchesService = $combo->services->contains(
                    fn ($s) => strtolower(trim((string) $s->name)) === $lowerKey
                );
                if ($matchesService && !$ascensionOpportunities->contains(fn ($o) => $o['combo_id'] === $combo->id)) {
                    $qty = (int) ($combo->quantity_included ?: $combo->services->count());
                    $ascensionOpportunities->push([
                        'combo_id' => $combo->id,
                        'combo_name' => $combo->name,
                        'combo_price' => (float) $combo->price,
                        'quantity_included' => $qty,
                        'validity_days' => (int) ($combo->validity_days ?: 7),
                        'matched_service' => $info['name'],
                        'completed_count' => $info['count'],
                        'included_services' => $combo->services->pluck('name')->values()->all(),
                        'pitch' => "Completed {$info['name']} {$info['count']}x without an active package — pitch \"{$combo->name}\" (" . number_format((float) $combo->price, 0) . " QAR · {$qty} sessions) on her next visit.",
                    ]);
                }
            }
        }

        // Uncaptured Department Cross-Sell
        $allCategories = ServiceCategory::orderBy('sort_order')->orderBy('name')->get();
        $capturedCategoryIds = array_values(array_unique($capturedCategoryIds));
        $capturedCategoryNames = array_values(array_unique($capturedCategoryNames));

        $uncapturedCategories = $allCategories->filter(function (ServiceCategory $cat) use ($capturedCategoryIds, $capturedCategoryNames) {
            return !in_array((int) $cat->id, $capturedCategoryIds, true)
                && !in_array(strtolower(trim((string) $cat->name)), $capturedCategoryNames, true);
        })->values();

        $uncapturedCategoryNames = $uncapturedCategories->pluck('name')->values()->all();
        $suggestedAddonService = null;
        if ($uncapturedCategories->isNotEmpty()) {
            $uncapturedIds = $uncapturedCategories->pluck('id')->all();
            $suggestedAddonService = $catalogServices
                ->whereIn('category_id', $uncapturedIds)
                ->sortBy('duration')
                ->first();
        }

        $lastCompletedVisit = $completedAppointments->first();
        $favoriteServiceName = $lastCompletedVisit?->service_name ?: ($favoriteServices->keys()->first() ?? null);
        $favoriteServiceNames = $favoriteServiceName
            ? array_values(array_filter(array_map('trim', explode(',', (string) $favoriteServiceName)), fn ($s) => $s !== '' && strcasecmp($s, 'Decide in Salon') !== 0))
            : [];
        $favoriteServiceIds = !empty($favoriteServiceNames)
            ? $catalogServices->filter(fn ($s) => in_array(strtolower(trim((string) $s->name)), array_map('strtolower', $favoriteServiceNames), true))->pluck('id')->values()->all()
            : [];

        $rebookFavoriteParams = array_filter([
            'open_booking' => 1,
            'customer_id' => $customer->id,
            'phone' => $customer->phone,
            'name' => $customer->name,
            'branch' => $lastCompletedVisit?->branch,
            'staff_id' => $lastCompletedVisit?->staff_id,
            'service_name' => $favoriteServiceName,
            'services' => !empty($favoriteServiceNames) ? implode(',', $favoriteServiceNames) : null,
            'service_ids' => !empty($favoriteServiceIds) ? implode(',', $favoriteServiceIds) : null,
        ], fn ($v) => $v !== null && $v !== '');

        return view('customers.show', compact(
            'customer',
            'appointments',
            'upcoming',
            'past',
            'lifetimeValue',
            'favoriteServices',
            'loyaltyHistory',
            'maintenanceSchedule',
            'clientPackages',
            'ascensionOpportunities',
            'uncapturedCategories',
            'uncapturedCategoryNames',
            'suggestedAddonService',
            'lastCompletedVisit',
            'rebookFavoriteParams'
        ));
    }

    /**
     * POST /customers/sync-retention-leads
     * Syncs all 3 retention tabs (Due for Refill, Packages Ending, Missing VIPs)
     * into the Daily Action Queue with short, front-loaded tags in customer_remarks:
     * - [Refill Due] {days}d overdue
     * - [Package Ending] {remaining} left
     * - [Missing VIP] {days}d away · {ltv} QAR
     */
    public function syncRetentionToLeads(Request $request)
    {
        $this->normalizeLegacyRetentionRemarks();

        $plannerQueue = (new ClientMaintenancePlanner())->dueQueue();
        $overdueRowsByCustomer = $plannerQueue
            ->where('urgency', 'overdue')
            ->groupBy('customer_id');

        $segments = $this->computeRetentionSegments();
        $dueForRefillIds = array_map('intval', $segments['due_for_refill'] ?? []);
        $packageRenewalIds = array_map('intval', $segments['package_renewal'] ?? []);
        $bleedingVipIds = array_map('intval', $segments['bleeding_vips'] ?? []);
        $dueDetailsMap = $segments['due_for_refill_details'] ?? [];
        $packageDetailsMap = $segments['package_renewal_details'] ?? [];
        $upcomingActiveLookup = array_flip($segments['upcoming_active_ids'] ?? []);
        $cooldownLookup = array_flip($segments['cooldown_ids'] ?? []);

        $targetCustomerIds = array_values(array_unique(array_merge(
            $overdueRowsByCustomer->keys()->map(fn ($id) => (int) $id)->all(),
            $dueForRefillIds,
            $packageRenewalIds,
            $bleedingVipIds
        )));

        $targetCustomerIds = array_values(array_filter(
            $targetCustomerIds,
            fn (int $cid) => !isset($upcomingActiveLookup[$cid]) && !isset($cooldownLookup[$cid])
        ));

        $customers = Customer::whereIn('id', $targetCustomerIds)->get()->keyBy('id');
        $lastCompletedByCustomer = Appointment::whereIn('customer_id', $targetCustomerIds)
            ->where('status', 'completed')
            ->orderByDesc('appointment_datetime')
            ->get(['customer_id', 'service_name', 'appointment_datetime', 'price'])
            ->groupBy('customer_id');

        $salesLtv = Sale::whereIn('customer_id', $targetCustomerIds)
            ->get(['customer_id', 'services_total', 'products_total', 'packages_total', 'discount_amount'])
            ->groupBy('customer_id')
            ->map(fn ($sales) => round((float) $sales->sum(fn ($s) => max(0, (float) $s->services_total + (float) $s->products_total + (float) $s->packages_total - (float) $s->discount_amount)), 2));

        $dueForRefillLookup = array_flip($dueForRefillIds);
        $packageRenewalLookup = array_flip($packageRenewalIds);
        $bleedingLookup = array_flip($bleedingVipIds);
        $pushedCount = 0;
        $today = today();
        $todayStart = now()->startOfDay();

        foreach ($targetCustomerIds as $cid) {
            try {
                $customer = $customers->get($cid);
                if (!$customer) {
                    continue;
                }

                $normalizedPhone = Customer::normalizePhone($customer->phone);
                if ($normalizedPhone === '') {
                    continue;
                }

                $overdueRows = $overdueRowsByCustomer->get($cid, collect());
                $dueDetail = $dueDetailsMap[$cid] ?? null;
                $pkgDetail = $packageDetailsMap[$cid] ?? null;

                $dueServices = $overdueRows->pluck('service_name')
                    ->map(fn ($s) => trim((string) $s))
                    ->filter()
                    ->unique()
                    ->values()
                    ->all();

                if (empty($dueServices) && !empty($dueDetail['service_name'])) {
                    $dueServices = [trim((string) $dueDetail['service_name'])];
                }

                if (empty($dueServices) && !empty($pkgDetail['combo_name'])) {
                    $dueServices = [trim((string) $pkgDetail['combo_name'])];
                }

                $clientCompleted = $lastCompletedByCustomer->get($cid, collect());
                if (empty($dueServices)) {
                    $favServices = $clientCompleted
                        ->flatMap(fn ($a) => array_map('trim', explode(',', (string) $a->service_name)))
                        ->filter(fn ($n) => $n !== '' && strcasecmp($n, 'Decide in Salon') !== 0)
                        ->countBy()
                        ->sortDesc();

                    $dueServices = $favServices->isNotEmpty()
                        ? [$favServices->keys()->first()]
                        : ['Signature Salon Service'];
                }

                if (isset($dueForRefillLookup[$cid]) || ($overdueRows->isNotEmpty() && !isset($bleedingLookup[$cid]) && !isset($packageRenewalLookup[$cid]))) {
                    $daysOverdue = (int) ($dueDetail['days_overdue'] ?? ($overdueRows->isNotEmpty() ? abs((int) $overdueRows->min('days_until')) : 0));
                    if ($daysOverdue > 0) {
                        $radarNote = "[Refill Due] {$daysOverdue}d overdue";
                    } else {
                        $daysUntil = max(0, (int) ($dueDetail['days_until'] ?? 0));
                        $radarNote = $daysUntil === 0 ? '[Refill Due] due today' : "[Refill Due] due in {$daysUntil}d";
                    }
                } elseif (isset($packageRenewalLookup[$cid])) {
                    $rem = (int) ($pkgDetail['remaining'] ?? 1);
                    $sessionText = $rem === 1 ? '1 session left' : "{$rem} left";
                    $radarNote = "[Package Ending] {$sessionText}";
                } else {
                    $lastVisitAt = $clientCompleted->first()?->appointment_datetime
                        ? Carbon::parse($clientCompleted->first()->appointment_datetime)
                        : null;
                    $daysAway = $lastVisitAt
                        ? max(46, (int) floor(($todayStart->timestamp - $lastVisitAt->copy()->startOfDay()->timestamp) / 86400))
                        : 46;
                    $netLtv = $salesLtv->has($cid)
                        ? (float) $salesLtv->get($cid)
                        : (float) $clientCompleted->sum(fn ($a) => (float) ($a->price ?? 0));
                    $ltvFormatted = number_format($netLtv, 0, '.', '');
                    $radarNote = "[Missing VIP] {$daysAway}d away · {$ltvFormatted} QAR";
                }

                $openLead = Lead::findOpenLeadByPhone($normalizedPhone, $customer->id);

                if ($openLead) {
                    $alreadyScheduledForToday = $openLead->next_followup_date && $openLead->next_followup_date->isSameDay($today);
                    $existingServices = is_array($openLead->service_interest) ? $openLead->service_interest : [];
                    $mergedServices = array_values(array_unique(array_filter(array_map('trim', array_merge($existingServices, $dueServices)))));
                    $mergedCategories = $openLead->mergeCategories('followup');

                    $existingRemarks = $this->convertLegacyRemarkString(trim((string) $openLead->customer_remarks), $customer->id);
                    $newRemarks = $this->upsertRetentionTagInRemarks($existingRemarks, $radarNote);

                    // Do not overwrite an existing open lead that already has a follow-up scheduled for today
                    $nextFollowup = $alreadyScheduledForToday
                        ? $openLead->next_followup_date
                        : $today;

                    $openLead->update([
                        'customer_id' => $openLead->customer_id ?: $customer->id,
                        'phone' => $openLead->phone ?: $normalizedPhone,
                        'category' => Lead::resolvePrimaryCategory($mergedCategories) ?: 'follow_up',
                        'categories' => $mergedCategories,
                        'service_interest' => !empty($mergedServices) ? $mergedServices : $dueServices,
                        'next_followup_date' => $nextFollowup,
                        'needful_done' => null,
                        'customer_remarks' => $newRemarks,
                    ]);
                    $openLead->touch();
                } else {
                    Lead::create([
                        'phone' => $normalizedPhone,
                        'customer_id' => $customer->id,
                        'category' => 'follow_up',
                        'categories' => ['follow_up'],
                        'service_interest' => $dueServices,
                        'next_followup_date' => $today,
                        'customer_remarks' => $radarNote,
                    ]);
                }

                $customer->update(['last_retention_contacted_at' => now()]);
                $pushedCount++;
            } catch (\Throwable $e) {
                Log::warning('CustomerController::syncRetentionToLeads row error for customer #' . $cid . ': ' . $e->getMessage());
            }
        }

        $message = "Pushed {$pushedCount} high-value retention lead" . ($pushedCount === 1 ? '' : 's') . " into the Daily Action Queue.";

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'pushed_count' => $pushedCount,
                'message' => $message,
            ]);
        }

        return back()->with('success', $message);
    }

    /**
     * Normalize any existing leads rows that still have legacy [Retention Radar] remarks
     * into the short [Refill Due] and [Missing VIP] tags.
     */
    public function normalizeLegacyRetentionRemarks(): void
    {
        $legacyLeads = Lead::where('customer_remarks', 'like', '%[Retention Radar]%')->get();
        foreach ($legacyLeads as $lead) {
            $cleaned = $this->convertLegacyRemarkString((string) $lead->customer_remarks, $lead->customer_id);
            if ($cleaned !== (string) $lead->customer_remarks) {
                $lead->update(['customer_remarks' => $cleaned]);
            }
        }
    }

    private function convertLegacyRemarkString(string $remarks, ?int $customerId = null): string
    {
        if (!str_contains($remarks, '[Retention Radar]')) {
            return $remarks;
        }

        return (string) preg_replace_callback(
            '/\[Retention Radar\]\s*([^|]+)/i',
            function (array $matches) use ($customerId) {
                $body = trim($matches[1]);
                if (preg_match('/Overdue\s+(\d+d)/i', $body, $m)) {
                    return "[Refill Due] {$m[1]} overdue";
                }
                if (stripos($body, 'Slipping VIP') !== false) {
                    if ($customerId) {
                        $lastAppt = Appointment::where('customer_id', $customerId)
                            ->where('status', 'completed')
                            ->orderByDesc('appointment_datetime')
                            ->first(['appointment_datetime']);
                        $daysAway = ($lastAppt && $lastAppt->appointment_datetime)
                            ? max(46, (int) floor((now()->startOfDay()->timestamp - Carbon::parse($lastAppt->appointment_datetime)->startOfDay()->timestamp) / 86400))
                            : 46;
                        $ltv = round((float) Sale::where('customer_id', $customerId)
                            ->get(['services_total', 'products_total', 'packages_total', 'discount_amount'])
                            ->sum(fn ($s) => max(0, (float) $s->services_total + (float) $s->products_total + (float) $s->packages_total - (float) $s->discount_amount)), 0);
                        if ($ltv <= 0) {
                            $ltv = round((float) Appointment::where('customer_id', $customerId)->where('status', 'completed')->sum('price'), 0);
                        }
                        return "[Missing VIP] {$daysAway}d away · " . number_format($ltv, 0, '.', '') . ' QAR';
                    }
                    return '[Missing VIP] 46d away';
                }

                return '[Refill Due] ' . $body;
            },
            $remarks
        );
    }

    /**
     * Replace any stale [Retention Radar], [Refill Due], [Package Ending], or [Missing VIP]
     * segment in customer_remarks cleanly while preserving active human notes.
     */
    private function upsertRetentionTagInRemarks(string $remarks, string $newTag): string
    {
        $segments = array_map('trim', explode('|', $remarks));
        $preserved = [];

        foreach ($segments as $segment) {
            if ($segment === '') {
                continue;
            }
            if (preg_match('/^\[(?:Retention Radar|Refill Due|Package Ending|Missing VIP)\]/i', $segment)) {
                continue;
            }
            $stripped = trim((string) preg_replace('/\[(?:Retention Radar|Refill Due|Package Ending|Missing VIP)\][^|]*/i', '', $segment));
            if ($stripped !== '' && !in_array($stripped, $preserved, true)) {
                $preserved[] = $stripped;
            }
        }

        $preserved[] = $newTag;

        return implode(' | ', $preserved);
    }

    public function store(Request $request)
    {
        if ($request->filled('phone')) {
            $normalizedPhone = Customer::normalizePhone($request->input('phone'));
            if ($normalizedPhone !== '') {
                $request->merge(['phone' => $normalizedPhone]);
            }
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
        ]);

        $rawDigits = preg_replace('/\D+/', '', (string) $validated['phone']);
        $phoneCandidates = array_values(array_unique(array_filter([$validated['phone'], $rawDigits])));

        $customer = Customer::whereIn('phone', $phoneCandidates)->first();
        if ($customer) {
            $customer->update([
                'name' => $validated['name'],
                'phone' => $validated['phone'],
                'email' => $validated['email'] ?? $customer->email,
            ]);
        } else {
            $customer = Customer::create($validated);
        }

        if ($request->wantsJson()) {
            return response()->json(['success' => true, 'customer' => $customer]);
        }

        return redirect()->route('customers.show', $customer)->with('success', 'Client profile saved.');
    }

    public function update(Request $request, Customer $customer)
    {
        if ($request->filled('phone')) {
            $normalizedPhone = Customer::normalizePhone($request->input('phone'));
            if ($normalizedPhone !== '') {
                $request->merge(['phone' => $normalizedPhone]);
            }
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:255', Rule::unique('customers', 'phone')->ignore($customer->id)],
        ]);

        $customer->update($validated);

        // Propagate updated client name and normalized phone to linked Appointments, Leads, and Complaints
        Appointment::where('customer_id', $customer->id)->update([
            'customer_name' => $customer->name,
            'phone' => $customer->phone,
        ]);

        Lead::where('customer_id', $customer->id)->update([
            'phone' => $customer->phone,
        ]);

        StaffComplaint::where('customer_id', $customer->id)->update([
            'customer_name' => $customer->name,
            'customer_phone' => $customer->phone,
        ]);

        return back()->with('success', 'Client profile updated successfully.');
    }

    public function destroy(Customer $customer)
    {
        if ($customer->appointments()->exists() || $customer->sales()->exists()) {
            return back()->with('error', 'Cannot delete a client with existing appointments or sales history.');
        }

        $customer->delete();

        return redirect()->route('customers.index')->with('success', 'Client deleted successfully.');
    }

    public function updateNotes(Request $request, Customer $customer)
    {
        $request->validate(['notes' => 'nullable|string|max:5000']);
        $customer->update(['notes' => $request->notes]);

        if ($request->wantsJson()) {
            return response()->json(['success' => true]);
        }

        return back()->with('success', 'Client notes updated.');
    }

    public function updateAllergies(Request $request, Customer $customer)
    {
        $request->validate(['allergies' => 'nullable|string|max:2000']);
        $customer->update(['allergies' => $request->allergies]);

        if ($request->wantsJson()) {
            return response()->json(['success' => true]);
        }

        return back()->with('success', 'Allergy information updated.');
    }

    public function redeemPoints(Request $request, Customer $customer)
    {
        $request->validate([
            'points' => 'required|integer|min:1|max:' . max($customer->loyalty_points, 1),
            'reward' => 'required|string|max:255',
        ]);

        try {
            $customer->redeemPoints((int) $request->points, $request->reward, auth()->id());
        } catch (\InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', "Redeemed {$request->points} points for {$request->reward}.");
    }

    /**
     * Live client search for the booking drawer's "Select a client" panel.
     * Matches by name or phone, returns a short list for a picker UI.
     */
    public function search(Request $request)
    {
        $q = trim((string) $request->query('q', ''));

        $query = Customer::query();

        if ($q !== '') {
            $digits = preg_replace('/\D/', '', $q);
            $query->where(function ($sub) use ($q, $digits) {
                $sub->where('name', 'like', '%' . $q . '%');
                if ($digits !== '') {
                    $sub->orWhere('phone', 'like', '%' . $digits . '%');
                }
            });
        }

        $customers = $query->orderBy('name')->limit(15)->get(['id', 'name', 'phone']);

        return response()->json($customers->map(fn($c) => [
            'id' => $c->id,
            'name' => $c->name ?: 'Unnamed',
            'phone' => $c->phone,
            'initials' => $c->name ? strtoupper(substr($c->name, 0, 1)) : '?',
        ]));
    }

    /**
     * This client's still-redeemable combo package services, for the
     * booking drawer's "Redeem Package Service" picker - fetched once a
     * client is selected so staff can apply what's left from an earlier
     * purchase directly onto a new appointment.
     */
    public function pendingPackages(Customer $customer)
    {
        return response()->json($customer->redeemablePackageServices());
    }

    /**
     * Quick phone lookup used by the booking modal to auto-fill returning customers.
     */
    public function lookup(Request $request)
    {
        $phone = preg_replace('/\D/', '', $request->query('phone', ''));

        if (strlen($phone) < 4) {
            return response()->json(['found' => false]);
        }

        $customer = Customer::where('phone', 'like', '%' . $phone . '%')->first();

        if (!$customer) {
            return response()->json(['found' => false]);
        }

        $visitCount = $customer->appointments()->where('status', 'completed')->count();
        $lastVisit = $customer->appointments()
            ->where('status', 'completed')
            ->orderByDesc('appointment_datetime')
            ->value('appointment_datetime');

        return response()->json([
            'found' => true,
            'id' => $customer->id,
            'name' => $customer->name,
            'phone' => $customer->phone,
            'email' => $customer->email,
            'visit_count' => $visitCount,
            'last_visit' => $lastVisit ? \Illuminate\Support\Carbon::parse($lastVisit)->format('d M Y') : null,
            'profile_url' => route('customers.show', $customer->id),
        ]);
    }
}
