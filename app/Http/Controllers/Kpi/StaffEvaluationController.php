<?php

namespace App\Http\Controllers\Kpi;

use App\Http\Controllers\Controller;
use App\Models\KpiStaffEvaluation;
use App\Models\Staff;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class StaffEvaluationController extends Controller
{
    public function index(Request $request)
    {
        // Carbon's startOfWeek()/endOfWeek() carry real times (00:00:00 /
        // 23:59:59), which matters below: Eloquent's 'date' cast still
        // stores a full "Y-m-d H:i:s" string, so comparing against a bare
        // toDateString() bound silently drops same-day rows on SQLite
        // (string comparison, not date-aware). Always compare with
        // toDateTimeString() here, not toDateString().
        $weekStart = now()->startOfWeek(Carbon::MONDAY);
        $weekEnd = now()->endOfWeek(Carbon::SUNDAY);

        $period = $request->query('period') === 'previous' ? 'previous' : 'current';

        $evaluationsQuery = KpiStaffEvaluation::with(['staff', 'evaluator']);

        if ($period === 'current') {
            $evaluationsQuery->whereBetween('evaluation_date', [$weekStart->toDateTimeString(), $weekEnd->toDateTimeString()]);
        } else {
            $evaluationsQuery->where('evaluation_date', '<', $weekStart->toDateTimeString());
        }

        $evaluations = $evaluationsQuery
            ->orderByDesc('evaluation_date')
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString();

        $staffList = Staff::active()->orderBy('name')->get(['id', 'name']);

        return view('kpi.evaluations.index', [
            'evaluations' => $evaluations,
            'staffList' => $staffList,
            'rubric' => KpiStaffEvaluation::RUBRIC,
            'branches' => KpiStaffEvaluation::BRANCHES,
            'defaultBranch' => KpiStaffEvaluation::defaultBranchFor($request->user()),
            'weeklyStatus' => $this->weeklyStatus($weekStart, $weekEnd),
            'weakestMetric' => $this->weakestMetricThisWeek($weekStart, $weekEnd),
            'period' => $period,
            'weekStart' => $weekStart,
            'weekEnd' => $weekEnd,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'staff_id' => 'required|exists:staff,id',
            'branch' => 'required|in:old_airport,wakrah',
            'evaluation_date' => 'required|date',
            'score_cleanliness' => 'required|in:1,5',
            'score_dress_code' => 'required|in:1,5',
            'score_attitude' => 'required|in:1,5',
            'score_sales' => 'required|in:1,5',
            'score_teamwork' => 'required|in:1,5',
            'score_communication' => 'required|in:1,5',
            'leader_notes' => 'nullable|string',
        ]);

        $scores = array_intersect_key($validated, array_flip(KpiStaffEvaluation::SCORE_FIELDS));

        KpiStaffEvaluation::create($validated + [
            'evaluator_id' => auth()->id(),
            'overall_average' => KpiStaffEvaluation::averageOf($scores),
        ]);

        return redirect()->route('kpi.evaluations.index')->with('success', 'Evaluation saved.');
    }

    /**
     * Per-branch compliance for the two top cards: how many distinct staff
     * have at least one evaluation logged this week, against how many
     * active staff that branch (or "both"-branch staff) actually has.
     */
    private function weeklyStatus(Carbon $weekStart, Carbon $weekEnd): Collection
    {
        return collect(KpiStaffEvaluation::BRANCHES)->map(function ($label, $branchKey) use ($weekStart, $weekEnd) {
            $total = Staff::active()
                ->where(fn ($q) => $q->where('branch', $branchKey)->orWhere('branch', 'both'))
                ->count();

            $evaluated = KpiStaffEvaluation::where('branch', $branchKey)
                ->whereBetween('evaluation_date', [$weekStart->toDateTimeString(), $weekEnd->toDateTimeString()])
                ->distinct('staff_id')
                ->count('staff_id');

            return [
                'branch' => $branchKey,
                'label' => $label,
                'evaluator_name' => KpiStaffEvaluation::BRANCH_EVALUATOR_NAMES[$branchKey] ?? null,
                'evaluated' => $evaluated,
                'total' => $total,
                'complete' => $total > 0 && $evaluated >= $total,
            ];
        })->values();
    }

    /**
     * Whichever of the 6 metrics collected the most "1" scores across all
     * branches this week — the thing branch leaders should coach on next.
     */
    private function weakestMetricThisWeek(Carbon $weekStart, Carbon $weekEnd): ?array
    {
        $evaluations = KpiStaffEvaluation::whereBetween('evaluation_date', [$weekStart->toDateTimeString(), $weekEnd->toDateTimeString()])->get();

        if ($evaluations->isEmpty()) {
            return null;
        }

        $counts = collect(KpiStaffEvaluation::SCORE_FIELDS)->mapWithKeys(fn ($field) => [
            $field => $evaluations->where($field, 1)->count(),
        ]);

        $worstField = $counts->sortDesc()->keys()->first();
        $worstCount = $counts[$worstField];

        if ($worstCount === 0) {
            return null;
        }

        return [
            'field' => $worstField,
            'title' => KpiStaffEvaluation::RUBRIC[$worstField]['title'],
            'count' => $worstCount,
            'total_evaluations' => $evaluations->count(),
        ];
    }
}
