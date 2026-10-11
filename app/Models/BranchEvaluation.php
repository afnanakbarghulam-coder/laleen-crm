<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class BranchEvaluation extends Model
{
    const BRANCHES = [
        'wakrah' => 'Wakrah',
        'old_airport' => 'Old Airport',
    ];

    const MANAGERS = ['Rhea', 'Ahana'];

    /** Every question is binary (1 = Fail, 5 = Pass), so max score = question count * 5. */
    const POINTS_PER_QUESTION = 5;

    /**
     * The 27-question mystery-shopper script, grouped into the 7 scored
     * sections. Field keys (q1..q27) are what the "answers" JSON column is
     * keyed by, and what the create-form's radio inputs are named after.
     */
    const SECTIONS = [
        'Attendance & Punctuality' => [
            'q1' => 'Was the salon open, clean, and ready at opening time?',
            'q2' => 'Were all staff present and at their assigned stations?',
        ],
        'Dress Code & Hygiene' => [
            'q3' => 'Were all staff in proper Laleen uniform?',
            'q4' => 'Did all staff look clean, groomed, and presentable?',
            'q5' => 'Was the salon spotless — floors, mirrors, stations, reception?',
        ],
        'Customer Care & Communication' => [
            'q6' => 'Were you acknowledged and greeted the moment you walked in?',
            'q7' => 'Were you offered tea, coffee, or water within 2 minutes?',
            'q8' => 'Did the beautician explain the product/service before starting?',
            'q9' => 'Did she check on your comfort during the service?',
            'q10' => 'Did she walk you back to reception and thank you warmly?',
            'q11' => 'Did staff stand up and greet when customers arrived?',
        ],
        'Zero Complaints' => [
            'q12' => 'Was there any idle staff sitting and ignoring customers?',
            'q13' => 'Did anything make you uncomfortable or frustrated?',
            'q14' => 'Was there any unprofessional behavior or argument?',
        ],
        'Teamwork & Attitude' => [
            'q15' => 'Did staff work together smoothly as a team?',
            'q16' => 'Was the overall salon atmosphere positive, warm, and welcoming?',
            'q17' => 'Did any staff member seem disinterested, bored, or lazy?',
        ],
        'Manager Evaluation' => [
            'q18' => 'Did the manager greet you with a genuine warm smile?',
            'q19' => 'Did she proactively offer refreshments without being asked?',
            'q20' => 'Did she check on your satisfaction during or after the service?',
            'q21' => 'Was she actively managing bookings and operations?',
            'q22' => 'Was she monitoring staff and ensuring KPIs were followed?',
            'q23' => 'Did she recommend any additional service or product naturally?',
            'q24' => 'Did she create positive energy and a welcoming atmosphere?',
            'q25' => 'Was she fully present — not on her phone or distracted?',
        ],
        'Bonus Observations' => [
            'q26' => 'Did anyone recommend an additional service or product?',
            'q27' => 'Would you return as a real customer?',
        ],
    ];

    protected $table = 'branch_evaluations';

    protected $fillable = [
        'branch',
        'manager',
        'evaluation_date',
        'answers',
        'total_score',
        'percentage',
        'grade',
        'action_plan',
    ];

    protected $casts = [
        'evaluation_date' => 'date',
        'answers' => 'array',
        'percentage' => 'decimal:2',
    ];

    /** Flat list of every question field key, e.g. ['q1', 'q2', ..., 'q27']. */
    public static function questionFields(): array
    {
        return collect(self::SECTIONS)->flatMap(fn ($questions) => array_keys($questions))->values()->all();
    }

    public static function maxScore(): int
    {
        return count(self::questionFields()) * self::POINTS_PER_QUESTION;
    }

    public static function gradeFor(float $percentage): string
    {
        return match (true) {
            $percentage >= 90 => 'Excellent',
            $percentage >= 75 => 'Pass',
            default => 'Warning',
        };
    }

    /**
     * Per-section totals for this evaluation's answers — score, max,
     * percentage, and a red/amber/green tier used to color section headers
     * and surface "critical" (tier = red) vs. "perfect" (100%) sections.
     */
    public function sectionResults(): Collection
    {
        return collect(self::SECTIONS)->map(function ($questions, $title) {
            $score = 0;

            foreach (array_keys($questions) as $field) {
                $score += (int) ($this->answers[$field] ?? 0);
            }

            $max = count($questions) * self::POINTS_PER_QUESTION;
            $pct = $max > 0 ? round($score / $max * 100, 1) : 0;
            $tier = $pct >= 90 ? 'green' : ($pct < 50 ? 'red' : 'amber');

            return [
                'title' => $title,
                'score' => $score,
                'max' => $max,
                'percentage' => $pct,
                'tier' => $tier,
                'perfect' => $pct == 100,
            ];
        })->values();
    }

    /**
     * This visit's position among all evaluations ever logged for the same
     * branch (chronological), e.g. ['number' => 2, 'total' => 3] for the
     * "Visit 2 of 3" info pill.
     */
    public function visitPosition(): array
    {
        $sameBranchIds = static::where('branch', $this->branch)
            ->orderBy('evaluation_date')
            ->orderBy('id')
            ->pluck('id');

        $position = $sameBranchIds->search($this->id);

        return [
            'number' => $position === false ? $sameBranchIds->count() : $position + 1,
            'total' => $sameBranchIds->count(),
        ];
    }

    /** Flat, ordered list of every question with its text, answer, and pass/fail status for this evaluation. */
    public function questionResults(): Collection
    {
        $results = collect();
        $num = 1;

        foreach (self::SECTIONS as $title => $questions) {
            foreach ($questions as $field => $text) {
                $answer = (int) ($this->answers[$field] ?? 0);

                $results->push([
                    'section' => $title,
                    'num' => $num,
                    'field' => $field,
                    'question' => $text,
                    'answer' => $answer,
                    'status' => $answer >= self::POINTS_PER_QUESTION ? 'pass' : 'fail',
                ]);

                $num++;
            }
        }

        return $results;
    }
}
