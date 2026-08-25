<?php

namespace App\Http\Controllers;

use App\Models\QaCorrection;
use App\Models\User;
use App\Models\Appointment;
use Illuminate\Http\Request;

class QACorrectionController extends Controller
{
    public function index()
    {
        $corrections = QaCorrection::with('agent', 'appointment')
            ->orderBy('status', 'asc')
            ->orderBy('created_at', 'desc')
            ->paginate(10);

        return view('qa.index', compact('corrections'));
    }

    public function create()
    {
        $agents = User::where('role', 'agent')->get();
        $appointments = Appointment::orderBy('id', 'desc')->get();
        return view('qa.main-form', compact('agents', 'appointments'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'agent_id' => 'required|exists:users,id',
            'customer_phone' => 'required|string|max:20',
            'notes' => 'nullable|string',
            'issue_type' => 'required|in:wrong-info,poor-follow-up,bad-convincing,rude-behaviour,booking-error,other',
            'severity'   => 'required|in:low,medium,high',
            'status'     => 'required|in:pending,done',
            'appointment_id' => 'nullable|exists:appointments,id',
            'proof_file' => 'nullable|file|mimes:jpg,png,pdf|max:2048'
        ]);

        if ($request->hasFile('proof_file')) {
            $validated['proof_file'] = $request->file('proof_file')->store('qa_proofs', 'public');
        }

        QaCorrection::create($validated);

        return redirect()->route('qa.index')->with('success', 'QA Issue Added!');
    }

    public function edit(QaCorrection $qa)
    {
        $agents = User::where('role', 'agent')->get();
        $appointments = Appointment::orderBy('id', 'desc')->get();
        
        return view('qa.main-form', compact('qa', 'agents', 'appointments'));
    }

    public function update(Request $request, QaCorrection $qa)
    {
        $validated = $request->validate([
            'agent_id' => 'required|exists:users,id',
            'customer_phone' => 'required|string|max:20',
            'notes' => 'nullable|string',
            'issue_type' => 'required|in:wrong-info,poor-follow-up,bad-convincing,rude-behaviour,booking-error,other',
            'severity'   => 'required|in:low,medium,high',
            'status'     => 'required|in:pending,done',
            'appointment_id' => 'nullable|exists:appointments,id',
            'proof_file' => 'nullable|file|mimes:jpg,png,pdf|max:2048'
        ]);

        if ($request->hasFile('proof_file')) {
            $validated['proof_file'] = $request->file('proof_file')->store('qa_proofs', 'public');
        }

        $qa->update($validated);

        return redirect()->route('qa.index')->with('success', 'QA Updated!');
    }

    public function destroy(QaCorrection $qa)
    {
        $qa->delete();
        return redirect()->route('qa.index')->with('success', 'QA Record Deleted');
    }
}
