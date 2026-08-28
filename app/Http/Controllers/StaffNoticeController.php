<?php

namespace App\Http\Controllers;

use App\Models\StaffNotice;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Notices are drafted exclusively via StaffComplaintController::generateNotice()
 * from the Complaints & Feedback module — this controller only handles
 * reviewing (editing/acknowledging) and removing them afterwards.
 */
class StaffNoticeController extends Controller
{
    public function update(Request $request, StaffNotice $staffNotice)
    {
        $staffNotice->update($this->validated($request));

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Notice updated.',
                'entry' => $staffNotice->fresh('staff')->toEditPayload(),
            ]);
        }

        return redirect()->route('staffs.index', ['tab' => 'notices'])->with('success', 'Notice updated.');
    }

    public function destroy(Request $request, StaffNotice $staffNotice)
    {
        $staffNotice->delete();

        if ($request->wantsJson()) {
            return response()->json(['success' => true, 'message' => 'Notice deleted.']);
        }

        return redirect()->route('staffs.index', ['tab' => 'notices'])->with('success', 'Notice deleted.');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'staff_id' => ['required', Rule::exists('staff', 'id')],
            'notice_date' => 'required|date',
            'type' => ['required', Rule::in(StaffNotice::TYPES)],
            'subject' => 'required|string|max:255',
            'description' => 'nullable|string|max:2000',
            'corrective_actions' => 'nullable|string|max:2000',
            'acknowledged' => ['required', Rule::in(['Y', 'N'])],
        ]);
    }
}
