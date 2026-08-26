<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Lead;
use App\Models\Service;
use App\Models\User;
use Illuminate\Http\Request;

class LeadController extends Controller
{
    public function index(Request $request)
    {
        $query = Lead::query()->with(['agent', 'customer']);

        if ($request->filled('agent_id')) {
            $query->whereIn('assigned_agent_id', (array) $request->agent_id);
        }

        if ($request->filled('category')) {
            $query->whereIn('category', (array) $request->category);
        }

        if ($request->filled('followup_date')) {
            $query->whereDate('next_followup_date', $request->followup_date);
        }

        if ($request->filled('phone')) {
            $query->where('phone', 'like', '%' . $request->phone . '%');
        }

        // With no filters at all, keep the table to recent leads rather than the full history.
        if (!$request->hasAny(['agent_id', 'category', 'followup_date', 'phone'])) {
            $query->where('created_at', '>=', now()->subMonth());
        }

        $leads = $query->orderBy('created_at', 'desc')->get();
        $agents = User::where('role', 'agent')->get();
        $services = Service::orderBy('name')->pluck('name');

        return view('leads.index', compact('leads', 'agents', 'services'));
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
            'customer_name' => 'nullable|string|max:255',
            'assigned_agent_id' => 'nullable|exists:users,id',
            'category' => 'nullable|in:' . implode(',', array_keys(Lead::CATEGORIES)),
            'needful_done' => 'nullable|in:' . implode(',', array_keys(Lead::NEEDFUL_STATUSES)),
            'next_followup_date' => 'nullable|date',
            'customer_id' => 'nullable|exists:customers,id',
        ]);

        $normalizedPhone = preg_replace('/\D+/', '', $request->country_code . $request->phone_number);

        $existingLead = Lead::whereRaw(
            "REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(phone,' ',''),'-',''),'(',''),')',''),'+','') = ?",
            [$normalizedPhone]
        )->first();

        if ($existingLead) {
            return redirect()->route('leads.index')->with('existing_lead_id', $existingLead->id)->with('warning', 'Lead already exists. You can update it.');
        }

        $customer = $this->resolveOrCreateCustomer($normalizedPhone, $request->customer_id, $request->customer_name);

        Lead::create([
            'phone' => $normalizedPhone,
            'customer_id' => $customer->id,
            'assigned_agent_id' => $request->assigned_agent_id,
            'category' => $request->category,
            'customer_remarks' => $request->customer_remarks,
            'service_interest' => $request->service_interest,
            'needful_done' => $request->needful_done,
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
        $request->validate([
            'country_code' => 'required|string|max:5',
            'phone_number' => 'required|string|max:20',
            'customer_name' => 'nullable|string|max:255',
            'assigned_agent_id' => 'nullable|exists:users,id',
            'category' => 'nullable|in:' . implode(',', array_keys(Lead::CATEGORIES)),
            'needful_done' => 'nullable|in:' . implode(',', array_keys(Lead::NEEDFUL_STATUSES)),
            'next_followup_date' => 'nullable|date',
            'customer_id' => 'nullable|exists:customers,id',
        ]);

        $normalizedPhone = preg_replace('/\D+/', '', $request->country_code . $request->phone_number);
        $customer = $this->resolveOrCreateCustomer($normalizedPhone, $request->customer_id, $request->customer_name);

        $data = [
            'phone' => $normalizedPhone,
            'customer_id' => $customer->id,
            'assigned_agent_id' => $request->assigned_agent_id,
            'category' => $request->category,
            'customer_remarks' => $request->customer_remarks,
            'service_interest' => $request->service_interest,
            'needful_done' => $request->needful_done,
            'next_followup_date' => $request->next_followup_date,
        ];

        $existingLead = Lead::whereRaw(
            "REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(phone,' ',''),'-',''),'(',''),')',''),'+','') = ?",
            [$normalizedPhone]
        )->where('id', '!=', $lead->id)->first();

        // If another lead already has this phone number, update that one instead of creating a duplicate.
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
