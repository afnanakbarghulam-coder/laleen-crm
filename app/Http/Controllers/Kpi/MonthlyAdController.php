<?php

namespace App\Http\Controllers\Kpi;

use App\Http\Controllers\Controller;
use App\Models\KpiMonthlyAd;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class MonthlyAdController extends Controller
{
    public function index(Request $request)
    {
        $selectedMonth = $request->query('month', now()->format('Y-m'));

        $availableMonths = KpiMonthlyAd::query()
            ->whereNotNull('plan_month')
            ->distinct()
            ->pluck('plan_month')
            ->push(now()->format('Y-m'))
            ->push(now()->addMonthNoOverflow()->format('Y-m'))
            ->unique()
            ->sort()
            ->values();

        $query = KpiMonthlyAd::orderByDesc('id');
        if ($selectedMonth !== 'all') {
            $query->where('plan_month', $selectedMonth);
        }
        $ads = $query->get();

        $adsByPhase = collect(KpiMonthlyAd::BOARD_PHASES)
            ->mapWithKeys(fn ($phase) => [$phase => $ads->where('phase', $phase)->values()]);

        $killedAds = $ads->where('phase', 'Killed')->values();

        $totalPlannedBudget = $ads->sum('total_budget');

        $staff = User::orderBy('name')->get(['id', 'name']);

        return view('kpi.ads-plan.index', compact(
            'adsByPhase',
            'killedAds',
            'staff',
            'selectedMonth',
            'availableMonths',
            'totalPlannedBudget'
        ));
    }

    public function store(Request $request)
    {
        KpiMonthlyAd::create($this->validated($request));

        return redirect()->route('kpi.ads-plan.index')->with('success', 'Ad added.');
    }

    public function update(Request $request, KpiMonthlyAd $monthlyAd)
    {
        $validated = $this->validated($request);

        // Server-side phase locking: a card can only edit the fields that belong
        // to its current (or an earlier) phase — later-phase fields are handled
        // by their own gated actions (completeProduction, scaleAd, killAd, etc).
        if ($monthlyAd->phase !== 'Planning') {
            $validated = collect($validated)->except([
                'ad_name',
                'person_responsible',
                'pre_filled_message',
                'offer',
                'plan_month',
                'target_branch',
                'offer_price',
                'live_date',
                'total_budget',
            ])->all();
        }

        if (!in_array($monthlyAd->phase, ['Planning', 'Production'], true)) {
            $validated = collect($validated)->except([
                'raw_data_status',
                'raw_data_deadline',
                'final_creative_deadline',
                'creative_1_name',
                'creative_asset_link',
                'creative_2_name',
                'creative_asset_link_2',
                'creative_3_name',
                'creative_asset_link_3',
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

        $monthlyAd->update($validated);

        return redirect()->route('kpi.ads-plan.index')->with('success', 'Ad updated.');
    }

    public function destroy(KpiMonthlyAd $monthlyAd)
    {
        $monthlyAd->delete();

        return redirect()->route('kpi.ads-plan.index')->with('success', 'Ad deleted.');
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

        if (
            empty($monthlyAd->offer)
            || $monthlyAd->offer_price === null
            || $monthlyAd->total_budget === null
            || empty($monthlyAd->pre_filled_message)
            || empty($monthlyAd->live_date)
        ) {
            return redirect()->back()->with('error', 'Planning Gate Failed: Offer, Offer Price, Total Budget, Pre-filled Message, and Live Date must all be filled before moving to Production.');
        }

        $monthlyAd->update([
            'phase' => 'Production',
            'production_started_at' => now()->toDateString(),
        ]);

        return redirect()->route('kpi.ads-plan.index')->with('success', 'Phase 1 completed — moved to Production.');
    }

    /**
     * Mark Phase 2 ("Production") complete and advance to Testing. Blocked
     * until the Raw Data status is resolved, all 3 named creatives (name +
     * link) are filled, and all 4 creative guidelines (Hook, Value, Risk,
     * CTA) are both ticked AND have a text description — the checkbox alone
     * is too easy to rubber-stamp.
     */
    public function completeProduction(Request $request, KpiMonthlyAd $monthlyAd)
    {
        $rawDataReady = $monthlyAd->raw_data_status === 'available' || !empty($monthlyAd->raw_data_deadline);

        if (
            !$rawDataReady
            || empty($monthlyAd->final_creative_deadline)
            || empty($monthlyAd->creative_1_name) || empty($monthlyAd->creative_asset_link)
            || empty($monthlyAd->creative_2_name) || empty($monthlyAd->creative_asset_link_2)
            || empty($monthlyAd->creative_3_name) || empty($monthlyAd->creative_asset_link_3)
            || !$monthlyAd->chk_hook || empty($monthlyAd->txt_hook)
            || !$monthlyAd->chk_value || empty($monthlyAd->txt_value)
            || !$monthlyAd->chk_risk || empty($monthlyAd->txt_risk)
            || !$monthlyAd->chk_cta || empty($monthlyAd->txt_cta)
        ) {
            return redirect()->back()->with('error', 'Production Gate Failed: Raw Data status, Final Creative Deadline, all 3 Named Creatives (name + link), and all 4 QC Checklist items (checked + described) must be completed before starting the 7-Day Test.');
        }

        $monthlyAd->update([
            'phase' => 'Testing',
            'testing_started_at' => now()->toDateString(),
            'testing_ends_at' => now()->addDays(7)->toDateString(),
        ]);

        return redirect()->route('kpi.ads-plan.index')->with('success', 'Phase 2 completed — moved to Testing.');
    }

    /**
     * Mark Phase 3 ("Testing") a winner and advance to Scaling. Requires the
     * media buyer to have already recorded a winning creative, its cost per
     * message, and a funnel verdict inside the ad modal.
     */
    public function scaleAd(Request $request, KpiMonthlyAd $monthlyAd)
    {
        if ($monthlyAd->phase !== 'Testing') {
            abort(403, 'Only an ad in Testing can be scaled.');
        }

        if (
            !in_array($monthlyAd->winning_creative, ['Creative 1', 'Creative 2', 'Creative 3'], true)
            || !($monthlyAd->cost_per_message > 0)
            || empty($monthlyAd->sales_verdict)
        ) {
            return redirect()->back()->with('error', 'Scaling Gate Failed: You must save a Winning Creative (C1/C2/C3), a Cost Per Message (QAR), and a Funnel Verdict inside the ad modal before scaling.');
        }

        $monthlyAd->update([
            'phase' => 'Scaling',
            'verdict_at' => now()->toDateString(),
        ]);

        return redirect()->route('kpi.ads-plan.index')->with('success', 'Winner confirmed — moved to Scaling.');
    }

    /**
     * Kill a test from Testing or Scaling. Requires the media buyer's cost,
     * verdict, and notes to already be saved so the Graveyard preserves why
     * the ad was killed for future learning.
     */
    public function killAd(Request $request, KpiMonthlyAd $monthlyAd)
    {
        if (!in_array($monthlyAd->phase, ['Testing', 'Scaling'], true)) {
            abort(403, 'Only an ad in Testing or Scaling can be killed.');
        }

        if (
            $monthlyAd->cost_per_message === null
            || empty($monthlyAd->sales_verdict)
            || empty($monthlyAd->testing_notes)
        ) {
            return redirect()->back()->with('error', 'Kill Gate Failed: Cost Per Message, Funnel Verdict, and Audience & Funnel Notes must be saved before killing this test.');
        }

        $monthlyAd->update([
            'phase' => 'Killed',
            'verdict_at' => now()->toDateString(),
        ]);

        return redirect()->route('kpi.ads-plan.index')->with('success', 'Ad moved to the Graveyard.');
    }

    private function validated(Request $request): array
    {
        $validated = $request->validate([
            'plan_month' => 'required|date_format:Y-m',
            'ad_name' => 'required|string|max:255',
            'person_responsible' => 'required|string|max:255',
            'pre_filled_message' => 'nullable|string',
            'offer' => 'nullable|string',
            'target_branch' => ['required', Rule::in(KpiMonthlyAd::BRANCHES)],
            'offer_price' => 'nullable|numeric|min:0',
            'live_date' => 'nullable|date',
            'raw_data_status' => ['nullable', Rule::in(KpiMonthlyAd::RAW_DATA_STATUSES)],
            'raw_data_deadline' => 'nullable|date|before_or_equal:final_creative_deadline',
            'final_creative_deadline' => 'nullable|date|before_or_equal:live_date',
            'total_budget' => 'nullable|numeric|min:0',
            'creative_1_name' => 'nullable|string|max:255',
            'creative_asset_link' => 'nullable|string|max:2048',
            'creative_2_name' => 'nullable|string|max:255',
            'creative_asset_link_2' => 'nullable|string|max:2048',
            'creative_3_name' => 'nullable|string|max:255',
            'creative_asset_link_3' => 'nullable|string|max:2048',
            'txt_hook' => 'nullable|string',
            'txt_value' => 'nullable|string',
            'txt_risk' => 'nullable|string',
            'txt_cta' => 'nullable|string',
            'winning_creative' => ['nullable', Rule::in(KpiMonthlyAd::WINNING_CREATIVES)],
            'cost_per_message' => 'nullable|numeric|min:0',
            'testing_ends_at' => 'nullable|date',
            'sales_verdict' => 'nullable|string|max:150',
            'sales_verdict_custom' => 'nullable|string|max:150',
            'testing_notes' => 'nullable|string',
        ], [
            'final_creative_deadline.before_or_equal' => 'The final creative deadline cannot be after the Ad Live Date.',
            'raw_data_deadline.before_or_equal' => 'The raw data deadline cannot be after the Final Creative Deadline.',
        ]);

        // The column itself is non-nullable with a DB default, so an absent
        // or blank selection falls back to it here rather than erroring.
        $validated['raw_data_status'] = $validated['raw_data_status'] ?: 'scheduled';

        if ($validated['raw_data_status'] === 'available') {
            $validated['raw_data_deadline'] = null;
        }

        // A custom funnel verdict category is typed into its own field and
        // only promoted into sales_verdict once the sentinel is selected.
        if ($request->input('sales_verdict') === '__custom__') {
            $request->validate(['sales_verdict_custom' => 'required|string|max:150']);
            $validated['sales_verdict'] = $request->input('sales_verdict_custom');
        }
        unset($validated['sales_verdict_custom']);

        // Unchecked checkboxes are omitted from the request entirely, so read
        // them explicitly rather than relying on validate() to default them.
        $validated['chk_hook'] = $request->boolean('chk_hook');
        $validated['chk_value'] = $request->boolean('chk_value');
        $validated['chk_risk'] = $request->boolean('chk_risk');
        $validated['chk_cta'] = $request->boolean('chk_cta');

        return $validated;
    }
}
