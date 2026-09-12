<?php

namespace App\NovaAI\Services;

use App\NovaAI\Models\NovaDecision;
use App\NovaAI\Models\NovaExperiment;
use App\NovaAI\Support\NovaRelevance;
use Illuminate\Support\Facades\Log;

/**
 * Owns storage and retrieval of Nova's decision/experiment memory
 * (nova_decisions, nova_experiments - isolated 'nova_memory' connection,
 * see app/NovaAI/README.md's NOVA READ-ONLY INTEGRATION RULE). Never
 * decides WHAT counts as a decision or experiment - that is
 * App\NovaAI\Services\NovaDecisionExtractor's job entirely; this class
 * only applies already-validated actions and retrieves already-recorded
 * rows. Nothing here ever touches a CRM table, model, or connection -
 * Nova remembers decisions, it never executes them.
 *
 * relevantDecisions()/relevantExperiments() use the same deterministic
 * keyword-overlap scoring as NovaBusinessFactService::relevantFacts()
 * (App\NovaAI\Support\NovaRelevance), bounding how many decisions/
 * experiments are ever injected into one request - but with a different
 * balance than facts, and an asymmetry between the two: active decisions
 * carry a flat, unconditional relevance baseline (never filtered out
 * purely for lacking keyword overlap - only capped/ordered), reflecting
 * that a standing commitment is worth staying aware of regardless of
 * topic; active experiments carry no baseline at all and are excluded
 * outright when they score zero (no topical overlap, no overdue review),
 * matching business facts' stricter, relevance-gated behavior, since an
 * experiment is more situational than a decision. Both get a modest boost
 * when their review_date has passed. activeItemsForExtraction() below is
 * deliberately NOT relevance-filtered: NovaDecisionExtractor must be able
 * to reference ANY active item by id for a complete/cancel/reverse
 * action, not just the ones currently relevant to the question.
 *
 * Every public method is failure-isolated, matching NovaConversationService
 * and NovaBusinessFactService: a nova_memory outage must never prevent
 * Nova from answering.
 */
class NovaDecisionService
{
    private const MAX_ACTIVE_ITEMS = 50;

    private const MAX_RELEVANT_ITEMS = 12;

    private const RELEVANT_CHAR_BUDGET = 1500;

    /** Unconditional per-decision baseline - stronger than a business fact's baseline, so decisions are rarely filtered out purely for lacking keyword overlap. */
    private const DECISION_BASE_SCORE = 2;

    private const DECISION_KEYWORD_WEIGHT = 3;

    /** No free baseline for experiments - relevance is driven by topical overlap with the question. */
    private const EXPERIMENT_KEYWORD_WEIGHT = 4;

    private const OVERDUE_REVIEW_BOOST = 1;

    /**
     * The short reference list handed to NovaDecisionExtractor so a
     * complete/cancel/reverse action can name an existing item - id, type,
     * and title only, nothing else.
     *
     * @return array<int, array{id: int, type: string, title: string}>
     */
    public function activeItemsForExtraction(): array
    {
        try {
            $decisions = NovaDecision::where('status', 'active')
                ->get(['id', 'title'])
                ->map(fn (NovaDecision $d) => ['id' => $d->id, 'type' => 'decision', 'title' => $d->title]);

            $experiments = NovaExperiment::where('status', 'active')
                ->get(['id', 'title'])
                ->map(fn (NovaExperiment $e) => ['id' => $e->id, 'type' => 'experiment', 'title' => $e->title]);

            return $decisions->concat($experiments)->values()->all();
        } catch (\Throwable $e) {
            Log::warning('Nova decision/experiment list unavailable - continuing without it', ['message' => $e->getMessage()]);

            return [];
        }
    }

    /**
     * @param array<int, array<string, mixed>> $actions
     */
    public function applyActions(array $actions, int $userId, string $sourceMessage): void
    {
        foreach ($actions as $action) {
            try {
                match ($action['action'] ?? null) {
                    'create' => $this->create($action, $userId, $sourceMessage),
                    'complete' => $this->transition($action, 'completed'),
                    'cancel' => $this->transition($action, 'cancelled'),
                    'reverse' => $this->transition($action, 'reversed'),
                    default => null,
                };
            } catch (\Throwable $e) {
                Log::warning('Nova decision/experiment action could not be applied', ['message' => $e->getMessage()]);
            }
        }
    }

    private function create(array $action, int $userId, string $sourceMessage): void
    {
        if ($action['type'] === 'experiment') {
            NovaExperiment::create([
                'title' => $action['title'],
                'description' => $action['description'] ?? null,
                'category' => $action['category'],
                'status' => 'active',
                'started_at' => $action['started_at'] ?? null,
                'ends_at' => $action['ends_at'] ?? null,
                'target_metric' => $action['target_metric'] ?? null,
                'success_criteria' => $action['success_criteria'] ?? null,
                'review_date' => $action['review_date'] ?? null,
                'decided_by_user_id' => $userId,
                'source_message' => $sourceMessage,
            ]);

            return;
        }

        NovaDecision::create([
            'title' => $action['title'],
            'description' => $action['description'] ?? null,
            'category' => $action['category'],
            'status' => 'active',
            'review_date' => $action['review_date'] ?? null,
            'decided_by_user_id' => $userId,
            'source_message' => $sourceMessage,
        ]);
    }

