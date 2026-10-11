<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class KpiStaffEvaluation extends Model
{
    const BRANCHES = [
        'old_airport' => 'Old Airport',
        'wakrah' => 'Wakrah',
    ];

    /** Display name of the branch leader who owns each branch's evaluations. */
    const BRANCH_EVALUATOR_NAMES = [
        'wakrah' => 'Rhea',
        'old_airport' => 'Ahana',
    ];

    /**
     * Each branch has one dedicated evaluator. Used to default the "Branch"
     * dropdown on the New Evaluation form to whichever branch the logged-in
     * leader runs, by a case-insensitive match against their name.
     */
    const EVALUATOR_BRANCH_DEFAULTS = [
        'rhea' => 'wakrah',
        'ahana' => 'old_airport',
    ];

    const SCORE_FIELDS = [
        'score_cleanliness',
        'score_dress_code',
        'score_attitude',
        'score_sales',
        'score_teamwork',
        'score_communication',
    ];

    /**
     * The six-block rubric shown on the evaluation form, keyed by score
     * column. Scoring is binary (1 or 5 only) — each block just anchors
     * what the low and high ends look like.
     */
    const RUBRIC = [
        'score_cleanliness' => [
            'title' => 'Cleanliness & Hygiene',
            'one_star' => 'Leaves the station messy, hair on the floor, or tools dirty. Slows down the next booking.',
            'five_star' => 'Cleans the station and sanitizes tools immediately after a client leaves. Ready for the next client instantly.',
        ],
        'score_dress_code' => [
            'title' => 'Dress Code',
            'one_star' => 'Uniform is dirty, wrinkled, or incomplete. Looks unprofessional.',
            'five_star' => 'Uniform is perfectly clean and neat. Looks highly professional to clients.',
        ],
        'score_attitude' => [
            'title' => 'Work Attitude',
            'one_star' => 'Complains, avoids taking walk-in clients, or creates drama on the salon floor.',
            'five_star' => 'Works hard, takes the next client quickly with zero complaints, and keeps a positive energy.',
        ],
        'score_sales' => [
            'title' => 'Sales Focus',
            'one_star' => 'Only does the booked service. Never tries to sell extra treatments or retail products.',
            'five_star' => 'Always offers extra deep-conditioning treatments and successfully sells retail products (like shampoos or oils) at the register.',
        ],
        'score_teamwork' => [
            'title' => 'Teamwork',
            'one_star' => "Says 'Not my job.' Stands around while the salon is very busy.",
            'five_star' => 'Helps others without being asked. Steps in to sweep floors or wash hair when the salon is busy.',
        ],
        'score_communication' => [
            'title' => 'Customer Communication',
            'one_star' => 'Quiet or awkward. Does not explain the service or ask the client to book again.',
            'five_star' => 'Speaks warmly, explains the service well, asks for a review, and asks the client to book their next visit before they leave.',
        ],
    ];

    protected $table = 'kpi_staff_evaluations';

    protected $fillable = [
        'staff_id',
        'evaluator_id',
        'branch',
        'evaluation_date',
        'score_cleanliness',
        'score_dress_code',
        'score_attitude',
        'score_sales',
        'score_teamwork',
        'score_communication',
        'overall_average',
        'leader_notes',
    ];

    protected $casts = [
        'evaluation_date' => 'date',
        'overall_average' => 'decimal:2',
    ];

    public function staff()
    {
        return $this->belongsTo(Staff::class);
    }

    public function evaluator()
    {
        return $this->belongsTo(User::class, 'evaluator_id');
    }

    public static function averageOf(array $scores): float
    {
        return round(array_sum($scores) / count($scores), 2);
    }

    /**
     * The branch key this evaluator's name maps to (e.g. "Rhea Jacob" ->
     * 'wakrah'), or null if they're not a recognized branch leader — the
     * form falls back to no pre-selection in that case.
     */
    public static function defaultBranchFor(?User $user): ?string
    {
        if (!$user || !$user->name) {
            return null;
        }

        $name = strtolower($user->name);

        foreach (self::EVALUATOR_BRANCH_DEFAULTS as $needle => $branch) {
            if (str_contains($name, $needle)) {
                return $branch;
            }
        }

        return null;
    }
}
