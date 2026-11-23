<?php

namespace App\Http\Controllers;

use App\Models\StaffDeduction;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class StaffDeductionController extends Controller
{
    public static function canManage(?User $user): bool
    {
        if (! $user) {
            return false;
        }

        return $user->isAdmin()
            || (strtolower(trim((string) $user->role)) === 'manager' && $user->canEdit('staff'));
    }

    public function store(Request $request)
    {
        abort_unless(self::canManage($request->user()), 403, 'Only administrators or authorized managers can manage manual staff deductions.');

        $validated = $this->validated($request);

        $deduction = StaffDeduction::create($validated + [
            'complaint_id' => null,
            'created_by' => auth()->id(),
        ]);

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'Manual deduction logged.',
                'entry' => $deduction->fresh(['staff', 'complaint'])->toEditPayload(),
            ], 201);
        }

        return redirect()->route('staffs.index', $this->redirectFilterParams($request))
            ->with('success', 'Manual deduction logged.');
    }

    public function update(Request $request, StaffDeduction $staffDeduction)
    {
        abort_unless(self::canManage($request->user()), 403, 'Only administrators or authorized managers can manage manual staff deductions.');

        if (! $staffDeduction->isManual()) {
            return $this->rejectComplaintLinked($request);
        }

        $validated = $this->validated($request);
        $staffDeduction->update($validated + ['complaint_id' => null]);

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'Manual deduction updated.',
                'entry' => $staffDeduction->fresh(['staff', 'complaint'])->toEditPayload(),
            ]);
        }

        return redirect()->route('staffs.index', $this->redirectFilterParams($request))
            ->with('success', 'Manual deduction updated.');
    }

    public function destroy(Request $request, StaffDeduction $staffDeduction)
    {
        abort_unless(self::canManage($request->user()), 403, 'Only administrators or authorized managers can manage manual staff deductions.');

        if (! $staffDeduction->isManual()) {
            return $this->rejectComplaintLinked($request);
        }

        $staffDeduction->delete();

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json(['success' => true, 'message' => 'Manual deduction deleted.']);
        }

        return redirect()->route('staffs.index', $this->redirectFilterParams($request))
            ->with('success', 'Manual deduction deleted.');
    }

    private function rejectComplaintLinked(Request $request)
    {
        $message = 'Complaint-linked deductions must be managed through the complaint HR Resolution panel.';

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'success' => false,
                'message' => $message,
            ], 422);
        }

        return redirect()->route('staffs.index', $this->redirectFilterParams($request))
            ->with('error', $message);
    }

    private function validated(Request $request): array
    {
        if (! $request->filled('date') && $request->filled('deduction_date')) {
            $request->merge(['date' => $request->input('deduction_date')]);
        }

        $data = $request->validate([
            'date' => 'required|date',
            'staff_id' => ['required', Rule::exists('staff', 'id')],
            'amount' => 'required|numeric|min:0.01',
            'reason' => 'required|string|max:500',
        ]);

        return [
            'deduction_date' => $data['date'],
            'staff_id' => (int) $data['staff_id'],
            'amount' => round((float) $data['amount'], 2),
            'reason' => trim($data['reason']),
        ];
    }

    private function redirectFilterParams(Request $request): array
    {
        return array_filter(array_merge(
            ['tab' => 'deductions'],
            $request->only([
                'deductions_date',
                'deductions_from',
                'deductions_to',
                'complaints_staff',
                'complaints_search',
                'complaints_date',
                'complaints_from',
                'complaints_to',
            ])
        ), fn ($v) => $v !== null && $v !== '');
    }
}
