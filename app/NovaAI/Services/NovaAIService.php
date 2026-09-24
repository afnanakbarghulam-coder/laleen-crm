<?php

namespace App\NovaAI\Services;

use App\Models\Appointment;
use App\Models\AppointmentService;
use App\Models\ClientPackage;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\Staff;
use App\NovaAI\Support\BookingAgentPerformanceAnalytics;
use App\NovaAI\Support\CustomerRetentionAnalytics;
use App\NovaAI\Support\MarketingLeadAnalytics;
use App\NovaAI\Support\PayrollPerformanceAnalytics;
use App\Support\BranchFinancialSummaryService;
use App\Support\StaffSalesAnalytics;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Nova's analytical engine: pulls a fresh business snapshot straight from
 * the CRM's own tables (today's and the trailing week's revenue, the
 * trailing week's appointment-status funnel, month-to-date branch net
 * profit/margin, sale-item-attributed staff performance, month-to-date
 * staff upsell target performance, calendar-month staff payroll cost,
 * month-to-date booking-agent shift performance, month-to-date
 * marketing/lead intelligence, service volume, aggregate customer &
 * retention intelligence, and clients sitting on an unredeemed combo
 * package balance) and hands it to Gemini alongside the admin's question,
 * under a system instruction that keeps Nova speaking like an executive
 * advisor rather than a generic chatbot. Never throws back to the
 * controller - a missing key or a failed request degrades to a plain,
 * clearly-labelled notice instead of breaking the widget.
 *
 * NOVA READ-ONLY INTEGRATION RULE (see app/NovaAI/README.md): every
 * section below only reads existing CRM models/tables or already-reusable
 * services without changing them. This class must never require a change
 * to an existing CRM controller, calculation, schema, or workflow -
 * Nova-specific calculations (like BranchFinancialSummaryService) are
 * kept as independent, read-only copies rather than making CRM code
 * depend on Nova.
 */
class NovaAIService
{
    private const STAFF_PERFORMANCE_DAYS = 7;
    private const SERVICE_COUNT_DAYS = 7;
    private const PACKAGE_LOOKAHEAD = 15;
    private const APPOINTMENT_FUNNEL_DAYS = 7;

    /**
     * The only statuses Appointment::status ever actually holds (enforced by
     * AppointmentController's own validation - see 'pending,arrived,in_progress,
     * completed,no_show,cancelled' on create and 'arrived,in_progress,completed,
     * no_show,cancelled' on updateStatus). There is no "rescheduled" status.
     */
    private const APPOINTMENT_STATUSES = ['pending', 'arrived', 'in_progress', 'completed', 'no_show', 'cancelled'];

    private const APPOINTMENT_FUNNEL_BRANCHES = ['old_airport', 'wakrah', 'home_service'];

    /** In-process memoization only - avoids re-reading the prompt file if ask() runs more than once per request. */
    private static ?string $cachedPersona = null;

    /**
     * Stable public contract, unchanged since Stage 1 - returns Nova's
     * reply text only. Stage 4's conversation-memory orchestration
     * (App\NovaAI\Services\NovaConversationService, wired in from the
     * controller) needs to know whether a reply was a genuine Gemini
     * answer or an infrastructure fallback message, so it can decide
     * whether that reply is worth remembering - askWithMeta() below
     * exposes that distinction without changing this method's signature
     * or behavior for any existing caller.
     */
    public function ask(string $message, array $recentTurns = [], array $businessFacts = [], array $decisions = [], array $experiments = [], ?array $domains = null): string
    {
        return $this->askWithMeta($message, $recentTurns, $businessFacts, $decisions, $experiments, $domains)['reply'];
    }

