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

        if ($request->filled('category')) {
            $query->whereIn('category', (array) $request->category);
        }

        if ($request->filled('followup_date')) {
            $query->whereDate('next_followup_date', $request->followup_date);
        }

        if ($request->filled('phone_number')) {
            $normalizedPhone = Lead::normalizePhone($request->country_code, $request->phone_number);
            $query->where('phone', 'like', '%' . $normalizedPhone . '%');
        }

        // With no filters at all, keep the table to recent leads rather than the full history.
        if (!$request->hasAny(['category', 'followup_date', 'phone_number'])) {
            $query->where('created_at', '>=', now()->subMonth());
        }

        $leads = $query->orderBy('created_at', 'desc')->get();
        $agents = User::where('role', 'agent')->get();
        $services = Service::orderBy('name')->pluck('name');
        $overdueLeads = $this->overdueLeadsQuery()->orderBy('next_followup_date')->get();
        $unscheduledLeads = $this->unscheduledLeadsQuery()->orderByDesc('created_at')->get();

        return view('leads.index', compact('leads', 'agents', 'services', 'overdueLeads', 'unscheduledLeads'));
    }

    public function checkTodaysFollowUps(Request $request)
    {
        $today = today()->format('Y-m-d');

        $dueLeads = Lead::whereDate('next_followup_date', $today)
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
            'service_interest' => 'required|string|max:255',
            'next_followup_date' => 'required|date',
            'customer_id' => 'nullable|exists:customers,id',
        ]);

        $normalizedPhone = Lead::normalizePhone($request->country_code, $request->phone_number);

        $existingLead = $this->findDuplicateLead($normalizedPhone, $request->category, $request->service_interest);

        if ($existingLead) {
            return redirect()->route('leads.index')->with('existing_lead_id', $existingLead->id)->with('warning', 'This client already has an open lead for the same category and service. You can update it.');
        }

        $customer = $this->resolveOrCreateCustomer($normalizedPhone, $request->customer_id, $request->customer_name);

        Lead::create([
            'phone' => $normalizedPhone,
            'customer_id' => $customer->id,
            'assigned_agent_id' => $request->assigned_agent_id,
            'category' => $request->category,
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
        // A lead already auto-marked No-show/Cancel keeps that category as a
        // read-only badge in the edit form (see edit.blade.php) rather than a
        // choosable option, so it round-trips via a hidden input. Allow that
        // one extra value through validation without opening the dropdown
        // itself up to staff picking No-show/Cancel for any other lead.
        $allowedCategories = array_keys(Lead::MANUAL_CATEGORIES);
        if ($lead->category && !array_key_exists($lead->category, Lead::MANUAL_CATEGORIES)) {
            $allowedCategories[] = $lead->category;
        }

        $request->validate([
            'country_code' => 'required|string|max:5',
            'phone_number' => 'required|string|max:20',
            'customer_name' => 'required|string|max:255',
            'assigned_agent_id' => 'required|exists:users,id',
            'category' => 'required|in:' . implode(',', $allowedCategories),
            'service_interest' => 'required|string|max:255',
            'next_followup_date' => 'required|date',
            'customer_id' => 'nullable|exists:customers,id',
        ]);

        $normalizedPhone = Lead::normalizePhone($request->country_code, $request->phone_number);
        $customer = $this->resolveOrCreateCustomer($normalizedPhone, $request->customer_id, $request->customer_name);

        // needful_done is intentionally left untouched here - it's managed
        // exclusively via the inline dropdown on the leads list.
        $data = [
            'phone' => $normalizedPhone,
            'customer_id' => $customer->id,
            'assigned_agent_id' => $request->assigned_agent_id,
            'category' => $request->category,
            'customer_remarks' => $request->customer_remarks,
            'service_interest' => $request->service_interest,
            'next_followup_date' => $request->next_followup_date,
        ];

        $existingLead = $this->findDuplicateLead($normalizedPhone, $request->category, $request->service_interest, $lead->id);

        // If another lead already covers this exact phone/category/service, update that one instead of creating a duplicate.
        if ($existingLead) {
            $existingLead->update($data);

            return redirect()->route('leads.index')
                ->with('existing_lead_id', $existingLead->id)
                ->with('success', 'Lead updated successfully.');
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
        $request->validate([
            'needful_done' => 'nullable|in:' . implode(',', array_keys(Lead::NEEDFUL_STATUSES)),
        ]);

        $lead->update(['needful_done' => $request->needful_done ?: null]);

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
        $period = $request->filled('period') && array_key_exists($request->period, self::PERIOD_LABELS)
            ? $request->period
            : 'this_month';
        [$from, $to] = $this->resolvePeriod($period);

        $leads = Lead::whereBetween('created_at', [$from, $to])->get();

        $totalLeads = $leads->count();

        $categoryCounts = [];
        foreach (Lead::CATEGORIES as $key => $label) {
            $categoryCounts[$key] = $leads->where('category', $key)->count();
        }
        $uncategorized = $leads->whereNull('category')->count();

        $categoryLabels = array_merge(array_values(Lead::CATEGORIES), ['Uncategorized']);
        $categorySeries = array_merge(array_values($categoryCounts), [$uncategorized]);

        $needfulCounts = [
            'yes' => $leads->where('needful_done', 'yes')->count(),
            'no' => $leads->where('needful_done', 'no')->count(),
            'unset' => $leads->whereNull('needful_done')->count(),
        ];

        $today = today();
        $allLeads = Lead::all();
        $followupPerformance = [
            'overdue' => $this->overdueLeadsQuery()->count(),
            'completed' => $allLeads->where('needful_done', 'yes')->count(),
            'upcoming' => $allLeads->filter(fn($l) => $l->next_followup_date && $l->next_followup_date->gte($today) && $l->needful_done !== 'yes')->count(),
            'no_date' => $allLeads->whereNull('next_followup_date')->count(),
        ];

        return view('leads.analytics', compact(
            'period',
            'from',
            'to',
            'totalLeads',
            'categoryCounts',
            'categoryLabels',
            'categorySeries',
            'uncategorized',
            'needfulCounts',
            'followupPerformance'
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
     * Leads whose next follow-up date has passed, haven't been marked
     * Needful Done = Yes, and aren't already a dead/cancelled lead.
     */
    private function overdueLeadsQuery()
    {
        return Lead::with(['agent', 'customer'])
            ->whereNotNull('next_followup_date')
            ->whereDate('next_followup_date', '<', today())
            ->where(function ($q) {
                $q->whereNull('needful_done')->orWhere('needful_done', '!=', 'yes');
            })
            ->where(function ($q) {
                $q->whereNull('category')->orWhere('category', '!=', 'cancel');
            });
    }

    /**
     * Cancelled/No-show leads (auto-logged from the Enhanced Calendar with no
     * follow-up date - see AppointmentController::syncLeadCategoryFromAppointment())
     * that still haven't had one set. Staff need to actually call the client
     * to learn their preferred rebooking timeline before a date can go here.
     */
    private function unscheduledLeadsQuery()
    {
        return Lead::with(['agent', 'customer'])
            ->whereIn('category', ['no_show', 'cancel'])
            ->whereNull('next_followup_date');
    }

    /**
     * A client can legitimately have several independent, simultaneous leads
     * (e.g. an active Follow up for Hair Color and a separate Inquiry for a
     * Manicure) - so matching on phone alone would wrongly treat a genuinely
     * new lead as a duplicate of an unrelated one and silently discard it.
     * Only phone + category + service_interest together mean "this exact
     * open lead already exists"; anything else is a distinct lead.
     */
    private function findDuplicateLead(string $normalizedPhone, ?string $category, ?string $serviceInterest, ?int $excludeLeadId = null)
    {
        return Lead::whereRaw(
            "REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(phone,' ',''),'-',''),'(',''),')',''),'+','') = ?",
            [$normalizedPhone]
        )
            ->where('category', $category)
            ->where('service_interest', $serviceInterest)
            ->when($excludeLeadId, fn($q) => $q->where('id', '!=', $excludeLeadId))
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
