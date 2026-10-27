<?php

namespace App\Http\Controllers;

use App\Models\Staff;
use App\Models\StaffOvertimeEntry;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
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
        if (!$request->filled('entry_date') && $request->filled('date')) {
            $request->merge(['entry_date' => $request->input('date')]);
        }

        $data = $request->validate([
            'staff_id' => ['required', Rule::exists('staff', 'id')],
            'entry_date' => 'required|date',
            'hours' => 'nullable|numeric|min:0|max:24',
            'minutes' => 'nullable|integer|min:0|max:59',
            'rate' => 'nullable|numeric|min:0',
            'customer_name' => 'required|string|max:255',
            'note' => 'nullable|string|max:255',
        ]);

        $hoursInput = (float) ($data['hours'] ?? 0);
        $minutesInput = (int) ($data['minutes'] ?? 0);
        unset($data['minutes']);

        $totalTime = $hoursInput + ($minutesInput / 60);
        $totalDecimalHours = round($totalTime, 2);
        if ($totalTime <= 0 || $totalTime > 24) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'hours' => ['Total overtime duration must be between 1 minute and 24 hours.'],
            ]);
        }

        $staff = Staff::findOrFail($data['staff_id']);
        $baseSalary = (float) ($staff->base_salary ?? 0);
        $daysInMonth = Carbon::parse($data['entry_date'])->daysInMonth;
        $dynamicHourlyRate = round(($baseSalary / $daysInMonth) / StaffOvertimeEntry::SHIFT_HOURS, 2);

        $data['hours'] = $totalDecimalHours;
        $data['rate'] = $dynamicHourlyRate;

        return $data;
    }
}