    /**
     * @param array<int, array{role: string, content: string}> $recentTurns
     *     Prior turns in this conversation, oldest first, each
     *     role = 'user' or 'assistant'. Passed straight through as native
     *     Gemini chat turns (see buildContents()) ahead of the current,
     *     always-fresh business snapshot + question - never persisted or
     *     re-derived here. Empty by default, which reproduces the exact
     *     single-turn request every existing caller/test already expects.
     * @param array<int, array{category: string, value: string}> $businessFacts
     *     Business facts relevant to $message (App\NovaAI\Services\
     *     NovaBusinessFactService::relevantFacts() - deterministic
     *     keyword-overlap scoring, never every active fact) - statements
     *     the owner has explicitly made in the past, never a live CRM
     *     figure and never something Nova inferred or recommended.
     *     Rendered as its own clearly-labelled section in the CURRENT turn
     *     (see buildBusinessFactsSection()), never blended into the CRM
     *     snapshot text.
     * @param array<int, array{title: string, description: ?string, category: string, review_date: ?string}> $decisions
     *     Decisions relevant to $message (App\NovaAI\Services\
     *     NovaDecisionService::relevantDecisions()) - durable choices the
     *     owner has explicitly approved, never a Nova recommendation
     *     alone. Rendered as its own section; never implies any CRM record
     *     was actually changed.
     * @param array<int, array{title: string, description: ?string, category: string, started_at: ?string, ends_at: ?string, target_metric: ?string, success_criteria: ?string, review_date: ?string}> $experiments
     *     Experiments relevant to $message (App\NovaAI\Services\
     *     NovaDecisionService::relevantExperiments()) - time-bounded
     *     trials the owner explicitly approved. Unspecified parameters
     *     arrive as null, never invented.
     * @param ?array<int, string> $domains
     *     Stage 7 question-aware retrieval (App\NovaAI\Support\
     *     NovaContextRouter::route()) - which CRM domains buildSnapshot()
     *     should actually build for this question. A non-empty array
     *     builds only those domains; [] builds none (the question needs no
     *     CRM data at all); null (the default, and what every pre-Stage-7
     *     caller/test still passes implicitly) builds the COMPLETE
     *     snapshot exactly as before Stage 7 - the permanent safety
     *     fallback. See buildSnapshot()'s own docblock.
     * @return array{reply: string, succeeded: bool}
     *     succeeded is true only for a genuine Gemini answer - false for
     *     every fallback branch (missing key, failed request, 429, empty
     *     response, exception), so callers can skip persisting a
     *     transient infrastructure message as if it were real memory.
     */
    public function askWithMeta(string $message, array $recentTurns = [], array $businessFacts = [], array $decisions = [], array $experiments = [], ?array $domains = null): array
    {
        $apiKey = config('services.gemini.key');

        if (empty($apiKey)) {
            return [
                'reply' => "Nova isn't connected yet - ask an administrator to set GEMINI_API_KEY in the environment configuration.",
                'succeeded' => false,
            ];
        }

        try {
            $model = config('services.gemini.model', 'gemini-3.5-flash-lite');

            // The key travels as a header, never a URL query string - a query
            // string ends up verbatim in cURL/Guzzle exception messages (and
            // any proxy/access log along the way), which is exactly how a key
            // leaks into storage/logs/laravel.log the first time a request
            // fails for any reason.
            //
            // This network's path to Google is flaky specifically *after* the
            // TCP/TLS handshake completes - connect succeeds fast, then the
            // response hangs with 0 bytes received until the full timeout,
            // which is the classic symptom of a lost HTTP/2 frame with no
            // clean reset on an unstable link. Forcing HTTP/1.1 (a fresh
            // connection per request instead of one multiplexed stream that
            // can stall entirely) plus IPv4 and a short connect cap are the
            // standard mitigations.
            //
            // The retry itself only fires for that kind of network-level
            // flakiness (a thrown ConnectionException) or a genuine 5xx on
            // Gemini's side - never for a 429/4xx. Those fail identically on
            // every attempt, so retrying just burns through the quota three
            // times as fast for the exact same outcome; `throw: false` keeps
            // a rejected-but-answered request (e.g. a 429) flowing into the
            // ordinary "not successful" branch below instead of being turned
            // into a generic exception that loses the real status/body.
            $response = Http::timeout(30)
                ->connectTimeout(8)
                ->retry(2, 500, function ($exception) {
                    if ($exception instanceof \Illuminate\Http\Client\ConnectionException) {
                        return true;
                    }

                    return $exception instanceof \Illuminate\Http\Client\RequestException
                        && $exception->response->serverError();
                }, throw: false)
                ->withOptions(['curl' => [
                    CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4,
                    CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                ]])
                ->withHeaders(['x-goog-api-key' => $apiKey])
                ->post(
                    "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent",
                    [
                        'systemInstruction' => [
                            'parts' => [['text' => $this->persona()]],
                        ],
                        'contents' => $this->buildContents($message, $recentTurns, $businessFacts, $decisions, $experiments, $domains),
                        'generationConfig' => [
                            'temperature' => 0.6,
                            // Stage 8: raised from 800 after live-verifying that a
                            // genuinely broad "full executive review across every
                            // domain" question hit finishReason MAX_TOKENS at
                            // 796/800 output tokens - a real, reproducible
                            // truncation risk, not a theoretical one. 1600 is a
                            // deliberate, bounded 2x headroom (confirmed by the
                            // same live question finishing naturally with
                            // finishReason STOP at ~1307 tokens), not an
                            // open-ended increase.
                            'maxOutputTokens' => 1600,
                        ],
                    ]
                );

            if (!$response->successful()) {
                Log::warning('Nova AI request failed', [
                    'status' => $response->status(),
                    'body' => $this->redactKey($response->body(), $apiKey),
                ]);

                if ($response->status() === 429) {
                    return [
                        'reply' => "Nova's Gemini quota is exhausted for now (429 from the API). Wait a bit before asking again, or check the plan/billing on the Gemini API key.",
                        'succeeded' => false,
                    ];
                }

                return [
                    'reply' => "Nova couldn't reach the analysis engine just now (the request failed). Try again in a moment.",
                    'succeeded' => false,
                ];
            }

            $text = data_get($response->json(), 'candidates.0.content.parts.0.text');
            $finishReason = data_get($response->json(), 'candidates.0.finishReason');

            // Observability only - Stage 8 finding: a genuinely broad
            // question can legitimately hit the output cap even after
            // raising it (see maxOutputTokens above). This never changes
            // what's returned to the admin (a MAX_TOKENS answer is still
            // usually a complete, useful partial answer) - it only makes a
            // real truncation visible in logs instead of silent. Never logs
            // reply content or business data, only the finish-reason code
            // and token counts.
            if ($finishReason === 'MAX_TOKENS') {
                Log::warning('Nova AI reply was truncated at the output token limit', [
                    'usage' => data_get($response->json(), 'usageMetadata'),
                ]);
            }

            if (empty(trim((string) $text))) {
                Log::warning('Nova AI returned an empty response', [
                    'finishReason' => $finishReason,
                    'usage' => data_get($response->json(), 'usageMetadata'),
                ]);

                return [
                    'reply' => "Nova didn't get a usable answer that time - try rephrasing the question.",
                    'succeeded' => false,
                ];
            }

            return ['reply' => trim($text), 'succeeded' => true];
        } catch (\Throwable $e) {
            // Belt-and-braces: even with the key out of the URL, never let a
            // raw exception message (which can echo request details) reach
            // the log without a pass through the redactor first.
            Log::warning('Nova AI errored', ['message' => $this->redactKey($e->getMessage(), $apiKey)]);

            return [
                'reply' => 'Nova hit an unexpected error reaching the analysis engine. Try again shortly.',
                'succeeded' => false,
            ];
        }
    }

    /**
     * Native Gemini multi-turn chat turns: prior conversation history first
     * (role 'user'/'model' - Gemini's own naming, translated from our
     * stored 'user'/'assistant'), then always exactly one final 'user' turn
     * carrying the CURRENT, freshly-built business snapshot + question.
     * History carries only the plain visible text of past turns - never a
     * snapshot - so recent conversation can never smuggle in stale CRM data
     * as if it were current (see resources/prompts/nova-system.md's "live
     * CRM data overrides recalled conversation" rule). When $recentTurns is
     * empty (every caller/test before Stage 4), this produces the exact
     * single-turn contents array Nova has always sent.
     */
    private function buildContents(string $message, array $recentTurns, array $businessFacts = [], array $decisions = [], array $experiments = [], ?array $domains = null): array
    {
        $contents = [];

        foreach ($recentTurns as $turn) {
            $contents[] = [
                'role' => ($turn['role'] ?? 'user') === 'assistant' ? 'model' : 'user',
                'parts' => [['text' => (string) ($turn['content'] ?? '')]],
            ];
        }

        $contents[] = ['role' => 'user', 'parts' => [['text' => $this->buildPrompt($message, $businessFacts, $decisions, $experiments, $domains)]]];

        return $contents;
    }

    /** Strips a literal API key out of any string before it's ever logged. */
    private function redactKey(?string $text, ?string $apiKey): string
    {
        $text = (string) $text;

        return $apiKey ? str_replace($apiKey, '[redacted]', $text) : $text;
    }

