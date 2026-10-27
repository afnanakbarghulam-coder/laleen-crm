<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\StaffComplaint;
use App\Models\StaffDeduction;
use App\Models\StaffNotice;
use App\Support\GeminiNoticeDrafter;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class StaffComplaintController extends Controller
{
    public function store(Request $request)
    {
        $validated = $this->validated($request);
        $staffIds = $validated['staff_ids'];
        unset($validated['staff_ids']);
        $validated = $this->resolveCustomer($validated);

        $validated['deduction_applied'] = 'N';
        $validated['deduction_amount'] = null;
        $validated['created_by'] = auth()->id();

        $complaint = StaffComplaint::create($validated);
        $complaint->staffMembers()->sync($staffIds);

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => "Complaint {$complaint->reference_number} logged.",
                'entry' => $complaint->fresh(['staffMembers', 'service'])->toEditPayload(),
            ]);
        }

        return redirect()->route('staffs.index', ['tab' => 'complaints'])->with('success', 'Complaint logged.');
    }

    public function update(Request $request, StaffComplaint $staffComplaint)
    {
        if (!$request->has('staff_ids') && $request->hasAny(['what_happened', 'corrective_action', 'manager_feedback'])) {
            $data = $request->validate([
                'what_happened' => 'required|string|max:2000',
                'corrective_action' => 'nullable|string|max:2000',
                'manager_feedback' => 'nullable|string|max:2000',
            ]);

            $whatHappened = trim($data['what_happened']);
            $correctiveAction = isset($data['corrective_action']) && trim($data['corrective_action']) !== ''
                ? trim($data['corrective_action'])
                : null;
            $managerFeedback = isset($data['manager_feedback']) && trim($data['manager_feedback']) !== ''
                ? trim($data['manager_feedback'])
                : null;

            $staffComplaint->update([
                'what_happened' => $whatHappened,
                'description' => $whatHappened,
                'corrective_action' => $correctiveAction,
                'manager_feedback' => $managerFeedback,
            ]);

            if ($request->wantsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Complaint details updated.',
                    'entry' => $staffComplaint->fresh(['staffMembers', 'service'])->toEditPayload(),
                ]);
            }

            return redirect()->route('staffs.index', ['tab' => 'complaints'])->with('success', 'Complaint details updated.');
        }

        $validated = $this->validated($request);
        $staffIds = $validated['staff_ids'];
        unset($validated['staff_ids']);
        $validated = $this->resolveCustomer($validated);

        $staffComplaint->update($validated);
        $staffComplaint->staffMembers()->sync($staffIds);
        if ($request->has('deduction_applied')) {
            $this->syncDeduction($staffComplaint, $staffIds);
        }

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Complaint updated.',
                'entry' => $staffComplaint->fresh(['staffMembers', 'service'])->toEditPayload(),
            ]);
        }

        return redirect()->route('staffs.index', ['tab' => 'complaints'])->with('success', 'Complaint updated.');
    }

    /**
     * Resolve an incident from the HR Resolution panel inside the expanded
     * complaint accordion row: reads the nested staff[id] payload (mistake,
     * deduction, deduction_amount/amount, notice, corrective_action) and
     * generates StaffDeduction and StaffNotice records only for the staff
     * members where those specific options were toggled "Yes".
     */
    public function resolve(Request $request, StaffComplaint $staffComplaint)
    {
        $validated = $request->validate([
            'staff' => 'required|array|min:1',
            'staff.*.mistake' => 'nullable|in:yes,no,1,0,true,false,Y,N',
            'staff.*.deduction' => 'nullable|in:yes,no,1,0,true,false,Y,N',
            'staff.*.deduction_amount' => 'nullable|numeric|min:0',
            'staff.*.amount' => 'nullable|numeric|min:0',
            'staff.*.notice' => 'nullable',
            'staff.*.corrective_action' => 'nullable|string|max:2000',
        ]);

        $attachedStaffIds = $staffComplaint->staffMembers()->pluck('staff.id')->map(fn ($id) => (int) $id)->all();
        $existingNoticesByStaff = $staffComplaint->notices()->get()->keyBy('staff_id');

        $staffComplaint->deductions()->delete();
        $staffComplaint->notices()->delete();

        $totalDeduction = 0.0;
        $anyMistake = false;
        $whatHappened = $staffComplaint->what_happened ?: $staffComplaint->description;
        $subject = "Complaint {$staffComplaint->reference_number}: {$staffComplaint->category}";

        foreach ($validated['staff'] as $rawStaffId => $res) {
            $staffId = (int) $rawStaffId;
            if (!in_array($staffId, $attachedStaffIds, true)) {
                continue;
            }

            $isMistake = in_array(strtolower((string) ($res['mistake'] ?? 'no')), ['yes', '1', 'true', 'y'], true);
            if ($isMistake) {
                $anyMistake = true;
            }

            $isDeduction = $isMistake && in_array(strtolower((string) ($res['deduction'] ?? 'no')), ['yes', '1', 'true', 'y'], true);
            $deductionAmount = round((float) ($res['deduction_amount'] ?? $res['amount'] ?? 0), 2);

            $generateNotice = $isMistake && (
                filter_var($res['notice'] ?? false, FILTER_VALIDATE_BOOLEAN)
                || in_array(strtolower((string) ($res['notice'] ?? '')), ['yes', '1', 'on', 'y', 'true'], true)
            );

            $customAction = isset($res['corrective_action'])
                ? trim((string) $res['corrective_action'])
                : '';

            if ($isDeduction && $deductionAmount > 0) {
                $totalDeduction += $deductionAmount;
                StaffDeduction::create([
                    'staff_id' => $staffId,
                    'deduction_date' => $staffComplaint->complaint_date,
                    'amount' => $deductionAmount,
                    'reason' => "Complaint {$staffComplaint->reference_number}: {$staffComplaint->category}",
                    'complaint_id' => $staffComplaint->id,
                    'created_by' => auth()->id(),
                ]);
            }

            if ($generateNotice) {
                $priorNotice = $existingNoticesByStaff->get($staffId);
                StaffNotice::create([
                    'staff_id' => $staffId,
                    'complaint_id' => $staffComplaint->id,
                    'notice_date' => $priorNotice?->notice_date?->toDateString() ?? now()->toDateString(),
                    'type' => $priorNotice?->type ?? 'Written Warning',
                    'subject' => $subject,
                    'description' => $whatHappened,
                    'corrective_actions' => $customAction,
                    'acknowledged' => $priorNotice?->acknowledged ?? 'N',
                    'acknowledged_by' => $priorNotice?->acknowledged_by,
                    'acknowledged_at' => $priorNotice?->acknowledged_at,
                    'created_by' => $priorNotice?->created_by ?? auth()->id(),
                ]);
            }
        }

        $staffComplaint->update([
            'is_staff_mistake' => $anyMistake,
            'deduction_applied' => $totalDeduction > 0 ? 'Y' : 'N',
            'deduction_amount' => $totalDeduction > 0 ? round($totalDeduction, 2) : null,
        ]);

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => "Resolutions saved for {$staffComplaint->reference_number}.",
                'entry' => $staffComplaint->fresh(['staffMembers', 'service', 'deductions.staff', 'notices.staff'])->toEditPayload(),
            ]);
        }

        return redirect()->route('staffs.index', ['tab' => 'complaints'])
            ->with('success', "Resolutions saved for {$staffComplaint->reference_number}.");
    }

    public function destroy(Request $request, StaffComplaint $staffComplaint)
    {
        $staffComplaint->deductions()->delete();
        $staffComplaint->delete();

        if ($request->wantsJson()) {
            return response()->json(['success' => true, 'message' => 'Complaint deleted.']);
        }

        return redirect()->route('staffs.index', ['tab' => 'complaints'])->with('success', 'Complaint deleted.');
    }

    /**
     * Draft one staff notice per selected staff member, from the modal on a
     * complaint row — pre-filled with the complaint's reference/summary, plus
     * a corrective-actions section the user fills in before it's created.
     */
    public function generateNotice(Request $request, StaffComplaint $staffComplaint)
    {
        $validated = $request->validate([
            'staff_ids' => 'required|array|min:1',
            'staff_ids.*' => [Rule::exists('staff', 'id')],
            'type' => ['required', Rule::in(StaffNotice::TYPES)],
            'subject' => 'required|string|max:255',
            'summary' => 'required|string|max:2000',
            'corrective_actions' => 'nullable|string|max:2000',
        ]);

        $notices = collect($validated['staff_ids'])->map(fn ($staffId) => StaffNotice::create([
            'staff_id' => $staffId,
            'complaint_id' => $staffComplaint->id,
            'notice_date' => now()->toDateString(),
            'type' => $validated['type'],
            'subject' => $validated['subject'],
            'description' => $validated['summary'],
            'corrective_actions' => $validated['corrective_actions'] ?? null,
            'acknowledged' => 'N',
            'created_by' => auth()->id(),
        ]));

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => $notices->count() > 1 ? "{$notices->count()} staff notices drafted." : 'Staff notice drafted.',
                'notices' => $notices->map(fn ($n) => $n->fresh('staff')->toEditPayload())->values(),
                'entry' => $staffComplaint->fresh(['staffMembers', 'service'])->toEditPayload(),
            ]);
        }

        return redirect()->route('staffs.index', ['tab' => 'complaints'])->with('success', 'Staff notice(s) drafted.');
    }

    /**
     * AI-drafted "Summary of what happened" + "Corrective actions to be
     * taken" text for the Generate Staff Notice modal. Always succeeds —
     * falls back to a plain template if Gemini isn't configured or errors.
     */
    public function draftNoticeAi(StaffComplaint $staffComplaint, GeminiNoticeDrafter $drafter)
    {
        $draft = $drafter->draft($staffComplaint->load(['staffMembers', 'service']));

        return response()->json([
            'success' => true,
            'summary' => $draft['summary'],
            'corrective_actions' => $draft['corrective_actions'],
            'source' => $draft['source'],
            'note' => $draft['note'] ?? null,
        ]);
    }

    /**
     * Match an existing customer by phone, or create one — mirrors
     * AppointmentController::findOrCreateCustomer() so a complaint filed
     * against a new client transparently adds them to the client list.
     */
    private function resolveCustomer(array $validated): array
    {
        if (empty($validated['customer_phone'])) {
            $validated['customer_id'] = null;
            return $validated;
        }

        $customer = Customer::firstOrCreate(
            ['phone' => $validated['customer_phone']],
            ['name' => $validated['customer_name']]
        );

        $validated['customer_id'] = $customer->id;

        return $validated;
    }

    /**
     * Keep the linked StaffDeduction rows in lockstep with deduction_applied /
     * deduction_amount / the current staff list, so the Payroll & Overtime tab
     * always reflects the complaint's current state. The entered amount is
     * split evenly across the involved staff (any rounding remainder lands
     * on the last one so the total deducted matches exactly); existing
     * synced deductions are replaced rather than patched, since the set of
     * staff or the amount may have changed.
     */
    private function syncDeduction(StaffComplaint $complaint, array $staffIds): void
    {
        $complaint->deductions()->delete();

        if ($complaint->deduction_applied !== 'Y' || !$complaint->deduction_amount || empty($staffIds)) {
            return;
        }

        $count = count($staffIds);
        $base = floor(($complaint->deduction_amount / $count) * 100) / 100;
        $remainder = round($complaint->deduction_amount - ($base * $count), 2);
        $reason = "Complaint {$complaint->reference_number}: {$complaint->category}" . ($count > 1 ? " (split {$count} ways)" : '');

        foreach (array_values($staffIds) as $index => $staffId) {
            StaffDeduction::create([
                'staff_id' => $staffId,
                'deduction_date' => $complaint->complaint_date,
                'amount' => $base + ($index === $count - 1 ? $remainder : 0),
                'reason' => $reason,
                'complaint_id' => $complaint->id,
                'created_by' => auth()->id(),
            ]);
        }
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'staff_ids' => 'required|array|min:1',
            'staff_ids.*' => [Rule::exists('staff', 'id')],
            'complaint_date' => 'required|date',
            'complaint_time' => 'nullable|date_format:H:i',
            'branch' => ['required', Rule::in(array_keys(StaffComplaint::BRANCHES))],
            'customer_name' => 'required|string|max:255',
            'customer_phone' => 'nullable|string|max:30',
            'service_id' => ['required', Rule::exists('services', 'id')],
            'category' => ['required', Rule::in(StaffComplaint::CATEGORIES)],
            'what_happened' => 'nullable|string|max:2000',
            'description' => 'nullable|string|max:2000',
            'corrective_action' => 'nullable|string|max:2000',
            'manager_feedback' => 'nullable|string|max:2000',
            'is_staff_mistake' => 'nullable|boolean',
            'deduction_applied' => ['nullable', Rule::in(['Y', 'N'])],
            'deduction_amount' => 'nullable|required_if:deduction_applied,Y|numeric|min:0',
        ]);

        $whatHappened = trim((string) ($data['what_happened'] ?? $data['description'] ?? ''));
        if ($whatHappened === '') {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'what_happened' => ['The What Happened field is required.'],
            ]);
        }

        $data['what_happened'] = $whatHappened;
        $data['description'] = $whatHappened;

        if (!array_key_exists('deduction_applied', $data) || $data['deduction_applied'] === null) {
            unset($data['deduction_applied'], $data['deduction_amount']);
        }

        return $data;
    }
}
