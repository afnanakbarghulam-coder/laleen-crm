<?php

namespace App\Http\Controllers;

use App\Models\Lead;
use App\Models\User;
use Illuminate\Http\Request;

class LeadController extends Controller
{

    public function index(Request $request)
    {
        $query = Lead::query()->with('agent');

        if ($request->filled('agent_id')) {
            $query->where('assigned_agent_id', $request->agent_id);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('from_date') && $request->filled('to_date')) {
            $query->whereBetween('followup_date', [$request->from_date, $request->to_date]);
        }

        if (!$request->filled('from_date') && !$request->filled('to_date')) {
            $query->where('created_at', '>=', now()->subMonth());
        }

        if ($request->filled('phone')) {
            $query->where('phone', 'like', '%' . $request->phone . '%');
        }

        $leads = $query->orderBy('created_at', 'desc')->get();
        $agents = User::where('role', 'agent')->get();

        return view('leads.index', compact('leads', 'agents'));
    }

    public function checkTodaysFollowUps(Request $request)
    {
        $today = today()->format('Y-m-d');

        $pendingLeads = Lead::whereDate('followup_date', $today)
            ->where('status', 'pending')
            ->get(['id', 'name', 'phone', 'followup_date']);

        return response()->json([
            'count' => $pendingLeads->count(),
            'today' => $today,
            'leads' => $pendingLeads
        ]);
    }



    public function create()
    {
        //
    }


    public function store(Request $request)
    {
        // dd($request->all());
        $request->validate([
            // 'name' => 'required|string|max:255',
            'phone' => 'required|string|max:20',
            'assigned_agent_id' => 'nullable|exists:users,id',
        ]);

        $normalizedPhone = preg_replace('/\D+/', '', $request->phone);

        $existingLead = Lead::whereRaw(
            "REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(phone,' ',''),'-',''),'(',''),')',''),'+','') = ?",
            [$normalizedPhone]
        )->first();

        if ($existingLead) {
            return redirect()->route('leads.index')->with('existing_lead_id', $existingLead->id)->with('warning', 'Lead already exists. You can update it.');
        }

        Lead::create([
            'name' => $request->name,
            'phone' => $request->phone,
            'assigned_agent_id' => $request->assigned_agent_id,
            'lead_source' => $request->lead_source,
            'followup_date' => $request->followup_date,
            'notes' => $request->notes,
            'status' => 'pending',
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


    // public function update(Request $request, Lead $lead)
    // {
    //     $request->validate([
    //         // 'name' => 'required|string|max:255',
    //         'phone' => 'required|string|max:20',
    //         'lead_source' => 'nullable|string|max:255',
    //         'followup_date' => 'nullable|date',
    //         'notes' => 'nullable|string',
    //         'status' => 'required|in:pending,done',
    //     ]);

    //     $normalizedPhone = preg_replace('/\D+/', '', $request->phone);

    //     $existingLead = Lead::whereRaw(
    //         "REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(phone,' ',''),'-',''),'(',''),')',''),'+','') = ?",
    //         [$normalizedPhone]
    //     )->first();

    //     if ($existingLead) {
    //         return redirect()->route('leads.index')->with('existing_lead_id', $existingLead->id)->with('warning', 'Lead already exists. You can update it.');
    //     }
    //     $lead->update([
    //         'name' => $request->name,
    //         'phone' => $request->phone,
    //         'assigned_agent_id' => $request->assigned_agent_id,
    //         'lead_source' => $request->lead_source,
    //         'followup_date' => $request->followup_date,
    //         'notes' => $request->notes,
    //         'status' => $request->status,
    //     ]);

    //     return redirect()->back()->with('success', 'Lead updated successfully.');
    // }


    public function update(Request $request, Lead $lead)
    {
        $request->validate([
            'phone' => 'required|string|max:20',
            'lead_source' => 'nullable|string|max:255',
            'followup_date' => 'nullable|date',
            'notes' => 'nullable|string',
            'status' => 'required|in:pending,done',
            'assigned_agent_id' => 'nullable|exists:users,id',
        ]);

        $normalizedPhone = preg_replace('/\D+/', '', $request->phone);
        $existingLead = Lead::whereRaw(
            "REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(phone,' ',''),'-',''),'(',''),')',''),'+','') = ?", [$normalizedPhone])
            ->where('id', '!=', $lead->id)->first();

        // ✅ If phone exists on another lead → update THAT lead
        if ($existingLead) {
            $existingLead->update([
                'name' => $request->name,
                'phone' => $request->phone,
                'assigned_agent_id' => $request->assigned_agent_id,
                'lead_source' => $request->lead_source,
                'followup_date' => $request->followup_date,
                'notes' => $request->notes,
                'status' => $request->status,
            ]);

            return redirect()->route('leads.index')
                ->with('existing_lead_id', $existingLead->id)
                ->with('success', 'Lead updated successfully.');
        }

        // ✅ Otherwise update current lead
        $lead->update([
            'name' => $request->name,
            'phone' => $request->phone,
            'assigned_agent_id' => $request->assigned_agent_id,
            'lead_source' => $request->lead_source,
            'followup_date' => $request->followup_date,
            'notes' => $request->notes,
            'status' => $request->status,
        ]);

        return redirect()->route('leads.index')
            ->with('success', 'Lead updated successfully.');
    }

    public function destroy(Lead $lead)
    {
        $lead->delete();

        return redirect()->back()->with('success', 'Lead deleted successfully.');
    }
}