    /**
     * Behavior/tone only - never the place for live data, since this is
     * sent once as Gemini's systemInstruction rather than per-question.
     * Lives in resources/prompts/nova-system.md (content, not code) so the
     * prompt can be iterated on without touching this class; memoized in
     * a static property for the lifetime of the process.
     */
    private function persona(): string
    {
        if (self::$cachedPersona !== null) {
            return self::$cachedPersona;
        }

        try {
            $contents = trim(File::get(resource_path('prompts/nova-system.md')));

            if ($contents === '') {
                throw new \RuntimeException('Nova system prompt file is empty.');
            }
        } catch (\Throwable $e) {
            Log::warning('Nova system prompt could not be loaded - using fallback persona', [
                'message' => $e->getMessage(),
            ]);

            $contents = $this->fallbackPersona();
        }

        return self::$cachedPersona = $contents;
    }

    /**
     * Only reached if resources/prompts/nova-system.md is missing, unreadable,
     * or empty - keeps Nova grounded and honest even without her full
     * executive prompt, rather than ever sending Gemini an empty
     * systemInstruction.
     */
    private function fallbackPersona(): string
    {
        return "You are Nova, the executive AI advisor for Laleen Ops. Speak with "
            . "direct, evidence-based executive judgment. Base every figure strictly "
            . "on the supplied business snapshot and never invent one; if data is "
            . "missing, say so plainly. Any commission figure is an estimate, not a "
            . 'posted payroll ledger.';
    }

    private function buildPrompt(string $message, array $businessFacts = [], array $decisions = [], array $experiments = [], ?array $domains = null): string
    {
        return 'BUSINESS SNAPSHOT (as of ' . now()->format('D, d M Y H:i') . "):\n"
            . $this->buildSnapshot($domains)
            . "\n\n" . $this->buildBusinessFactsSection($businessFacts)
            . "\n\n" . $this->buildDecisionsSection($decisions)
            . "\n\n" . $this->buildExperimentsSection($experiments)
            . "\n\nADMIN'S QUESTION:\n{$message}";
    }

    /**
     * @param array<int, array{category: string, value: string}> $facts
     *
     * Deliberately never merged into buildSnapshot()'s text: these are
     * facts the owner previously stated, not live CRM data, and the
     * system prompt's memory-priority rule (CRM snapshot > business facts
     * > conversation) depends on them staying visibly, structurally
     * separate from it.
     */
    private function buildBusinessFactsSection(array $facts): string
    {
        $header = 'REMEMBERED BUSINESS FACTS (previously stated by the owner - not live CRM data;'
            . ' if this conflicts with the business snapshot above, the snapshot wins)';

        if (empty($facts)) {
            return "{$header}\n- No business facts recorded yet.";
        }

        $lines = [$header];
        foreach ($facts as $fact) {
            $lines[] = sprintf('- [%s] %s', $fact['category'] ?? 'other', $fact['value'] ?? '');
        }

        return implode("\n", $lines);
    }

    /**
     * @param array<int, array{title: string, description: ?string, category: string, review_date: ?string}> $decisions
     *
     * Never merged into the CRM snapshot or the business-facts section:
     * a decision is the owner's stated intent, never proof the CRM
     * changed to match it - see resources/prompts/nova-system.md's
     * MEMORY PRIORITY ORDER and CRM-conflict-disclosure rules.
     */
    private function buildDecisionsSection(array $decisions): string
    {
        $header = 'ACTIVE DECISIONS (approved/committed to by the owner - never proof the CRM'
            . ' was actually updated to match; Nova never executes these)';

        if (empty($decisions)) {
            return "{$header}\n- No active decisions recorded yet.";
        }

        $lines = [$header];
        foreach ($decisions as $decision) {
            $lines[] = sprintf(
                '- [%s] %s%s%s',
                $decision['category'] ?? 'other',
                $decision['title'] ?? '',
                !empty($decision['description']) ? ' - ' . $decision['description'] : '',
                $this->reviewDateSuffix($decision['review_date'] ?? null)
            );
        }

        return implode("\n", $lines);
    }

    /**
     * @param array<int, array{title: string, description: ?string, category: string, started_at: ?string, ends_at: ?string, target_metric: ?string, success_criteria: ?string, review_date: ?string}> $experiments
     */
    private function buildExperimentsSection(array $experiments): string
    {
        $header = 'ACTIVE EXPERIMENTS (time-bounded trials approved by the owner - never proof of'
            . ' a result until the owner reports one; Nova never executes or runs these)';

        if (empty($experiments)) {
            return "{$header}\n- No active experiments recorded yet.";
        }

        $lines = [$header];
        foreach ($experiments as $experiment) {
            $window = $experiment['started_at'] || $experiment['ends_at']
                ? sprintf(' (%s - %s)', $experiment['started_at'] ?? '?', $experiment['ends_at'] ?? '?')
                : '';
            $metric = !empty($experiment['target_metric']) ? " | measuring: {$experiment['target_metric']}" : '';
            $criteria = !empty($experiment['success_criteria']) ? " | success = {$experiment['success_criteria']}" : '';

            $lines[] = sprintf(
                '- [%s] %s%s%s%s%s%s',
                $experiment['category'] ?? 'other',
                $experiment['title'] ?? '',
                $window,
                !empty($experiment['description']) ? ' - ' . $experiment['description'] : '',
                $metric,
                $criteria,
                $this->reviewDateSuffix($experiment['review_date'] ?? null)
            );
        }

        return implode("\n", $lines);
    }

    /**
     * "Review-date awareness only": no scheduler or notification exists
     * anywhere for this - this is purely a derived label on data already
     * being shown, computed fresh on every request, so Nova can recognize
     * an overdue review if asked without any automation behind it.
     */
    private function reviewDateSuffix(?string $reviewDate): string
    {
        if (empty($reviewDate)) {
            return '';
        }

        $isOverdue = \Illuminate\Support\Carbon::parse($reviewDate)->isPast();

        return sprintf(' | review date: %s%s', $reviewDate, $isOverdue ? ' (OVERDUE)' : '');
    }

    /* ---------------- data gathering ---------------- */

