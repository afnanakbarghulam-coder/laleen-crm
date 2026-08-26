<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

class KpiAgentTargetReport extends Model
{
    const MONTHLY_TARGET_PER_SHIFT = 403; // 13 bookings/day/shift x 31 days
    const RECOVERY_TARGET_PCT = 88.0;

    protected $fillable = [
        'date_from',
        'date_to',
        'morning_bookings',
        'morning_target',
        'evening_bookings',
        'evening_target',
        'prev_morning_pct',
        'prev_evening_pct',
        'created_by',
    ];

    protected $casts = [
        'date_from' => 'date',
        'date_to' => 'date',
        'prev_morning_pct' => 'float',
        'prev_evening_pct' => 'float',
    ];

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    private function daysInPeriod(): int
    {
        return max($this->date_from->diffInDays($this->date_to) + 1, 1);
    }

    public function shiftStats(): array
    {
        $days = $this->daysInPeriod();

        $build = function (int $bookings, int $target, ?float $prevPct) use ($days) {
            $pct = $target > 0 ? round($bookings / $target * 100, 1) : 0.0;

            return [
                'bookings' => $bookings,
                'target' => $target,
                'gap' => max($target - $bookings, 0),
                'pct' => $pct,
                'daily_avg' => round($bookings / $days, 1),
                'prev_pct' => $prevPct,
                'change' => $prevPct !== null ? round($pct - $prevPct, 1) : null,
                'border' => $this->borderFor($pct),
            ];
        };

        return [
            'morning' => $build($this->morning_bookings, $this->morning_target, $this->prev_morning_pct),
            'evening' => $build($this->evening_bookings, $this->evening_target, $this->prev_evening_pct),
        ];
    }

    public function borderFor(float $pct): string
    {
        return match (true) {
            $pct >= 85 => 'green',
            $pct >= 70 => 'amber',
            default => 'red',
        };
    }

    public function combined(): array
    {
        $totalBookings = $this->morning_bookings + $this->evening_bookings;
        $totalTarget = $this->morning_target + $this->evening_target;
        $pct = $totalTarget > 0 ? round($totalBookings / $totalTarget * 100, 1) : 0.0;

        return [
            'bookings' => $totalBookings,
            'target' => $totalTarget,
            'gap' => max($totalTarget - $totalBookings, 0),
            'pct' => $pct,
        ];
    }

    /**
     * Daily bookings needed per shift, for the remaining days of the month,
     * to reach 88% of the fixed 403/shift monthly target.
     */
    public function recoveryMath(): array
    {
        $target88 = self::MONTHLY_TARGET_PER_SHIFT * self::RECOVERY_TARGET_PCT / 100;
        $daysInMonth = $this->date_to->daysInMonth;
        $daysRemaining = max($daysInMonth - $this->date_to->day, 0);

        $build = function (int $bookings) use ($target88, $daysRemaining) {
            $remainingNeeded = max($target88 - $bookings, 0);
            $perDay = $daysRemaining > 0 ? ceil($remainingNeeded / $daysRemaining) : 0;

            return [
                'target_88' => round($target88, 1),
                'remaining_needed' => round($remainingNeeded, 1),
                'per_day' => (int) $perDay,
                'on_track' => $bookings >= $target88,
            ];
        };

        return [
            'days_remaining' => $daysRemaining,
            'morning' => $build($this->morning_bookings),
            'evening' => $build($this->evening_bookings),
        ];
    }
}
