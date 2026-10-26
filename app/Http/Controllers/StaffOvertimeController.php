<?php

namespace App\Http\Controllers;

use App\Models\StaffOvertimeEntry;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class StaffOvertimeController extends Controller
{
    public function store(Request $request)
    {
        $validated = $this->validated($request);

        $entry = StaffOvertimeEntry::create($validated + ['created_by' => auth()->id()]);

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Overtime entry logged.',
                'entry' => $entry->fresh('staff')->toEditPayload(),
            ]);
        }

        return redirect()->route('staffs.index', ['tab' => 'payroll'])->with('success', 'Overtime entry logged.');
    }

    public function update(Request $request, StaffOvertimeEntry $staffOvertimeEntry)
    {
        $staffOvertimeEntry->update($this->validated($request));

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Overtime entry updated.',
                'entry' => $staffOvertimeEntry->fresh('staff')->toEditPayload(),
            ]);
        }

        return redirect()->route('staffs.index', ['tab' => 'payroll'])->with('success', 'Overtime entry updated.');
    }

    public function destroy(Request $request, StaffOvertimeEntry $staffOvertimeEntry)
    {
        $staffOvertimeEntry->delete();

        if ($request->wantsJson()) {
            return response()->json(['success' => true, 'message' => 'Overtime entry deleted.']);
        }

        return redirect()->route('staffs.index', ['tab' => 'payroll'])->with('success', 'Overtime entry deleted.');
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'staff_id' => ['required', Rule::exists('staff', 'id')],
            'entry_date' => 'required|date',
            'hours' => 'nullable|numeric|min:0|max:24',
            'minutes' => 'nullable|integer|min:0|max:59',
            'rate' => 'nullable|numeric|min:0',
            'customer_name' => 'nullable|string|max:255',
            'note' => 'nullable|string|max:255',
        ]);

        $hoursInput = (float) ($data['hours'] ?? 0);
        $minutesInput = (int) ($data['minutes'] ?? 0);
        unset($data['minutes']);

        $totalDecimalHours = round($hoursInput + ($minutesInput / 60), 2);
        if ($totalDecimalHours <= 0 || $totalDecimalHours > 24) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'hours' => ['Total overtime duration must be between 1 minute and 24 hours.'],
            ]);
        }

        $data['hours'] = $totalDecimalHours;

        return $data;
    }
}