    /**
     * TEMPORARY CURRENT-CAPABILITY BOUNDARY: the "CURRENT DATA BOUNDARIES"
     * section of resources/prompts/nova-system.md tells Nova exactly which
     * sections below she has (still no ad spend, leads/CAC,
     * expenses/profitability, retention, LTV, staff targets/utilization).
     * If this method grows new sections in a later stage, that part of the
     * prompt must be revised to match, or Nova will keep disclaiming data
     * she actually has.
     *
     * Every section-builder method below, in the exact canonical order
     * they have always run in - this order is preserved by buildSnapshot()
     * regardless of which subset Stage 7's App\NovaAI\Support\
     * NovaContextRouter selects, so a multi-domain answer's prompt
     * structure never depends on router-match order (see Stage 7 note in
     * app/NovaAI/README.md).
     */
    private const SECTION_DOMAIN_MAP = [
        'revenueSection' => 'finance',
        'appointmentFunnelSection' => 'appointments',
        'branchFinancialSection' => 'finance',
        'staffPerformanceSection' => 'staff_performance',
        'staffTargetSection' => 'staff_performance',
        'staffPayrollSection' => 'payroll',
        'bookingAgentPerformanceSection' => 'booking_agents',
        'marketingLeadSection' => 'marketing',
        'serviceVolumeSection' => 'service_activity',
        'customerRetentionSection' => 'customers',
        'pendingPackagesSection' => 'packages',
    ];

    private const NO_DOMAIN_NOTICE = 'No CRM domain was required for this request.';

    /**
     * @param ?array<int, string> $domains Stage 7 question-aware retrieval
     *     (see askWithMeta()'s own docblock for the full three-outcome
     *     contract):
     *     - null (default): every section above runs, in the same order,
     *       with the same wording/calculations/queries as before Stage 7 -
     *       this is the permanent, byte-identical full-snapshot fallback
     *       every pre-Stage-7 test and caller already exercises.
     *     - []: no section runs at all - not even a query is issued for
     *       any of them - and a single clearly-worded marker is returned
     *       instead, so Nova can tell "no CRM domain was selected" apart
     *       from "a domain was checked and is empty".
     *     - a non-empty subset of App\NovaAI\Support\
     *       NovaContextRouter::DOMAINS: only the section-builder methods
     *       mapped to those domains run - an unselected domain's method is
     *       never called, so its query cost is never paid either.
     */
    private function buildSnapshot(?array $domains = null): string
    {
        if ($domains === []) {
            return self::NO_DOMAIN_NOTICE;
        }

        $methods = $domains === null
            ? array_keys(self::SECTION_DOMAIN_MAP)
            : array_keys(array_intersect(self::SECTION_DOMAIN_MAP, $domains));

        $sections = array_map(fn (string $method): string => $this->buildSectionSafely($method), $methods);

        return implode("\n\n", array_filter($sections));
    }

    /**
     * Stage 8 hardening: one section throwing (a query exception, a bad
     * cast, a collaborator error) must never take down the whole request -
     * the admin still gets an answer built from every section that DID
     * load, with the failed one clearly marked as unavailable rather than
     * silently missing (which the admin/Nova could otherwise misread as
     * "checked and zero" - the same "absence is not zero" distinction
     * Stage 7's system-prompt update already establishes for unselected
     * domains). This is the same catch-all discipline every other Nova
     * collaborator (NovaBusinessFactService, NovaDecisionService,
     * NovaConversationService) already uses for exactly this reason - it
     * is not a new indiscriminate pattern. It does not hide the failure: a
     * warning is always logged with the section name, domain, and
     * exception class/code - deliberately never $e->getMessage() (a
     * database/query exception's message can carry raw SQL, bindings, or
     * the input values themselves - potentially real customer/business
     * data), so this stays fully diagnosable without ever risking CRM
     * content in the logs.
     *
     * The eleven section-builder methods below are `protected`, not
     * `private` (a Stage 8 visibility-only change, no behavior change) -
     * PHP does not allow a subclass to override a private method when the
     * call site lives in the parent class, which would make this exact
     * failure-isolation behavior untestable without it. Still not part of
     * any public API - just testable-by-subclass.
     */
    private function buildSectionSafely(string $method): string
    {
        try {
            return $this->{$method}();
        } catch (\Throwable $e) {
            Log::warning('Nova CRM section failed to build - continuing with the remaining sections', [
                'section' => $method,
                'domain' => self::SECTION_DOMAIN_MAP[$method] ?? 'unknown',
                'exception' => get_class($e),
                'code' => $e->getCode(),
            ]);

            $domain = self::SECTION_DOMAIN_MAP[$method] ?? 'this';

            return "[{$domain} DATA UNAVAILABLE]\n- This business-data section could not be loaded right now due to an"
                . ' internal error. This is NOT evidence the underlying figures are zero - say so plainly if asked'
                . ' and suggest trying again.';
        }
    }

    protected function revenueSection(): string
    {
        $todayStart = now()->startOfDay();
        $todayEnd = now()->endOfDay();
        $weekStart = now()->copy()->subDays(6)->startOfDay();
        $monthStart = now()->copy()->startOfMonth();

        $today = Sale::whereBetween('created_at', [$todayStart, $todayEnd])
            ->selectRaw('branch, COUNT(*) as sales, SUM(total_amount) as revenue')
            ->groupBy('branch')
            ->get();

        $week = (float) Sale::whereBetween('created_at', [$weekStart, $todayEnd])->sum('total_amount');
        $month = (float) Sale::whereBetween('created_at', [$monthStart, $todayEnd])->sum('total_amount');

        $lines = ['REVENUE'];

        if ($today->isEmpty()) {
            $lines[] = '- No sales recorded yet today.';
        }

        foreach ($today as $row) {
            $lines[] = sprintf(
                '- Today, %s: %d sale(s), %.2f QAR',
                self::branchLabel($row->branch),
                $row->sales,
                (float) $row->revenue
            );
        }

        $lines[] = sprintf('- Trailing 7 days (all branches): %.2f QAR', $week);
        $lines[] = sprintf('- Month to date (all branches): %.2f QAR', $month);

        return implode("\n", $lines);
    }

    /**
     * Real, code-enforced appointment status data (see APPOINTMENT_STATUSES)
     * - not an estimate, not the ambiguous appointment-service rows
     * serviceVolumeSection() reads. Windowed on appointment_datetime (when
     * the visit is/was scheduled to happen), not created_at, and capped at
     * now() so a booking scheduled for later this week never counts toward
     * a historical outcome. A pending appointment whose scheduled time has
     * already passed stays counted as pending - it is not reclassified as
     * a no-show, cancellation, or completion just because time has moved on.
     */
    protected function appointmentFunnelSection(): string
    {
        $from = now()->copy()->subDays(self::APPOINTMENT_FUNNEL_DAYS - 1)->startOfDay();
        $to = now();

        $rows = Appointment::whereBetween('appointment_datetime', [$from, $to])
            ->selectRaw('branch, status, COUNT(*) as count')
            ->groupBy('branch', 'status')
            ->get();

        $header = 'APPOINTMENT FUNNEL (last ' . self::APPOINTMENT_FUNNEL_DAYS
            . ' days scheduled, ' . $from->format('d M') . ' - ' . $to->format('d M') . ')';

        if ($rows->isEmpty()) {
            return "{$header}\n- No appointments scheduled in this window.";
        }

        $lines = [$header];
        $lines[] = $this->formatFunnelLine('All branches', $rows);

        foreach (self::APPOINTMENT_FUNNEL_BRANCHES as $branch) {
            $branchRows = $rows->where('branch', $branch);

            if ($branchRows->isEmpty()) {
                continue;
            }

            $lines[] = $this->formatFunnelLine(self::branchLabel($branch), $branchRows);
        }

        return implode("\n", $lines);
    }

