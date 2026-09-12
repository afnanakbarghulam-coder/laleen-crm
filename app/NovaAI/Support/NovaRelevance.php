<?php

namespace App\NovaAI\Support;

/**
 * Shared, deterministic keyword-overlap relevance scoring for Nova's own
 * memory retrieval (business facts, decisions, experiments) - no
 * embeddings, no vector database, no external service, and no LLM call.
 * Deliberately simple: lowercase + strip punctuation + drop a small fixed
 * stopword list, then score candidates by token-set intersection size.
 * Read-only utility - never touches any database connection itself.
 */
class NovaRelevance
{
    private const STOPWORDS = [
        'a', 'an', 'the', 'is', 'are', 'am', 'was', 'were', 'be', 'been', 'being',
        'to', 'of', 'in', 'on', 'at', 'for', 'and', 'or', 'but', 'with', 'this',
        'that', 'it', 'i', 'we', 'you', 'your', 'our', 'my', 'me', 'do', 'does',
        'did', 'can', 'could', 'would', 'should', 'will', 'shall', 'not', 'no',
        'yes', 'if', 'so', 'as', 'by', 'from', 'about', 'into', 'up', 'out',
        'us', 'them', 'they', 'he', 'she', 'have', 'has', 'had', 'give', 'get',
    ];

    /**
     * Lowercase, punctuation stripped to spaces (normalizes "20%" / "20?"
     * / "20." alike), split on whitespace, stopwords dropped, deduplicated.
     *
     * @return array<int, string>
     */
    public static function tokenize(string $text): array
    {
        $normalized = strtolower($text);
        $normalized = preg_replace('/[^a-z0-9\s]/', ' ', $normalized) ?? '';
        $tokens = preg_split('/\s+/', trim($normalized), -1, PREG_SPLIT_NO_EMPTY);

        return array_values(array_unique(array_diff($tokens, self::STOPWORDS)));
    }

    /** Count of $text's tokens that also appear in the already-tokenized $questionTokens. */
    public static function overlapScore(array $questionTokens, ?string $text): int
    {
        if (empty($questionTokens) || empty($text) || trim($text) === '') {
            return 0;
        }

        return count(array_intersect($questionTokens, self::tokenize($text)));
    }

    /**
     * Ranks scored candidates by score descending, then recency
     * descending, then id descending. Relevance always wins - recency and
     * id are pure tie-breaks and can never outrank a more relevant but
     * older/lower-id item.
     *
     * @param array<int, array{item: mixed, score: int, updated_at: \Illuminate\Support\Carbon, id: int}> $scored
     * @return array<int, array{item: mixed, score: int, updated_at: \Illuminate\Support\Carbon, id: int}>
     */
    public static function rank(array $scored): array
    {
        usort($scored, function (array $a, array $b) {
            if ($a['score'] !== $b['score']) {
                return $b['score'] <=> $a['score'];
            }

            $byRecency = $b['updated_at']->timestamp <=> $a['updated_at']->timestamp;

            return $byRecency !== 0 ? $byRecency : $b['id'] <=> $a['id'];
        });

        return $scored;
    }

    /**
     * Greedily keeps already-ranked (most relevant first) items while
     * within $maxCount and $charBudget. An item too large to fit is
     * skipped in favor of trying smaller, lower-ranked ones - relevance
     * order is not chronological, so skipping (not stopping outright) gets
     * more of the budget's value used on genuinely relevant items. Always
     * keeps at least the single top-ranked item, even if it alone exceeds
     * the character budget, so one long item can never empty the whole
     * selection.
     *
     * @param array<int, array{item: mixed, score: int, updated_at: \Illuminate\Support\Carbon, id: int}> $ranked
     * @return array<int, mixed>
     */
    public static function selectWithinBudget(array $ranked, int $maxCount, int $charBudget, callable $lengthOf): array
    {
        $selected = [];
        $charsUsed = 0;

        foreach ($ranked as $entry) {
            if (count($selected) >= $maxCount) {
                break;
            }

            $length = $lengthOf($entry['item']);

            if (!empty($selected) && $charsUsed + $length > $charBudget) {
                continue;
            }

            $selected[] = $entry['item'];
            $charsUsed += $length;
        }

        if (empty($selected) && !empty($ranked)) {
            $selected[] = $ranked[0]['item'];
        }

        return $selected;
    }
}
