<?php

namespace App\Http\Controllers\Kpi;

use App\Http\Controllers\Controller;
use App\Models\KpiMonthlyAd;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class MonthlyAdController extends Controller
{
    public function index()
    {
        $ads = KpiMonthlyAd::orderByDesc('shoot_date')->orderByDesc('id')->get();

        $adsByPhase = collect(KpiMonthlyAd::BOARD_PHASES)
            ->mapWithKeys(fn ($phase) => [$phase => $ads->where('phase', $phase)->values()]);

        return view('kpi.ads-plan.index', compact('adsByPhase'));
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

    private function validated(Request $request): array
    {
        return $request->validate([
            'ad_name' => 'required|string|max:255',
            'person_responsible' => 'required|string|max:255',
            'pre_filled_message' => 'nullable|string',
            'performance_target' => 'nullable|string|max:255',
            'shoot_date' => 'nullable|date',
            'team' => 'nullable|string|max:255',
            'raw_clips_date' => 'nullable|date',
            'editing_deadline' => 'nullable|date',
            'approved_by' => 'nullable|string|max:255',
            'live_date' => 'nullable|date',
            'total_budget' => 'nullable|numeric|min:0',
        ]);
    }
}
