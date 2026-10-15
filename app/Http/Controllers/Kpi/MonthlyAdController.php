<?php

namespace App\Http\Controllers\Kpi;

use App\Http\Controllers\Controller;
use App\Models\KpiMonthlyAd;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class MonthlyAdController extends Controller
{
    public function index()
    {
        $ads = KpiMonthlyAd::orderByDesc('id')->get();

        $adsByPhase = collect(KpiMonthlyAd::BOARD_PHASES)
            ->mapWithKeys(fn ($phase) => [$phase => $ads->where('phase', $phase)->values()]);

        $staff = User::orderBy('name')->get(['id', 'name']);

        return view('kpi.ads-plan.index', compact('adsByPhase', 'staff'));
    }

    public function store(Request $request)
    {
        KpiMonthlyAd::create($this->validated($request));

        return redirect()->route('kpi.ads-plan.index')->with('success', 'Ad added.');
    }

    public function update(Request $request, KpiMonthlyAd $monthlyAd)
    {
        $monthlyAd->update($this->validated($request));

        return redirect()->route('kpi.ads-plan.index')->with('success', 'Ad updated.');
    }

    public function destroy(KpiMonthlyAd $monthlyAd)
    {
        $monthlyAd->delete();

        return redirect()->route('kpi.ads-plan.index')->with('success', 'Ad deleted.');
    }

    /** Quick phase change from the Kanban card's inline <select> — only touches the phase column. */
    public function updatePhase(Request $request, KpiMonthlyAd $monthlyAd)
    {
        $validated = $request->validate([
            'phase' => ['required', Rule::in(KpiMonthlyAd::PHASES)],
        ]);

        $monthlyAd->update($validated);

        return redirect()->route('kpi.ads-plan.index')->with('success', 'Ad moved to ' . $validated['phase'] . '.');
    }

    /**
     * Mark Phase 1 ("Planning") complete and advance to Production. Only the
     * staff member named in person_responsible may do this — that column
     * stores the assignee's name (selected from the staff dropdown), not a
     * user id, since person_responsible isn't a foreign key.
     */
    public function completePlanning(Request $request, KpiMonthlyAd $monthlyAd)
    {
        if ($monthlyAd->person_responsible !== $request->user()->name) {
            abort(403, 'Only the assigned person can complete this phase.');
        }

        $monthlyAd->update(['phase' => 'Production']);

        return redirect()->route('kpi.ads-plan.index')->with('success', 'Phase 1 completed — moved to Production.');
    }

    /**
     * Mark Phase 2 ("Production") complete and advance to Testing. Blocked
     * until the asset link is filled in and all 4 creative guidelines
     * (Hook, Value, Risk, CTA) are both ticked AND have a text description —
     * the checkbox alone is too easy to rubber-stamp.
     */
    public function completeProduction(Request $request, KpiMonthlyAd $monthlyAd)
    {
        if (
            empty($monthlyAd->creative_asset_link)
            || !$monthlyAd->chk_hook || empty($monthlyAd->txt_hook)
            || !$monthlyAd->chk_value || empty($monthlyAd->txt_value)
            || !$monthlyAd->chk_risk || empty($monthlyAd->txt_risk)
            || !$monthlyAd->chk_cta || empty($monthlyAd->txt_cta)
        ) {
            return redirect()->back()->with('error', 'Quality Control Failed: All 4 Creative Guidelines must be checked AND their text descriptions must be filled out before moving to Testing.');
        }

        $monthlyAd->update(['phase' => 'Testing']);

        return redirect()->route('kpi.ads-plan.index')->with('success', 'Phase 2 completed — moved to Testing.');
    }

    private function validated(Request $request): array
    {
        $validated = $request->validate([
            'ad_name' => 'required|string|max:255',
            'person_responsible' => 'required|string|max:255',
            'pre_filled_message' => 'nullable|string',
            'offer' => 'nullable|string',
            'live_date' => 'nullable|date',
            'raw_data_deadline' => 'nullable|date',
            'final_creative_deadline' => 'nullable|date|before_or_equal:live_date',
            'total_budget' => 'nullable|numeric|min:0',
            'creative_asset_link' => 'nullable|string|max:2048',
            'txt_hook' => 'nullable|string',
            'txt_value' => 'nullable|string',
            'txt_risk' => 'nullable|string',
            'txt_cta' => 'nullable|string',
        ], [
            'final_creative_deadline.before_or_equal' => 'The final creative deadline cannot be after the Ad Live Date.',
        ]);

        // Unchecked checkboxes are omitted from the request entirely, so read
        // them explicitly rather than relying on validate() to default them.
        $validated['chk_hook'] = $request->boolean('chk_hook');
        $validated['chk_value'] = $request->boolean('chk_value');
        $validated['chk_risk'] = $request->boolean('chk_risk');
        $validated['chk_cta'] = $request->boolean('chk_cta');

        return $validated;
    }
}
