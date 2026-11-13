<?php

namespace App\Http\Controllers\Kpi;

use App\Http\Controllers\Controller;
use App\Models\KpiStaffEvaluation;
use App\Models\Staff;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class StaffEvaluationController extends Controller
{
    public function index(Request $request)
    {
        $nowQatar = now('Asia/Qatar');
        $weekStart = $nowQatar->copy()->startOfWeek(Carbon::MONDAY)->startOfDay();
        $weekEnd = $nowQatar->copy()->endOfWeek(Carbon::SUNDAY)->endOfDay();

        $rawMonth = trim((string) $request->query('month', ''));
        $monthFilter = preg_match('/^\d{4}-\d{2}$/', $rawMonth) ? $rawMonth : null;

        $branchFilter = $request->filled('branch') && array_key_exists($request->query('branch'), KpiStaffEvaluation::BRANCHES)
            ? $request->query('branch')
            : null;

        $evaluationsQuery = KpiStaffEvaluation::with(['staff', 'evaluator']);

        if ($monthFilter) {
            $period = 'month';
            $monthStart = Carbon::createFromFormat('Y-m-d', $monthFilter . '-01', 'Asia/Qatar')->startOfMonth();
            $monthEnd = $monthStart->copy()->endOfMonth();

            $evaluationsQuery
                ->whereDate('evaluation_date', '>=', $monthStart->toDateString())
                ->whereDate('evaluation_date', '<=', $monthEnd->toDateString());
        } else {
            $period = $request->query('period') === 'previous' ? 'previous' : 'current';

            if ($period === 'current') {
                $evaluationsQuery
                    ->whereDate('evaluation_date', '>=', $weekStart->toDateString())
                    ->whereDate('evaluation_date', '<=', $weekEnd->toDateString());
            } else {
                $evaluationsQuery->whereDate('evaluation_date', '<', $weekStart->toDateString());
            }
        }

        if ($branchFilter) {
            $evaluationsQuery->where('branch', $branchFilter);
        }

        $evaluations = $evaluationsQuery
            ->orderByDesc('evaluation_date')
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString();

        $staffList = Staff::salonEvaluatable(null, $nowQatar->toDateString())
            ->orderBy('name')
            ->get(['id', 'name', 'branch']);

        $staffByBranch = collect(KpiStaffEvaluation::BRANCHES)->keys()->mapWithKeys(
            fn ($branchKey) => [
                $branchKey => $staffList
                    ->filter(fn ($staff) => in_array($staff->branch, [$branchKey, 'both'], true))
                    ->map(fn ($staff) => ['id' => $staff->id, 'name' => $staff->name])
                    ->values(),
            ]
        );

        $user = $request->user();
        $isAdminUser = (bool) ($user?->isAdmin());
        $managedBranch = KpiStaffEvaluation::managedBranchFor($user);
        $canCreateEvaluation = $isAdminUser || $managedBranch !== null;
        $modalBranches = $isAdminUser
            ? KpiStaffEvaluation::BRANCHES
            : ($managedBranch ? [$managedBranch => KpiStaffEvaluation::BRANCHES[$managedBranch]] : []);

        $canSubmitWindow = $isAdminUser || $nowQatar->isMonday() || $nowQatar->isTuesday();
        $currentMondayStr = $weekStart->toDateString();
        $currentTuesdayStr = $weekStart->copy()->addDay()->toDateString();

        return view('kpi.evaluations.index', [
            'evaluations' => $evaluations,
            'staffList' => $staffList,
            'staffByBranch' => $staffByBranch,
            'rubric' => KpiStaffEvaluation::RUBRIC,
            'branches' => KpiStaffEvaluation::BRANCHES,
            'modalBranches' => $modalBranches,
            'managedBranch' => $managedBranch,
            'canCreateEvaluation' => $canCreateEvaluation,
            'isAdminUser' => $isAdminUser,
            'defaultBranch' => $managedBranch ?? KpiStaffEvaluation::defaultBranchFor($user),
            'weeklyStatus' => $this->weeklyStatus($weekStart, $weekEnd),
            'weakestMetric' => $this->weakestMetricThisWeek($weekStart, $weekEnd),
            'period' => $period,
            'monthFilter' => $monthFilter,
            'branchFilter' => $branchFilter,
            'weekStart' => $weekStart,
            'weekEnd' => $weekEnd,
            'currentMondayStr' => $currentMondayStr,
            'currentTuesdayStr' => $currentTuesdayStr,
            'todayQatarStr' => $nowQatar->toDateString(),
            'isMonday' => $canSubmitWindow,
            'canSubmitWindow' => $canSubmitWindow,
        ]);
    }

    public function store(Request $request)
    {
        $user = $request->user();
        $isAdmin = (bool) ($user?->isAdmin());
        $managedBranch = KpiStaffEvaluation::managedBranchFor($user);

        if (!$isAdmin && $managedBranch === null) {
            abort(403, 'Only Admins or assigned Branch Managers may submit salon staff evaluations.');
        }

        $nowQatar = now('Asia/Qatar');
        if (!$isAdmin && !$nowQatar->isMonday() && !$nowQatar->isTuesday()) {
            abort(403, 'Evaluations can be submitted on Mondays and Tuesdays (2-day window).');
        }

        $submittedBranch = (string) $request->input('branch', '');
        if (
            !$isAdmin
            && array_key_exists($submittedBranch, KpiStaffEvaluation::BRANCHES)
            && $submittedBranch !== $managedBranch
        ) {
            abort(403, 'You may only submit evaluations for your assigned branch.');
        }

        $currentMondayStr = $nowQatar->copy()->startOfWeek(Carbon::MONDAY)->toDateString();
        $currentTuesdayStr = $nowQatar->copy()->startOfWeek(Carbon::MONDAY)->addDay()->toDateString();

        $dateRules = $isAdmin
            ? ['required', 'date']
            : ['required', 'date', "after_or_equal:{$currentMondayStr}", "before_or_equal:{$currentTuesdayStr}"];

        $validated = $request->validate([
            'branch' => 'required|in:old_airport,wakrah',
            'staff_id' => [
                'required',
                'exists:staff,id',
                function (string $attribute, mixed $value, \Closure $fail) use ($request, $nowQatar) {
                    $branch = (string) $request->input('branch', '');
                    if (!array_key_exists($branch, KpiStaffEvaluation::BRANCHES)) {
                        return;
                    }

                    $rawEvalDate = $request->input('evaluation_date');
                    try {
                        $asOfDate = $rawEvalDate
                            ? Carbon::parse($rawEvalDate, 'Asia/Qatar')->toDateString()
                            : $nowQatar->toDateString();
                    } catch (\Throwable) {
                        $asOfDate = $nowQatar->toDateString();
                    }

                    $isEligible = Staff::salonEvaluatable($branch, $asOfDate)
                        ->whereKey($value)
                        ->exists();

                    if (!$isEligible) {
                        $fail('The selected staff member is not an eligible salon stylist for this branch.');
                    }
                },
            ],
            'evaluation_date' => $dateRules,
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
            'evaluator_id' => $user->id,
            'overall_average' => KpiStaffEvaluation::averageOf($scores),
        ]);

        return redirect()->route('kpi.evaluations.index')->with('success', 'Evaluation saved.');
    }

    /**
     * Per-branch compliance for the two top cards: how many distinct eligible
     * salon staff have at least one evaluation logged this week, against how
     * many eligible salon staff that branch (or "both"-branch staff) has.
     */
    private function weeklyStatus(Carbon $weekStart, Carbon $weekEnd): Collection
    {
        return collect(KpiStaffEvaluation::BRANCHES)->map(function ($label, $branchKey) use ($weekStart, $weekEnd) {
            $eligibleIds = Staff::salonEvaluatable($branchKey, $weekStart->toDateString())
                ->pluck('id');

            $total = $eligibleIds->count();

            $evaluated = $total > 0
                ? KpiStaffEvaluation::where('branch', $branchKey)
                    ->whereIn('staff_id', $eligibleIds)
                    ->whereDate('evaluation_date', '>=', $weekStart->toDateString())
                    ->whereDate('evaluation_date', '<=', $weekEnd->toDateString())
                    ->distinct('staff_id')
                    ->count('staff_id')
                : 0;

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
        $evaluations = KpiStaffEvaluation::query()
            ->whereDate('evaluation_date', '>=', $weekStart->toDateString())
            ->whereDate('evaluation_date', '<=', $weekEnd->toDateString())
            ->get();

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
