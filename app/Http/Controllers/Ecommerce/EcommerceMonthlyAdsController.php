<?php

namespace App\Http\Controllers\Ecommerce;

use App\Http\Controllers\Controller;
use App\Models\EcommerceMonthlyAd;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class EcommerceMonthlyAdsController extends Controller
{
    public function index()
    {
        $ads = EcommerceMonthlyAd::orderByDesc('id')->get();

        return view('ecommerce.marketing.ads-plan', [
            'adsByPhase' => collect(EcommerceMonthlyAd::BOARD_PHASES)
                ->mapWithKeys(fn ($phase) => [$phase => $ads->where('phase', $phase)->values()]),
            'killedAds' => $ads->where('phase', 'Killed')->values(),
            'staff' => User::orderBy('name')->get(['id', 'name']),
            'scalingStatuses' => EcommerceMonthlyAd::SCALING_STATUSES,
        ]);
    }

    public function store(Request $request)
    {
        EcommerceMonthlyAd::create($this->validated($request));

        return back()->with('success', 'Ad added to Planning.');
    }

    public function update(Request $request, EcommerceMonthlyAd $ad)
    {
        $ad->update($this->validated($request));

        return back()->with('success', 'Ad updated.');
    }

    public function destroy(EcommerceMonthlyAd $ad)
    {
        $ad->delete();

        return back()->with('success', 'Ad deleted.');
    }

    /**
     * Planning -> Production.
     *
     * The brief has to be complete before anyone starts making creative,
     * otherwise production works against a moving target.
     */
    public function completePlanning(EcommerceMonthlyAd $ad)
    {
        if ($ad->phase !== 'Planning') {
            return back()->withErrors(['error' => 'Only an ad in Planning can be moved to Production.']);
        }

        $missing = [];
        if (blank($ad->offer)) {
            $missing[] = 'Offer';
        }
        if ($ad->total_budget === null || (float) $ad->total_budget <= 0) {
            $missing[] = 'Total Budget';
        }
        if (blank($ad->pre_filled_message)) {
            $missing[] = 'Pre-filled Message';
        }
        if (blank($ad->live_date)) {
            $missing[] = 'Live Date';
        }

        if (!empty($missing)) {
            return back()->withErrors(['error' => 'Planning Gate Failed: ' . implode(', ', $missing) . ' must be filled before moving to Production.']);
        }

        $ad->update(['phase' => 'Production']);

        return back()->with('success', 'Phase 1 complete — moved to Production.');
    }

    /**
     * Production -> Testing.
     *
     * Blocked until the creative actually exists AND all four QC points are
     * both ticked and justified in writing.
     */
    public function completeProduction(EcommerceMonthlyAd $ad)
    {
        if ($ad->phase !== 'Production') {
            return back()->withErrors(['error' => 'Only an ad in Production can be moved to Testing.']);
        }

        $problems = [];
        if (blank($ad->creative_asset_link)) {
            $problems[] = 'a Creative Asset Link';
        }

        $missingPoints = $ad->missingChecklistPoints();
        if (!empty($missingPoints)) {
            $problems[] = 'all 4 QC points ticked AND described (missing: ' . implode(', ', $missingPoints) . ')';
        }

        if (!empty($problems)) {
            return back()->withErrors(['error' => 'Production Gate Failed: This ad needs ' . implode(' and ', $problems) . ' before testing can start.']);
        }

        $ad->update(['phase' => 'Testing']);

        return back()->with('success', 'Phase 2 complete — moved to Testing.');
    }

    /**
     * Testing -> Scaling.
     *
     * Purely a CPL decision: real cost per lead must beat the target CPL.
     * Spending money without leads can never pass, since actualCpl() is null.
     */
    public function scale(Request $request, EcommerceMonthlyAd $ad)
    {
        if ($ad->phase !== 'Testing') {
            return back()->withErrors(['error' => 'Only an ad in Testing can be scaled.']);
        }

        $validated = $request->validate([
            'scaling_status' => ['required', Rule::in(EcommerceMonthlyAd::SCALING_STATUSES)],
        ]);

        if ($ad->test_spend === null || (float) $ad->test_spend <= 0) {
            return back()->withErrors(['error' => 'Scaling Gate Failed: record the Test Spend before scaling.']);
        }

        if ((int) $ad->leads_generated <= 0) {
            return back()->withErrors(['error' => 'Scaling Gate Failed: record Leads Generated before scaling — spend with no leads cannot be scaled.']);
        }

        if ($ad->target_cpl === null || (float) $ad->target_cpl <= 0) {
            return back()->withErrors(['error' => 'Scaling Gate Failed: set a Target CPL so performance can be judged against it.']);
        }

        $actual = $ad->actualCpl();
        if ($actual > (float) $ad->target_cpl) {
            return back()->withErrors(['error' => sprintf(
                'Scaling Gate Failed: actual CPL is PKR %s against a target of PKR %s. Scaling a loss just loses money faster — optimise or kill it.',
                number_format($actual, 2),
                number_format((float) $ad->target_cpl, 2)
            )]);
        }

        $ad->update([
            'phase' => 'Scaling',
            'scaling_status' => $validated['scaling_status'],
        ]);

        return back()->with('success', sprintf(
            'Winner confirmed at PKR %s CPL — moved to Scaling.',
            number_format($actual, 2)
        ));
    }

    /** Testing / Scaling -> Killed. */
    public function kill(EcommerceMonthlyAd $ad)
    {
        if (!in_array($ad->phase, ['Testing', 'Scaling'], true)) {
            return back()->withErrors(['error' => 'Only an ad in Testing or Scaling can be killed.']);
        }

        if ($ad->test_spend === null) {
            return back()->withErrors(['error' => 'Kill Gate Failed: record the Test Spend first so the graveyard preserves what the lesson cost.']);
        }

        $ad->update(['phase' => 'Killed']);

        return back()->with('success', 'Ad moved to the graveyard.');
    }

    private function validated(Request $request): array
    {
        $validated = $request->validate([
            'ad_name' => 'required|string|max:255',
            'person_responsible' => 'nullable|string|max:255',
            'offer' => 'nullable|string|max:5000',
            'total_budget' => 'nullable|numeric|min:0',
            'pre_filled_message' => 'nullable|string|max:5000',
            'live_date' => 'nullable|date',
            'raw_data_deadline' => 'nullable|date',
            'final_creative_deadline' => 'nullable|date|before_or_equal:live_date',
            'creative_asset_link' => 'nullable|string|max:2048',
            'txt_hook' => 'nullable|string|max:2000',
            'txt_value' => 'nullable|string|max:2000',
            'txt_risk' => 'nullable|string|max:2000',
            'txt_cta' => 'nullable|string|max:2000',
            'test_spend' => 'nullable|numeric|min:0',
            'leads_generated' => 'nullable|integer|min:0',
            'target_cpl' => 'nullable|numeric|min:0',
            'scaling_status' => ['nullable', Rule::in(EcommerceMonthlyAd::SCALING_STATUSES)],
        ], [
            'final_creative_deadline.before_or_equal' => 'The final creative deadline cannot fall after the Live Date.',
        ]);

        // Unchecked boxes are omitted from the request entirely, so read them
        // explicitly rather than trusting validate() to default them.
        foreach (['hook', 'value', 'risk', 'cta'] as $point) {
            $validated["chk_{$point}"] = $request->boolean("chk_{$point}");
        }

        return $validated;
    }
}
