<?php

namespace App\Http\Controllers;

use App\Models\StaffDeduction;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class StaffDeductionController extends Controller
{
    public function store(Request $request)
    {
        $validated = $this->validated($request);

        $deduction = StaffDeduction::create($validated + ['created_by' => auth()->id()]);

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Deduction logged.',
                'entry' => $deduction->fresh('staff')->toEditPayload(),
            ]);
        }

        return redirect()->route('staffs.index', ['tab' => 'complaints'])->with('success', 'Deduction logged.');
    }

    public function update(Request $request, StaffDeduction $staffDeduction)
    {
        $staffDeduction->update($this->validated($request));

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Deduction updated.',
                'entry' => $staffDeduction->fresh('staff')->toEditPayload(),
            ]);
        }

        return redirect()->route('staffs.index', ['tab' => 'complaints'])->with('success', 'Deduction updated.');
    }

    public function destroy(Request $request, StaffDeduction $staffDeduction)
    {
        $staffDeduction->delete();

        if ($request->wantsJson()) {
            return response()->json(['success' => true, 'message' => 'Deduction deleted.']);
        }

        return redirect()->route('staffs.index', ['tab' => 'complaints'])->with('success', 'Deduction deleted.');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'staff_id' => ['required', Rule::exists('staff', 'id')],
            'deduction_date' => 'required|date',
            'amount' => 'required|numeric|min:0',
            'reason' => 'required|string|max:255',
            'complaint_id' => ['nullable', Rule::exists('staff_complaints', 'id')],
        ]);
    }
}