    /**
     * Counts are the verified fact; the three rates are calculations with
     * explicit, narrow denominators - never invented as 0% when nothing in
     * that population exists yet. Definitions (no established convention
     * existed anywhere else in the app to reuse - see Stage 3A audit):
     *   - cancellation rate = cancelled / total scheduled in the period
     *   - attendance-decision population = arrived + in_progress + completed + no_show
     *     (excludes cancelled and still-unresolved pending)
     *   - show rate = (arrived + in_progress + completed) / attendance-decision population
     *   - no-show rate = no_show / attendance-decision population
     */
    private function formatFunnelLine(string $label, $rows): string
    {
        $counts = array_fill_keys(self::APPOINTMENT_STATUSES, 0);

        foreach ($rows as $row) {
            if (array_key_exists($row->status, $counts)) {
                $counts[$row->status] += (int) $row->count;
            }
        }

        $total = array_sum($counts);
        $showed = $counts['arrived'] + $counts['in_progress'] + $counts['completed'];
        $attendanceDecided = $showed + $counts['no_show'];

        $showRate = $attendanceDecided > 0 ? round($showed / $attendanceDecided * 100, 1) . '%' : 'N/A';
        $noShowRate = $attendanceDecided > 0 ? round($counts['no_show'] / $attendanceDecided * 100, 1) . '%' : 'N/A';
        $cancellationRate = $total > 0 ? round($counts['cancelled'] / $total * 100, 1) . '%' : 'N/A';

        return sprintf(
            '- %s: %d scheduled | %d completed | %d arrived/in-progress | %d cancelled | %d no-show | %d pending'
                . ' - show rate %s, no-show rate %s, cancellation rate %s',
            $label,
            $total,
            $counts['completed'],
            $counts['arrived'] + $counts['in_progress'],
            $counts['cancelled'],
            $counts['no_show'],
            $counts['pending'],
            $showRate,
            $noShowRate,
            $cancellationRate
        );
    }

    /**
     * The same "CRM net profit" calculation already trusted and displayed
     * on the Finance dashboard - BranchFinancialSummaryService, extracted
     * from FinanceController::branchBreakdown() in Stage 3C, not a second,
     * independently-derived figure. Month-to-date to match that
     * dashboard's own default reporting period. Only the two branches the
     * finance dashboard has ever covered are included here - Home Service
     * was never part of that breakdown and is not fabricated in this
     * section either.
     */
    protected function branchFinancialSection(): string
    {
        $from = now()->startOfMonth();
        $to = now()->endOfDay();

        $branches = (new BranchFinancialSummaryService())->summarize($from, $to);

        $header = 'BRANCH FINANCIAL PERFORMANCE (month to date, '
            . $from->format('d M') . ' - ' . $to->format('d M') . ')';

        $lines = [$header];

        $combinedSales = 0.0;
        $combinedExpenses = 0.0;

        foreach ($branches as $branch) {
            $lines[] = $this->formatBranchFinancialLine(
                $branch['label'],
                $branch['sales'],
                $branch['expenses'],
                $branch['profit'],
                $branch['margin_percent']
            );
            $combinedSales += $branch['sales'];
            $combinedExpenses += $branch['expenses'];
        }

        $combinedProfit = $combinedSales - $combinedExpenses;
        $combinedMargin = $combinedSales > 0 ? ($combinedProfit / $combinedSales) * 100 : null;
        $lines[] = $this->formatBranchFinancialLine(
            'Combined (Old Airport + Al Wakrah)',
            $combinedSales,
            $combinedExpenses,
            $combinedProfit,
            $combinedMargin
        );

        $lines[] = '- CRM net profit = recorded sales revenue minus recorded expenses in the CRM;'
            . ' no service/product cost data exists to calculate gross margin or contribution margin.';
        $lines[] = '- Home Service is not included in this branch financial breakdown.';

        return implode("\n", $lines);
    }

    private function formatBranchFinancialLine(string $label, float $sales, float $expenses, float $profit, ?float $marginPercent): string
    {
        $marginStr = $marginPercent === null ? 'N/A' : round($marginPercent, 1) . '%';

        return sprintf(
            '- %s: %.2f QAR revenue, %.2f QAR recorded expenses, CRM net profit %.2f QAR, CRM profit margin %s',
            $label,
            $sales,
            $expenses,
            $profit,
            $marginStr
        );
    }

    protected function staffPerformanceSection(): string
    {
        $from = now()->copy()->subDays(self::STAFF_PERFORMANCE_DAYS - 1)->startOfDay();
        $to = now()->endOfDay();

        $rows = SaleItem::query()
            ->join('sales', 'sales.id', '=', 'sale_items.sale_id')
            ->whereNotNull('sale_items.staff_id')
            ->where('sale_items.type', 'service')
            ->whereBetween('sales.created_at', [$from, $to])
            ->groupBy('sale_items.staff_id')
            ->selectRaw('sale_items.staff_id, COUNT(*) as services_done, SUM(sale_items.total) as revenue')
            ->orderByDesc('revenue')
            ->get();

        $header = 'STAFF PERFORMANCE (last ' . self::STAFF_PERFORMANCE_DAYS . ' days, revenue attributed per service performed)';

        if ($rows->isEmpty()) {
            return "{$header}\n- No staff-attributed service revenue in this window.";
        }

        $staffById = Staff::whereIn('id', $rows->pluck('staff_id'))->get()->keyBy('id');

        $lines = [$header];
        foreach ($rows as $row) {
            $staff = $staffById->get($row->staff_id);
            $name = $staff->name ?? "Staff #{$row->staff_id}";
            $revenue = (float) $row->revenue;
            $line = sprintf('- %s: %d service(s), %.2f QAR attributed revenue', $name, $row->services_done, $revenue);

            if ($staff && $staff->commission_rate) {
                $estimate = round($revenue * ((float) $staff->commission_rate / 100), 2);
                $line .= sprintf(
                    ' (est. commission at %.1f%%: %.2f QAR, not a posted ledger)',
                    (float) $staff->commission_rate,
                    $estimate
                );
            }

            $lines[] = $line;
        }

        return implode("\n", $lines);
    }

