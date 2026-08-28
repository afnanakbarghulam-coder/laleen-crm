<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class KpiContentEntry extends Model
{
    /** Suggestions only — activity_type stays free text (spreadsheet examples, not an enum). */
    const ACTIVITY_TYPE_SUGGESTIONS = ['Feed Post', 'Stories Only', 'Feed + Event'];

    protected $fillable = [
        'creator_name',
        'entry_date',
        'activity_type',
        'feed_post_schedule',
        'story_theme',
        'story_flow',
        'feed_posted',
        'standards_feed',
        'standards_stories',
        'issues',
        'created_by',
    ];

    protected $casts = [
        'entry_date' => 'date',
    ];

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function dayName(): string
    {
        return $this->entry_date->format('l');
    }

    public function weekNumber(): int
    {
        return (int) $this->entry_date->format('W');
    }

    /**
     * Compact payload for the edit-modal JS. A single-argument call (no
     * inline array literal) so Blade's @json directive — which naively
     * explode(',')s its expression — doesn't truncate on commas.
     */
    public function toEditPayload(): array
    {
        return $this->only([
            'id', 'creator_name', 'entry_date', 'activity_type', 'feed_post_schedule',
            'story_theme', 'story_flow', 'feed_posted', 'standards_feed', 'standards_stories', 'issues',
        ]);
    }
}
