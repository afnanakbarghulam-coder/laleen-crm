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

        $complaint = StaffComplaint::create($validated + ['created_by' => auth()->id()]);
        $complaint->staffMembers()->sync($staffIds);
        $this->syncDeduction($complaint, $staffIds);

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
        $validated = $this->validated($request);
        $staffIds = $validated['staff_ids'];
        unset($validated['staff_ids']);
        $validated = $this->resolveCustomer($validated);

        $staffComplaint->update($validated);
        $staffComplaint->staffMembers()->sync($staffIds);
        $this->syncDeduction($staffComplaint, $staffIds);

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Complaint updated.',
                'entry' => $staffComplaint->fresh(['staffMembers', 'service'])->toEditPayload(),
            ]);
        }

        return redirect()->route('staffs.index', ['tab' => 'complaints'])->with('success', 'Complaint updated.');
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

        return redirect()->route('staffs.index', ['tab' => 'notices'])->with('success', 'Staff notice(s) drafted.');
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
        return $request->validate([
            'staff_ids' => 'required|array|min:1',
            'staff_ids.*' => [Rule::exists('staff', 'id')],
            'complaint_date' => 'required|date',
            'complaint_time' => 'nullable|date_format:H:i',
            'branch' => ['required', Rule::in(array_keys(StaffComplaint::BRANCHES))],
            'customer_name' => 'required|string|max:255',
            'customer_phone' => 'nullable|string|max:30',
            'service_id' => ['required', Rule::exists('services', 'id')],
            'category' => ['required', Rule::in(StaffComplaint::CATEGORIES)],
            'description' => 'required|string|max:2000',
            'deduction_applied' => ['required', Rule::in(['Y', 'N'])],
            'deduction_amount' => 'nullable|required_if:deduction_applied,Y|numeric|min:0',
        ]);
    }
}