    /**
     * NOVA READ-ONLY INTEGRATION RULE: this reads the CRM's existing
     * App\Support\StaffSalesAnalytics directly - the exact class that
     * already powers the "Sales & Upsells"/"Analytics" KPI screens and the
     * dashboard's branch comparison card - without changing it in any way.
     * No third constructor argument is passed, so Nova always inherits
     * whatever StaffSalesAnalytics::DEFAULT_MONTHLY_TARGET actually is
     * rather than hardcoding a copy of it that could drift. Month-to-date
     * to match how both existing callers (UserController::dashboard(),
     * Kpi\StaffSalesController) already use this class by default.
     *
     * This is an UPSELL target only - StaffSalesAnalytics has no concept
     * of total sales, salary, or general productivity, and neither does
     * this section. Rows within a branch are sorted by achievement
     * ascending (lowest first) as the more executive-useful ordering than
     * the class's own alphabetical default - a read-only presentation
     * choice, not a recalculation.
     */
    protected function staffTargetSection(): string
    {
        $from = now()->startOfMonth();
        $to = now()->endOfDay();

        $analytics = new StaffSalesAnalytics($from, $to);

        $header = 'STAFF TARGET PERFORMANCE (month to date, upsell target only, '
            . $from->format('d M') . ' - ' . $to->format('d M') . ')';

        $lines = [$header];
        $anyStaff = false;

        foreach (StaffSalesAnalytics::BRANCHES as $key => $label) {
            $staffRows = $analytics->computedStaff($key)->sortBy('pct')->values();

            if ($staffRows->isEmpty()) {
                continue;
            }

            $anyStaff = true;
            $lines[] = $label . ':';

            foreach ($staffRows as $row) {
                $lines[] = $this->formatStaffTargetLine($row);
            }

            $totals = $analytics->totals($key);
            $borderCounts = $staffRows->countBy('border');

            $lines[] = sprintf(
                '- %s summary: %d green / %d amber / %d red, team %.2f QAR of %.2f QAR target (%.1f%%),'
                    . ' top performer %s (%.2f QAR)',
                $label,
                $borderCounts->get('green', 0),
                $borderCounts->get('amber', 0),
                $borderCounts->get('red', 0),
                $totals['team_total'],
                $totals['team_target'],
                $totals['team_pct'],
                $totals['top_performer'],
                $totals['top_performer_amount']
            );
        }

        if (!$anyStaff) {
            $lines[] = '- No staff recorded against Old Airport or Al Wakrah for this period.';
        }

        $lines[] = '- This is the CRM\'s existing UPSELL target only - a flat '
            . StaffSalesAnalytics::DEFAULT_MONTHLY_TARGET . ' QAR/month target per staff member, prorated'
            . ' by days elapsed - never total sales, salary, or overall productivity. Missing this target'
            . ' does not by itself mean someone is a poor performer: apply the usual skill/will/process/'
            . 'opportunity/training/management reasoning before concluding anything about a specific'
            . ' person. It also does not measure attendance, actual hours worked, or opportunity/customer'
            . ' volume - stylists have no clock-in attendance system in this CRM.';

        return implode("\n", $lines);
    }

    private function formatStaffTargetLine(array $row): string
    {
        return sprintf(
            '- %s: %.2f QAR upsell | target %.2f QAR | %.1f%% | gap %.2f QAR | %s',
            $row['name'],
            $row['upsell'],
            $row['prorated_target'],
            $row['pct'],
            $row['gap'],
            strtoupper($row['border'])
        );
    }

    /**
     * NOVA READ-ONLY INTEGRATION RULE: the payroll formula (base salary +
     * overtime pay - deductions) comes straight from the CRM's existing,
     * unmodified StaffPayrollCalculator::rowFor()/payrollFor() - exactly
     * how StaffController::index()'s Payroll tab already uses it, same
     * active-staff population. See
     * App\NovaAI\Support\PayrollPerformanceAnalytics for the branch
     * bucketing (never double-counting a 'both'-branch staff member).
     *
     * CRITICAL DISTINCTIONS this method must never blur:
     * - PAYROLL is not COMMISSION. staffPerformanceSection() above shows
     *   an ESTIMATED commission (attributed revenue x commission_rate) -
     *   a completely different, unrelated number computed by a different
     *   mechanism. Never add them together or imply one measures the other.
     * - Full calendar month, not month-to-date: StaffController's own
     *   Payroll tab uses startOfMonth()->endOfMonth() (the whole month,
     *   including days that haven't happened yet), unlike every other
     *   Nova section's "start of month to now" - mirrored exactly here
     *   since that is the CRM's own established payroll period.
     * - base_salary is never prorated - it is always the staff member's
     *   full stored monthly rate regardless of period length.
     * - Whether this calculated payroll is already represented in the
     *   CRM's recorded Expense rows is NOT established anywhere in the
     *   code - Expense::create() is only ever called from a plain manual
     *   admin form with no link to payroll at all. This method must never
     *   be combined with branchFinancialSection()'s net profit figure.
     */
    protected function staffPayrollSection(): string
    {
        $from = now()->startOfMonth();
        $to = now()->endOfMonth();

        $data = (new PayrollPerformanceAnalytics())->summary($from, $to);

        $header = 'STAFF PAYROLL COST (calendar month, not limited to elapsed days - the CRM\'s own payroll period, '
            . $from->format('d M') . ' - ' . $to->format('d M') . ')';

        $lines = [$header];

        if ($data['rows']->isEmpty()) {
            $lines[] = '- No active staff recorded for payroll calculation.';
        } else {
            foreach ($data['rows'] as $row) {
                $lines[] = sprintf(
                    '- %s (%s): base salary %.2f QAR | overtime %.2f QAR (%.1f hrs) | deductions %.2f QAR |'
                        . ' calculated net salary %.2f QAR',
                    $row['name'],
                    self::branchLabel($row['branch']),
                    $row['base_salary'],
                    $row['overtime_pay'],
                    $row['overtime_hours'],
                    $row['deductions'],
                    $row['net_salary']
                );
            }
        }

        $lines[] = $this->formatPayrollTotalsLine('Old Airport', $data['old_airport']);
        $lines[] = $this->formatPayrollTotalsLine('Al Wakrah', $data['wakrah']);

        if ($data['both']['staff_count'] > 0) {
            $lines[] = $this->formatPayrollTotalsLine(
                'Both-branch staff (not included in either branch total above, to avoid double-counting)',
                $data['both']
            );
        }

        $lines[] = $this->formatPayrollTotalsLine('All active staff combined', $data['overall']);

        $lines[] = '- This is the CRM\'s existing payroll formula only: base salary + overtime pay - deductions.'
            . ' It excludes commission entirely - the estimated commission figure in staff performance above is a'
            . ' separate, unrelated calculation; never add the two together. Whether this calculated payroll is'
            . ' already represented in the CRM\'s recorded expenses is not established anywhere in the code - do not'
            . ' subtract it from branch net profit, which would risk double-counting.';

        return implode("\n", $lines);
    }

