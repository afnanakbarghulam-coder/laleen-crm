<?php

namespace App\Http\Controllers\Kpi;

use App\Http\Controllers\Controller;
use App\Models\BranchEvaluation;
use Illuminate\Http\Request;

class BranchEvaluationController extends Controller
{
    public function index(Request $request)
    {
        $branchFilter = $request->filled('branch') && array_key_exists($request->query('branch'), BranchEvaluation::BRANCHES)
            ? $request->query('branch')
            : null;

        $evaluations = BranchEvaluation::query()
            ->when($branchFilter, fn ($query) => $query->where('branch', $branchFilter))
            ->orderByDesc('evaluation_date')
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString();

        return view('kpi.branch-evaluations.index', [
            'evaluations' => $evaluations,
            'branches' => BranchEvaluation::BRANCHES,
            'branchFilter' => $branchFilter,
        ]);
    }

    public function create()
    {
        return view('kpi.branch-evaluations.create', [
            'branches' => BranchEvaluation::BRANCHES,
            'managers' => BranchEvaluation::MANAGERS,
            'sections' => BranchEvaluation::SECTIONS,
        ]);
    }

    public function store(Request $request)
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

        $validated = $request->validate($rules);

        $totalScore = array_sum($validated['answers']);
        $percentage = round($totalScore / BranchEvaluation::maxScore() * 100, 2);

        $evaluation = BranchEvaluation::create([
            'branch' => $validated['branch'],
            'manager' => $validated['manager'],
            'evaluation_date' => $validated['evaluation_date'],
            'answers' => $validated['answers'],
            'total_score' => $totalScore,
            'percentage' => $percentage,
            'grade' => BranchEvaluation::gradeFor($percentage),
            'action_plan' => $validated['action_plan'] ?? null,
        ]);

        return redirect()->route('kpi.branch-evaluation.show', $evaluation)->with('success', 'Branch evaluation saved.');
    }

    public function show(BranchEvaluation $branchEvaluation)
    {
        return view('kpi.branch-evaluations.show', ['evaluation' => $branchEvaluation]);
    }
}
