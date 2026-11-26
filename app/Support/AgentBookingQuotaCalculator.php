<?php

namespace App\Support;

use App\Models\Appointment;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Single source of truth for Chat Agent booking quota calculations
 * ("Today's Daily Booking Target" & "MTD Booking Quota" / selected range)
 * across KPI Hub (/kpis), Agent Target Reports (/kpis/agents), and Dashboard (/dashboard).
 *
 * Strict counting rules:
 * - Source: Scheduled Enhanced Calendar appointments explicitly assigned via `booking_agent_id`
 *   to the target Chat Agent(s), excluding `Salon Staff`, cancelled, and soft-deleted bookings.
 * - Timestamp: Strictly `created_at` (never `booking_date`, `appointment_datetime`, `start_time`,
 *   or `updated_at`), so future appointments count on the day booked and later edits never recount.
 * - Window: Exact 24-hour `Asia/Qatar` (UTC+3) half-open bounds:
 *   `created_at >= start (00:00:00 Asia/Qatar) AND created_at < nextDayStart (00:00:00 Asia/Qatar)`,
 *   converted to the database storage timezone (`config('app.timezone')`).
 * - Cardinality: 1 customer + 1 appointment = 1 booking (parent `appointments` row, never child
 *   `appointment_services` or combo blocks `1/3`, `2/3`, `3/3`, deduplicated against same-day splits).
 */
class AgentBookingQuotaCalculator
{
    public const QATAR_TIMEZONE = 'Asia/Qatar';

    public const UI_TOOLTIP_COPY = 'Counts Enhanced Calendar bookings created per day (12 AM–12 AM Qatar time · 1 per appointment)';

    /**
     * Exact half-open [00:00:00 Asia/Qatar, nextDay 00:00:00 Asia/Qatar) bounds
     * for a single Qatar calendar day, converted to DB storage timezone.
     *
     * @return array{start_qatar: Carbon, end_qatar: Carbon, start_db: Carbon, end_db: Carbon}
     */
    public static function qatarDayBounds(?Carbon $referenceDate = null): array
    {
        $ref = ($referenceDate ? $referenceDate->copy() : Carbon::now(self::QATAR_TIMEZONE))
            ->timezone(self::QATAR_TIMEZONE);

        $dateStr = $ref->toDateString();

        return self::qatarRangeBounds($dateStr, $dateStr);
    }

    /**
     * Exact half-open [fromDate 00:00:00 Asia/Qatar, (toDate + 1 day) 00:00:00 Asia/Qatar)
     * bounds converted to the DB storage timezone (`config('app.timezone')`).
     *
     * @return array{start_qatar: Carbon, end_qatar: Carbon, start_db: Carbon, end_db: Carbon}
     */
    public static function qatarRangeBounds(string|Carbon $fromDate, string|Carbon $toDate): array
    {
        $fromStr = $fromDate instanceof Carbon
            ? $fromDate->copy()->timezone(self::QATAR_TIMEZONE)->toDateString()
            : Carbon::parse($fromDate, self::QATAR_TIMEZONE)->toDateString();

        $toStr = $toDate instanceof Carbon
            ? $toDate->copy()->timezone(self::QATAR_TIMEZONE)->toDateString()
            : Carbon::parse($toDate, self::QATAR_TIMEZONE)->toDateString();

        if ($fromStr > $toStr) {
            [$fromStr, $toStr] = [$toStr, $fromStr];
        }

        $dbTz = config('app.timezone', self::QATAR_TIMEZONE);

        $startQatar = Carbon::createFromFormat('Y-m-d', $fromStr, self::QATAR_TIMEZONE)->startOfDay();
        $endQatar = Carbon::createFromFormat('Y-m-d', $toStr, self::QATAR_TIMEZONE)->startOfDay()->addDay();

        return [
            'start_qatar' => $startQatar,
            'end_qatar' => $endQatar,
            'start_db' => $startQatar->copy()->timezone($dbTz),
            'end_db' => $endQatar->copy()->timezone($dbTz),
        ];
    }

    /**
     * Query & deduplicate eligible Enhanced Calendar appointments created within
     * the exact half-open Asia/Qatar date window `[fromDate, toDate]`.
     *
     * @return Collection<int, Appointment>
     */
    public static function fetchForRange(iterable $agentUserIds, string|Carbon $fromDate, string|Carbon $toDate): Collection
    {
        $ids = collect($agentUserIds)->map(fn ($id) => (int) $id)->filter()->unique()->values()->all();
        if (empty($ids)) {
            return collect();
        }

        $bounds = self::qatarRangeBounds($fromDate, $toDate);

        $raw = Appointment::query()
            ->scheduledForAgentQuota($ids)
            ->where('created_at', '>=', $bounds['start_db']->format('Y-m-d H:i:s'))
            ->where('created_at', '<', $bounds['end_db']->format('Y-m-d H:i:s'))
            ->get([
                'id',
                'booking_agent_id',
                'booking_agent_type',
                'customer_id',
                'phone',
                'customer_name',
                'booking_date',
                'start_time',
                'appointment_datetime',
                'status',
                'created_at',
            ]);

        return Appointment::deduplicateForAgentQuota($raw);
    }

    /**
     * Determine whether an appointment is a Pending Time / Unscheduled Queue booking
     * (`start_time IS NULL`).
     */
    public static function isPendingTime(Appointment $appt): bool
    {
        $raw = $appt->getRawOriginal('start_time');
        if ($raw === null) {
            return true;
        }

        return trim((string) $raw) === '';
    }

    /**
     * Resolve an appointment's `created_at` in `Asia/Qatar`, explicitly honoring
     * the database storage timezone (`config('app.timezone')`) for raw DB strings.
     */
    public static function createdAtQatar(Appointment $appt): ?Carbon
    {
        $dbTz = config('app.timezone', self::QATAR_TIMEZONE);
        $raw = $appt->getRawOriginal('created_at');

        if (is_string($raw) && trim($raw) !== '') {
            return Carbon::parse($raw, $dbTz)->timezone(self::QATAR_TIMEZONE);
        }

        if ($raw instanceof Carbon) {
            return $raw->copy()->timezone(self::QATAR_TIMEZONE);
        }

        return $appt->created_at ? $appt->created_at->copy()->timezone(self::QATAR_TIMEZONE) : null;
    }

    /**
     * Determine whether an appointment's `created_at` falls strictly inside a half-open
     * Asia/Qatar window (`created_at >= start_qatar && created_at < end_qatar`).
     *
     * @param  array{start_qatar: Carbon, end_qatar: Carbon, start_db: Carbon, end_db: Carbon}  $bounds
     */
    public static function isCreatedWithinBounds(Appointment $appt, array $bounds): bool
    {
        $createdQatar = self::createdAtQatar($appt);
        if (! $createdQatar) {
            return false;
        }

        return $createdQatar->greaterThanOrEqualTo($bounds['start_qatar'])
            && $createdQatar->lessThan($bounds['end_qatar']);
    }
}
