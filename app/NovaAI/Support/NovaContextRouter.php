<?php

namespace App\NovaAI\Support;

use Illuminate\Support\Facades\Log;

/**
 * Stage 7B question-aware CRM retrieval: maps the admin's raw question to
 * the small subset of Nova's CRM intelligence domains actually relevant to
 * it, so NovaAIService::buildSnapshot() can skip building (and querying)
 * every domain it doesn't need. Deterministic keyword/phrase matching only
 * - no LLM call, no embeddings, no vector store, no external API, and no
 * database access of its own (this class never touches a CRM or
 * nova_memory connection). See app/NovaAI/README.md's Stage 7 section for
 * the full design rationale.
 *
 * THREE-OUTCOME CONTRACT - this distinction is load-bearing everywhere this
 * class is consumed:
 * - A non-empty array (e.g. ['finance']): build only these domains.
 * - An empty array []: the question confidently needs no CRM data at all
 *   (a greeting/meta message like "Hello Nova") - build zero CRM sections,
 *   but every other current-turn layer (memory, decisions, experiments,
 *   conversation) is unaffected.
 * - null: routing was uncertain, the question spread across too many
 *   domains, or routing itself failed - build the COMPLETE pre-Stage-7
 *   snapshot exactly as before. This is the permanent safety fallback;
 *   `[]` and `null` are never interchangeable.
 *
 * route() itself never throws: any unexpected failure is caught and
 * logged, degrading to `null` (full snapshot) - the same failure-isolation
 * discipline NovaBusinessFactService/NovaDecisionService already use, so a
 * routing bug can never cost Nova the ability to answer.
 */
class NovaContextRouter
{
    /**
     * The only domain keys buildSnapshot() understands. route() can never
     * return a value outside this list - every match below is looked up
     * against this fixed enum, never built from arbitrary input.
     */
    public const DOMAINS = [
        'finance',
        'appointments',
        'staff_performance',
        'payroll',
        'booking_agents',
        'marketing',
        'customers',
        'packages',
        'service_activity',
    ];

    /** Never return more than this many domains for a specific selection - beyond this, the question is too broad to trust a partial slice, so fall back to null (full snapshot) instead. */
    private const MAX_SELECTED_DOMAINS = 4;

    /**
     * Each domain's own, relatively unambiguous vocabulary. A domain is
     * "hit" the moment ANY one of its own phrases appears as a
     * word-bounded substring of the normalized question. Deliberately
     * phrase-based rather than pure single-token overlap (see
     * NovaRelevance for that different model, used for memory retrieval,
     * not reused here) - some real business phrasing only disambiguates as
     * a whole phrase ("no show" vs a bare "show", "staff target" vs a bare
     * "target").
     */
    private const DOMAIN_SIGNALS = [
        'finance' => [
            'revenue', 'profit', 'profits', 'profitability', 'margin', 'margins',
            'income', 'earning', 'earnings', 'earned', 'expense', 'expenses',
            'financial', 'finances', 'qar', 'afford', 'making enough', 'net profit',
            'make', 'made', 'money', 'sales',
        ],
        'appointments' => [
            'appointment', 'appointments', 'show rate', 'no show', 'no shows',
            'cancellation', 'cancellations', 'cancel', 'cancelled', 'arrived',
            'completed appointment', 'attendance', 'funnel',
        ],
        // Bare "performance"/"target" are deliberately excluded here - too
        // generic on their own (shared with booking_agents/payroll
        // phrasing) - see the TARGET ambiguity group below for how a bare
        // "target" is actually resolved.
        'staff_performance' => [
            'staff performance', 'beautician', 'beauticians', 'stylist', 'stylists',
            'upsell', 'upselling', 'upsells', 'upsell target', 'staff target',
            'performance target', 'coach', 'coaching',
        ],
        'payroll' => [
            'payroll', 'salary', 'salaries', 'overtime', 'deduction', 'deductions',
            'wage', 'wages', 'staff cost', 'paying staff', 'costing us', 'cost us',
        ],
        'booking_agents' => [
            'booking agent', 'booking agents', 'agent target', 'agent performance',
            'morning agent', 'evening agent', 'booking target',
        ],
        'marketing' => [
            'marketing', 'advertising', 'ads', 'ad', 'lead', 'leads', 'inquiry',
            'inquiries', 'conversion', 'conversions', 'campaign', 'campaigns',
            'cost per lead', 'cpl', 'cac', 'roas',
        ],
        // Bare "client"/"clients" deliberately excluded - packages
        // questions ("which clients have packages expiring?") legitimately
        // say "clients" too, and packages must stay resolvable on its own
        // without also always dragging in customers. "customer(s)" alone
        // is unambiguous enough to keep as a direct signal.
        'customers' => [
            'customer', 'customers', 'retention', 'returning',
            'repeat customer', 'repeat customers', 'repeat clients', 'dormant',
            'reactivation', 'rebooking', 'maintenance', 'lifetime spend',
        ],
        'packages' => [
            'package', 'packages', 'package balance', 'remaining services',
            'package expiry', 'package expiring', 'redeemed', 'unredeemed',
        ],
        // Bare "service"/"services" deliberately excluded - too generic
        // (this is a salon CRM; "service" appears incidentally all over
        // real questions) to be a safe standalone signal.
        'service_activity' => [
            'most booked', 'top service', 'top services', 'service volume',
            'popular service', 'popular services', 'most popular',
        ],
    ];

