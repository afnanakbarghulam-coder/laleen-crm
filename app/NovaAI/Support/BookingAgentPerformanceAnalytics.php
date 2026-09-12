<?php

namespace App\NovaAI\Support;

use App\Models\AgentShiftLog;
use App\Models\Appointment;
use App\Models\KpiAgentTargetReport;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * NOVA READ-ONLY INTEGRATION RULE (see app/NovaAI/README.md): the
 * shift-level bookings/target/achievement figures come straight from the
 * CRM's own, unmodified App\Models\KpiAgentTargetReport::shiftStats()/
 * combined() - the exact methods UserController::dashboard() already
 * calls for its "Morning/Evening Shift Recovery" cards. A fresh instance
 * is constructed here purely as a calculator (date_from/date_to only,
 * matching that same existing usage) - ::create()/save() is never called,
 * so no row is ever written to kpi_agent_target_reports.
 *
 * The per-agent breakdown below is Nova-specific: KpiAgentTargetReport's
 * public API only returns the two shift-level aggregates, never a
 * per-agent split, and its attribution logic (rawShiftBookings()) is
 * private. This class mirrors that exact same attribution rule rather
 * than calling it - see perAgentBookingCounts()'s own docblock. There is
 * no per-agent target anywhere in the CRM: only the aggregate shift-level
 * target from shiftStats() is an established calculation.
 */
class BookingAgentPerformanceAnalytics
{
    public function summary(Carbon $from, Carbon $to): array
    {
        $report = new KpiAgentTargetReport(['date_from' => $from, 'date_to' => $to]);

        return [
            'shift_stats' => $report->shiftStats(),
            'combined' => $report->combined(),
            'combined_border' => $report->borderFor($report->combined()['pct']),
            'per_agent' => $this->perAgentBookingCounts($report, $from, $to),
        ];
    }

    /**
     * Raw per-agent booking counts (+ logged shift hours), using the exact
     * same attribution rule as KpiAgentTargetReport::rawShiftBookings():
     * a booking counts only when the SAME agent account created it
     * (Appointment::created_by, never booking_agent_id/"credited to") and
     * that account has its own AgentShiftLog row for that calendar date
     * with both check-in and check-out recorded, with the booking's
     * created_at falling strictly inside that exact window. Every agent
     * with a qualifying shift log in the period is included even at zero
     * bookings - a missing shift log means no attribution data exists for
     * that agent that day, not a proven absence.
     */
    private function perAgentBookingCounts(KpiAgentTargetReport $report, Carbon $from, Carbon $to): Collection
    {
        $bounds = [$from->copy()->startOfDay(), $to->copy()->endOfDay()];

        $logs = AgentShiftLog::whereBetween('date', $bounds)
            ->whereNotNull('check_in_time')
            ->whereNotNull('check_out_time')
            ->get();

        if ($logs->isEmpty()) {
            return collect();
        }

        $logsByAgentAndDate = $logs->keyBy(fn ($log) => $log->user_id . '|' . $log->date->format('Y-m-d'));
        $agentIds = $logs->pluck('user_id')->unique();

        $appointments = Appointment::query()
            ->join('users', 'users.id', '=', 'appointments.created_by')
            ->whereIn('appointments.created_by', $agentIds)
            ->where('users.role', 'agent')
            ->whereBetween('appointments.created_at', $bounds)
            ->get(['appointments.id', 'appointments.created_by', 'appointments.created_at']);

        $counts = [];
        foreach ($agentIds as $id) {
            $counts[$id] = ['morning' => 0, 'evening' => 0];
        }

        foreach ($appointments as $appointment) {
            $key = $appointment->created_by . '|' . $appointment->created_at->format('Y-m-d');
            $log = $logsByAgentAndDate->get($key);

            if ($log && $appointment->created_at->between($log->windowStart(), $log->windowEnd())) {
                $counts[$appointment->created_by][$log->shift]++;
            }
        }

        $loggedMinutesByAgent = $logs->groupBy('user_id')
            ->map(fn (Collection $rows) => $rows->sum(fn (AgentShiftLog $log) => $log->windowStart()->diffInMinutes($log->windowEnd())));

        $agents = User::whereIn('id', $agentIds)->get()->keyBy('id');

        return collect($counts)
            ->map(function ($shifts, $agentId) use ($agents, $loggedMinutesByAgent) {
                return [
                    'agent_id' => $agentId,
                    'name' => $agents->get($agentId)->name ?? "Agent #{$agentId}",
                    'morning' => $shifts['morning'],
                    'evening' => $shifts['evening'],
                    'total' => $shifts['morning'] + $shifts['evening'],
                    'logged_hours' => round(($loggedMinutesByAgent->get($agentId) ?? 0) / 60, 1),
                ];
            })
            ->sortByDesc('total')
            ->values();
    }
}
