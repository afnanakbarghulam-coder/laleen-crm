<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class KpiChatEvaluation extends Model
{
    /** The 15-question weighted script. Every question is binary: Pass scores the full weight, Fail scores 0. */
    const QUESTIONS = [
        1 => ['text' => 'Real intent to close?', 'weight' => 15],
        2 => ['text' => 'Asked for booking directly?', 'weight' => 15],
        3 => ['text' => 'Handled objections correctly?', 'weight' => 15],
        4 => ['text' => 'Followed up if client went quiet?', 'weight' => 15],
        5 => ['text' => 'Genuine empathy?', 'weight' => 25],
        6 => ['text' => 'Prompt responses — no delays?', 'weight' => 15],
        7 => ['text' => 'List updated accurately?', 'weight' => 15],
        8 => ['text' => 'Tone warm, professional, confident?', 'weight' => 10],
        9 => ['text' => 'Reviewed all pending chats before shift end?', 'weight' => 10],
        10 => ['text' => 'Close confirmed or next step set?', 'weight' => 10],
        11 => ['text' => 'Identified what client wants?', 'weight' => 5],
        12 => ['text' => 'Presented right service or offer?', 'weight' => 5],
        13 => ['text' => 'Correct greeting script?', 'weight' => 5],
        14 => ['text' => 'Replied within 3 minutes?', 'weight' => 5],
        15 => ['text' => 'Asked if anything else to assist?', 'weight' => 5],
    ];

    /**
     * How the 15 questions roll up into the report's "Score Breakdown".
     * Keyed by group label => question numbers; the group's max is derived
     * from QUESTIONS, never hardcoded, so it always stays in sync.
     */
    const BREAKDOWN_GROUPS = [
        'Closing + Booking' => [1, 2],
        'Objection' => [3],
        'Follow-Up' => [4],
        'Empathy' => [5],
        'Responses' => [6],
        'List' => [7],
        'Tone' => [8],
        'Shift Review' => [9],
        'Close' => [10],
        'Identify + Service' => [11, 12],
        'Greeting + Reply + Assist' => [13, 14, 15],
    ];

    protected $table = 'kpi_chat_evaluations';

    protected $fillable = [
        'agent_id',
        'agent_ids',
        'agent_names',
        'all_agents',
        'evaluator_id',
        'evaluation_date',
        'answers',
        'total_score',
        'percentage',
        'notes',
    ];

    protected $casts = [
        'agent_ids' => 'array',
        'agent_names' => 'array',
        'all_agents' => 'boolean',
        'evaluation_date' => 'date',
        'answers' => 'array',
        'percentage' => 'decimal:2',
    ];

    public function agent()
    {
        return $this->belongsTo(User::class, 'agent_id');
    }

    public function agents(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'kpi_chat_evaluation_agents', 'kpi_chat_evaluation_id', 'user_id')
            ->withTimestamps();
    }

    public function evaluator()
    {
        return $this->belongsTo(User::class, 'evaluator_id');
    }

    /**
     * Query all staff selectable in Chat Evaluations:
     * all users with role === 'agent' PLUS Areeba, Zoya, and Laden explicitly
     * (so Laden is always selectable even when assigned a lead/manager role).
     */
    public static function selectableAgentsQuery(): Builder
    {
        return User::query()
            ->where(function (Builder $query) {
                $query->where('role', 'agent')
                    ->orWhereRaw('LOWER(name) LIKE ?', ['%areeba%'])
                    ->orWhereRaw('LOWER(name) LIKE ?', ['%zoya%'])
                    ->orWhereRaw('LOWER(name) LIKE ?', ['%laden%']);
            })
            ->orderByRaw("
                CASE
                    WHEN LOWER(name) LIKE '%areeba%' THEN 1
                    WHEN LOWER(name) LIKE '%zoya%' THEN 2
                    WHEN LOWER(name) LIKE '%laden%' THEN 3
                    ELSE 4
                END
            ")
            ->orderBy('name');
    }

    public static function selectableAgents(): Collection
    {
        return self::selectableAgentsQuery()->get(['id', 'name', 'role']);
    }

    /**
     * Filter evaluations belonging to any of the given agent user IDs,
     * matching both legacy single-agent `agent_id` and the multi-agent pivot.
     *
     * @param  array<int|string>|Collection<int, int|string>  $agentIds
     */
    public function scopeForAgentIds(Builder $query, array|Collection $agentIds): Builder
    {
        $ids = collect($agentIds)
            ->map(fn ($id) => (int) $id)
            ->filter(fn (int $id) => $id > 0)
            ->unique()
            ->values()
            ->all();

        if (empty($ids)) {
            return $query->whereRaw('1 = 0');
        }

        return $query->where(function (Builder $q) use ($ids) {
            $q->whereIn('agent_id', $ids)
                ->orWhereHas('agents', fn (Builder $sub) => $sub->whereIn('users.id', $ids));
        });
    }

    /**
     * Return all evaluated agent IDs for this evaluation (pivot -> JSON -> legacy agent_id).
     *
     * @return array<int, int>
     */
    public function evaluatedAgentIds(): array
    {
        if ($this->relationLoaded('agents') && $this->agents->isNotEmpty()) {
            return $this->agents->pluck('id')->map(fn ($id) => (int) $id)->unique()->values()->all();
        }

        if (is_array($this->agent_ids) && count($this->agent_ids) > 0) {
            return collect($this->agent_ids)->map(fn ($id) => (int) $id)->filter()->unique()->values()->all();
        }

        if ($this->exists) {
            $pivotIds = $this->agents()->pluck('users.id')->map(fn ($id) => (int) $id)->unique()->values()->all();
            if (! empty($pivotIds)) {
                return $pivotIds;
            }
        }

        return $this->agent_id ? [(int) $this->agent_id] : [];
    }

    /**
     * Return evaluated agent display names for this evaluation.
     *
     * @return array<int, string>
     */
    public function evaluatedAgentNames(): array
    {
        if ($this->relationLoaded('agents') && $this->agents->isNotEmpty()) {
            return $this->agents->pluck('name')->filter()->values()->all();
        }

        if (is_array($this->agent_names) && count($this->agent_names) > 0) {
            return collect($this->agent_names)->filter()->values()->all();
        }

        if ($this->exists) {
            $pivotNames = $this->agents()->pluck('users.name')->filter()->values()->all();
            if (! empty($pivotNames)) {
                return $pivotNames;
            }
        }

        $legacyName = $this->agent?->name;

        return $legacyName ? [$legacyName] : [];
    }

    public function isAllAgents(): bool
    {
        return (bool) $this->all_agents;
    }

    /**
     * Order names in canonical Laleen chat-team order (Areeba, Zoya, Laden, then alphabetical).
     *
     * @param  array<int, string>  $names
     * @return array<int, string>
     */
    private static function orderCanonicalNames(array $names): array
    {
        usort($names, function (string $a, string $b): int {
            $rank = function (string $name): int {
                $lower = mb_strtolower($name);
                if (str_contains($lower, 'areeba')) {
                    return 1;
                }
                if (str_contains($lower, 'zoya')) {
                    return 2;
                }
                if (str_contains($lower, 'laden')) {
                    return 3;
                }

                return 4;
            };

            $cmp = $rank($a) <=> $rank($b);

            return $cmp !== 0 ? $cmp : strcasecmp($a, $b);
        });

        return array_values($names);
    }

    /**
     * Human-readable formatted evaluated agents label:
     * - Single: "Zoya"
     * - Multiple: "Laden + Zoya" / "Areeba + Zoya"
     * - All Agents: "All Agents (Areeba, Zoya, Laden)"
     */
    public function formattedAgentsLabel(): string
    {
        $names = $this->evaluatedAgentNames();

        if (empty($names)) {
            return '—';
        }

        if ($this->isAllAgents()) {
            $ordered = self::orderCanonicalNames($names);

            return 'All Agents (' . implode(', ', $ordered) . ')';
        }

        if (count($names) === 1) {
            return $names[0];
        }

        $alphabetical = $names;
        natcasesort($alphabetical);

        return implode(' + ', array_values($alphabetical));
    }

    /**
     * Filename-safe slug for evaluated agent(s):
     * - All Agents -> "all-agents"
     * - Single -> "zoya"
     * - Multiple -> "laden-zoya"
     */
    public function agentsSlug(): string
    {
        if ($this->isAllAgents()) {
            return 'all-agents';
        }

        $names = $this->evaluatedAgentNames();
        if (empty($names)) {
            return 'evaluation';
        }

        $alphabetical = $names;
        natcasesort($alphabetical);

        $slug = Str::slug(implode('-', array_values($alphabetical)));

        return $slug !== '' ? $slug : 'evaluation';
    }

    public function pdfFilename(): string
    {
        $date = $this->evaluation_date ? $this->evaluation_date->format('Y-m-d') : now()->format('Y-m-d');

        return "chat-evaluation-{$date}-{$this->agentsSlug()}.pdf";
    }

    /** Max achievable score — always the live sum of question weights, not a magic number. */
    public static function maxScore(): int
    {
        return array_sum(array_column(self::QUESTIONS, 'weight'));
    }

    /** Score a single question's stored answer ('pass'/'fail') against its weight. */
    public static function scoreFor(int $questionNumber, ?string $answer): int
    {
        return $answer === 'pass' ? self::QUESTIONS[$questionNumber]['weight'] : 0;
    }

    /** The "answers" JSON key for a question number, e.g. 1 -> "q1". */
    public static function answerKey(int $questionNumber): string
    {
        return "q{$questionNumber}";
    }

    /** Red/amber/green tier for a percentage, per the report's exact 0% / between / 100% rule. */
    public static function tierFor(float $pct): string
    {
        return match (true) {
            $pct >= 100 => 'green',
            $pct <= 0 => 'red',
            default => 'amber',
        };
    }

    public static function gradeFor(float $pct): string
    {
        return match (true) {
            $pct >= 90 => 'Excellent',
            $pct >= 75 => 'Pass',
            $pct >= 60 => 'Warning',
            default => 'Fail',
        };
    }

    public function grade(): string
    {
        return self::gradeFor((float) $this->percentage);
    }

    /** Every question merged with its text, weight, saved score/remark, and pass/fail status. */
    public function questionResults(): Collection
    {
        return collect(self::QUESTIONS)->map(function ($question, $num) {
            $saved = $this->answers[self::answerKey($num)] ?? [];
            $score = (int) ($saved['score'] ?? 0);

            return [
                'num' => $num,
                'text' => $question['text'],
                'weight' => $question['weight'],
                'score' => $score,
                'remark' => $saved['remark'] ?? null,
                'passed' => $score >= $question['weight'],
            ];
        })->values();
    }

    /** Score Breakdown groups — label, score, max, percentage, color tier — derived from questionResults(). */
    public function sectionBreakdown(): Collection
    {
        $results = $this->questionResults()->keyBy('num');

        return collect(self::BREAKDOWN_GROUPS)->map(function ($questionNumbers, $label) use ($results) {
            $score = collect($questionNumbers)->sum(fn ($num) => $results[$num]['score']);
            $max = collect($questionNumbers)->sum(fn ($num) => $results[$num]['weight']);
            $pct = $max > 0 ? round($score / $max * 100, 1) : 0;

            return [
                'label' => $label,
                'score' => $score,
                'max' => $max,
                'percentage' => $pct,
                'tier' => self::tierFor($pct),
            ];
        })->values();
    }

    public function passedCount(): int
    {
        return $this->questionResults()->where('passed', true)->count();
    }

    public function failedCount(): int
    {
        return count(self::QUESTIONS) - $this->passedCount();
    }

    /**
     * Count of consecutive "Excellent" reports for this agent, ending at
     * (and including) this report, walking backward through history.
     */
    public function consecutiveExcellent(): int
    {
        $agentIds = $this->evaluatedAgentIds();

        $history = self::query()
            ->when(! empty($agentIds), fn (Builder $q) => $q->forAgentIds($agentIds), fn (Builder $q) => $q->where('agent_id', $this->agent_id))
            ->where('evaluation_date', '<=', $this->evaluation_date)
            ->orderByDesc('evaluation_date')
            ->orderByDesc('id')
            ->get();

        $count = 0;
        foreach ($history as $report) {
            if ($report->grade() === 'Excellent') {
                $count++;
            } else {
                break;
            }
        }

        return $count;
    }
}
