<?php

namespace App\Http\Controllers\Kpi;

use App\Http\Controllers\Controller;
use App\Models\AgentShiftLog;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AgentShiftLogController extends Controller
{
    public function store(Request $request)
    {
        $validated = $this->validated($request);

        AgentShiftLog::create($validated + ['created_by' => auth()->id()]);

        return redirect()->route('kpi.agents.index')->with('success', 'Shift sign-in logged.');
    }

    public function update(Request $request, AgentShiftLog $agentShiftLog)
    {
        $agentShiftLog->update($this->validated($request, $agentShiftLog));

        return redirect()->route('kpi.agents.index')->with('success', 'Shift sign-in updated.');
    }

    public function destroy(AgentShiftLog $agentShiftLog)
    {
        $agentShiftLog->delete();

        return redirect()->route('kpi.agents.index')->with('success', 'Shift sign-in deleted.');
    }

    private function validated(Request $request, ?AgentShiftLog $agentShiftLog = null): array
    {
        return $request->validate([
            'date' => 'required|date',
            'user_id' => [
                'required',
                Rule::exists('users', 'id')->where('role', 'agent'),
                Rule::unique('agent_shift_logs', 'user_id')
                    ->where('date', $request->date)
                    ->ignore($agentShiftLog?->id),
            ],
            'shift' => ['required', Rule::in(array_keys(AgentShiftLog::SHIFTS))],
            'check_in_time' => 'required|date_format:H:i',
            // Sign-out is optional: an agent may only be signed in so far, and
            // have the check-out time added later via edit once their shift ends.
            'check_out_time' => 'nullable|date_format:H:i|after:check_in_time',
        ], [
            'user_id.unique' => 'This agent already has a shift logged for that date.',
            'check_out_time.after' => 'Check-out time must be after check-in time.',
        ]);
    }
}
