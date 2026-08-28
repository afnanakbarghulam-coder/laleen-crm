<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

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
     * % of entries where $field === 'Y', out of entries where $field is
     * actually applicable to that row's activity type (see
     * KpiContentEntry::FIELD_VISIBILITY) — a Stories Only day, say, can't
     * fail "feed posted", so it's excluded rather than counted against the
     * creator.
     */
    private function metPct($entries, string $field): float
    {
        $applicable = $entries->filter(fn ($e) => in_array($field, $e->visibleFields(), true));
        if ($applicable->isEmpty()) {
            return 0.0;
        }

        return round($applicable->where($field, 'Y')->count() / $applicable->count() * 100, 1);
    }

    public function metrics(): array
    {
        $entries = $this->entriesInRange();
        $fields = ['stories_posted', 'feed_posted', 'standards_stories', 'standards_feed', 'event'];

        $breakdown = [];
        $totalApplicable = 0;
        $totalMet = 0;

        foreach ($fields as $field) {
            $applicable = $entries->filter(fn ($e) => in_array($field, $e->visibleFields(), true));
            $breakdown[$field] = $applicable->isEmpty() ? 0.0 : round($applicable->where($field, 'Y')->count() / $applicable->count() * 100, 1);
            $totalApplicable += $applicable->count();
            $totalMet += $applicable->where($field, 'Y')->count();
        }

        // Overall is pooled across every applicable (field, entry) pair rather
        // than an unweighted average of the five percentages, so a metric with
        // only one applicable row doesn't swing the total as much as one with
        // twenty.
        $overall = $totalApplicable > 0 ? round($totalMet / $totalApplicable * 100, 1) : 0.0;

        return array_merge($breakdown, [
            'overall' => $overall,
            'grade' => self::gradeFor($overall),
            'entry_count' => $entries->count(),
        ]);
    }

    public function flaggedDays()
    {
        return $this->entriesInRange()->filter(fn ($e) => filled($e->issues));
    }

    /**
     * Same pooled-percentage calculation as metrics(), but across every
     * creator's entries in the range — for the Dashboard's team-wide
     * Content KPI compliance card, which has no single creator to scope to.
     */
    public static function overallMetrics(Carbon $from, Carbon $to): array
    {
        $entries = KpiContentEntry::whereBetween('entry_date', [$from->copy()->startOfDay(), $to->copy()->endOfDay()])->get();
        $fields = ['stories_posted', 'feed_posted', 'standards_stories', 'standards_feed', 'event'];

        $totalApplicable = 0;
        $totalMet = 0;

        foreach ($fields as $field) {
            $applicable = $entries->filter(fn ($e) => in_array($field, $e->visibleFields(), true));
            $totalApplicable += $applicable->count();
            $totalMet += $applicable->where($field, 'Y')->count();
        }

        $overall = $totalApplicable > 0 ? round($totalMet / $totalApplicable * 100, 1) : 0.0;

        return [
            'overall' => $overall,
            'grade' => self::gradeFor($overall),
            'entry_count' => $entries->count(),
        ];
    }
}
