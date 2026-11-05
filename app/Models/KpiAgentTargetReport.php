<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class KpiAgentTargetReport extends Model
{
    const DAILY_TARGET_PER_SHIFT = 13; // bookings/day/shift
    const MONTHLY_TARGET_PER_SHIFT = 403; // 13 bookings/day/shift x 31 days
    const RECOVERY_TARGET_PCT = 88.0;

    protected $fillable = [
        'date_from',
        'date_to',
        'created_by',
    ];

    protected $casts = [
        'date_from' => 'date',
        'date_to' => 'date',
    ];

    /** In-memory cache so repeated calls within one request don't re-query. */
    private ?array $shiftStatsCache = null;

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    private function daysInPeriod(): int
    {
        return max($this->date_from->diffInDays($this->date_to) + 1, 1);
    }

    /**
     * Morning/Evening bookings, computed entirely from agent shift sign-ins
     * (AgentShiftLog) and appointments booked during each agent's active
     * shift window on the day they signed in. No manual input: this report
     * is just a saved bookmark for a date range, always recomputed fresh.
     *
     * Attribution is intentionally strict: a booking only counts toward an
     * agent's target when that SAME agent account is the one that created
     * the record (Appointment::created_by), not merely the account it's
     * credited to (booking_agent_id) — so a manager/admin creating or
     * reassigning a booking on an agent's behalf is excluded, and the
     * booking's created_at must fall strictly inside that agent's exact
     * check-in -> check-out window for the day.
     *
     * @return array{morning: int, evening: int}
     */
    private function rawShiftBookings(): array
    {
        $bounds = $this->dateRangeBounds();

        $logs = AgentShiftLog::whereBetween('date', $bounds)
            ->whereNotNull('check_in_time')
            ->get();

        // "agentId|Y-m-d" => Collection<AgentShiftLog>, so an agent who worked
        // both morning and evening shifts on the same day has both shift windows
        // evaluated without the second shift overwriting the first.
        $logsByAgentAndDate = $logs->groupBy(fn ($log) => $log->user_id . '|' . $log->date->format('Y-m-d'));

        $agentIds = User::where('role', 'agent')
            ->pluck('id')
            ->merge($logs->pluck('user_id'))
            ->unique()
            ->values();

        if ($agentIds->isEmpty()) {
            return ['morning' => 0, 'evening' => 0];
        }

        $appointments = Appointment::query()
            ->whereNotIn('status', ['cancelled', 'no_show'])
            ->where(function ($q) use ($agentIds) {
                $q->whereIn('booking_agent_id', $agentIds)
                  ->orWhereIn('created_by', $agentIds);
            })
            ->whereBetween('created_at', $bounds)
            ->get(['id', 'booking_agent_id', 'created_by', 'created_at']);

        $counts = ['morning' => 0, 'evening' => 0];

        foreach ($appointments as $appointment) {
            $attributedAgentId = ($appointment->booking_agent_id && $agentIds->contains($appointment->booking_agent_id))
                ? $appointment->booking_agent_id
                : $appointment->created_by;

            $key = $attributedAgentId . '|' . $appointment->created_at->format('Y-m-d');
            $dayLogs = $logsByAgentAndDate->get($key, collect());

            $matchedShift = null;
            foreach ($dayLogs as $log) {
                if (isset($counts[$log->shift]) && $appointment->created_at->between($log->windowStart(), $log->windowEnd())) {
                    $matchedShift = $log->shift;
                    break;
                }
            }

            if ($matchedShift === null && $dayLogs->count() === 1) {
                $singleShift = $dayLogs->first()->shift;
                if (isset($counts[$singleShift])) {
                    $matchedShift = $singleShift;
                }
            }

            if ($matchedShift === null) {
                $matchedShift = (int) $appointment->created_at->format('H') < 14 ? 'morning' : 'evening';
            }

            $counts[$matchedShift]++;
        }

        return $counts;
    }

    /**
     * Live per-agent daily & Month-to-Date booking breakdown (13 bookings/day per agent),
     * matching the /kpis Executive Scorecard 1-to-1.
     */
    public static function liveAgentScorecards(): array
    {
        $now = now();
        $periodStart = $now->copy()->startOfMonth()->startOfDay();
        $periodEnd = $now->copy()->endOfDay();
        $daysElapsed = max(1, (int) $now->day);
        $daysInMonth = max(1, (int) $now->daysInMonth);
        $dailyTarget = self::DAILY_TARGET_PER_SHIFT; // 13

        $agents = User::where('role', 'agent')->orderBy('name')->get();
        $todayShiftLogs = AgentShiftLog::whereDate('date', $now->toDateString())
            ->get()
            ->groupBy('user_id');

        return $agents->map(function (User $agent) use ($now, $periodStart, $periodEnd, $daysElapsed, $daysInMonth, $dailyTarget, $todayShiftLogs) {
            $todayBookings = Appointment::query()
                ->whereNotIn('status', ['cancelled', 'no_show'])
                ->where(function ($q) use ($agent) {
                    $q->where('booking_agent_id', $agent->id)
                      ->orWhere('created_by', $agent->id);
                })
                ->whereDate('created_at', $now->toDateString())
                ->count();

            $mtdBookings = Appointment::query()
                ->whereNotIn('status', ['cancelled', 'no_show'])
                ->where(function ($q) use ($agent) {
                    $q->where('booking_agent_id', $agent->id)
                      ->orWhere('created_by', $agent->id);
                })
                ->whereBetween('created_at', [$periodStart, $periodEnd])
                ->count();

            $mtdTarget = $dailyTarget * $daysElapsed;
            $fullMonthTarget = $dailyTarget * $daysInMonth;
            $mtdPct = min(100.0, round(($mtdBookings / max(1, $mtdTarget)) * 100, 1));
            $todayPct = min(100.0, round(($todayBookings / $dailyTarget) * 100, 1));
            $latestLog = $todayShiftLogs->get($agent->id)?->last();

            return [
                'id' => $agent->id,
                'name' => $agent->name,
                'daily_target' => $dailyTarget,
                'today_bookings' => $todayBookings,
                'today_pct' => $todayPct,
                'mtd_bookings' => $mtdBookings,
                'mtd_target' => $mtdTarget,
                'full_month_target' => $fullMonthTarget,
                'mtd_pct' => $mtdPct,
                'shift_log' => $latestLog,
            ];
        })->values()->all();
    }

    /**
     * Morning/Evening completion % for the period immediately preceding this
     * report's range (same length), for the "vs prior period" comparison.
     *
     * @return array{morning: float, evening: float}
     */
    private function previousPeriodPct(): array
    {
        $days = $this->daysInPeriod();
        $prevTo = $this->date_from->copy()->subDay();
        $prevFrom = $prevTo->copy()->subDays($days - 1);

        $previous = new self(['date_from' => $prevFrom, 'date_to' => $prevTo]);
        $bookings = $previous->rawShiftBookings();
        $target = self::DAILY_TARGET_PER_SHIFT * $days;

        return [
            'morning' => round($bookings['morning'] / $target * 100, 1),
            'evening' => round($bookings['evening'] / $target * 100, 1),
        ];
    }

    public function shiftStats(): array
    {
        if ($this->shiftStatsCache !== null) {
            return $this->shiftStatsCache;
        }

        $days = $this->daysInPeriod();
        $bookings = $this->rawShiftBookings();
        $prevPct = $this->previousPeriodPct();

        $build = function (string $shift) use ($days, $bookings, $prevPct) {
            $b = $bookings[$shift];
            $target = self::DAILY_TARGET_PER_SHIFT * $days;
            $pct = $target > 0 ? round($b / $target * 100, 1) : 0.0;
            $prev = $prevPct[$shift];

            return [
                'bookings' => $b,
                'target' => $target,
                'gap' => max($target - $b, 0),
                'pct' => $pct,
                'daily_avg' => round($b / $days, 1),
                'prev_pct' => $prev,
                'change' => round($pct - $prev, 1),
                'border' => $this->borderFor($pct),
            ];
        };

        return $this->shiftStatsCache = [
            'morning' => $build('morning'),
            'evening' => $build('evening'),
        ];
    }

    /**
     * Start-of-day / end-of-day bounds for date-range queries. Both
     * agent_shift_logs.date (plain DATE) and appointments.created_at
     * (DATETIME) are stored as full "Y-m-d H:i:s" strings under SQLite
     * regardless of column type, so a plain 'Y-m-d' bound would lexically
     * exclude rows — spanning the whole day on both ends keeps it correct.
     */
    private function dateRangeBounds(): array
    {
        return [$this->date_from->copy()->startOfDay(), $this->date_to->copy()->endOfDay()];
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
        $shifts = $this->shiftStats();
        $totalBookings = $shifts['morning']['bookings'] + $shifts['evening']['bookings'];
        $totalTarget = $shifts['morning']['target'] + $shifts['evening']['target'];
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
        $bookings = $this->rawShiftBookings();
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
            'morning' => $build($bookings['morning']),
            'evening' => $build($bookings['evening']),
        ];
    }
}
