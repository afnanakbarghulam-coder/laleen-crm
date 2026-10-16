<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SocialContentPlan extends Model
{
    public const DAYS_OF_WEEK = [
        'Monday',
        'Tuesday',
        'Wednesday',
        'Thursday',
        'Friday',
        'Saturday',
        'Sunday',
    ];

    public const BRANCHES = ['All Branches', 'Old Airport', 'Wakrah'];

    public const REEL_PILLARS = [
        'Transformation / Before-After',
        'Educational / Beauty Tip',
        'Offer / Promo',
        'Behind The Scenes',
        'Client Review / Social Proof',
    ];

    public const WEEKLY_REEL_TARGET = 4;
    public const WEEKLY_STORIES_TARGET = 7;

    /** Max days per week that can be marked "No Reel / Rest Day" — 7 days minus the 4-Reel target. */
    public const MAX_REEL_SKIP_DAYS = 3;

    protected $fillable = [
        'week_start_date',
        'day_of_week',
        'reel_content',
        'stories_content',
        'is_reel_posted',
        'are_stories_posted',
        'target_branch',
        'assigned_to',
        'reel_pillar',
        'reel_link',
        'reel_posted_at',
        'stories_posted_at',
        'is_reel_skipped',
    ];

    protected $casts = [
        'is_reel_posted' => 'boolean',
        'are_stories_posted' => 'boolean',
        'reel_posted_at' => 'datetime',
        'stories_posted_at' => 'datetime',
        'is_reel_skipped' => 'boolean',
    ];

    // week_start_date is deliberately uncast: kept as a plain 'Y-m-d' string
    // end-to-end. A 'date' cast formats the attribute with a time suffix
    // when writing (via the model's generic $dateFormat), which stops it
    // matching the bare date strings used in firstOrCreate/where lookups
    // elsewhere.
}