    private function formatPayrollTotalsLine(string $label, array $totals): string
    {
        return sprintf(
            '- %s (%d staff): base salary %.2f QAR | overtime %.2f QAR | deductions %.2f QAR | calculated net'
                . ' salary %.2f QAR',
            $label,
            $totals['staff_count'],
            $totals['base_salary'],
            $totals['overtime_pay'],
            $totals['deductions'],
            $totals['net_salary']
        );
    }

    /**
     * NOVA READ-ONLY INTEGRATION RULE: shift-level bookings/target/
     * achievement come straight from the CRM's existing, unmodified
     * KpiAgentTargetReport::shiftStats()/combined() - the exact methods
     * UserController::dashboard() already calls. Month-to-date to match
     * that same existing usage. See
     * App\NovaAI\Support\BookingAgentPerformanceAnalytics for exactly how
     * the per-agent breakdown is derived and why no per-agent target
     * exists to compare it against.
     *
     * Booking agents and salon staff/stylists (staffTargetSection() above)
     * are kept strictly separate populations - never combined or compared.
     * "Bookings" here means appointment RECORDS a booking-agent account
     * created during its own logged shift - never a completed appointment,
     * a sale, or a conversion; the appointment funnel section already
     * covers completion/show-rate separately.
     */
    protected function bookingAgentPerformanceSection(): string
    {
        $from = now()->startOfMonth();
        $to = now()->endOfDay();

        $data = (new BookingAgentPerformanceAnalytics())->summary($from, $to);

        $header = 'BOOKING AGENT PERFORMANCE (month to date, bookings attributed under the CRM\'s'
            . ' agent-shift logic, ' . $from->format('d M') . ' - ' . $to->format('d M') . ')';

        $lines = [$header];

        foreach (['morning' => 'Morning', 'evening' => 'Evening'] as $key => $label) {
            $shift = $data['shift_stats'][$key];
            $lines[] = sprintf(
                '- %s: %d bookings | target %d | %.1f%% | gap %d | %s',
                $label,
                $shift['bookings'],
                $shift['target'],
                $shift['pct'],
                $shift['gap'],
                strtoupper($shift['border'])
            );
        }

        $combined = $data['combined'];
        $lines[] = sprintf(
            '- Combined: %d bookings | target %d | %.1f%% | gap %d | %s',
            $combined['bookings'],
            $combined['target'],
            $combined['pct'],
            $combined['gap'],
            strtoupper($data['combined_border'])
        );

        if ($data['per_agent']->isEmpty()) {
            $lines[] = '- No agent shift logs (with both check-in and check-out recorded) in this period.';
        } else {
            $lines[] = 'Per-agent bookings this period (raw counts - no individual per-agent target exists'
                . ' in the CRM, only the aggregate shift target above):';
            foreach ($data['per_agent'] as $agent) {
                $lines[] = sprintf(
                    '- %s: %d morning / %d evening (%d total), %.1f logged shift hours',
                    $agent['name'],
                    $agent['morning'],
                    $agent['evening'],
                    $agent['total'],
                    $agent['logged_hours']
                );
            }
        }

        $lines[] = '- Attribution is strict: a booking only counts here when the SAME agent account'
            . ' created the appointment record (not merely credited to them) during their own logged'
            . ' check-in-to-check-out window that day. A day with no shift log for an agent means no'
            . ' attribution data exists, not a proven absence.';

        return implode("\n", $lines);
    }

    /**
     * NOVA READ-ONLY INTEGRATION RULE: ad-inquiry figures come from the
     * CRM's existing, unmodified KpiAdsConversionReport (exactly how
     * UserController::dashboard() already uses it - a fresh, unsaved
     * instance, never persisted). Lead follow-up figures mirror
     * LeadController's own conventions. See
     * App\NovaAI\Support\MarketingLeadAnalytics for the full data-quality
     * reasoning this section depends on.
     *
     * This is the weakest-evidence domain Nova has: every AdLeadEntry row
     * is manually typed, "booked" is a manual branch selection rather than
     * an Appointment/Sale link, ticket_amount is self-reported and never
     * reconciled to Sale, and there is no advertising-spend field anywhere
     * in this schema - CAC/CPL/ROAS cannot be calculated, full stop, and
     * this method must never produce them.
     */
    protected function marketingLeadSection(): string
    {
        $from = now()->startOfMonth();
        $to = now()->endOfDay();

        $analytics = new MarketingLeadAnalytics();
        $ads = $analytics->adSummary($from, $to);
        $leads = $analytics->leadSummary($from, $to);

        $header = 'MARKETING & LEAD INTELLIGENCE (month to date, manually maintained ad-inquiry log,'
            . ' ' . $from->format('d M') . ' - ' . $to->format('d M') . ')';

        $lines = [$header];

        $totals = $ads['totals'];
        $lines[] = sprintf(
            '- Total recorded ad inquiries: %d | marked booked (branch manually selected, not an appointment/sale link): %d'
                . ' | recorded booking conversion: %.1f%% (target 20%%)',
            $totals['total_leads'],
            $totals['total_bookings'],
            $totals['overall_conversion']
        );
        $lines[] = sprintf(
            '- Reported booking value (self-reported ticket amounts on booked entries, NOT reconciled to Sale records): %.2f QAR',
            $totals['total_revenue']
        );

        $branch = $ads['branch_comparison'];
        $lines[] = sprintf(
            '- Booked entries by branch (inquiry volume by branch is not tracked - branch is only recorded when'
                . ' booked): Old Airport %d booked / %.2f QAR reported, Al Wakrah %d booked / %.2f QAR reported.'
                . ' Home Service is not a supported branch in the ad-inquiry log.',
            $branch['old_airport']['bookings'],
            $branch['old_airport']['revenue'],
            $branch['wakrah']['bookings'],
            $branch['wakrah']['revenue']
        );

        if (!empty($ads['top_categories'])) {
            $lines[] = 'Top categories by recorded inquiry volume:';
            foreach ($ads['top_categories'] as $category) {
                $lines[] = sprintf(
                    '- %s: %d leads / %d booked / %.1f%% conversion (%s)',
                    $category['name'],
                    $category['leads'],
                    $category['bookings'],
                    $category['conversion'],
                    strtoupper($category['status'])
                );
            }
        }

        $lines[] = sprintf(
            'Lead follow-up log (separate from the ad-inquiry data above; Lead.customer_id links to a real'
                . ' Customer record): %d lead records this period | %d marked follow-up-completed'
                . ' (needful_done - task completed, NOT a sale/conversion) | %d pending | %d overdue for'
                . ' follow-up (all-time count, not limited to this period)',
            $leads['total_leads'],
            $leads['follow_up_completed'],
            $leads['follow_up_pending'],
            $leads['overdue_all_time']
        );

        $lines[] = '- No advertising-spend data exists anywhere in this CRM - CAC, cost-per-lead, and ROAS'
            . ' cannot be calculated from any current source.';

        return implode("\n", $lines);
    }

