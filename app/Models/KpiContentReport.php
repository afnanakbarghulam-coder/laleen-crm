<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class KpiContentReport extends Model
{
    protected $fillable = [
        'creator_name',
        'date_from',
        'date_to',
        'created_by',
    ];

    protected $casts = [
        'date_from' => 'date',
        'date_to' => 'date',
    ];

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function entries()
    {
        return $this->hasMany(KpiContentEntry::class, 'report_id')->orderBy('entry_date');
    }

    public static function gradeFor(float $pct): string
    {
        return match (true) {
            $pct >= 90 => 'Excellent',
            $pct >= 75 => 'Pass',
            $pct >= 60 => 'Warning',
            default => 'Fail',
        };
    }

    private function postedPct($entries, string $scheduledField, string $postedField): float
    {
        $scheduled = $entries->where($scheduledField, true);
        if ($scheduled->isEmpty()) {
            return 0.0;
        }

        $posted = $scheduled->where($postedField, true)->count();

        return round($posted / $scheduled->count() * 100, 1);
    }

    private function standardsPct($entries, string $field): float
    {
        $applicable = $entries->where($field, '!=', 'NA');
        if ($applicable->isEmpty()) {
            return 0.0;
        }

        $met = $applicable->where($field, 'Y')->count();

        return round($met / $applicable->count() * 100, 1);
    }

    public function metrics(): array
    {
        $entries = $this->entries;

        $feedPosted = $this->postedPct($entries, 'feed_scheduled', 'feed_posted');
        $storiesPosted = $this->postedPct($entries, 'stories_scheduled', 'stories_posted');
        $standardsFeed = $this->standardsPct($entries, 'standards_feed');
        $standardsStories = $this->standardsPct($entries, 'standards_stories');

        $overall = round(($feedPosted + $storiesPosted + $standardsFeed + $standardsStories) / 4, 1);

        return [
            'feed_posted' => $feedPosted,
            'stories_posted' => $storiesPosted,
            'standards_feed' => $standardsFeed,
            'standards_stories' => $standardsStories,
            'overall' => $overall,
            'grade' => self::gradeFor($overall),
        ];
    }

    public function flaggedDays()
    {
        return $this->entries->filter(fn ($e) => filled($e->issues));
    }
}
