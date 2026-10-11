<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class KpiStaffEvaluation extends Model
{
    const SCORE_FIELDS = [
        'score_cleanliness',
        'score_dress_code',
        'score_attitude',
        'score_sales',
        'score_teamwork',
        'score_communication',
    ];

    /**
     * The six-block ROI-focused rubric shown on the evaluation form, keyed by
     * score column. Each "Hormozi Lens" framing and the 1/5-star anchors are
     * fixed copy, not database content, so they live here next to the field
     * they score.
     */
    const RUBRIC = [
        'score_cleanliness' => [
            'title' => 'Cleanliness and Hygiene',
            'subtitle' => 'The "Frictionless Reset"',
            'lens' => 'The Hormozi Lens: A dirty station creates friction for the next sale and ruins the premium brand perception.',
            'one_star' => "Leaves hair on the floor, station is a mess, tools aren't sanitized. Costs the business money by slowing down turnover.",
            'five_star' => 'Station is instantly reset to "brand new" the second a client stands up. Tools are spotless. Zero friction for the next booking.',
        ],
        'score_dress_code' => [
            'title' => 'Dress Code',
            'subtitle' => '"Visual Authority"',
            'lens' => "The Hormozi Lens: People buy from people they respect. If the staff looks sloppy, the client won't trust them with a high-ticket balayage or an expensive Apricot Oil purchase.",
            'one_star' => 'Uniform is wrinkled, stained, or incomplete. Looks like an amateur.',
            'five_star' => 'Flawless grooming. Looks like an absolute professional who commands premium pricing.',
        ],
        'score_attitude' => [
            'title' => 'Staff Attitude Towards Work',
            'subtitle' => '"Execution & Capacity"',
            'lens' => 'The Hormozi Lens: "Attitude" is just your willingness to do the work without creating drag. Does this person maximize their chair time, or do they complain and drain the manager\'s energy?',
            'one_star' => 'Complains, avoids walk-ins, creates drama, drags their feet when fully booked.',
            'five_star' => "A machine. Takes the next client immediately, handles back-to-back bookings with zero complaints, and makes the manager's life effortless.",
        ],
        'score_sales' => [
            'title' => 'Sales Focused',
            'subtitle' => '"Ticket Maximization & Ascension"',
            'lens' => 'The Hormozi Lens: This is the most critical metric. Are they just an order-taker, or are they actively increasing the Average Order Value (AOV) by pitching backbar treatments and retail bottles?',
            'one_star' => 'Does exactly what the client booked and nothing else. Lets them walk out without mentioning a single retail product.',
            'five_star' => 'Consistently upsells deep conditioning treatments on the floor and closes retail (shampoos/oils) at the register. Maximizes the cash collected per chair hour.',
        ],
        'score_teamwork' => [
            'title' => 'Team Work',
            'subtitle' => '"Bottleneck Removal"',
            'lens' => 'The Hormozi Lens: "Teamwork" means removing bottlenecks so the business can process more volume. If the wash basins are full, does the stylist wait around, or do they jump in to clear the queue?',
            'one_star' => '"That\'s not my job." Stands around while the Wakrah branch waiting area is backed up.',
            'five_star' => "Proactively sweeps floors, folds towels, or washes hair for other stylists to keep the entire salon's money-making machine moving fast.",
        ],
        'score_communication' => [
            'title' => 'Communication with Customers',
            'subtitle' => '"LTV & Rebooking Engine"',
            'lens' => 'The Hormozi Lens: Good communication isn\'t just being chatty. It is specifically scripting the client to guarantee they come back and leave a 5-star review.',
            'one_star' => "Silent or awkward. Doesn't educate the client on the process. Client leaves without rebooking.",
            'five_star' => 'Controls the frame. Educates the client on exactly what they are doing, asks for the review, and locks in the next appointment before the client walks out the door.',
        ],
    ];

    protected $table = 'kpi_staff_evaluations';

    protected $fillable = [
        'staff_id',
        'evaluator_id',
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
}
