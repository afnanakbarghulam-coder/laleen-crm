<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class KpiChatEvaluation extends Model
{
    const QUESTIONS = [
        1  => ['text' => "Did the agent greet warmly?", 'max' => 15],
        2  => ['text' => "Was the agent's response time acceptable?", 'max' => 15],
        3  => ['text' => "Did the agent show empathy?", 'max' => 15],
        4  => ['text' => "Was the sales video shared?", 'max' => 15],
        5  => ['text' => "Did the agent present the offer clearly?", 'max' => 25],
        6  => ['text' => "Was pricing communicated correctly?", 'max' => 15],
        7  => ['text' => "Was the follow-up list updated?", 'max' => 15],
        8  => ['text' => "Did the agent handle objections well?", 'max' => 10],
        9  => ['text' => "Was the conversation tone professional?", 'max' => 10],
        10 => ['text' => "Did the agent confirm details accurately?", 'max' => 10],
        11 => ['text' => "Was urgency created appropriately?", 'max' => 5],
        12 => ['text' => "Were service benefits mentioned?", 'max' => 5],
        13 => ['text' => "Was the client's name used?", 'max' => 5],
        14 => ['text' => "Was a booking attempt made?", 'max' => 5],
        15 => ['text' => "Was a closing assist line used?", 'max' => 5],
    ];

    protected $table = 'kpi_chat_evaluations';

    protected $fillable = [
        'eval_date',
        'coordinator_name',
        'chats_reviewed',
        'answers',
        'created_by',
    ];

    protected $casts = [
        'eval_date' => 'date',
        'answers' => 'array',
    ];

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public static function maxPossible(): int
    {
        return array_sum(array_column(self::QUESTIONS, 'max'));
    }

    /**
     * Answers merged with question text/max points.
     */
    public function computedAnswers(): array
    {
        return array_map(function ($qNum) {
            $answer = $this->answers[$qNum] ?? ['answer' => 'No', 'score' => 0];
            $question = self::QUESTIONS[$qNum];

            return [
                'number' => $qNum,
                'text' => $question['text'],
                'max' => $question['max'],
                'answer' => $answer['answer'] ?? 'No',
                'score' => (float) ($answer['score'] ?? 0),
                'passed' => ($answer['answer'] ?? 'No') === 'Yes',
            ];
        }, array_keys(self::QUESTIONS));
    }

    public function totalScore(): float
    {
        return round(array_sum(array_column($this->computedAnswers(), 'score')), 1);
    }

    public function percentage(): float
    {
        $max = self::maxPossible();

        return $max > 0 ? round($this->totalScore() / $max * 100, 1) : 0.0;
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
        return self::gradeFor($this->percentage());
    }

    public function passedCount(): int
    {
        return count(array_filter($this->computedAnswers(), fn ($a) => $a['passed']));
    }

    public function failedCount(): int
    {
        return count(self::QUESTIONS) - $this->passedCount();
    }

    /**
     * Count of consecutive "Excellent" reports for this coordinator, ending
     * at (and including) this report, walking backward through history.
     */
    public function consecutiveExcellent(): int
    {
        $history = self::where('coordinator_name', $this->coordinator_name)
            ->where('eval_date', '<=', $this->eval_date)
            ->orderByDesc('eval_date')
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

    /**
     * This coordinator's score history for the performance journey trend.
     */
    public function history(int $limit = 10)
    {
        return self::where('coordinator_name', $this->coordinator_name)
            ->orderBy('eval_date')
            ->orderBy('id')
            ->get()
            ->map(fn ($r) => [
                'date' => $r->eval_date->format('d M'),
                'pct' => $r->percentage(),
            ])
            ->take(-$limit)
            ->values();
    }
}
