<?php

namespace App\Http\Controllers\Kpi;

use App\Http\Controllers\Controller;
use App\Models\KpiChatEvaluation;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
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
        $selectedAgentId = $request->filled('agent_id') ? (int) $request->query('agent_id') : null;

        $dateFromInput = $request->date('date_from');
        $dateToInput = $request->date('date_to');

        $dateFrom = null;
        $dateTo = null;

        if (! $showAll) {
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
            ->when($dateTo, fn ($q) => $q->whereDate('evaluation_date', '<=', $dateTo->toDateString()))
            ->when($selectedAgentId, fn ($q) => $q->forAgentIds([$selectedAgentId]));

        $gradedCount = (clone $baseQuery)->count();
        $avgPct = $gradedCount > 0 ? round((float) (clone $baseQuery)->avg('percentage'), 1) : 0.0;

        $reports = (clone $baseQuery)
            ->with(['agent', 'agents', 'evaluator'])
            ->orderByDesc('evaluation_date')
            ->orderByDesc('id')
            ->paginate(15)
            ->appends(array_filter([
                'month' => $selectedMonth,
                'date_from' => $dateFromInput?->format('Y-m-d'),
                'date_to' => $dateToInput?->format('Y-m-d'),
                'all' => $showAll ? '1' : null,
                'agent_id' => $selectedAgentId,
            ]));

        return view('kpi.chat-eval.index', [
            'reports' => $reports,
            'agents' => $this->agentOptions(),
            'selectedAgentId' => $selectedAgentId,
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

        $selectableAgents = $this->agentOptions();
        $validated = $request->validate(
            $this->validationRules($request, $selectableAgents),
            $this->validationMessages()
        );

        [$attributes, $selectedIds] = $this->scoredAttributes($validated, $selectableAgents);

        $report = DB::transaction(function () use ($attributes, $selectedIds) {
            $report = KpiChatEvaluation::create($attributes + [
                'evaluator_id' => auth()->id(),
            ]);

            $report->agents()->sync($selectedIds);

            return $report;
        });

        return redirect()->route('kpi.chat-eval.show', $report)->with('success', 'Chat evaluation saved.');
    }

    public function show(KpiChatEvaluation $report)
    {
        $report->load(['agent', 'agents', 'evaluator']);

        return view('kpi.chat-eval.show', compact('report'));
    }

    public function download(KpiChatEvaluation $report)
    {
        $report->load(['agent', 'agents', 'evaluator']);

        $pdf = Pdf::loadView('kpi.chat-eval.pdf', [
            'report' => $report,
        ])->setPaper('a4', 'portrait');

        return $pdf->download($report->pdfFilename());
    }

    public function edit(Request $request, KpiChatEvaluation $report)
    {
        abort_unless($request->user()?->isAdmin(), 403);

        $report->load(['agent', 'agents', 'evaluator']);

        return view('kpi.chat-eval.edit', [
            'report' => $report,
            'agents' => $this->agentOptions(),
            'questions' => KpiChatEvaluation::QUESTIONS,
        ]);
    }

    public function update(Request $request, KpiChatEvaluation $report)
    {
        abort_unless($request->user()?->isAdmin(), 403);

        $selectableAgents = $this->agentOptions();
        $validated = $request->validate(
            $this->validationRules($request, $selectableAgents),
            $this->validationMessages()
        );

        [$attributes, $selectedIds] = $this->scoredAttributes($validated, $selectableAgents);

        DB::transaction(function () use ($report, $attributes, $selectedIds) {
            $report->update($attributes);
            $report->agents()->sync($selectedIds);
        });

        return redirect()->route('kpi.chat-eval.show', $report)->with('success', 'Chat evaluation updated.');
    }

    public function destroy(Request $request, KpiChatEvaluation $report)
    {
        abort_unless($request->user()?->isAdmin(), 403);

        $report->delete();

        return redirect()->route('kpi.chat-eval.index')->with('success', 'Report deleted.');
    }

    private function agentOptions(): Collection
    {
        return KpiChatEvaluation::selectableAgents();
    }

    /** Shared by store() and update() so both validate multi-agent selection + 15 binary answers + remarks identically. */
    private function validationRules(Request $request, Collection $selectableAgents): array
    {
        $selectableIds = $selectableAgents->pluck('id')->map(fn ($id) => (int) $id)->all();
        $usingLegacySingleAgent = ! $request->has('agent_ids') && $request->has('agent_id');

        $rules = [
            'evaluation_date' => 'required|date',
            'answers' => 'required|array|size:' . count(KpiChatEvaluation::QUESTIONS),
            'notes' => 'nullable|string',
        ];

        if ($usingLegacySingleAgent) {
            $rules['agent_id'] = ['required', 'integer', Rule::in($selectableIds)];
        } else {
            $rules['agent_ids'] = ['required', 'array', 'min:1'];
            $rules['agent_ids.*'] = ['integer', Rule::in($selectableIds)];
        }

        foreach (array_keys(KpiChatEvaluation::QUESTIONS) as $num) {
            $key = KpiChatEvaluation::answerKey($num);
            $rules["answers.{$key}.answer"] = 'required|in:pass,fail';
            $rules["answers.{$key}.remark"] = 'nullable|string|max:1000';
        }

        return $rules;
    }

    private function validationMessages(): array
    {
        return [
            'agent_ids.required' => 'Please select at least one agent to evaluate.',
            'agent_ids.min' => 'Please select at least one agent to evaluate.',
            'agent_ids.*.in' => 'One or more selected agents are not eligible for chat evaluation.',
        ];
    }

    /**
     * Validated input -> [model attributes, selected agent IDs].
     * The score is always computed server-side from the weighted questions.
     *
     * @return array{0: array<string, mixed>, 1: array<int, int>}
     */
    private function scoredAttributes(array $validated, Collection $selectableAgents): array
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

        $rawAgentIds = array_key_exists('agent_ids', $validated)
            ? $validated['agent_ids']
            : [$validated['agent_id']];

        $selectedIds = collect($rawAgentIds)
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->unique()
            ->values()
            ->all();

        $agentsById = $selectableAgents->keyBy('id');
        // Preserve canonical selectable order for stored IDs & names
        $orderedSelected = $selectableAgents
            ->filter(fn ($agent) => in_array((int) $agent->id, $selectedIds, true))
            ->values();

        $orderedIds = $orderedSelected->pluck('id')->map(fn ($id) => (int) $id)->all();
        $orderedNames = $orderedSelected->pluck('name')->values()->all();

        $selectableIds = $selectableAgents->pluck('id')->map(fn ($id) => (int) $id)->all();
        $allSelected = count($selectableIds) > 1 && empty(array_diff($selectableIds, $orderedIds));

        return [
            [
                'agent_id' => $orderedIds[0],
                'agent_ids' => $orderedIds,
                'agent_names' => $orderedNames,
                'all_agents' => $allSelected,
                'evaluation_date' => $validated['evaluation_date'],
                'answers' => $answers,
                'total_score' => $totalScore,
                'percentage' => $percentage,
                'notes' => $validated['notes'] ?? null,
            ],
            $orderedIds,
        ];
    }
}
