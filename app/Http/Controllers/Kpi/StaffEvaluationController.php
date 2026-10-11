<?php

namespace App\Http\Controllers\Kpi;

use App\Http\Controllers\Controller;
use App\Models\KpiStaffEvaluation;
use App\Models\Staff;
use Illuminate\Http\Request;

class StaffEvaluationController extends Controller
{
    public function index()
    {
        $evaluations = KpiStaffEvaluation::with(['staff', 'evaluator'])
            ->orderByDesc('evaluation_date')
            ->orderByDesc('id')
            ->paginate(15);

        $staffList = Staff::active()->orderBy('name')->get(['id', 'name']);

        return view('kpi.evaluations.index', [
            'evaluations' => $evaluations,
            'staffList' => $staffList,
            'rubric' => KpiStaffEvaluation::RUBRIC,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'staff_id' => 'required|exists:staff,id',
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
}
