<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class KpiContentEntry extends Model
{
    protected $fillable = [
        'report_id',
        'entry_date',
        'activity_type',
        'feed_scheduled',
        'stories_scheduled',
        'feed_posted',
        'stories_posted',
        'standards_feed',
        'standards_stories',
        'issues',
    ];

    protected $casts = [
        'entry_date' => 'date',
        'feed_scheduled' => 'boolean',
        'stories_scheduled' => 'boolean',
        'feed_posted' => 'boolean',
        'stories_posted' => 'boolean',
    ];

    public function report()
    {
        return $this->belongsTo(KpiContentReport::class, 'report_id');
    }

    public function dayName(): string
    {
        return $this->entry_date->format('l');
    }

    public function weekNumber(): int
    {
        return (int) $this->entry_date->format('W');
    }
}
