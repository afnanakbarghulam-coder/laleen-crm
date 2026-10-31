<?php

namespace App\Http\Controllers;

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
        $query = Lead::query()->with(['agent', 'customer']);
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
            $query->where('needful_done', 'booked');
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

        // With no filters at all, keep the table to recent leads rather than the full history.
        if (!$request->hasAny(['category', 'followup_date', 'phone_number', 'start_date', 'end_date', 'agent_id', 'alert_filter'])) {
            $query->where('created_at', '>=', now()->subMonth());
        }

        $leads = $query->orderBy('created_at', 'desc')->get();
        $agents = User::where('role', 'agent')->get();
        $services = Service::orderBy('name')->pluck('name');
        $overdueLeads = $this->overdueLeadsQuery()->orderBy('next_followup_date')->get();
        $unscheduledLeads = $this->unscheduledLeadsQuery()->orderByDesc('created_at')->get();
        $overdueCount = $overdueLeads->count();
        $unscheduledCount = $unscheduledLeads->count();
        $bookedCount = Lead::where('needful_done', 'booked')->count();

        return view('leads.index', compact(
            'leads',
            'agents',
            'services',
            'overdueLeads',
            'unscheduledLeads',
            'overdueCount',
            'unscheduledCount',
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

            $existingLead->update([
                'customer_id' => $existingLead->customer_id ?: $customer->id,
                'assigned_agent_id' => $request->assigned_agent_id ?: $existingLead->assigned_agent_id,
                'category' => $primaryCategory ?: $existingLead->category,
                'categories' => $mergedCategories,
                'customer_remarks' => $mergedRemarks !== '' ? $mergedRemarks : null,
                'service_interest' => !empty($mergedServices) ? $mergedServices : $existingLead->service_interest,
                'next_followup_date' => $request->next_followup_date ?: $existingLead->next_followup_date,
            ]);

            return redirect()->route('leads.index')
                ->with('existing_lead_id', $existingLead->id)
                ->with('warning', 'This client already has an open lead. Their services and details have been merged into the existing lead.');
        }

        $initialCategories = Lead::normalizeCategories([$request->category]);

        Lead::create([
            'phone' => $normalizedPhone,
            'customer_id' => $customer->id,
            'assigned_agent_id' => $request->assigned_agent_id,
            'category' => Lead::resolvePrimaryCategory($initialCategories) ?? $request->category,
            'categories' => $initialCategories,
            'customer_remarks' => $request->customer_remarks,
            'service_interest' => $request->service_interest,
            'next_followup_date' => $request->next_followup_date,
        ]);

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
            $lead->delete();

            return redirect()->route('leads.index')
                ->with('existing_lead_id', $existingLead->id)
                ->with('success', 'Lead updated and merged successfully.');
        }

        $lead->update($data);

        return redirect()->route('leads.index')
            ->with('success', 'Lead updated successfully.');
    }

    /**
     * Quick inline toggle from the leads table - no modal needed.
     */
    public function updateNeedfulDone(Request $request, Lead $lead)
    {
        if ($lead->needful_done === 'booked') {
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

        $newStatus = $request->needful_done ?: null;
        $payload = ['needful_done' => $newStatus];
        if (in_array($newStatus, Lead::CLOSED_STATUSES, true)) {
            $payload['next_followup_date'] = null;
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

        $leads = Lead::with('agent')->whereBetween('created_at', [$from, $to])->get();

        $totalLeads = $leads->count();

        $categoryCounts = [];
        foreach (Lead::CATEGORIES as $key => $label) {
            $categoryCounts[$key] = $leads->filter(fn(Lead $l) => $l->hasCategory($key))->count();
        }
        $uncategorized = $leads->filter(fn(Lead $l) => empty($l->categories_list))->count();

        $categoryLabels = array_merge(array_values(Lead::CATEGORIES), ['Uncategorized']);
        $categorySeries = array_merge(array_values($categoryCounts), [$uncategorized]);

        $needfulCounts = [
            'booked' => $leads->where('needful_done', 'booked')->count(),
            'yes' => $leads->where('needful_done', 'yes')->count(),
            'no' => $leads->where('needful_done', 'no')->count(),
            'unset' => $leads->whereNull('needful_done')->count(),
        ];

        $bookedLeadsCount = $needfulCounts['booked'];
        $conversionRate = $totalLeads > 0 ? round(($bookedLeadsCount / $totalLeads) * 100, 1) : 0.0;

        $winBackPool = $leads->filter(fn(Lead $l) => $l->hasCategory(['cancel', 'no_show']));
        $winBackTotal = $winBackPool->count();
        $winBackBooked = $winBackPool->where('needful_done', 'booked')->count();
        $winBackRate = $winBackTotal > 0 ? round(($winBackBooked / $winBackTotal) * 100, 1) : 0.0;

        $today = today();
        $allLeads = Lead::all();
        $overdueLeadsLive = $this->overdueLeadsQuery()->get();
        $followupPerformance = [
            'overdue' => $overdueLeadsLive->count(),
            'completed' => $allLeads->where('needful_done', 'booked')->count(),
            'upcoming' => $allLeads->filter(fn($l) => $l->next_followup_date && $l->next_followup_date->gte($today) && !in_array($l->needful_done, Lead::CLOSED_STATUSES, true))->count(),
            'no_date' => $allLeads->whereNull('next_followup_date')->filter(fn($l) => !in_array($l->needful_done, Lead::CLOSED_STATUSES, true))->count(),
        ];

        $agents = User::where('role', 'agent')->orderBy('name')->get();
        $overdueByAgent = $overdueLeadsLive->groupBy('assigned_agent_id')->map->count();

        $agentLeaderboard = $agents->map(function (User $agent) use ($leads, $overdueByAgent) {
            $agentLeads = $leads->where('assigned_agent_id', $agent->id);
            $assigned = $agentLeads->count();
            $booked = $agentLeads->where('needful_done', 'booked')->count();
            $contacted = $agentLeads->where('needful_done', 'yes')->count();
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
            $unassignedBooked = $unassignedLeads->where('needful_done', 'booked')->count();
            $unassignedContacted = $unassignedLeads->where('needful_done', 'yes')->count();
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
            'agentLeaderboard'
        ));
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
        $customer = $requestedCustomerId ? Customer::find($requestedCustomerId) : null;
        $customer = $customer ?: Customer::firstOrCreate(['phone' => $normalizedPhone]);

        if ($name) {
            $customer->name = $name;
            $customer->save();
        }

        return $customer;
    }
}
