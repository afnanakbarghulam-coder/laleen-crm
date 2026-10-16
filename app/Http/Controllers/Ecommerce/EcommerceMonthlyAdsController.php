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

        $adsByPhase = collect(EcommerceMonthlyAd::BOARD_PHASES)
            ->mapWithKeys(fn ($phase) => [$phase => $ads->where('phase', $phase)->values()]);

        $killedAds = $ads->where('phase', 'Killed')->values();

        $totalPlannedBudget = $ads->sum('total_budget');

        $staff = User::orderBy('name')->get(['id', 'name']);

        $scalingStatuses = EcommerceMonthlyAd::SCALING_STATUSES;

        return view('ecommerce.marketing.ads-plan', compact(
            'adsByPhase',
            'killedAds',
            'staff',
            'scalingStatuses',
            'totalPlannedBudget'
        ));
    }

    public function store(Request $request)
    {
        EcommerceMonthlyAd::create($this->validated($request));

        return redirect()->route('ecommerce.marketing.ads-plan.index')->with('success', 'Ad added.');
    }

    public function update(Request $request, EcommerceMonthlyAd $ad)
    {
        $validated = $this->validated($request, $ad);

        // Server-side phase locking: a card can only edit the fields that belong
        // to its current (or an earlier) phase — later-phase fields are handled
        // by their own gated actions (completePlanning, completeProduction, scale, kill).
        if ($ad->phase !== 'Planning') {
            $validated = collect($validated)->except([
                'ad_name',
                'person_responsible',
                'offer',
                'total_budget',
                'pre_filled_message',
                'live_date',
            ])->all();
        }

        if (!in_array($ad->phase, ['Planning', 'Production'], true)) {
            $validated = collect($validated)->except([
                'raw_data_deadline',
                'final_creative_deadline',
                'creative_asset_link',
                'chk_hook',
                'chk_value',
                'chk_risk',
                'chk_cta',
                'txt_hook',
                'txt_value',
                'txt_risk',
                'txt_cta',
            ])->all();
        }

        $ad->update($validated);

        return redirect()->route('ecommerce.marketing.ads-plan.index')->with('success', 'Ad updated.');
    }

    public function destroy(EcommerceMonthlyAd $ad)
    {
        $ad->delete();

        return redirect()->route('ecommerce.marketing.ads-plan.index')->with('success', 'Ad deleted.');
    }

    /**
     * Mark Phase 1 ("Planning") complete and advance to Production. Only the
     * staff member named in person_responsible may do this, and all Phase 1
     * brief fields must be filled in first.
     */
    public function completePlanning(Request $request, EcommerceMonthlyAd $ad)
    {
        if ($ad->phase !== 'Planning') {
            abort(403, 'Only an ad in Planning can be moved to Production.');
        }

        if ($ad->person_responsible !== $request->user()->name) {
            abort(403, 'Only the assigned person can complete this phase.');
        }

        if (
            empty($ad->offer)
            || $ad->total_budget === null
            || (float) $ad->total_budget <= 0
            || empty($ad->pre_filled_message)
            || empty($ad->live_date)
        ) {
            return redirect()->back()->with('error', 'Planning Gate Failed: Offer, Total Budget, Pre-filled Message, and Live Date must all be filled before moving to Production.');
        }

        $ad->update(['phase' => 'Production']);

        return redirect()->route('ecommerce.marketing.ads-plan.index')->with('success', 'Phase 1 completed — moved to Production.');
    }

    /**
     * Mark Phase 2 ("Production") complete and advance to Testing. Blocked
     * until both production deadlines are set, the creative asset link is
     * provided, and all 4 QC checklist items (Hook, Value, Risk, CTA) are
     * both ticked AND justified with text descriptions.
     */
    public function completeProduction(Request $request, EcommerceMonthlyAd $ad)
    {
        if ($ad->phase !== 'Production') {
            abort(403, 'Only an ad in Production can be moved to Testing.');
        }

        if (
            empty($ad->raw_data_deadline)
            || empty($ad->final_creative_deadline)
            || empty($ad->creative_asset_link)
            || !$ad->chk_hook || empty(trim((string) $ad->txt_hook))
            || !$ad->chk_value || empty(trim((string) $ad->txt_value))
            || !$ad->chk_risk || empty(trim((string) $ad->txt_risk))
            || !$ad->chk_cta || empty(trim((string) $ad->txt_cta))
        ) {
            return redirect()->back()->with('error', 'Production Gate Failed: Raw Data Deadline, Final Creative Deadline, Creative Asset Link, and all 4 QC Checklist items (checked + described) must be completed before moving to Testing.');
        }

        $ad->update(['phase' => 'Testing']);

        return redirect()->route('ecommerce.marketing.ads-plan.index')->with('success', 'Phase 2 completed — moved to Testing.');
    }

    /**
     * Mark Phase 3 ("Testing") a winner and advance to Scaling. Enforces the
     * strict CPL gatekeeper rule: Test Spend, Leads Generated (> 0), and
     * Target CPL must be recorded, and Actual CPL must be <= Target CPL.
     */
    public function scale(Request $request, EcommerceMonthlyAd $ad)
    {
        if ($ad->phase !== 'Testing') {
            abort(403, 'Only an ad in Testing can be scaled.');
        }

        $validated = $request->validate([
            'scaling_status' => ['nullable', Rule::in(EcommerceMonthlyAd::SCALING_STATUSES)],
        ]);

        if (
            $ad->test_spend === null || (float) $ad->test_spend <= 0
            || (int) $ad->leads_generated <= 0
            || $ad->target_cpl === null || (float) $ad->target_cpl <= 0
        ) {
            return redirect()->back()->with('error', 'Scaling Gate Failed: You must save Test Spend, Leads Generated (> 0), and Target CPL inside the ad modal before scaling.');
        }

        $actual = $ad->actualCpl();
        if ($actual === null || $actual > (float) $ad->target_cpl) {
            return redirect()->back()->with('error', sprintf(
                'Scaling Gate Failed: Actual CPL (PKR %s) exceeds Target CPL (PKR %s). Scaling a loss just loses money faster — optimise or kill this test.',
                $actual !== null ? number_format($actual, 2) : '—',
                number_format((float) $ad->target_cpl, 2)
            ));
        }

        $scalingStatus = $validated['scaling_status'] ?? $ad->scaling_status ?? 'Scale Aggressively';

        $ad->update([
            'phase' => 'Scaling',
            'scaling_status' => $scalingStatus,
        ]);

        return redirect()->route('ecommerce.marketing.ads-plan.index')->with('success', sprintf(
            'Winner confirmed at PKR %s CPL — moved to Scaling.',
            number_format($actual, 2)
        ));
    }

    /**
     * Kill a test from Testing or Scaling. Requires Test Spend, Leads Generated,
     * and Target CPL to be saved first so the Graveyard preserves why the ad
     * was killed for future learning.
     */
    public function kill(Request $request, EcommerceMonthlyAd $ad)
    {
        if (!in_array($ad->phase, ['Testing', 'Scaling'], true)) {
            abort(403, 'Only an ad in Testing or Scaling can be killed.');
        }

        if (
            $ad->test_spend === null
            || $ad->leads_generated === null
            || $ad->target_cpl === null
        ) {
            return redirect()->back()->with('error', 'Kill Gate Failed: Test Spend, Leads Generated, and Target CPL must be saved inside the ad modal before killing this test.');
        }

        $ad->update(['phase' => 'Killed']);

        return redirect()->route('ecommerce.marketing.ads-plan.index')->with('success', 'Ad moved to the Graveyard.');
    }

    private function validated(Request $request, ?EcommerceMonthlyAd $ad = null): array
    {
        $briefLocked = $ad !== null && $ad->phase !== 'Planning';

        $rawDeadlineRules = ['nullable', 'date'];
        if ($request->filled('final_creative_deadline')) {
            $rawDeadlineRules[] = 'before_or_equal:final_creative_deadline';
        }

        $finalDeadlineRules = ['nullable', 'date'];
        if ($request->filled('live_date')) {
            $finalDeadlineRules[] = 'before_or_equal:live_date';
        }

        $validated = $request->validate([
            'ad_name' => ($briefLocked ? 'sometimes|' : '') . 'required|string|max:255',
            'person_responsible' => ($briefLocked ? 'sometimes|' : '') . 'required|string|max:255',
            'offer' => 'nullable|string|max:5000',
            'total_budget' => 'nullable|numeric|min:0',
            'pre_filled_message' => 'nullable|string|max:5000',
            'live_date' => 'nullable|date',
            'raw_data_deadline' => $rawDeadlineRules,
            'final_creative_deadline' => $finalDeadlineRules,
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
            'final_creative_deadline.before_or_equal' => 'The final creative deadline cannot be after the Ad Live Date.',
            'raw_data_deadline.before_or_equal' => 'The raw data deadline cannot be after the Final Creative Deadline.',
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

