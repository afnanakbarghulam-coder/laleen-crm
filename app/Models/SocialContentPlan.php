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

    protected $fillable = [
        'week_start_date',
        'day_of_week',
        'reel_content',
        'stories_content',
    ];

    // Deliberately uncast: kept as a plain 'Y-m-d' string end-to-end. A
    // 'date' cast formats the attribute with a time suffix when writing
    // (via the model's generic $dateFormat), which stops it matching the
    // bare date strings used in firstOrCreate/where lookups elsewhere.
}
