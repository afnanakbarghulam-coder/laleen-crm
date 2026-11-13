<?php

namespace App\Http\Controllers\Kpi;

use App\Http\Controllers\Controller;
use App\Models\BranchEvaluation;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class BranchEvaluationController extends Controller
{
    public function index(Request $request)
    {
        $branchFilter = $request->filled('branch') && array_key_exists($request->query('branch'), BranchEvaluation::BRANCHES)
            ? $request->query('branch')
            : null;

        $rawMonth = trim((string) $request->query('month', ''));
        $monthFilter = preg_match('/^\d{4}-\d{2}$/', $rawMonth) ? $rawMonth : null;

        $evaluations = BranchEvaluation::query()
            ->when($branchFilter, fn ($query) => $query->where('branch', $branchFilter))
            ->when($monthFilter, function ($query) use ($monthFilter) {
                $monthStart = Carbon::createFromFormat('Y-m-d', $monthFilter . '-01', 'Asia/Qatar')->startOfMonth();
                $monthEnd = $monthStart->copy()->endOfMonth();

                $query->whereDate('evaluation_date', '>=', $monthStart->toDateString())
                    ->whereDate('evaluation_date', '<=', $monthEnd->toDateString());
            })
            ->orderByDesc('evaluation_date')
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString();

        return view('kpi.branch-evaluations.index', [
            'evaluations' => $evaluations,
            'branches' => BranchEvaluation::BRANCHES,
            'branchFilter' => $branchFilter,
            'monthFilter' => $monthFilter,
        ]);
    }

    public function create(Request $request)
    {
        abort_unless($request->user()?->isAdmin(), 403);

        return view('kpi.branch-evaluations.create', [
            'branches' => BranchEvaluation::BRANCHES,
            'managers' => BranchEvaluation::MANAGERS,
            'sections' => BranchEvaluation::SECTIONS,
        ]);
    }

    public function store(Request $request)
    {
        abort_unless($request->user()?->isAdmin(), 403);

        $validated = $request->validate($this->validationRules());
        $userId = $request->user()->id;

        $evaluation = BranchEvaluation::create($this->scoredAttributes($validated) + [
            'created_by' => $userId,
            'updated_by' => $userId,
        ]);

        return redirect()->route('kpi.branch-evaluation.show', $evaluation)->with('success', 'Branch evaluation saved.');
    }

    public function show(BranchEvaluation $branchEvaluation)
    {
        return view('kpi.branch-evaluations.show', ['evaluation' => $branchEvaluation]);
    }

    public function edit(Request $request, BranchEvaluation $branchEvaluation)
    {
        abort_unless($request->user()?->isAdmin(), 403);

        return view('kpi.branch-evaluations.edit', [
            'evaluation' => $branchEvaluation,
            'branches' => BranchEvaluation::BRANCHES,
            'managers' => BranchEvaluation::MANAGERS,
            'sections' => BranchEvaluation::SECTIONS,
        ]);
    }

    public function update(Request $request, BranchEvaluation $branchEvaluation)
    {
        abort_unless($request->user()?->isAdmin(), 403);

        $validated = $request->validate($this->validationRules());

        $branchEvaluation->update($this->scoredAttributes($validated) + [
            'updated_by' => $request->user()->id,
        ]);

        return redirect()->route('kpi.branch-evaluation.show', $branchEvaluation)->with('success', 'Branch evaluation updated.');
    }

    /** Shared by store() and update() so both validate the 27 binary answers identically. */
    private function validationRules(): array
    {
        $fields = BranchEvaluation::questionFields();

        $rules = [
            'branch' => 'required|in:' . implode(',', array_keys(BranchEvaluation::BRANCHES)),
            'manager' => 'required|in:' . implode(',', BranchEvaluation::MANAGERS),
            'evaluation_date' => 'required|date',
            'answers' => 'required|array|size:' . count($fields),
            'action_plan' => 'nullable|string',
        ];

        foreach ($fields as $field) {
            $rules["answers.{$field}"] = 'required|in:1,5';
        }

        return $rules;
    }

    /** Validated input -> model attributes, with total_score/percentage/grade recalculated from the answers. */
    private function scoredAttributes(array $validated): array
    {
        $totalScore = array_sum($validated['answers']);
        $percentage = round($totalScore / BranchEvaluation::maxScore() * 100, 2);

        return [
            'branch' => $validated['branch'],
            'manager' => $validated['manager'],
            'evaluation_date' => $validated['evaluation_date'],
            'answers' => $validated['answers'],
            'total_score' => $totalScore,
            'percentage' => $percentage,
            'grade' => BranchEvaluation::gradeFor($percentage),
            'action_plan' => $validated['action_plan'] ?? null,
        ];
    }
}
