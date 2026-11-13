<?php

namespace App\Http\Controllers\Kpi;

use App\Http\Controllers\Controller;
use App\Models\KpiChatEvaluation;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

class ChatEvaluationController extends Controller
{
    public function index(Request $request)
    {
        $showAll = $request->boolean('all');
        $rawMonth = $request->query('month');
        $selectedMonth = (is_string($rawMonth) && preg_match('/^\d{4}-\d{2}$/', $rawMonth))
            ? $rawMonth
            : null;

        $dateFromInput = $request->date('date_from');
        $dateToInput = $request->date('date_to');

        $dateFrom = null;
        $dateTo = null;

        if (!$showAll) {
            if ($dateFromInput || $dateToInput) {
                $dateFrom = $dateFromInput?->copy()->startOfDay();
                $dateTo = $dateToInput?->copy()->endOfDay();
            } else {
                $effectiveMonth = $selectedMonth ?: now()->format('Y-m');
                $selectedMonth = $effectiveMonth;
                $monthStart = Carbon::createFromFormat('Y-m-d', $effectiveMonth . '-01')->startOfMonth()->startOfDay();
                $monthEnd = $monthStart->copy()->endOfMonth()->endOfDay();
                $dateFrom = $monthStart;
                $dateTo = ($effectiveMonth === now()->format('Y-m'))
                    ? now()->copy()->endOfDay()
                    : $monthEnd;
            }
        }

        $baseQuery = KpiChatEvaluation::query()
            ->when($dateFrom, fn ($q) => $q->whereDate('evaluation_date', '>=', $dateFrom->toDateString()))
            ->when($dateTo, fn ($q) => $q->whereDate('evaluation_date', '<=', $dateTo->toDateString()));

        $gradedCount = (clone $baseQuery)->count();
        $avgPct = $gradedCount > 0 ? round((float) (clone $baseQuery)->avg('percentage'), 1) : 0.0;

        $reports = (clone $baseQuery)
            ->with(['agent', 'evaluator'])
            ->orderByDesc('evaluation_date')
            ->orderByDesc('id')
            ->paginate(15)
            ->appends(array_filter([
                'month' => $selectedMonth,
                'date_from' => $dateFromInput?->format('Y-m-d'),
                'date_to' => $dateToInput?->format('Y-m-d'),
                'all' => $showAll ? '1' : null,
            ]));

        return view('kpi.chat-eval.index', [
            'reports' => $reports,
            'selectedMonth' => $selectedMonth,
            'dateFrom' => $dateFrom?->format('Y-m-d'),
            'dateTo' => $dateTo?->format('Y-m-d'),
            'showAll' => $showAll,
            'gradedCount' => $gradedCount,
            'avgPct' => $avgPct,
        ]);
    }

    public function create(Request $request)
    {
        abort_unless($request->user()?->isAdmin(), 403);

        return view('kpi.chat-eval.create', [
            'agents' => $this->agentOptions(),
            'questions' => KpiChatEvaluation::QUESTIONS,
        ]);
    }

    public function store(Request $request)
    {
        abort_unless($request->user()?->isAdmin(), 403);

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

    public function edit(Request $request, KpiChatEvaluation $report)
    {
        abort_unless($request->user()?->isAdmin(), 403);

        return view('kpi.chat-eval.edit', [
            'report' => $report,
            'agents' => $this->agentOptions(),
            'questions' => KpiChatEvaluation::QUESTIONS,
        ]);
    }

    public function update(Request $request, KpiChatEvaluation $report)
    {
        abort_unless($request->user()?->isAdmin(), 403);

        $validated = $request->validate($this->validationRules());

        $report->update($this->scoredAttributes($validated));

        return redirect()->route('kpi.chat-eval.show', $report)->with('success', 'Chat evaluation updated.');
    }

    public function destroy(Request $request, KpiChatEvaluation $report)
    {
        abort_unless($request->user()?->isAdmin(), 403);

        $report->delete();

        return redirect()->route('kpi.chat-eval.index')->with('success', 'Report deleted.');
    }

    private function agentOptions()
    {
        return User::where('role', 'agent')->orderBy('name')->get(['id', 'name']);
    }

    /** Shared by store() and update() so both validate the 15 binary answers + remarks identically. */
    private function validationRules(): array
    {
        $rules = [
            'agent_id' => ['required', Rule::exists('users', 'id')->where('role', 'agent')],
            'evaluation_date' => 'required|date',
            'answers' => 'required|array|size:' . count(KpiChatEvaluation::QUESTIONS),
            'notes' => 'nullable|string',
        ];

        foreach (array_keys(KpiChatEvaluation::QUESTIONS) as $num) {
            $key = KpiChatEvaluation::answerKey($num);
            $rules["answers.{$key}.answer"] = 'required|in:pass,fail';
            $rules["answers.{$key}.remark"] = 'nullable|string|max:1000';
        }

        return $rules;
    }

    /**
     * Validated input -> model attributes. The score is always computed
     * here from the weighted questions and each question's Pass/Fail
     * toggle — a submitted score value is never trusted, unlike the old
     * free-score system. The remark is free text and stored as given.
     */
    private function scoredAttributes(array $validated): array
    {
        $totalScore = 0;
        $answers = [];

        foreach (array_keys(KpiChatEvaluation::QUESTIONS) as $num) {
            $key = KpiChatEvaluation::answerKey($num);
            $submitted = $validated['answers'][$key];
            $score = KpiChatEvaluation::scoreFor($num, $submitted['answer']);

            $answers[$key] = [
                'score' => $score,
                'remark' => $submitted['remark'] ?? null,
            ];

            $totalScore += $score;
        }

        $percentage = round($totalScore / KpiChatEvaluation::maxScore() * 100, 2);

        return [
            'agent_id' => $validated['agent_id'],
            'evaluation_date' => $validated['evaluation_date'],
            'answers' => $answers,
            'total_score' => $totalScore,
            'percentage' => $percentage,
            'notes' => $validated['notes'] ?? null,
        ];
    }
}
