<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class EcommerceContentCalendar extends Model
{
    protected $table = 'ecommerce_content_calendar';

    public const DAYS_OF_WEEK = [
        'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday',
    ];

    /** The six content pillars the weekly factory rotates through. */
    public const CONTENT_TYPES = [
        'Transformation',
        'Mechanism',
        'Bundle',
        'Behind-the-Scenes',
        'Social Proof',
        'Direct Response',
    ];

    public const BRANCHES = ['All Branches', 'Old Airport', 'Wakrah'];

    public const WEEKLY_REEL_TARGET = 4;
    public const WEEKLY_STORIES_TARGET = 7;

    /** 7 days minus the 4-reel target — at most 3 days can be a Rest Day. */
    public const MAX_REEL_SKIP_DAYS = 3;

    /** Suggested default pillar per day, used to seed an empty week. */
    public const DEFAULT_PILLAR_BY_DAY = [
        'Monday' => 'Transformation',
        'Tuesday' => 'Mechanism',
        'Wednesday' => 'Bundle',
        'Thursday' => 'Behind-the-Scenes',
        'Friday' => 'Social Proof',
        'Saturday' => 'Direct Response',
        'Sunday' => 'Transformation',
    ];

    public const STORY_TEMPLATE = "• Morning (Offer Tease / BTS): \n• Afternoon (Transformation / Proof): \n• Evening (Direct Response + Link CTA): ";

    protected $fillable = [
        'week_start_date',
        'day_of_week',
        'content_type',
        'objective',
        'script_concept',
        'reel_link',
        'target_branch',
        'person_responsible',
        'status',
        'is_reel_posted',
        'is_reel_skipped',
        'reel_posted_at',
        'stories_content',
        'are_stories_posted',
        'stories_posted_at',
    ];

    protected $casts = [
        'is_reel_posted' => 'boolean',
        'is_reel_skipped' => 'boolean',
        'are_stories_posted' => 'boolean',
        'reel_posted_at' => 'datetime',
        'stories_posted_at' => 'datetime',
    ];

    // week_start_date is deliberately uncast. A 'date' cast writes a time
    // suffix ('2026-10-12 00:00:00') via the model's generic $dateFormat,
    // which then fails to match the bare 'Y-m-d' strings used by the
    // firstOrCreate / where lookups that drive week navigation.

    public function dayIndex(): int
    {
        $index = array_search($this->day_of_week, self::DAYS_OF_WEEK, true);

        return $index === false ? count(self::DAYS_OF_WEEK) : $index;
    }

    public function toEditPayload(): array
    {
        return $this->only([
            'id', 'week_start_date', 'day_of_week', 'content_type', 'objective',
            'script_concept', 'reel_link', 'target_branch', 'person_responsible',
            'is_reel_posted', 'is_reel_skipped', 'stories_content', 'are_stories_posted',
        ]);
    }

    /**
     * Weekly execution stats for one week's rows.
     *
     * Every figure returns a real count; consistency is capped at 100 so a
     * week that over-delivers on reels cannot mask a stories shortfall.
     */
    public static function weeklyStats(Collection $rows): array
    {
        $reelsSkipped = $rows->where('is_reel_skipped', true)->count();
        $reelsPosted = $rows->where('is_reel_posted', true)->where('is_reel_skipped', false)->count();
        $storiesPosted = $rows->where('are_stories_posted', true)->count();

        $target = self::WEEKLY_REEL_TARGET + self::WEEKLY_STORIES_TARGET;
        $achieved = min($reelsPosted, self::WEEKLY_REEL_TARGET) + $storiesPosted;

        return [
            'reels_target' => self::WEEKLY_REEL_TARGET,
            'stories_target' => self::WEEKLY_STORIES_TARGET,
            'max_reel_skip_days' => self::MAX_REEL_SKIP_DAYS,
            'reels_posted' => $reelsPosted,
            'reels_skipped' => $reelsSkipped,
            'stories_posted' => $storiesPosted,
            'consistency_pct' => min(100, (int) round(($achieved / $target) * 100)),
        ];
    }
}