    protected function serviceVolumeSection(): string
    {
        $from = now()->copy()->subDays(self::SERVICE_COUNT_DAYS - 1)->startOfDay();

        $rows = AppointmentService::where('start_time', '>=', $from)
            ->selectRaw('name, COUNT(*) as count')
            ->groupBy('name')
            ->orderByDesc('count')
            ->limit(10)
            ->get();

        $header = 'SERVICE VOLUME (last ' . self::SERVICE_COUNT_DAYS . ' days, top services by count)';

        if ($rows->isEmpty()) {
            return "{$header}\n- No services logged in this window.";
        }

        $lines = [$header];
        foreach ($rows as $row) {
            $lines[] = sprintf('- %s: %d', $row->name, $row->count);
        }

        return implode("\n", $lines);
    }

    /**
     * NOVA READ-ONLY INTEGRATION RULE: reads Appointment/Sale directly and
     * consumes the CRM's existing, unmodified ClientMaintenancePlanner - see
     * App\NovaAI\Support\CustomerRetentionAnalytics for the exact query
     * definitions (deliberately more precise than CustomerController's own
     * last-visit/LTV queries, which have no status or future-date filter at
     * all - a discovered nuance, not a CRM bug this stage touches).
     * Aggregate-only by design: no customer name, phone, or email ever
     * appears in this section - individual-customer retrieval is explicitly
     * a later, question-aware stage.
     */
    protected function customerRetentionSection(): string
    {
        $data = (new CustomerRetentionAnalytics())->summary();

        $lines = ['CUSTOMER & RETENTION INTELLIGENCE'];

        if ($data['customers_with_visit_history'] === 0) {
            $lines[] = '- No customers with a qualifying (non-cancelled, non-no-show, already-happened) visit yet.';
            return implode("\n", $lines);
        }

        $lines[] = sprintf('- Customers with completed visit history: %d', $data['customers_with_visit_history']);
        $lines[] = sprintf(
            '- Repeat customers (2+ qualifying visits): %d (%s of customers with visit history)',
            $data['repeat_customers'],
            $data['repeat_rate_percent'] === null ? 'N/A' : $data['repeat_rate_percent'] . '%'
        );
        $lines[] = sprintf(
            '- Revenue-based lifetime customer spend (all-time, not profit): %.2f QAR total across %d customers with a sale, average %s',
            $data['total_lifetime_spend'],
            $data['customers_with_spend'],
            $data['average_lifetime_spend'] === null ? 'N/A' : number_format($data['average_lifetime_spend'], 2) . ' QAR/customer'
        );
        $lines[] = sprintf(
            '- Repeat-customer revenue: %.2f QAR (%s of total lifetime spend)',
            $data['repeat_customer_revenue'],
            $data['repeat_revenue_share_percent'] === null ? 'N/A' : $data['repeat_revenue_share_percent'] . '%'
        );
        $lines[] = sprintf(
            '- Rebooking/maintenance (distinct customers, any tracked service): %d overdue | %d due soon | %d upcoming',
            $data['overdue_customers'],
            $data['due_soon_customers'],
            $data['upcoming_customers']
        );
        $lines[] = sprintf(
            '- Dormant customers (no qualifying visit in %d+ days - Nova-defined threshold, not an established CRM rule): %d, of which %d have prior spend and are potentially reactivatable',
            CustomerRetentionAnalytics::DORMANT_THRESHOLD_DAYS,
            $data['dormant_customers'],
            $data['potentially_reactivatable']
        );

        $branch = $data['branch_distribution'];
        $lines[] = sprintf(
            '- Branch history (2+ visits required to classify): %d primarily Old Airport, %d primarily Al Wakrah, %d primarily Home Service, %d mixed, %d insufficient history (fewer than 2 visits)',
            $branch['old_airport'],
            $branch['wakrah'],
            $branch['home_service'],
            $branch['mixed'],
            $branch['insufficient_history']
        );

        if (!empty($data['top_maintenance_services'])) {
            $lines[] = 'Top maintenance opportunities (by service, overdue + due soon):';
            foreach ($data['top_maintenance_services'] as $service) {
                $lines[] = sprintf('- %s: %d overdue / %d due soon', $service['service'], $service['overdue'], $service['due_soon']);
            }
        }

        return implode("\n", $lines);
    }

    protected function pendingPackagesSection(): string
    {
        $packages = ClientPackage::query()
            ->where('status', 'active')
            ->where('expires_at', '>=', now())
            ->with('customer')
            ->get()
            ->filter(fn ($pkg) => $pkg->remaining_count > 0)
            ->sortBy('expires_at')
            ->take(self::PACKAGE_LOOKAHEAD);

        if ($packages->isEmpty()) {
            return "UNREDEEMED COMBO PACKAGE BALANCES\n- No client currently has an unused package balance.";
        }

        $lines = ['UNREDEEMED COMBO PACKAGE BALANCES (soonest-expiring first)'];
        foreach ($packages as $pkg) {
            $daysLeft = max(0, (int) floor(now()->diffInDays($pkg->expires_at, false)));
            $lines[] = sprintf(
                '- %s: %d service(s) left on "%s", expires %s (%dd left)',
                optional($pkg->customer)->name ?? 'Unknown client',
                $pkg->remaining_count,
                $pkg->combo_name,
                $pkg->expires_at->format('d M'),
                $daysLeft
            );
        }

        return implode("\n", $lines);
    }

    private static function branchLabel(?string $branch): string
    {
        return match ($branch) {
            'old_airport' => 'Old Airport',
            'wakrah' => 'Al Wakrah',
            'home_service' => 'Home Service',
            'both' => 'Both Branches',
            default => $branch ?: 'Unknown branch',
        };
    }
}
