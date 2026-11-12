<?php

namespace App\Http\Controllers;

use App\Models\AdLeadEntry;
use App\Models\Appointment;
use App\Models\Combo;
use App\Models\Customer;
use App\Models\Lead;
use App\Models\Service;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class LeadController extends Controller
{
    const PERIOD_LABELS = [
        'today' => 'Today',
        'yesterday' => 'Yesterday',
        'this_week' => 'This Week',
        'this_month' => 'This Month',
        'last_month' => 'Last Month',
        'all_time' => 'All Time',
    ];

    public function index(Request $request)
    {
        $query = Lead::query()->with(['agent', 'customer', 'appointment:id,status']);
        $alertFilter = $request->input('alert_filter');

        if ($alertFilter === 'overdue') {
            $query->whereNotNull('next_followup_date')
                ->whereDate('next_followup_date', '<', today())
                ->where(function ($q) {
                    $q->whereNull('needful_done')->orWhereNotIn('needful_done', Lead::CLOSED_STATUSES);
                })
                ->where(function ($q) {
                    $q->whereNull('category')->orWhere('category', '!=', 'cancel');
                });
        } elseif ($alertFilter === 'unscheduled') {
            $query->whereHasCategory(['no_show', 'cancel'])
                ->whereNull('next_followup_date')
                ->where(function ($q) {
                    $q->whereNull('needful_done')->orWhereNotIn('needful_done', Lead::CLOSED_STATUSES);
                });
        } elseif ($alertFilter === 'booked') {
            $query->where(function ($q) {
                $q->where(function ($sub) {
                    $sub->where('needful_done', 'booked')
                        ->where(function ($aq) {
                            $aq->whereNull('appointment_id')
                                ->orWhereHas('appointment', fn ($ap) => $ap->whereNotIn('status', ['cancelled', 'no_show']))
                                ->orWhereDoesntHave('appointment');
                        });
                })->orWhereHas('appointment', fn ($ap) => $ap->whereNotIn('status', ['cancelled', 'no_show']));
            });
        }

        if ($request->filled('category')) {
            $query->whereHasCategory((array) $request->category);
        }

        if ($request->filled('followup_date')) {
            $query->whereDate('next_followup_date', $request->followup_date);
        }

        if ($request->filled('phone_number')) {
            $normalizedPhone = Lead::normalizePhone($request->country_code, $request->phone_number);
            $query->where('phone', 'like', '%' . $normalizedPhone . '%');
        }

        if ($request->filled('start_date')) {
            $query->whereDate('created_at', '>=', $request->start_date);
        }

        if ($request->filled('end_date')) {
            $query->whereDate('created_at', '<=', $request->end_date);
        }

        if ($request->filled('agent_id')) {
            $query->where('assigned_agent_id', $request->agent_id);
        }

        // With no filters at all, keep the table to recent or recently synced/scheduled leads rather than ancient history.
        if (!$request->hasAny(['category', 'followup_date', 'phone_number', 'start_date', 'end_date', 'agent_id', 'alert_filter'])) {
            $query->where(function ($q) {
                $q->where('created_at', '>=', now()->subMonth())
                    ->orWhere('updated_at', '>=', now()->subMonth())
                    ->orWhereDate('next_followup_date', '>=', now()->subMonth()->toDateString());
            });
        }

        $leads = $query->orderBy('created_at', 'desc')->get();
        $agents = User::where('role', 'agent')->get();
        $services = Service::orderBy('name')->pluck('name');
        $serviceCombos = Combo::with('services:id,name')
            ->where(function ($q) {
                $q->whereNull('status')->orWhere('status', 'active');
            })
            ->orderBy('name')
            ->get();
        $overdueLeads = $this->overdueLeadsQuery()->orderBy('next_followup_date')->get();
        $unscheduledLeads = $this->unscheduledLeadsQuery()->orderByDesc('created_at')->get();
        $overdueCount = $overdueLeads->count();
        $unscheduledCount = $unscheduledLeads->count();
        $bookedCount = Lead::with('appointment:id,status')
            ->where(function ($q) {
                $q->where('needful_done', 'booked')->orWhereNotNull('appointment_id');
            })
            ->get()
            ->filter(fn (Lead $l) => $l->isBookedConversion())
            ->count();

        $morningBrief = $this->buildMorningMoneyBrief($overdueCount, $unscheduledCount);
        $dueTodayCount = $morningBrief['due_today_count'];
        $topMoneyLeads = $morningBrief['top_money_leads'];
        $topThreePotentialQar = $morningBrief['top_three_potential_qar'];
        $morningBriefText = $morningBrief['brief_text'];

        return view('leads.index', compact(
            'leads',
            'agents',
            'services',
            'serviceCombos',
            'overdueLeads',
            'unscheduledLeads',
            'overdueCount',
            'unscheduledCount',
            'dueTodayCount',
            'topMoneyLeads',
            'topThreePotentialQar',
            'morningBriefText',
            'morningBrief',
            'bookedCount',
            'alertFilter'
        ));
    }

    public function checkTodaysFollowUps(Request $request)
    {
        $today = today()->format('Y-m-d');

        $dueLeads = Lead::whereDate('next_followup_date', $today)
            ->where(function ($q) {
                $q->whereNull('needful_done')->orWhereNotIn('needful_done', Lead::CLOSED_STATUSES);
            })
            ->where(function ($q) {
                $q->whereNull('category')->orWhere('category', '!=', 'cancel');
            })
            ->get(['id', 'phone', 'next_followup_date']);

        return response()->json([
            'count' => $dueLeads->count(),
            'today' => $today,
            'leads' => $dueLeads,
        ]);
    }

    public function create()
    {
        //
    }

    public function store(Request $request)
    {
        if ($request->filled('category')) {
            $normalizedCat = Lead::normalizeCategories([$request->input('category')])[0] ?? $request->input('category');
            $request->merge(['category' => $normalizedCat]);
        }

        $request->validate([
            'country_code' => 'required|string|max:5',
            'phone_number' => 'required|string|max:20',
            'customer_name' => 'required|string|max:255',
            'assigned_agent_id' => 'nullable|exists:users,id',
            'category' => 'required|in:' . implode(',', array_keys(Lead::MANUAL_CATEGORIES)),
            'service_interest' => 'required|array|min:1',
            'service_interest.*' => 'string|max:255',
            'next_followup_date' => 'required|date',
            'customer_id' => 'nullable|exists:customers,id',
            'needful_done' => 'nullable|in:' . implode(',', array_keys(Lead::MANUAL_NEEDFUL_STATUSES)),
        ], [
            'needful_done.in' => 'Leads can only be marked as Booked automatically by creating a Calendar appointment.',
        ]);

        $normalizedPhone = Lead::normalizePhone($request->country_code, $request->phone_number);
        $customer = $this->resolveOrCreateCustomer($normalizedPhone, $request->customer_id, $request->customer_name);

        $existingLead = $this->findDuplicateLead($normalizedPhone, $request->category, $request->service_interest);

        if ($existingLead) {
            $existingServices = is_array($existingLead->service_interest) ? $existingLead->service_interest : [];
            $incomingServices = is_array($request->service_interest) ? $request->service_interest : [];
            $mergedServices = array_values(array_unique(array_filter(array_map('trim', array_merge($existingServices, $incomingServices)))));

            $existingRemarks = trim((string) $existingLead->customer_remarks);
            $incomingRemarks = trim((string) $request->customer_remarks);
            $mergedRemarks = $existingRemarks;
            if ($incomingRemarks !== '' && !str_contains($existingRemarks, $incomingRemarks)) {
                $mergedRemarks = $existingRemarks !== '' ? $existingRemarks . ' | ' . $incomingRemarks : $incomingRemarks;
            }

            $mergedCategories = $existingLead->mergeCategories($request->category);
            $primaryCategory = Lead::resolvePrimaryCategory($mergedCategories);

            $existingUpdatePayload = [
            'customer_id' => $existingLead->customer_id ?: $customer->id,
            'assigned_agent_id' => $request->assigned_agent_id ?: $existingLead->assigned_agent_id,
            'category' => $primaryCategory ?: $existingLead->category,
            'categories' => $mergedCategories,
            'customer_remarks' => $mergedRemarks !== '' ? $mergedRemarks : null,
            'service_interest' => !empty($mergedServices) ? $mergedServices : $existingLead->service_interest,
            'next_followup_date' => $request->next_followup_date ?: $existingLead->next_followup_date,
        ];
        if ($request->filled('source') && \Illuminate\Support\Facades\Schema::hasColumn('leads', 'source')) {
            $existingUpdatePayload['source'] = trim((string) $request->source);
        }
        $existingLead->update($existingUpdatePayload);

            $this->linkSameMonthAdLeadEntries($existingLead, $customer, $normalizedPhone);

            return redirect()->route('leads.index')
                ->with('existing_lead_id', $existingLead->id)
                ->with('warning', 'This client already has an open lead. Their services and details have been merged into the existing lead.');
        }

        $initialCategories = Lead::normalizeCategories([$request->category]);

        $createPayload = [
            'phone' => $normalizedPhone,
            'customer_id' => $customer->id,
            'assigned_agent_id' => $request->assigned_agent_id,
            'category' => Lead::resolvePrimaryCategory($initialCategories) ?? $request->category,
            'categories' => $initialCategories,
            'customer_remarks' => $request->customer_remarks,
            'service_interest' => $request->service_interest,
            'next_followup_date' => $request->next_followup_date,
        ];
        if ($request->filled('source') && \Illuminate\Support\Facades\Schema::hasColumn('leads', 'source')) {
            $createPayload['source'] = trim((string) $request->source);
        }

        $newLead = Lead::create($createPayload);

        $this->linkSameMonthAdLeadEntries($newLead, $customer, $normalizedPhone);

        return redirect()->back()->with('success', 'Lead added successfully.');
    }

    public function show(Lead $lead)
    {
        //
    }

    public function edit(Lead $lead)
    {
        //
    }

    public function update(Request $request, Lead $lead)
    {
        if ($request->filled('category')) {
            $normalizedCat = Lead::normalizeCategories([$request->input('category')])[0] ?? $request->input('category');
            $request->merge(['category' => $normalizedCat]);
        }
        if ($request->filled('categories') && is_array($request->input('categories'))) {
            $request->merge(['categories' => Lead::normalizeCategories($request->input('categories'))]);
        }

        $allowedCategories = array_unique(array_merge(
            array_keys(Lead::CATEGORIES),
            array_keys(Lead::MANUAL_CATEGORIES),
            $lead->categories_list
        ));

        $request->validate([
            'country_code' => 'required|string|max:5',
            'phone_number' => 'required|string|max:20',
            'customer_name' => 'required|string|max:255',
            'assigned_agent_id' => 'nullable|exists:users,id',
            'category' => 'required_without:categories|nullable|in:' . implode(',', $allowedCategories),
            'categories' => 'required_without:category|nullable|array|min:1',
            'categories.*' => 'string|in:' . implode(',', $allowedCategories),
            'service_interest' => 'required|array|min:1',
            'service_interest.*' => 'string|max:255',
            'next_followup_date' => 'required|date',
            'customer_id' => 'nullable|exists:customers,id',
            'needful_done' => 'nullable|in:' . implode(',', array_keys(Lead::MANUAL_NEEDFUL_STATUSES)),
        ], [
            'needful_done.in' => 'Leads can only be marked as Booked automatically by creating a Calendar appointment.',
        ]);

        $normalizedPhone = Lead::normalizePhone($request->country_code, $request->phone_number);
        $customer = $this->resolveOrCreateCustomer($normalizedPhone, $request->customer_id, $request->customer_name);

        if ($request->filled('categories') && is_array($request->categories)) {
            $updatedCategories = Lead::normalizeCategories($request->categories);
        } else {
            // Preserve any secondary badges already on the lead when only a single `category` field is submitted
            $preservedAuto = array_intersect($lead->categories_list, ['no_show', 'cancel']);
            $updatedCategories = Lead::normalizeCategories(array_merge($preservedAuto, [$request->category]));
        }
        $primaryCategory = Lead::resolvePrimaryCategory($updatedCategories);

        // needful_done is intentionally left untouched here - it's managed
        // exclusively via the inline dropdown on the leads list.
        $data = [
            'phone' => $normalizedPhone,
            'customer_id' => $customer->id,
            'assigned_agent_id' => $request->assigned_agent_id ?: null,
            'category' => $primaryCategory,
            'categories' => $updatedCategories,
            'customer_remarks' => $request->customer_remarks,
            'service_interest' => $request->service_interest,
            'next_followup_date' => $request->next_followup_date,
        ];
        if ($request->has('source') && \Illuminate\Support\Facades\Schema::hasColumn('leads', 'source')) {
            $data['source'] = $request->filled('source') ? trim((string) $request->source) : null;
        }

        $existingLead = $this->findDuplicateLead($normalizedPhone, $primaryCategory, $request->service_interest, $lead->id);

        // If another open lead already exists for this phone, merge into that one and remove the duplicate row.
        if ($existingLead) {
            $existingServices = is_array($existingLead->service_interest) ? $existingLead->service_interest : [];
            $incomingServices = is_array($request->service_interest) ? $request->service_interest : [];
            $mergedCats = $existingLead->mergeCategories($updatedCategories);

            $data['service_interest'] = array_values(array_unique(array_filter(array_map('trim', array_merge($existingServices, $incomingServices)))));
            $data['assigned_agent_id'] = $request->assigned_agent_id ?: $existingLead->assigned_agent_id;
            $data['categories'] = $mergedCats;
            $data['category'] = Lead::resolvePrimaryCategory($mergedCats);

            $existingLead->update($data);
            $mergedFromLeadId = $lead->id;
            $lead->delete();

            $this->linkSameMonthAdLeadEntries($existingLead, $customer, $normalizedPhone, $mergedFromLeadId);

            return redirect()->route('leads.index')
                ->with('existing_lead_id', $existingLead->id)
                ->with('success', 'Lead updated and merged successfully.');
        }

        $lead->update($data);
        $this->linkSameMonthAdLeadEntries($lead, $customer, $normalizedPhone);

        return redirect()->route('leads.index')
            ->with('success', 'Lead updated successfully.');
    }

    /**
     * Quick inline toggle from the leads table - no modal needed.
     */
    public function updateNeedfulDone(Request $request, Lead $lead)
    {
        if ($lead->needful_done === 'booked' && $lead->hasVerifiedConversionActivity()) {
            return response()->json([
                'message' => 'Leads can only be marked as Booked automatically by creating a Calendar appointment.',
                'errors' => [
                    'needful_done' => ['This lead is already verified as Booked via the Calendar and cannot be manually overwritten.'],
                ],
            ], 422);
        }

        $request->validate([
            'needful_done' => 'nullable|in:' . implode(',', array_keys(Lead::MANUAL_NEEDFUL_STATUSES)),
        ], [
            'needful_done.in' => 'Leads can only be marked as Booked automatically by creating a Calendar appointment.',
        ]);

        $wasBooked = $lead->needful_done === 'booked';
        $newStatus = $request->needful_done ?: null;
        $payload = ['needful_done' => $newStatus];
        if (in_array($newStatus, Lead::CLOSED_STATUSES, true)) {
            $payload['next_followup_date'] = null;
        } elseif ($wasBooked && !$lead->next_followup_date) {
            $payload['next_followup_date'] = now()->toDateString();
        }

        $lead->update($payload);

        return response()->json(['success' => true]);
    }

    public function destroy(Lead $lead)
    {
        $lead->delete();

        return redirect()->back()->with('success', 'Lead deleted successfully.');
    }

    /**
     * Leads Analytics: category breakdown, needful-done ("conversion") status,
     * and follow-up performance (overdue/upcoming/completed), scoped to a
     * chosen creation-date period - except follow-up performance, which is
     * always a live, all-time snapshot since "overdue" is a right-now concept.
     */
    public function analytics(Request $request)
    {
        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date');
        $isCustomRange = $request->filled('start_date') || $request->filled('end_date');

        if ($isCustomRange) {
            $from = $request->filled('start_date')
                ? Carbon::parse($startDate)->startOfDay()
                : (($earliest = Lead::min('created_at')) ? Carbon::parse($earliest)->startOfDay() : now()->startOfMonth());
            $to = $request->filled('end_date')
                ? Carbon::parse($endDate)->endOfDay()
                : now()->endOfDay();

            if ($from->gt($to)) {
                [$from, $to] = [$to->copy()->startOfDay(), $from->copy()->endOfDay()];
            }

            $period = 'custom';
            $startDate = $from->format('Y-m-d');
            $endDate = $to->format('Y-m-d');
        } else {
            $period = $request->filled('period') && array_key_exists($request->period, self::PERIOD_LABELS)
                ? $request->period
                : 'this_month';
            [$from, $to] = $this->resolvePeriod($period);
        }

        $leads = $this->leadsForAnalyticsPeriod($from, $to);

        $totalLeads = $leads->count();

        $categoryCounts = [];
        foreach (Lead::CATEGORIES as $key => $label) {
            $categoryCounts[$key] = $leads->filter(fn(Lead $l) => $l->hasCategory($key))->count();
        }
        $uncategorized = $leads->filter(fn(Lead $l) => empty($l->categories_list))->count();

        $categoryLabels = array_merge(array_values(Lead::CATEGORIES), ['Uncategorized']);
        $categorySeries = array_merge(array_values($categoryCounts), [$uncategorized]);

        $needfulCounts = [
            'booked' => $leads->filter(fn (Lead $l) => $l->isBookedConversion())->count(),
            'yes' => $leads->filter(fn (Lead $l) => !$l->isBookedConversion() && $l->needful_done === 'yes')->count(),
            'no' => $leads->filter(fn (Lead $l) => !$l->isBookedConversion() && $l->needful_done === 'no')->count(),
            'unset' => $leads->filter(fn (Lead $l) => !$l->isBookedConversion() && $l->needful_done === null)->count(),
        ];

        $bookedLeadsCount = $needfulCounts['booked'];
        $conversionRate = $totalLeads > 0 ? round(($bookedLeadsCount / $totalLeads) * 100, 1) : 0.0;

        $winBackPool = $leads->filter(fn(Lead $l) => $l->hasCategory(['cancel', 'no_show']));
        $winBackTotal = $winBackPool->count();
        $winBackBooked = $winBackPool->filter(fn (Lead $l) => $l->isBookedConversion())->count();
        $winBackRate = $winBackTotal > 0 ? round(($winBackBooked / $winBackTotal) * 100, 1) : 0.0;

        $today = today();
        $allLeads = Lead::with('appointment:id,status')->get();
        $overdueLeadsLive = $this->overdueLeadsQuery()->get();
        $followupPerformance = [
            'overdue' => $overdueLeadsLive->count(),
            'completed' => $allLeads->filter(fn (Lead $l) => $l->isBookedConversion())->count(),
            'upcoming' => $allLeads->filter(fn($l) => $l->next_followup_date && $l->next_followup_date->gte($today) && !$l->isBookedConversion() && !in_array($l->needful_done, Lead::CLOSED_STATUSES, true))->count(),
            'no_date' => $allLeads->whereNull('next_followup_date')->filter(fn($l) => !$l->isBookedConversion() && !in_array($l->needful_done, Lead::CLOSED_STATUSES, true))->count(),
        ];

        $agents = User::where('role', 'agent')->orderBy('name')->get();
        $overdueByAgent = $overdueLeadsLive->groupBy('assigned_agent_id')->map->count();

        $agentLeaderboard = $agents->map(function (User $agent) use ($leads, $overdueByAgent) {
            $agentLeads = $leads->where('assigned_agent_id', $agent->id);
            $assigned = $agentLeads->count();
            $booked = $agentLeads->filter(fn (Lead $l) => $l->isBookedConversion())->count();
            $contacted = $agentLeads->filter(fn (Lead $l) => !$l->isBookedConversion() && $l->needful_done === 'yes')->count();
            $rate = $assigned > 0 ? round(($booked / $assigned) * 100, 1) : 0.0;

            return [
                'id' => $agent->id,
                'name' => $agent->name,
                'assigned' => $assigned,
                'booked' => $booked,
                'contacted' => $contacted,
                'conversion_rate' => $rate,
                'overdue' => (int) ($overdueByAgent->get($agent->id, 0)),
            ];
        })
            ->sortByDesc(fn($row) => [$row['booked'], $row['conversion_rate'], $row['assigned']])
            ->values();

        $unassignedLeads = $leads->whereNull('assigned_agent_id');
        if ($unassignedLeads->isNotEmpty()) {
            $unassignedCount = $unassignedLeads->count();
            $unassignedBooked = $unassignedLeads->filter(fn (Lead $l) => $l->isBookedConversion())->count();
            $unassignedContacted = $unassignedLeads->filter(fn (Lead $l) => !$l->isBookedConversion() && $l->needful_done === 'yes')->count();
            $unassignedRate = $unassignedCount > 0 ? round(($unassignedBooked / $unassignedCount) * 100, 1) : 0.0;

            $agentLeaderboard->push([
                'id' => null,
                'name' => 'Unassigned / Front Desk',
                'assigned' => $unassignedCount,
                'booked' => $unassignedBooked,
                'contacted' => $unassignedContacted,
                'conversion_rate' => $unassignedRate,
                'overdue' => (int) ($overdueByAgent->get('', 0)),
            ]);
        }

        $aiDiagnosis = $this->buildAnalyticsDiagnosis($from, $to, $leads, $agentLeaderboard, $overdueLeadsLive);
        $overdueCount = (int) ($followupPerformance['overdue'] ?? 0);
        $openPipelineQar = (int) ($aiDiagnosis['metrics']['recoverable_qar'] ?? 0);

        return view('leads.analytics', compact(
            'period',
            'from',
            'to',
            'startDate',
            'endDate',
            'isCustomRange',
            'totalLeads',
            'categoryCounts',
            'categoryLabels',
            'categorySeries',
            'uncategorized',
            'needfulCounts',
            'bookedLeadsCount',
            'conversionRate',
            'winBackTotal',
            'winBackBooked',
            'winBackRate',
            'followupPerformance',
            'agentLeaderboard',
            'aiDiagnosis',
            'overdueCount',
            'openPipelineQar'
        ));
    }

    public function resolveDateRangeFromRequest(Request $request): array
    {
        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date');
        $isCustomRange = $request->filled('start_date') || $request->filled('end_date');

        if ($isCustomRange) {
            $from = $request->filled('start_date')
                ? Carbon::parse($startDate)->startOfDay()
                : (($earliest = Lead::min('created_at')) ? Carbon::parse($earliest)->startOfDay() : now()->startOfMonth());
            $to = $request->filled('end_date')
                ? Carbon::parse($endDate)->endOfDay()
                : now()->endOfDay();

            if ($from->gt($to)) {
                [$from, $to] = [$to->copy()->startOfDay(), $from->copy()->endOfDay()];
            }

            $period = 'custom';
            $startDate = $from->format('Y-m-d');
            $endDate = $to->format('Y-m-d');
        } else {
            $period = $request->filled('period') && array_key_exists($request->period, self::PERIOD_LABELS)
                ? $request->period
                : 'this_month';
            [$from, $to] = $this->resolvePeriod($period);
            $startDate = $from->format('Y-m-d');
            $endDate = $to->format('Y-m-d');
        }

        return [$period, $from, $to, $startDate, $endDate, $isCustomRange];
    }

    public static function buildServicePriceMap(): array
    {
        $map = [];
        foreach (Service::query()->get(['name', 'price']) as $service) {
            $key = strtolower(trim((string) $service->name));
            if ($key !== '') {
                $map[$key] = $service->price !== null && (float) $service->price > 0
                    ? (int) round((float) $service->price)
                    : 150;
            }
        }

        foreach (Combo::query()->get(['name', 'price']) as $combo) {
            $key = strtolower(trim((string) $combo->name));
            if ($key !== '') {
                $comboPrice = $combo->price !== null && (float) $combo->price > 0
                    ? (int) round((float) $combo->price)
                    : 250;
                $map[$key] = $comboPrice;
                $map['[combo] ' . $key] = $comboPrice;
            }
        }

        return $map;
    }

    public static function resolveServicePrice(string $serviceName, array $servicePriceMap): int
    {
        $norm = strtolower(trim($serviceName));
        if ($norm === '') {
            return 150;
        }

        if (isset($servicePriceMap[$norm])) {
            return $servicePriceMap[$norm];
        }

        foreach ($servicePriceMap as $nameKey => $price) {
            if (str_contains($nameKey, $norm) || str_contains($norm, $nameKey)) {
                return $price;
            }
        }

        return 150;
    }

    public static function estimateLeadQar(Lead $lead, array $servicePriceMap): int
    {
        $services = is_array($lead->service_interest) ? array_values(array_filter($lead->service_interest)) : [];
        if (empty($services)) {
            return 150;
        }

        $total = 0;
        foreach ($services as $srv) {
            $total += self::resolveServicePrice((string) $srv, $servicePriceMap);
        }

        return max(150, $total);
    }

    public function leadsForAnalyticsPeriod(Carbon $from, Carbon $to)
    {
        return Lead::with(['agent', 'customer', 'appointment:id,status'])
            ->where(function ($q) use ($from, $to) {
                $q->whereBetween('created_at', [$from, $to])
                    ->orWhere(function ($sub) use ($from, $to) {
                        $sub->whereBetween('updated_at', [$from, $to])
                            ->where(function ($rq) {
                                $rq->where('customer_remarks', 'like', '%[Refill Due]%')
                                    ->orWhere('customer_remarks', 'like', '%[Package Ending]%')
                                    ->orWhere('customer_remarks', 'like', '%[Missing VIP]%')
                                    ->orWhere('customer_remarks', 'like', '%[Retention Radar]%');
                            });
                    })
                    ->orWhere(function ($sub) use ($from, $to) {
                        $sub->whereBetween('next_followup_date', [$from->toDateString(), $to->toDateString()])
                            ->where(function ($rq) {
                                $rq->where('customer_remarks', 'like', '%[Refill Due]%')
                                    ->orWhere('customer_remarks', 'like', '%[Package Ending]%')
                                    ->orWhere('customer_remarks', 'like', '%[Missing VIP]%')
                                    ->orWhere('customer_remarks', 'like', '%[Retention Radar]%');
                            });
                    });
            })
            ->get();
    }

    public function buildMorningMoneyBrief(?int $overdueCount = null, ?int $unscheduledCount = null): array
    {
        $today = today();
        $openLeads = Lead::with('customer')
            ->whereNotNull('phone')
            ->where('phone', '!=', '')
            ->where('phone', '!=', '-')
            ->where(function ($q) {
                $q->whereNull('needful_done')->orWhereNotIn('needful_done', Lead::CLOSED_STATUSES);
            })
            ->get()
            ->filter(function (Lead $lead) {
                $digits = preg_replace('/\D+/', '', (string) $lead->phone);
                return strlen($digits) >= 7;
            })
            ->values();

        $dueTodayCount = $openLeads
            ->filter(fn(Lead $l) => $l->next_followup_date && $l->next_followup_date->isSameDay($today))
            ->count();

        if ($overdueCount === null) {
            $overdueCount = $this->overdueLeadsQuery()->count();
        }
        if ($unscheduledCount === null) {
            $unscheduledCount = $this->unscheduledLeadsQuery()->count();
        }

        $servicePriceMap = self::buildServicePriceMap();
        $returningCustomerIds = Appointment::where('status', 'completed')
            ->whereNotNull('customer_id')
            ->pluck('customer_id')
            ->unique()
            ->flip()
            ->all();

        $customersByPhone = Customer::whereNotNull('phone')
            ->where('phone', '!=', '')
            ->get(['id', 'name', 'phone'])
            ->keyBy(fn (Customer $c) => preg_replace('/\D+/', '', (string) $c->phone));

        $scoredLeads = $openLeads->map(function (Lead $lead) use ($servicePriceMap, $today, $returningCustomerIds, $customersByPhone) {
            $estimatedQar = self::estimateLeadQar($lead, $servicePriceMap);
            $bonus = 0;

            if ($lead->hasCategory(['cancel', 'no_show'])) {
                $bonus += 60;
            }
            if ($lead->next_followup_date && $lead->next_followup_date->lt($today)) {
                $bonus += 50;
            } elseif ($lead->next_followup_date && $lead->next_followup_date->isSameDay($today)) {
                $bonus += 40;
            }
            if ($lead->customer_id && isset($returningCustomerIds[$lead->customer_id])) {
                $bonus += 30;
            }

            $services = is_array($lead->service_interest) && count($lead->service_interest)
                ? implode(', ', array_slice($lead->service_interest, 0, 2))
                : ($lead->category ? (Lead::CATEGORIES[$lead->category] ?? $lead->category) : 'Salon Service');

            $categories = $lead->categories_list;
            $catLabel = !empty($categories)
                ? implode(' + ', array_map(fn($c) => Lead::CATEGORIES[$c] ?? $c, $categories))
                : 'Inquiry';

            $rawName = trim((string) ($lead->customer?->name ?? ''));
            $isPlaceholder = ($rawName === '' || in_array(strtolower($rawName), ['guest', 'unnamed', 'unnamed client', '-', '—'], true));
            if ($isPlaceholder) {
                $phoneDigits = preg_replace('/\D+/', '', (string) $lead->phone);
                $matchedCustomer = $phoneDigits !== '' ? $customersByPhone->get($phoneDigits) : null;
                $matchedName = trim((string) ($matchedCustomer?->name ?? ''));
                if ($matchedName !== '' && !in_array(strtolower($matchedName), ['guest', 'unnamed', 'unnamed client', '-', '—'], true)) {
                    $rawName = $matchedName;
                    $isPlaceholder = false;
                }
            }

            $clientName = !$isPlaceholder ? $rawName : trim((string) $lead->phone);

            return [
                'id' => $lead->id,
                'name' => $clientName,
                'phone' => $lead->phone,
                'services' => $services,
                'category_label' => $catLabel,
                'estimated_qar' => $estimatedQar,
                'priority_score' => $estimatedQar + $bonus,
            ];
        })
            ->sortByDesc(fn(array $item) => [$item['estimated_qar'], $item['priority_score'], $item['id']])
            ->values();

        $topMoneyLeads = $scoredLeads->take(3)->values();
        $topThreePotentialQar = (int) $topMoneyLeads->sum('estimated_qar');

        if ($topMoneyLeads->isNotEmpty()) {
            $callParts = [];
            foreach ($topMoneyLeads as $idx => $item) {
                $rank = $idx + 1;
                if ($item['name'] === $item['phone']) {
                    $callParts[] = "#{$rank} {$item['phone']} ({$item['services']}, ~{$item['estimated_qar']} QAR)";
                } else {
                    $callParts[] = "#{$rank} {$item['name']} ({$item['phone']} — {$item['services']}, ~{$item['estimated_qar']} QAR)";
                }
            }

            $count = $topMoneyLeads->count();
            $joinedCalls = count($callParts) > 1
                ? implode(', ', array_slice($callParts, 0, -1)) . ' and ' . end($callParts)
                : $callParts[0];
            $priorityWord = $count === 1 ? 'priority' : 'priorities';
            $briefText = "🧠 Today's Top Money Moves: Call {$joinedCalls}. Closing your top {$count} {$priorityWord} today recovers ~" . number_format($topThreePotentialQar) . " QAR.";
        } else {
            $briefText = "🧠 Today's Top Money Moves: All active leads are booked or closed—zero pending revenue sitting in the queue today.";
        }

        return [
            'due_today_count' => $dueTodayCount,
            'overdue_count' => $overdueCount,
            'unscheduled_count' => $unscheduledCount,
            'open_leads_count' => $openLeads->count(),
            'top_money_leads' => $topMoneyLeads->all(),
            'top_three_potential_qar' => $topThreePotentialQar,
            'brief_text' => $briefText,
        ];
    }

    public function buildAnalyticsDiagnosis(
        Carbon $from,
        Carbon $to,
        $leads = null,
        $agentLeaderboard = null,
        $overdueLeadsLive = null
    ): array {
        if ($leads === null) {
            $leads = $this->leadsForAnalyticsPeriod($from, $to);
        }
        if ($overdueLeadsLive === null) {
            $overdueLeadsLive = $this->overdueLeadsQuery()->get();
        }

        $servicePriceMap = self::buildServicePriceMap();
        $totalLeads = $leads->count();
        $unbookedLeads = $leads->filter(fn(Lead $l) => !$l->isBookedConversion())->values();
        $unbookedCount = $unbookedLeads->count();

        // 1. Service Bottleneck Math
        $serviceUnbookedCounts = [];
        foreach ($unbookedLeads as $lead) {
            $services = is_array($lead->service_interest) ? array_filter($lead->service_interest) : [];
            if (empty($services)) {
                $services = ['General Inquiry'];
            }
            foreach ($services as $srv) {
                $cleanSrv = trim((string) $srv);
                if ($cleanSrv !== '') {
                    $serviceUnbookedCounts[$cleanSrv] = ($serviceUnbookedCounts[$cleanSrv] ?? 0) + 1;
                }
            }
        }
        arsort($serviceUnbookedCounts);

        $topBottleneckService = !empty($serviceUnbookedCounts) ? (string) array_key_first($serviceUnbookedCounts) : 'Salon Treatment';
        $topBottleneckCount = !empty($serviceUnbookedCounts) ? (int) reset($serviceUnbookedCounts) : 0;
        $topBottleneckPct = $unbookedCount > 0 ? round(($topBottleneckCount / $unbookedCount) * 100, 1) : 0.0;
        $topBottleneckPriceQar = self::resolveServicePrice($topBottleneckService, $servicePriceMap);

        // 2. Customer Remarks & Lost Reasons
        $remarksList = $unbookedLeads
            ->map(function (Lead $l) {
                $raw = trim((string) $l->customer_remarks);
                $cleaned = trim((string) preg_replace('/\[(Auto-closed|Reopened):[^\]]*\]/i', '', $raw));
                return $cleaned;
            })
            ->filter(fn(string $r) => $r !== '')
            ->unique()
            ->take(6)
            ->values()
            ->all();

        $remarksSnippet = !empty($remarksList)
            ? 'Client notes cite: "' . implode('", "', array_slice($remarksList, 0, 2)) . '".'
            : 'Most unbooked leads have no objection note logged.';

        // 3. Speed & Follow-up Discipline Leak
        $unscheduledCancelNoShowCount = $this->unscheduledLeadsQuery()->count();
        $liveOverdueCount = $overdueLeadsLive->count();
        $avgUnbookedAgeDays = $unbookedCount > 0
            ? round($unbookedLeads->avg(fn(Lead $l) => max(0, (int) floor(($l->created_at ?? now())->diffInDays(now())))), 1)
            : 0.0;

        // 4. Cash Recovery Math (open/unbooked leads in period + live overdue/unscheduled queue)
        $openRecoverableLeads = Lead::query()
            ->where(function ($q) {
                $q->whereNull('needful_done')->orWhereNotIn('needful_done', Lead::CLOSED_STATUSES);
            })
            ->get();

        $recoverablePool = $openRecoverableLeads->isNotEmpty() ? $openRecoverableLeads : $unbookedLeads;
        $recoverableQar = (int) $recoverablePool->sum(fn(Lead $l) => self::estimateLeadQar($l, $servicePriceMap));
        $recoverableLeadCount = $recoverablePool->count();

        // Build the 3 deterministic Hormozi Executive Diagnostic Bullets
        $bottleneckText = $unbookedCount > 0
            ? "{$topBottleneckService} ({$topBottleneckPriceQar} QAR) accounts for {$topBottleneckCount} of {$unbookedCount} unbooked leads ({$topBottleneckPct}%). {$remarksSnippet} Value-Anchor Fix: Pair every {$topBottleneckService} quote with a complimentary 10-min senior specialist consultation or express blowdry/scalp finish so clients feel the luxury value before comparing price."
            : "0 unbooked service bottlenecks in this period ({$totalLeads} total leads). Keep pairing price quotes with a complimentary 10-min specialist consultation.";

        $speedAlertText = ($liveOverdueCount > 0 || $unscheduledCancelNoShowCount > 0 || $unbookedCount > 0)
            ? "{$liveOverdueCount} follow-up(s) are currently overdue, {$unscheduledCancelNoShowCount} cancelled/no-show lead(s) have no follow-up date set, and unbooked leads sit an average of {$avgUnbookedAgeDays} day(s). Fix Rule: Enforce a mandatory follow-up date within 2 hours of every cancellation or inquiry."
            : "0 overdue leads and 0 unscheduled cancellations right now (average unbooked age: {$avgUnbookedAgeDays}d). Maintain the 2-hour follow-up rule on all new inquiries.";

        $recoveryPlayText = $recoverableLeadCount > 0
            ? "~" . number_format($recoverableQar) . " QAR in salon revenue is sitting across {$recoverableLeadCount} unbooked/open lead(s) right now (led by {$topBottleneckService} at {$topBottleneckPriceQar} QAR). #1 Action Today: Have agents work the Overdue & Cancelled queue first using the Objection Crusher scripts to lock in morning/afternoon consultations."
            : "~0 QAR sitting in unbooked leads right now. #1 Action Today: Review completed appointments for rebooking opportunities.";

        return [
            'bottleneck' => $bottleneckText,
            'speed_alert' => $speedAlertText,
            'recovery_play' => $recoveryPlayText,
            'source' => 'deterministic',
            'metrics' => [
                'total_leads' => $totalLeads,
                'unbooked_count' => $unbookedCount,
                'top_bottleneck_service' => $topBottleneckService,
                'top_bottleneck_count' => $topBottleneckCount,
                'top_bottleneck_pct' => $topBottleneckPct,
                'top_bottleneck_price_qar' => $topBottleneckPriceQar,
                'service_unbooked_counts' => $serviceUnbookedCounts,
                'remarks_sample' => $remarksList,
                'live_overdue_count' => $liveOverdueCount,
                'unscheduled_cancel_noshow_count' => $unscheduledCancelNoShowCount,
                'avg_unbooked_age_days' => $avgUnbookedAgeDays,
                'recoverable_qar' => $recoverableQar,
                'recoverable_lead_count' => $recoverableLeadCount,
            ],
        ];
    }

    private function resolvePeriod(string $period): array
    {
        $now = now();

        return match ($period) {
            'today' => [$now->copy()->startOfDay(), $now->copy()->endOfDay()],
            'yesterday' => [$now->copy()->subDay()->startOfDay(), $now->copy()->subDay()->endOfDay()],
            'this_week' => [$now->copy()->startOfWeek(Carbon::SUNDAY), $now->copy()->endOfDay()],
            'last_month' => [$now->copy()->subMonthNoOverflow()->startOfMonth(), $now->copy()->subMonthNoOverflow()->endOfMonth()],
            'all_time' => [
                ($earliest = Lead::min('created_at')) ? Carbon::parse($earliest)->startOfDay() : $now->copy()->startOfMonth(),
                $now->copy()->endOfDay(),
            ],
            default => [$now->copy()->startOfMonth(), $now->copy()->endOfDay()],
        };
    }

    /**
     * Leads whose next follow-up date has passed, haven't been closed
     * (Booked or Not Interested/Lost), and aren't already a cancelled lead.
     */
    private function overdueLeadsQuery()
    {
        return Lead::with(['agent', 'customer'])
            ->whereNotNull('next_followup_date')
            ->whereDate('next_followup_date', '<', today())
            ->where(function ($q) {
                $q->whereNull('needful_done')->orWhereNotIn('needful_done', Lead::CLOSED_STATUSES);
            })
            ->where(function ($q) {
                $q->whereNull('category')->orWhere('category', '!=', 'cancel');
            });
    }

    /**
     * Cancelled/No-show leads (auto-logged from the Enhanced Calendar with no
     * follow-up date - see AppointmentController::syncLeadCategoryFromAppointment())
     * that still haven't had one set and aren't closed yet.
     */
    private function unscheduledLeadsQuery()
    {
        return Lead::with(['agent', 'customer'])
            ->whereHasCategory(['no_show', 'cancel'])
            ->whereNull('next_followup_date')
            ->where(function ($q) {
                $q->whereNull('needful_done')->orWhereNotIn('needful_done', Lead::CLOSED_STATUSES);
            });
    }

    /**
     * Enforce strictly ONE active (open) lead per normalized phone number,
     * regardless of whether category or service_interest differs. If an open
     * lead already exists for this phone, callers merge new services/remarks
     * into it instead of spawning a second open row.
     */
    private function findDuplicateLead(string $normalizedPhone, ?string $category = null, array $serviceInterest = [], ?int $excludeLeadId = null)
    {
        return Lead::whereRaw(
            "REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(phone,' ',''),'-',''),'(',''),')',''),'+','') = ?",
            [$normalizedPhone]
        )
            ->where(function ($q) {
                $q->whereNull('needful_done')->orWhereNotIn('needful_done', Lead::CLOSED_STATUSES);
            })
            ->when($excludeLeadId, fn($q) => $q->where('id', '!=', $excludeLeadId))
            ->orderByDesc('id')
            ->first();
    }

    /**
     * Link to the client the browser matched (if it's a real customer), fall
     * back to a phone match done server-side, or create a brand new client
     * profile so every lead ends up tied to a customer record. Refreshes the
     * stored name so the directory reflects the latest spelling given.
     */
    private function resolveOrCreateCustomer(string $normalizedPhone, ?int $requestedCustomerId, ?string $name): Customer
    {
        $canonicalPhone = Customer::normalizePhone($normalizedPhone) ?: $normalizedPhone;
        $candidates = array_values(array_unique(array_filter([$canonicalPhone, $normalizedPhone])));

        $customer = $requestedCustomerId ? Customer::find($requestedCustomerId) : null;
        if (!$customer && !empty($candidates)) {
            $customer = Customer::whereIn('phone', $candidates)->first();
        }

        $trimmedName = trim((string) $name);

        if (!$customer) {
            return Customer::create([
                'phone' => $canonicalPhone,
                'name' => $trimmedName !== '' ? $trimmedName : null,
            ]);
        }

        $existingName = trim((string) ($customer->name ?? ''));
        $isPlaceholder = $existingName === ''
            || in_array(strtolower($existingName), ['walk-in', 'guest', 'unnamed', 'unnamed client', '-', '—'], true);

        if ($trimmedName !== '' && $isPlaceholder) {
            $customer->name = $trimmedName;
            $customer->save();
        }

        return $customer;
    }

    /**
     * Link any AdLeadEntry rows within the 30-day attribution window for this customer/phone
     * that do not yet have a lead_id (or were pointing at a merged duplicate lead).
     */
    private function linkSameMonthAdLeadEntries(Lead $lead, Customer $customer, string $normalizedPhone, ?int $mergedFromLeadId = null): void
    {
        $refDate = $lead->created_at ? Carbon::parse($lead->created_at) : now();
        $windowStart = $refDate->copy()->startOfMonth()->startOfDay();
        $windowEnd = $refDate->copy()->endOfMonth()->endOfDay();

        [, $phoneVariants, $strippedDigits] = AdLeadEntry::buildPhoneLookupSets([$normalizedPhone]);

        AdLeadEntry::query()
            ->whereBetween('date', [$windowStart, $windowEnd])
            ->where(function ($q) use ($customer, $phoneVariants, $strippedDigits, $mergedFromLeadId) {
                $q->where('customer_id', $customer->id);
                AdLeadEntry::applyPhoneCohortClause($q, $phoneVariants, $strippedDigits, 'or', 'phone');
                if ($mergedFromLeadId) {
                    $q->orWhere('lead_id', $mergedFromLeadId);
                }
            })
            ->where(function ($q) use ($mergedFromLeadId) {
                $q->whereNull('lead_id');
                if ($mergedFromLeadId) {
                    $q->orWhere('lead_id', $mergedFromLeadId);
                }
            })
            ->update([
                'lead_id' => $lead->id,
                'customer_id' => $customer->id,
            ]);

        if ($lead->isPaidAdLead()) {
            AdLeadEntry::syncFromCustomerActivity(
                customer: $customer,
                activityDate: $refDate,
                phone: $normalizedPhone,
                resetIfNoActivity: false
            );
        }
    }
}