    /**
     * Genuinely cross-cutting business words that do not belong to one
     * domain more than another. Each group only contributes to ITS OWN
     * member domains, and only when NONE of that group's domains already
     * has independent evidence from DOMAIN_SIGNALS above - a specific
     * phrase (e.g. "booking agent", "upsell target") already disambiguates
     * the sentence, so the generic shared word is treated as already
     * "explained" and does not pull in the other, unrelated domain(s).
     * This is what makes "who is behind upsell target?" resolve to
     * staff_performance alone while a bare "who is behind target?"
     * resolves to both - see NovaContextRouterTest for the full matrix.
     */
    private const AMBIGUOUS_GROUPS = [
        [
            'words' => ['target'],
            'domains' => ['staff_performance', 'booking_agents'],
        ],
        [
            'words' => ['booking', 'bookings'],
            'domains' => ['appointments', 'booking_agents', 'marketing'],
        ],
        [
            // "which offer should I push" is a real cross-cutting
            // question in this business (what's popular + who to target +
            // how to advertise it) - not a fabricated name/roster lookup.
            'words' => ['offer', 'offers', 'push', 'promote', 'promotion', 'promotions'],
            'domains' => ['marketing', 'service_activity', 'customers'],
        ],
    ];

    /**
     * Narrow, exact-flavoured greeting/meta detection - deliberately not a
     * general intent classifier. Only consulted when zero domains were hit
     * at all; any real business vocabulary anywhere in the message always
     * wins over this (see routeInternal()).
     */
    private const GREETING_PHRASES = [
        'hello', 'hi', 'hey', 'good morning', 'good evening', 'good afternoon',
        'thanks', 'thank you', 'who are you', 'what can you do', 'how are you',
        'help',
    ];

    private const GREETING_MAX_WORDS = 6;

    private const NOVA_TOKEN_PATTERN = '/\bnova\b/';

    /**
     * @return array<int, string>|null Selected domain keys, [] for
     *     "confidently no CRM data needed", or null for "use the complete
     *     full snapshot" (uncertain, too broad, or a routing failure).
     */
    public static function route(string $question): ?array
    {
        try {
            return self::routeInternal($question);
        } catch (\Throwable $e) {
            Log::warning('Nova context router failed - falling back to the full CRM snapshot', [
                'message' => $e->getMessage(),
            ]);

            return null;
        }
    }

    private static function routeInternal(string $question): ?array
    {
        $normalized = self::normalize($question);
        $padded = " {$normalized} ";

        $hits = [];

        foreach (self::DOMAIN_SIGNALS as $domain => $phrases) {
            foreach ($phrases as $phrase) {
                if (str_contains($padded, " {$phrase} ")) {
                    $hits[$domain] = true;
                    break;
                }
            }
        }

        foreach (self::AMBIGUOUS_GROUPS as $group) {
            $wordPresent = false;

            foreach ($group['words'] as $word) {
                if (str_contains($padded, " {$word} ")) {
                    $wordPresent = true;
                    break;
                }
            }

            if (!$wordPresent) {
                continue;
            }

            $alreadyExplained = false;

            foreach ($group['domains'] as $domain) {
                if (!empty($hits[$domain])) {
                    $alreadyExplained = true;
                    break;
                }
            }

            if ($alreadyExplained) {
                continue;
            }

            foreach ($group['domains'] as $domain) {
                $hits[$domain] = true;
            }
        }

        // Preserve DOMAINS' canonical order regardless of match order.
        $selected = array_values(array_intersect(self::DOMAINS, array_keys($hits)));

        if (empty($selected)) {
            // normalize() only understands ASCII letters/digits. A message
            // written entirely in a script it can't represent - Arabic,
            // Chinese, emoji-only, etc. - normalizes to an empty string
            // exactly like a genuinely blank/greeting message would. Those
            // are NOT the same thing: unreadable-by-this-router is
            // uncertain (null, full snapshot), never a confident "no CRM
            // data needed" ([]). Only trust the greeting check when the
            // admin's original text was itself empty/whitespace, or the
            // normalizer actually had real ASCII content to evaluate.
            if (trim($question) !== '' && trim($normalized) === '') {
                return null;
            }

            return self::isGreeting($padded) ? [] : null;
        }

        if (count($selected) > self::MAX_SELECTED_DOMAINS) {
            return null;
        }

        return $selected;
    }

    /** Lowercase, punctuation collapsed to spaces, whitespace collapsed - mirrors NovaRelevance::tokenize()'s own normalization, without dropping stopwords (routing needs phrases like "who are you" intact). */
    private static function normalize(string $text): string
    {
        $normalized = strtolower($text);
        $normalized = preg_replace('/[^a-z0-9\s]/', ' ', $normalized) ?? '';

        return trim(preg_replace('/\s+/', ' ', $normalized) ?? '');
    }

    /**
     * Only reached when zero domains were hit. Strips a standalone "nova"
     * address term, then requires either a short message that contains one
     * of the fixed greeting phrases. Deliberately capped at
     * GREETING_MAX_WORDS so an unmatched but clearly substantive question
     * (no domain vocabulary this router knows, but not a greeting either)
     * falls to null rather than being miscategorized as "no CRM needed".
     */
    private static function isGreeting(string $padded): bool
    {
        $withoutNova = trim((string) preg_replace(self::NOVA_TOKEN_PATTERN, ' ', trim($padded)));
        $withoutNova = trim((string) preg_replace('/\s+/', ' ', $withoutNova));

        if ($withoutNova === '') {
            return true;
        }

        if (count(explode(' ', $withoutNova)) > self::GREETING_MAX_WORDS) {
            return false;
        }

        $paddedWithoutNova = " {$withoutNova} ";

        foreach (self::GREETING_PHRASES as $phrase) {
            if (str_contains($paddedWithoutNova, " {$phrase} ")) {
                return true;
            }
        }

        return false;
    }
}