    private function transition(array $action, string $newStatus): void
    {
        $targetId = $action['target_id'];
        $resultSummary = $action['result_summary'] ?? null;

        $decision = NovaDecision::where('id', $targetId)->where('status', 'active')->first();

        if ($decision) {
            $decision->forceFill(['status' => $newStatus, 'result_summary' => $resultSummary])->save();

            return;
        }

        $experiment = NovaExperiment::where('id', $targetId)->where('status', 'active')->first();

        if ($experiment) {
            $experiment->forceFill(['status' => $newStatus, 'result_summary' => $resultSummary])->save();
        }
    }

    private function isOverdue(?\Illuminate\Support\Carbon $reviewDate): bool
    {
        return $reviewDate !== null && $reviewDate->isPast();
    }

    /**
     * @return array<int, array{title: string, description: ?string, category: string, review_date: ?string}>
     */
    public function relevantDecisions(string $question): array
    {
        try {
            $questionTokens = NovaRelevance::tokenize($question);

            $scored = NovaDecision::where('status', 'active')->get()
                ->map(function (NovaDecision $decision) use ($questionTokens) {
                    $text = $decision->title . ' ' . ($decision->description ?? '') . ' ' . $decision->category;
                    $overlap = NovaRelevance::overlapScore($questionTokens, $text);
                    $overdue = $this->isOverdue($decision->review_date) ? self::OVERDUE_REVIEW_BOOST : 0;

                    $score = self::DECISION_BASE_SCORE + ($overlap * self::DECISION_KEYWORD_WEIGHT) + $overdue;

                    return ['item' => $decision, 'score' => $score, 'updated_at' => $decision->updated_at, 'id' => $decision->id];
                })
                ->values()
                ->all();

            $ranked = NovaRelevance::rank($scored);

            $selected = NovaRelevance::selectWithinBudget(
                $ranked,
                self::MAX_RELEVANT_ITEMS,
                self::RELEVANT_CHAR_BUDGET,
                fn (NovaDecision $d) => mb_strlen($d->title) + mb_strlen($d->description ?? '')
            );

            return collect($selected)
                ->map(fn (NovaDecision $d) => [
                    'title' => $d->title,
                    'description' => $d->description,
                    'category' => $d->category,
                    'review_date' => optional($d->review_date)->toDateString(),
                ])
                ->values()
                ->all();
        } catch (\Throwable $e) {
            Log::warning('Nova decisions unavailable - continuing without them', ['message' => $e->getMessage()]);

            return [];
        }
    }

    /**
     * @return array<int, array{title: string, description: ?string, category: string, started_at: ?string, ends_at: ?string, target_metric: ?string, success_criteria: ?string, review_date: ?string}>
     */
    public function relevantExperiments(string $question): array
    {
        try {
            $questionTokens = NovaRelevance::tokenize($question);

            $scored = NovaExperiment::where('status', 'active')->get()
                ->map(function (NovaExperiment $experiment) use ($questionTokens) {
                    $text = $experiment->title . ' ' . ($experiment->description ?? '') . ' ' . $experiment->category
                        . ' ' . ($experiment->target_metric ?? '') . ' ' . ($experiment->success_criteria ?? '');
                    $overlap = NovaRelevance::overlapScore($questionTokens, $text);
                    $overdue = $this->isOverdue($experiment->review_date) ? self::OVERDUE_REVIEW_BOOST : 0;

                    $score = ($overlap * self::EXPERIMENT_KEYWORD_WEIGHT) + $overdue;

                    return ['item' => $experiment, 'score' => $score, 'updated_at' => $experiment->updated_at, 'id' => $experiment->id];
                })
                // Unlike decisions (which carry an unconditional baseline),
                // an experiment with zero topical overlap and no overdue
                // review is excluded outright - "a stronger baseline when
                // their business area overlaps the question" implies no
                // baseline at all otherwise, matching business facts'
                // stricter relevance-gated behavior rather than decisions'
                // more lenient one.
                ->filter(fn (array $s) => $s['score'] > 0)
                ->values()
                ->all();

            $ranked = NovaRelevance::rank($scored);

            $selected = NovaRelevance::selectWithinBudget(
                $ranked,
                self::MAX_RELEVANT_ITEMS,
                self::RELEVANT_CHAR_BUDGET,
                fn (NovaExperiment $e) => mb_strlen($e->title) + mb_strlen($e->description ?? '')
            );

            return collect($selected)
                ->map(fn (NovaExperiment $e) => [
                    'title' => $e->title,
                    'description' => $e->description,
                    'category' => $e->category,
                    'started_at' => optional($e->started_at)->toDateString(),
                    'ends_at' => optional($e->ends_at)->toDateString(),
                    'target_metric' => $e->target_metric,
                    'success_criteria' => $e->success_criteria,
                    'review_date' => optional($e->review_date)->toDateString(),
                ])
                ->values()
                ->all();
        } catch (\Throwable $e) {
            Log::warning('Nova experiments unavailable - continuing without them', ['message' => $e->getMessage()]);

            return [];
        }
    }
}
