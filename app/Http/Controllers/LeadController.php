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
            'phone' => 'required|string|max:20',
            'assigned_agent_id' => 'nullable|exists:users,id',
            'category' => 'nullable|in:' . implode(',', array_keys(Lead::CATEGORIES)),
            'correction_done' => 'nullable|in:' . implode(',', array_keys(Lead::CORRECTION_STATUSES)),
            'next_followup_date' => 'nullable|date',
            'customer_id' => 'nullable|exists:customers,id',
        ]);

        $normalizedPhone = preg_replace('/\D+/', '', $request->phone);

        $existingLead = Lead::whereRaw(
            "REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(phone,' ',''),'-',''),'(',''),')',''),'+','') = ?",
            [$normalizedPhone]
        )->first();

        if ($existingLead) {
            return redirect()->route('leads.index')->with('existing_lead_id', $existingLead->id)->with('warning', 'Lead already exists. You can update it.');
        }

        $customer = $this->resolveOrCreateCustomer($normalizedPhone, $request->customer_id);

        Lead::create([
            'phone' => $request->phone,
            'customer_id' => $customer->id,
            'assigned_agent_id' => $request->assigned_agent_id,
            'category' => $request->category,
            'customer_remarks' => $request->customer_remarks,
            'service_interest' => $request->service_interest,
            'booking_status' => $request->booking_status,
            'correction_done' => $request->correction_done,
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
            'phone' => 'required|string|max:20',
            'assigned_agent_id' => 'nullable|exists:users,id',
            'category' => 'nullable|in:' . implode(',', array_keys(Lead::CATEGORIES)),
            'correction_done' => 'nullable|in:' . implode(',', array_keys(Lead::CORRECTION_STATUSES)),
            'next_followup_date' => 'nullable|date',
            'customer_id' => 'nullable|exists:customers,id',
        ]);

        $normalizedPhone = preg_replace('/\D+/', '', $request->phone);
        $customer = $this->resolveOrCreateCustomer($normalizedPhone, $request->customer_id);

        $data = [
            'phone' => $request->phone,
            'customer_id' => $customer->id,
            'assigned_agent_id' => $request->assigned_agent_id,
            'category' => $request->category,
            'customer_remarks' => $request->customer_remarks,
            'service_interest' => $request->service_interest,
            'booking_status' => $request->booking_status,
            'correction_done' => $request->correction_done,
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

    public function destroy(Lead $lead)
    {
        $lead->delete();

        return redirect()->back()->with('success', 'Lead deleted successfully.');
    }

    /**
     * Link to the client the browser matched (if it's a real customer), fall
     * back to a phone match done server-side, or create a brand new client
     * profile so every lead ends up tied to a customer record.
     */
    private function resolveOrCreateCustomer(string $normalizedPhone, ?int $requestedCustomerId): Customer
    {
        if ($requestedCustomerId) {
            $customer = Customer::find($requestedCustomerId);
            if ($customer) {
                return $customer;
            }
        }

        return Customer::firstOrCreate(['phone' => $normalizedPhone]);
    }
}
