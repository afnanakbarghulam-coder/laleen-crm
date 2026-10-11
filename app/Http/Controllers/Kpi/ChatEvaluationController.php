<?php

namespace App\Http\Controllers\Kpi;

use App\Http\Controllers\Controller;
use App\Models\KpiChatEvaluation;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ChatEvaluationController extends Controller
{
    public function index()
    {
        $reports = KpiChatEvaluation::with(['agent', 'evaluator'])
            ->orderByDesc('evaluation_date')
            ->orderByDesc('id')
            ->paginate(15);

        return view('kpi.chat-eval.index', compact('reports'));
    }

    public function create()
    {
        return view('kpi.chat-eval.create', [
            'agents' => $this->agentOptions(),
            'questions' => KpiChatEvaluation::QUESTIONS,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate($this->validationRules());

        $report = KpiChatEvaluation::create($this->scoredAttributes($validated) + [
            'evaluator_id' => auth()->id(),
        ]);

        return redirect()->route('kpi.chat-eval.show', $report)->with('success', 'Chat evaluation saved.');
    }

    public function show(KpiChatEvaluation $report)
    {
        return view('kpi.chat-eval.show', compact('report'));
    }

    public function edit(KpiChatEvaluation $report)
    {
        return view('kpi.chat-eval.edit', [
            'report' => $report,
            'agents' => $this->agentOptions(),
            'questions' => KpiChatEvaluation::QUESTIONS,
        ]);
    }

    public function update(Request $request, KpiChatEvaluation $report)
    {
        $validated = $request->validate($this->validationRules());

        $report->update($this->scoredAttributes($validated));

        return redirect()->route('kpi.chat-eval.show', $report)->with('success', 'Chat evaluation updated.');
    }

    public function destroy(KpiChatEvaluation $report)
    {
        $report->delete();

        return redirect()->route('kpi.chat-eval.index')->with('success', 'Report deleted.');
    }

    private function agentOptions()
    {
        return User::where('role', 'agent')->orderBy('name')->get(['id', 'name']);
    }

    /** Shared by store() and update() so both validate the 15 binary answers identically. */
    private function validationRules(): array
    {
        $rules = [
            'agent_id' => ['required', Rule::exists('users', 'id')->where('role', 'agent')],
            'evaluation_date' => 'required|date',
            'answers' => 'required|array|size:' . count(KpiChatEvaluation::QUESTIONS),
        ];

        foreach (array_keys(KpiChatEvaluation::QUESTIONS) as $num) {
            $rules["answers.{$num}"] = 'required|in:pass,fail';
        }

        return $rules;
    }

    /**
     * Validated input -> model attributes. The score is always computed
     * here from the weighted questions — a submitted pass/fail answer can
     * never carry its own score value, unlike the old free-score system.
     */
    private function scoredAttributes(array $validated): array
    {
        $totalScore = 0;

        foreach ($validated['answers'] as $num => $answer) {
            $totalScore += KpiChatEvaluation::scoreFor((int) $num, $answer);
        }

        $percentage = round($totalScore / KpiChatEvaluation::maxScore() * 100, 2);

        return [
            'agent_id' => $validated['agent_id'],
            'evaluation_date' => $validated['evaluation_date'],
            'answers' => $validated['answers'],
            'total_score' => $totalScore,
            'percentage' => $percentage,
        ];
    }
}
