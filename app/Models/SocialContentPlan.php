<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

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

    /** Canonical UI branch choices */
    public const BRANCH_OPTIONS = [
        'All Branches',
        'Old Airport',
        'Al Wakrah',
    ];

    /** Allowed branch values (includes legacy 'Wakrah' alias for backward compatibility) */
    public const BRANCHES = [
        'All Branches',
        'Old Airport',
        'Al Wakrah',
        'Wakrah',
    ];

    public const FORMATS = [
        'Reel / Short Video',
        'Before / After Reel',
        'POV Reel / Trendy Audio',
        'Carousel (Before & After)',
        'Static Graphic / Story',
        'Story Series (3 Frames)',
        'Static Offer Post',
    ];

    public const REEL_PILLARS = [
        'Transformation / Before-After',
        'Educational / Beauty Tip',
        'Offer / Promo',
        'Behind The Scenes',
        'Client Review / Social Proof',
    ];

    public const STATUSES = ['pending', 'posted'];

    public const WEEKLY_REEL_TARGET = 4;
    public const WEEKLY_STORIES_TARGET = 7;

    /** Max days per week that can be marked "No Reel / Rest Day" — 7 days minus the 4-Reel target. */
    public const MAX_REEL_SKIP_DAYS = 3;

    protected $fillable = [
        'week_start_date',
        'date',
        'day_of_week',
        'branch',
        'target_branch',
        'assigned_user_id',
        'assigned_to',
        'focus',
        'format',
        'visual_concept',
        'caption_cta',
        'proof_url',
        'is_story_verified',
        'status',
        'posted_at',
        'reel_content',
        'stories_content',
        'is_reel_posted',
        'are_stories_posted',
        'reel_pillar',
        'reel_link',
        'reel_posted_at',
        'stories_posted_at',
        'is_reel_skipped',
        'late_replies_count',
    ];

    protected $casts = [
        'is_story_verified' => 'boolean',
        'posted_at' => 'datetime',
        'is_reel_posted' => 'boolean',
        'are_stories_posted' => 'boolean',
        'reel_posted_at' => 'datetime',
        'stories_posted_at' => 'datetime',
        'is_reel_skipped' => 'boolean',
        'late_replies_count' => 'integer',
    ];

    // week_start_date and date are deliberately kept uncast as bare 'Y-m-d' strings
    // so SQLite and MySQL string comparisons match cleanly across scorecards & tests.

    protected static function booted(): void
    {
        static::saving(function (self $plan): void {
            if (!empty($plan->day_of_week)) {
                $plan->day_of_week = self::normalizeDayName($plan->day_of_week);
            }

            if (empty($plan->date) && !empty($plan->week_start_date) && !empty($plan->day_of_week)) {
                try {
                    $plan->date = $plan->exactDate()->toDateString();
                } catch (\Throwable) {
                    // Leave date null if week_start_date cannot be parsed
                }
            }

            // Keep branch & legacy target_branch aligned
            if ($plan->isDirty('branch') && !$plan->isDirty('target_branch')) {
                $normalized = self::normalizeBranch($plan->branch);
                $plan->branch = $normalized;
                $plan->target_branch = $normalized === 'Al Wakrah' ? 'Wakrah' : $normalized;
            } elseif ($plan->isDirty('target_branch') && !$plan->isDirty('branch')) {
                $plan->branch = self::normalizeBranch($plan->target_branch);
            } else {
                $resolved = self::normalizeBranch($plan->branch ?: $plan->target_branch);
                $plan->branch = $resolved;
                if (empty($plan->target_branch)) {
                    $plan->target_branch = $resolved === 'Al Wakrah' ? 'Wakrah' : $resolved;
                }
            }

            // If created/updated purely via legacy columns, mirror into unified columns non-destructively
            if (empty($plan->visual_concept)) {
                if (filled($plan->reel_content)) {
                    $plan->visual_concept = $plan->reel_content;
                } elseif (filled($plan->stories_content)) {
                    $plan->visual_concept = $plan->stories_content;
                }
            }

            if (empty($plan->proof_url) && filled($plan->reel_link)) {
                $plan->proof_url = $plan->reel_link;
            }

            if (empty($plan->status)) {
                $plan->status = ($plan->is_reel_posted || ($plan->are_stories_posted && empty($plan->reel_content)))
                    ? 'posted'
                    : 'pending';
            }
        });
    }

    public function assignedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_user_id');
    }

    public static function normalizeDayName(?string $day): string
    {
        $clean = strtolower(trim((string) $day));
        foreach (self::DAYS_OF_WEEK as $canonical) {
            if (strtolower($canonical) === $clean) {
                return $canonical;
            }
        }

        return ucfirst($clean);
    }

    public static function normalizeBranch(?string $branch): string
    {
        $clean = trim((string) $branch);
        if ($clean === '' || strcasecmp($clean, 'All Branches') === 0) {
            return 'All Branches';
        }
        if (strcasecmp($clean, 'Wakrah') === 0 || strcasecmp($clean, 'Al Wakrah') === 0) {
            return 'Al Wakrah';
        }
        if (strcasecmp($clean, 'Old Airport') === 0) {
            return 'Old Airport';
        }

        return $clean;
    }

    public function effectiveBranch(): string
    {
        return self::normalizeBranch($this->branch ?: $this->target_branch);
    }

    public function effectiveVisualConcept(): string
    {
        return (string) ($this->visual_concept ?? $this->reel_content ?? $this->stories_content ?? '');
    }

    public function effectiveProofUrl(): string
    {
        return (string) ($this->proof_url ?? $this->reel_link ?? '');
    }

    public function isStoryFormat(?string $format = null): bool
    {
        $target = trim((string) ($format ?? $this->format ?? ''));

        return $target !== '' && stripos($target, 'story') !== false;
    }

    public function isReelFormat(?string $format = null): bool
    {
        $target = trim((string) ($format ?? $this->format ?? ''));
        if ($target === '') {
            return filled($this->reel_content);
        }

        return stripos($target, 'reel') !== false || stripos($target, 'video') !== false;
    }

    public function isCompleted(): bool
    {
        if ($this->status === 'posted') {
            return true;
        }

        if ($this->is_reel_posted && !$this->is_reel_skipped) {
            return true;
        }

        if ($this->are_stories_posted && empty($this->reel_content) && ($this->isStoryFormat() || filled($this->stories_content))) {
            return true;
        }

        return false;
    }

    public function dayOffset(): int
    {
        $canonical = self::normalizeDayName($this->day_of_week);
        $idx = array_search($canonical, self::DAYS_OF_WEEK, true);

        return $idx === false ? 0 : (int) $idx;
    }

    /**
     * Resolve the exact 'Y-m-d' calendar date represented by week_start_date (Monday)
     * plus day_of_week offset (Monday = +0 ... Sunday = +6).
     */
    public function exactDate(): Carbon
    {
        $rawWeekStart = substr(trim((string) $this->week_start_date), 0, 10);

        return Carbon::createFromFormat('Y-m-d', $rawWeekStart)
            ->startOfDay()
            ->addDays($this->dayOffset());
    }
}
