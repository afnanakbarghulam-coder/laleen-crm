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
use Illuminate\Validation\Rule;

class CustomerController extends Controller
{
    public function index(Request $request)
    {
        $segments = $this->computeRetentionSegments();
        $activeSegment = strtolower(trim((string) $request->query('segment', 'all')));
        if (!in_array($activeSegment, ['all', 'second_visit_zone', 'bleeding_vips', 'package_renewal', 'idle_points'], true)) {
            $activeSegment = 'all';
        }

        $query = Customer::query()->withCount([
            'appointments' => fn ($q) => $q->where('status', 'completed'),
        ]);

        if ($activeSegment !== 'all') {
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

        $customers = $query->orderBy('name')->paginate(20)->withQueryString();

        // LTV + last visit, computed in bulk to avoid N+1 per-row queries.
        $customerIds = $customers->pluck('id');

        $ltv = Sale::whereIn('customer_id', $customerIds)
            ->get(['customer_id', 'services_total', 'products_total', 'packages_total', 'discount_amount'])
            ->groupBy('customer_id')
            ->map(fn ($sales) => round((float) $sales->sum(fn ($s) => max(0, (float) $s->services_total + (float) $s->products_total + (float) $s->packages_total - (float) $s->discount_amount)), 2));

        $lastVisit = Appointment::whereIn('customer_id', $customerIds)
            ->where('status', 'completed')
            ->selectRaw('customer_id, MAX(appointment_datetime) as last_visit')
            ->groupBy('customer_id')
            ->pluck('last_visit', 'customer_id');

        $dueCustomerIds = (new ClientMaintenancePlanner())->dueQueue()->pluck('customer_id')->unique();

        $segmentCounts = [
            'all' => $segments['all_count'],
            'second_visit_zone' => count($segments['second_visit_zone']),
            'bleeding_vips' => count($segments['bleeding_vips']),
            'package_renewal' => count($segments['package_renewal']),
            'idle_points' => count($segments['idle_points']),
        ];

        $secondVisitLookup = array_flip($segments['second_visit_zone']);
        $bleedingVipsLookup = array_flip($segments['bleeding_vips']);
        $packageRenewalLookup = array_flip($segments['package_renewal']);
        $idlePointsLookup = array_flip($segments['idle_points']);

        $customerTiers = [];
        foreach ($customers as $c) {
            $cid = (int) $c->id;
            $cLtv = (float) ($ltv[$cid] ?? 0);
            $isVip = ((int) $c->appointments_count >= 2) || ($cLtv >= 500);
            $badges = [];

            if ($isVip) {
                $badges[] = [
                    'label' => '👑 VIP',
                    'class' => 'status-pill-vip',
                    'tooltip' => '2+ completed visits or 500+ QAR spent.',
                ];
            }
            if (isset($bleedingVipsLookup[$cid])) {
                $badges[] = [
                    'label' => '⚠️ 45d+ Away',
                    'class' => 'status-pill-slipping',
                    'tooltip' => 'No visit in over 45 days and no upcoming booking.',
                ];
            } elseif (isset($secondVisitLookup[$cid])) {
                $badges[] = [
                    'label' => '🔥 Needs 2nd Visit',
                    'class' => 'status-pill-second',
                    'tooltip' => 'Came once 14–60 days ago with no next booking.',
                ];
            } elseif (isset($packageRenewalLookup[$cid])) {
                $badges[] = [
                    'label' => '🎟️ Package Low',
                    'class' => 'status-pill-package',
                    'tooltip' => '1 session left or package expires within 3 weeks.',
                ];
            }

            // Cap at max 2 compact badges so the status cell never wraps or stacks
            $badges = array_slice($badges, 0, 2);

            $rawDigits = preg_replace('/\D+/', '', (string) $c->phone);
            if ($rawDigits !== '' && strlen($rawDigits) <= 8) {
                $rawDigits = '974' . $rawDigits;
            }

            $customerTiers[$cid] = [
                'is_vip' => $isVip,
                'badges' => $badges,
                'wa_phone' => $rawDigits,
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
     * Compute the 4 Hormozi Retention Cash Buckets across all salon clients.
     * Excludes clients who already have an upcoming active appointment on or after today.
     */
    public function computeRetentionSegments(): array
    {
        ClientPackage::expireDue();

        $todayStart = now()->startOfDay();
        $allCustomers = Customer::query()->get(['id', 'loyalty_points']);

        $upcomingActiveCustomerIds = Appointment::query()
            ->whereNotNull('customer_id')
            ->whereIn('status', ['pending', 'confirmed', 'scheduled', 'arrived', 'in_progress'])
            ->whereDate('appointment_datetime', '>=', $todayStart->toDateString())
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

        $activePackages = ClientPackage::query()
            ->withCount(['services as redeemed_services_count' => fn ($q) => $q->where('status', 'redeemed')])
            ->where('status', 'active')
            ->where('expires_at', '>=', now())
            ->where(function ($q) {
                $q->whereNotNull('sale_id')->orWhereNull('appointment_id');
            })
            ->get();

        $packageRenewalMap = [];
        $expiryWindowEnd = now()->addDays(21)->endOfDay();
        foreach ($activePackages as $pkg) {
            $cid = (int) $pkg->customer_id;
            if ($upcomingActiveCustomerIds->has($cid)) {
                continue;
            }
            $remaining = max(0, (int) $pkg->quantity_included - (int) $pkg->redeemed_services_count);
            if ($remaining <= 1 || ($pkg->expires_at && $pkg->expires_at->lte($expiryWindowEnd))) {
                $packageRenewalMap[$cid] = true;
            }
        }

        $secondVisitZone = [];
        $bleedingVips = [];
        $idlePoints = [];

        foreach ($allCustomers as $customer) {
            $cid = (int) $customer->id;
            if ($upcomingActiveCustomerIds->has($cid)) {
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

            // 1. 🔥 2nd-Visit Danger Zone: exactly 1 completed visit, 14 to 60 days ago, 0 upcoming active appointments
            if ($completedCount === 1 && $daysSinceLastVisit !== null && $daysSinceLastVisit >= 14 && $daysSinceLastVisit <= 60) {
                $secondVisitZone[] = $cid;
            }

            // 2. ⚠️ Slipping VIPs: 2+ completed visits (or Net LTV >= 500 QAR), last completed visit > 45 days ago, 0 upcoming active appointments
            if (($completedCount >= 2 || $netLtv >= 500) && $daysSinceLastVisit !== null && $daysSinceLastVisit > 45) {
                $bleedingVips[] = $cid;
            }

            // 3. 💎 Idle Loyalty Credit: loyalty_points >= 30 and no completed visit in the last 30 days (and 0 upcoming active appointments)
            if ((int) $customer->loyalty_points >= 30 && ($daysSinceLastVisit === null || $daysSinceLastVisit >= 30)) {
                $idlePoints[] = $cid;
            }
        }

        return [
            'all_count' => $allCustomers->count(),
            'second_visit_zone' => array_values(array_unique($secondVisitZone)),
            'bleeding_vips' => array_values(array_unique($bleedingVips)),
            'package_renewal' => array_keys($packageRenewalMap),
            'idle_points' => array_values(array_unique($idlePoints)),
            'upcoming_active_ids' => $upcomingActiveCustomerIds->keys()->all(),
        ];
    }

    /**
     * Beauty Planning: front-desk task queue of clients whose re-booking
     * window is overdue or due soon for a specific treatment, ready to
     * message right away.
     */
    public function beautyPlanning()
    {
        $queue = (new ClientMaintenancePlanner())->dueQueue();

        return view('customers.beauty_planning', compact('queue'));
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
        $rebookFavoriteParams = array_filter([
            'customer_id' => $customer->id,
            'phone' => $customer->phone,
            'name' => $customer->name,
            'branch' => $lastCompletedVisit?->branch,
            'staff_id' => $lastCompletedVisit?->staff_id,
            'service_name' => $lastCompletedVisit?->service_name ?: ($favoriteServices->keys()->first() ?? null),
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
     * Finds all currently Overdue clients from ClientMaintenancePlanner::dueQueue()
     * + bleeding_vips who have a valid phone number and 0 upcoming active appointments,
     * and creates or updates their single open Lead row in the Daily Action Queue.
     */
    public function syncRetentionToLeads(Request $request)
    {
        $plannerQueue = (new ClientMaintenancePlanner())->dueQueue();
        $overdueRowsByCustomer = $plannerQueue
            ->where('urgency', 'overdue')
            ->groupBy('customer_id');

        $segments = $this->computeRetentionSegments();
        $bleedingVipIds = $segments['bleeding_vips'] ?? [];
        $upcomingActiveLookup = array_flip($segments['upcoming_active_ids'] ?? []);

        $targetCustomerIds = array_values(array_unique(array_merge(
            $overdueRowsByCustomer->keys()->map(fn ($id) => (int) $id)->all(),
            array_map('intval', $bleedingVipIds)
        )));

        $targetCustomerIds = array_values(array_filter(
            $targetCustomerIds,
            fn (int $cid) => !isset($upcomingActiveLookup[$cid])
        ));

        $customers = Customer::whereIn('id', $targetCustomerIds)->get()->keyBy('id');
        $lastCompletedByCustomer = Appointment::whereIn('customer_id', $targetCustomerIds)
            ->where('status', 'completed')
            ->orderByDesc('appointment_datetime')
            ->get(['customer_id', 'service_name', 'appointment_datetime'])
            ->groupBy('customer_id');

        $bleedingLookup = array_flip(array_map('intval', $bleedingVipIds));
        $pushedCount = 0;
        $today = today();

        foreach ($targetCustomerIds as $cid) {
            $customer = $customers->get($cid);
            if (!$customer) {
                continue;
            }

            $normalizedPhone = Customer::normalizePhone($customer->phone);
            if ($normalizedPhone === '') {
                continue;
            }

            $overdueRows = $overdueRowsByCustomer->get($cid, collect());
            $dueServices = $overdueRows->pluck('service_name')
                ->map(fn ($s) => trim((string) $s))
                ->filter()
                ->unique()
                ->values()
                ->all();

            if (empty($dueServices)) {
                $clientCompleted = $lastCompletedByCustomer->get($cid, collect());
                $favServices = $clientCompleted
                    ->flatMap(fn ($a) => array_map('trim', explode(',', (string) $a->service_name)))
                    ->filter(fn ($n) => $n !== '' && strcasecmp($n, 'Decide in Salon') !== 0)
                    ->countBy()
                    ->sortDesc();

                $dueServices = $favServices->isNotEmpty()
                    ? [$favServices->keys()->first()]
                    : ['Signature Salon Service'];
            }

            $reasonParts = [];
            if ($overdueRows->isNotEmpty()) {
                $maxOverdueDays = abs((int) $overdueRows->min('days_until'));
                $reasonParts[] = 'Overdue ' . $maxOverdueDays . 'd for ' . implode(', ', $dueServices);
            }
            if (isset($bleedingLookup[$cid])) {
                $reasonParts[] = 'Slipping VIP (>45d since last visit)';
            }
            $radarNote = '[Retention Radar] ' . implode(' · ', $reasonParts ?: ['Due for ' . implode(', ', $dueServices)]);

            $openLead = Lead::findOpenLeadByPhone($normalizedPhone, $customer->id);

            if ($openLead) {
                $alreadyScheduledForToday = $openLead->next_followup_date && $openLead->next_followup_date->isSameDay($today);
                $existingServices = is_array($openLead->service_interest) ? $openLead->service_interest : [];
                $mergedServices = array_values(array_unique(array_filter(array_map('trim', array_merge($existingServices, $dueServices)))));
                $mergedCategories = $openLead->mergeCategories('followup');

                $existingRemarks = trim((string) $openLead->customer_remarks);
                $newRemarks = $existingRemarks;
                if (!str_contains($existingRemarks, '[Retention Radar]')) {
                    $newRemarks = $existingRemarks !== '' ? $existingRemarks . ' | ' . $radarNote : $radarNote;
                }

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
                    'customer_remarks' => $newRemarks,
                ]);
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

            $pushedCount++;
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
