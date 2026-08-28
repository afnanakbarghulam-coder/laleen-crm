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

    /**
     * This creator's calendar entries for the report's date range — computed
     * fresh every time, never stored. A report is just a saved (creator,
     * date range) bookmark; deleting it never touches the underlying
     * content calendar.
     */
    public function entriesInRange()
    {
        return KpiContentEntry::where('creator_name', $this->creator_name)
            ->whereBetween('entry_date', [$this->date_from->copy()->startOfDay(), $this->date_to->copy()->endOfDay()])
            ->orderBy('entry_date')
            ->get();
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

    /**
     * % of entries where $field === 'Y', out of entries where $field isn't
     * 'NA' (a day with no story activity, say, can't fail "standards met for
     * stories" — it's excluded rather than counted against the creator).
     */
    private function metPct($entries, string $field): float
    {
        $applicable = $entries->where($field, '!=', 'NA');
        if ($applicable->isEmpty()) {
            return 0.0;
        }

        return round($applicable->where($field, 'Y')->count() / $applicable->count() * 100, 1);
    }

    public function metrics(): array
    {
        $entries = $this->entriesInRange();

        $feedPosted = $entries->isEmpty() ? 0.0 : round($entries->where('feed_posted', 'Y')->count() / $entries->count() * 100, 1);
        $standardsFeed = $this->metPct($entries, 'standards_feed');
        $standardsStories = $this->metPct($entries, 'standards_stories');

        $overall = round(($feedPosted + $standardsFeed + $standardsStories) / 3, 1);

        return [
            'feed_posted' => $feedPosted,
            'standards_feed' => $standardsFeed,
            'standards_stories' => $standardsStories,
            'overall' => $overall,
            'grade' => self::gradeFor($overall),
            'entry_count' => $entries->count(),
        ];
    }

    public function flaggedDays()
    {
        return $this->entriesInRange()->filter(fn ($e) => filled($e->issues));
    }
}
