<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

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
        'evaluator_id',
        'evaluation_date',
        'answers',
        'total_score',
        'percentage',
    ];

    protected $casts = [
        'evaluation_date' => 'date',
        'answers' => 'array',
        'percentage' => 'decimal:2',
    ];

    public function agent()
    {
        return $this->belongsTo(User::class, 'agent_id');
    }

    public function evaluator()
    {
        return $this->belongsTo(User::class, 'evaluator_id');
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
        $history = self::where('agent_id', $this->agent_id)
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
