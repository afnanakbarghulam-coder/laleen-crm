<?php

namespace App\NovaAI\Services;

use App\NovaAI\Models\NovaBusinessFact;
use App\NovaAI\Support\NovaRelevance;
use Illuminate\Support\Facades\Log;

/**
 * Owns storage and retrieval of Nova's structured business memory
 * (nova_business_facts, isolated 'nova_memory' connection - see
 * app/NovaAI/README.md's NOVA READ-ONLY INTEGRATION RULE). Never decides
 * WHAT counts as a fact - that is App\NovaAI\Services\NovaFactExtractor's
 * job entirely; this class only persists and retrieves already-validated
 * facts.
 *
 * Dedup/supersession is keyed on normalized_key: recording a fact whose
 * key matches an existing ACTIVE fact marks the old row 'superseded'
 * (kept, for history/audit) and inserts the new one as the sole active
 * fact for that key - so retrieval never returns two conflicting active
 * facts for the same thing.
 *
 * Retrieval (relevantFacts()) is deterministic keyword-overlap relevance
 * scoring (App\NovaAI\Support\NovaRelevance) against the admin's current
 * question - no vector search, no embeddings, no LLM call. Sending every
 * active fact into every request doesn't scale and buries the handful
 * that actually matter under unrelated ones, so a fact with zero keyword
 * overlap and no "broadly applicable" category is excluded entirely
 * rather than merely down-ranked - see GLOBAL_CATEGORIES below for the
 * categories (policy/preference/constraint) treated as broadly applicable
 * regardless of keyword overlap, since a standing non-negotiable policy
 * is often relevant without ever sharing a word with the question that
 * triggers it. Question-aware retrieval beyond this simple scoring
 * (embeddings, a vector store, semantic search) is explicitly out of
 * scope.
 *
 * Every public method is failure-isolated, matching
 * NovaConversationService: a nova_memory outage must never prevent Nova
 * from answering, and a fact that fails to record is simply lost for that
 * turn rather than breaking the request.
 */
class NovaBusinessFactService
{
    private const MAX_RELEVANT_FACTS = 12;

    /** Bounds total prompt size contributed by facts - values are capped at 500 chars each in NovaFactExtractor, so this comfortably fits several. */
    private const RELEVANT_CHAR_BUDGET = 2000;

    /** normalized_key overlap is a stronger signal than value overlap - a key match means the fact is precisely about that topic. */
    private const KEY_OVERLAP_WEIGHT = 3;

    private const VALUE_OVERLAP_WEIGHT = 1;

    /**
     * Categories representing standing, business-wide rules (a
     * non-negotiable policy, an owner communication preference, a
     * universal constraint) - these get a modest flat relevance baseline
     * so they can surface even without direct keyword overlap, without
     * inventing a general-purpose "importance" system.
     */
    private const GLOBAL_CATEGORIES = ['policy', 'preference', 'constraint'];

    private const GLOBAL_CATEGORY_BASELINE = 1;

    /**
     * @param array<int, array{category: string, normalized_key: string, value: string}> $facts
     */
    public function recordFacts(array $facts, int $statedByUserId, string $sourceMessage): void
    {
        foreach ($facts as $fact) {
            $this->recordFact($fact, $statedByUserId, $sourceMessage);
        }
    }

    private function recordFact(array $fact, int $statedByUserId, string $sourceMessage): void
    {
        try {
            $existing = NovaBusinessFact::where('normalized_key', $fact['normalized_key'])
                ->where('status', 'active')
                ->first();

            $created = NovaBusinessFact::create([
                'category' => $fact['category'],
                'normalized_key' => $fact['normalized_key'],
                'value' => $fact['value'],
                'status' => 'active',
                'source_message' => $sourceMessage,
                'stated_by_user_id' => $statedByUserId,
            ]);

            if ($existing) {
                $existing->forceFill([
                    'status' => 'superseded',
                    'superseded_by_id' => $created->id,
                ])->save();
            }
        } catch (\Throwable $e) {
            Log::warning('Nova business fact could not be recorded', ['message' => $e->getMessage()]);
        }
    }

    /**
     * Deterministic relevance retrieval: scores every active fact against
     * $question's tokens (normalized_key overlap weighted higher than
     * value overlap, plus a flat baseline for broadly-applicable
     * categories), drops anything scoring zero, then keeps the highest-
     * scoring facts within MAX_RELEVANT_FACTS and RELEVANT_CHAR_BUDGET.
     * Relevance always outranks recency - recency only breaks ties between
     * equally-relevant facts. Returns fewer than the cap (or none at all)
     * when fewer facts are actually relevant; never pads with irrelevant
     * ones to reach a target count.
     *
     * @param int $ownerUserId The authenticated admin asking the question.
     *     Accepted for interface symmetry with the rest of Nova's
     *     owner-scoped memory (conversations) and to keep this method
     *     ready if a future stage ever needs owner-scoped facts. Business
     *     facts themselves remain global/business-wide by Stage 5's
     *     original design (never redesigned here) - every admin sees the
     *     same relevant facts for the same question, so this parameter
     *     does not filter anything today.
     * @return array<int, array{category: string, value: string}>
     */
    public function relevantFacts(string $question, int $ownerUserId): array
    {
        try {
            $questionTokens = NovaRelevance::tokenize($question);

            $scored = NovaBusinessFact::where('status', 'active')->get()
                ->map(function (NovaBusinessFact $fact) use ($questionTokens) {
                    $keyOverlap = NovaRelevance::overlapScore($questionTokens, str_replace('_', ' ', $fact->normalized_key));
                    $valueOverlap = NovaRelevance::overlapScore($questionTokens, $fact->value);
                    $baseline = in_array($fact->category, self::GLOBAL_CATEGORIES, true) ? self::GLOBAL_CATEGORY_BASELINE : 0;

                    $score = ($keyOverlap * self::KEY_OVERLAP_WEIGHT) + ($valueOverlap * self::VALUE_OVERLAP_WEIGHT) + $baseline;

                    return ['item' => $fact, 'score' => $score, 'updated_at' => $fact->updated_at, 'id' => $fact->id];
                })
                ->filter(fn (array $s) => $s['score'] > 0)
                ->values()
                ->all();

            $ranked = NovaRelevance::rank($scored);

            $selected = NovaRelevance::selectWithinBudget(
                $ranked,
                self::MAX_RELEVANT_FACTS,
                self::RELEVANT_CHAR_BUDGET,
                fn (NovaBusinessFact $f) => mb_strlen($f->value)
            );

            return collect($selected)
                ->map(fn (NovaBusinessFact $f) => ['category' => $f->category, 'value' => $f->value])
                ->values()
                ->all();
        } catch (\Throwable $e) {
            Log::warning('Nova business facts unavailable - continuing without them', ['message' => $e->getMessage()]);

            return [];
        }
    }
}
