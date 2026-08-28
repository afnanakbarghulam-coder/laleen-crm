<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class KpiContentEntry extends Model
{
    /** The only allowed activity_type values — enforced in ContentEntryController's validation. */
    const ACTIVITY_TYPES = [
        'Feed Post + Stories',
        'Stories Only',
        'Feed Post + Stories + Event',
        'Stories + Event',
    ];

    /**
     * Which of the five Y/N tracking fields apply to each activity type.
     * Drives both the inline grid's dynamic show/hide and which fields a
     * report's metrics count as "applicable" for a given row — there's no
     * stored NA value anymore, applicability is purely structural.
     */
    const FIELD_VISIBILITY = [
        'Stories Only' => ['stories_posted', 'standards_stories'],
        'Feed Post + Stories' => ['stories_posted', 'feed_posted', 'standards_stories', 'standards_feed'],
        'Stories + Event' => ['stories_posted', 'standards_stories', 'event'],
        'Feed Post + Stories + Event' => ['stories_posted', 'feed_posted', 'standards_stories', 'standards_feed', 'event'],
    ];

    protected $fillable = [
        'creator_name',
        'entry_date',
        'activity_type',
        'feed_post_schedule',
        'stories_posted',
        'feed_posted',
        'standards_stories',
        'standards_feed',
        'event',
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

    /** The tracking fields applicable to this row's activity type. */
    public function visibleFields(): array
    {
        return self::FIELD_VISIBILITY[$this->activity_type] ?? [];
    }

    /**
     * Compact payload for the inline-editable calendar row JS — used both
     * for the page's initial data embed and as the JSON shape returned by
     * store/update, so the client always has one consistent row shape to
     * render from. A single-argument call (no inline array literal) so
     * Blade's @json directive — which naively explode(',')s its expression
     * — doesn't truncate on commas.
     */
    public function toEditPayload(): array
    {
        return array_merge($this->only([
            'id', 'creator_name', 'activity_type', 'feed_post_schedule',
            'stories_posted', 'feed_posted', 'standards_stories', 'standards_feed', 'event', 'issues',
        ]), [
            'entry_date' => $this->entry_date->format('Y-m-d'),
            'entry_date_label' => $this->entry_date->format('d M Y'),
            'day_name' => $this->dayName(),
        ]);
    }
}
