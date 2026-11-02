<?php

namespace App\Support;

use App\Models\Appointment;
use App\Models\AppointmentService;
use App\Models\Customer;
use App\Models\Service;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Live, non-persisted Service-Specific Maintenance Window computation. A
 * client's "next due" date for a given service is simply their most recent
 * completed visit for that service plus the service's configured
 * rebooking_interval_days (or the 28-day default fallback) — nothing here is
 * ever written to the database; every schedule is derived fresh from
 * completed appointments + appointment_services + services.
 */
class ClientMaintenancePlanner
{
    const DUE_SOON_WINDOW_DAYS = 7;
    const DEFAULT_INTERVAL_DAYS = 28;

    /**
     * One row per (customer, service) pair from completed appointments.
     * Uses Service.rebooking_interval_days when > 0, or falls back to
     * DEFAULT_INTERVAL_DAYS (28 days) so 1st-visit clients never fall through
     * the cracks. Pass $customerId to scope to a single client's profile timeline.
     */
    public function buildSchedule(?int $customerId = null): Collection
    {
        $catalogServices = Service::with('category')->get();
        $servicesById = $catalogServices->keyBy('id');
        $servicesByName = $catalogServices->keyBy(fn ($s) => strtolower(trim((string) $s->name)));

        $completedQuery = Appointment::query()
            ->with('appointmentServices')
            ->whereNotNull('customer_id')
            ->where('status', 'completed')
            ->where('appointment_datetime', '<=', now());

        if ($customerId) {
            $completedQuery->where('customer_id', $customerId);
        }

        $completedAppointments = $completedQuery->get();
        $completedVisitsByCustomer = $completedAppointments
            ->groupBy('customer_id')
            ->map(fn ($group) => $group->count());

        $groupedRows = [];

        foreach ($completedAppointments as $appt) {
            $cid = (int) $appt->customer_id;
            $apptTime = Carbon::parse($appt->appointment_datetime);
            $items = [];

            if ($appt->appointmentServices && $appt->appointmentServices->isNotEmpty()) {
                foreach ($appt->appointmentServices as $line) {
                    $lineTime = $line->start_time ? Carbon::parse($line->start_time) : $apptTime;
                    if ($lineTime->gt(now())) {
                        continue;
                    }
                    $svc = $line->service_id ? $servicesById->get((int) $line->service_id) : null;
                    $rawName = trim((string) ($svc?->name ?: $line->name));
                    if ($rawName === '' || strcasecmp($rawName, 'Decide in Salon') === 0) {
                        continue;
                    }
                    if (!$svc) {
                        $svc = $servicesByName->get(strtolower($rawName));
                    }
                    $items[] = [
                        'service' => $svc,
                        'service_name' => $svc ? $svc->name : $rawName,
                        'visit_time' => $lineTime,
                    ];
                }
            } else {
                $flatNames = array_filter(array_map('trim', explode(',', (string) $appt->service_name)));
                foreach ($flatNames as $rawName) {
                    if ($rawName === '' || strcasecmp($rawName, 'Decide in Salon') === 0) {
                        continue;
                    }
                    $svc = $servicesByName->get(strtolower($rawName));
                    $items[] = [
                        'service' => $svc,
                        'service_name' => $svc ? $svc->name : $rawName,
                        'visit_time' => $apptTime,
                    ];
                }
            }

            foreach ($items as $item) {
                $svc = $item['service'];
                $serviceName = $item['service_name'];
                $visitTime = $item['visit_time'];

                $explicitInterval = ($svc && $svc->rebooking_interval_days !== null && (int) $svc->rebooking_interval_days > 0)
                    ? (int) $svc->rebooking_interval_days
                    : null;

                $intervalDays = $explicitInterval ?? self::DEFAULT_INTERVAL_DAYS;
                $hasExplicitInterval = $explicitInterval !== null;
                $serviceId = $svc ? (int) $svc->id : 0;
                $groupKey = $cid . ':' . ($serviceId > 0 ? 'id_' . $serviceId : 'name_' . strtolower($serviceName));

                if (!isset($groupedRows[$groupKey]) || $visitTime->gt($groupedRows[$groupKey]['last_visit'])) {
                    $groupedRows[$groupKey] = [
                        'customer_id' => $cid,
                        'service_id' => $serviceId,
                        'service_name' => $serviceName,
                        'interval_days' => $intervalDays,
                        'has_explicit_interval' => $hasExplicitInterval,
                        'last_visit' => $visitTime,
                    ];
                }
            }
        }

        $rows = collect(array_values($groupedRows));
        $today = now()->startOfDay();

        // Check if the customer already has an upcoming active appointment (pending, confirmed, arrived, in_progress)
        // on or after today so we don't flag them as overdue when they're already booked!
        $upcomingAppointments = Appointment::query()
            ->with('appointmentServices:id,appointment_id,service_id,name')
            ->whereNotNull('customer_id')
            ->whereNotIn('status', ['cancelled', 'no_show', 'completed'])
            ->whereDate('appointment_datetime', '>=', $today->toDateString())
            ->when($customerId, fn ($q) => $q->where('customer_id', $customerId))
            ->orderBy('appointment_datetime')
            ->get()
            ->groupBy('customer_id');

        return $rows->map(function (array $row) use ($today, $upcomingAppointments, $completedVisitsByCustomer) {
            $lastVisit = Carbon::parse($row['last_visit']);
            $nextDue = $lastVisit->copy()->startOfDay()->addDays((int) $row['interval_days']);

            // Whole-day difference via raw timestamps: negative = overdue,
            // 0 = due today, positive = still days away. Avoids relying on
            // Carbon::diffInDays()'s sign convention, which varies by version.
            $daysUntil = (int) floor(($nextDue->timestamp - $today->timestamp) / 86400);

            $cid = (int) $row['customer_id'];
            $clientUpcoming = $upcomingAppointments->get($cid, collect());
            $matchingUpcoming = $clientUpcoming->first(function (Appointment $appt) use ($row) {
                foreach ($appt->appointmentServices as $line) {
                    if (((int) $row['service_id'] > 0 && (int) $line->service_id === (int) $row['service_id']) || strcasecmp(trim((string) $line->name), trim((string) $row['service_name'])) === 0) {
                        return true;
                    }
                }
                $flatNames = array_filter(array_map('trim', explode(',', (string) $appt->service_name)));
                foreach ($flatNames as $flatName) {
                    if (strcasecmp($flatName, trim((string) $row['service_name'])) === 0) {
                        return true;
                    }
                }

                return false;
            });

            $upcomingDate = $matchingUpcoming ? Carbon::parse($matchingUpcoming->appointment_datetime) : null;

            return (object) [
                'customer_id' => $cid,
                'service_id' => (int) $row['service_id'],
                'service_name' => $row['service_name'],
                'interval_days' => (int) $row['interval_days'],
                'has_explicit_interval' => (bool) $row['has_explicit_interval'],
                'completed_visits_count' => (int) ($completedVisitsByCustomer->get($cid, 0)),
                'last_visit' => $lastVisit,
                'next_due' => $nextDue,
                'days_until' => $daysUntil,
                'days_since_visit' => (int) floor(($today->timestamp - $lastVisit->copy()->startOfDay()->timestamp) / 86400),
                'upcoming_appointment_date' => $upcomingDate,
                'urgency' => match (true) {
                    $upcomingDate !== null => 'scheduled',
                    $daysUntil < 0 => 'overdue',
                    $daysUntil <= self::DUE_SOON_WINDOW_DAYS => 'due_soon',
                    default => 'upcoming',
                },
            ];
        })->sortBy('days_until')->values();
    }

    /** Maintenance timeline for a single client's profile page. */
    public function scheduleForCustomer(Customer $customer): Collection
    {
        return $this->buildSchedule($customer->id);
    }

    /**
     * Front-desk task queue: every (customer, service) pair that's overdue
     * or due within the look-ahead window, across all clients, with the
     * customer eager-loaded for name/phone/loyalty display. Sorted most
     * overdue first. Excludes services where the client already has an
     * upcoming appointment scheduled.
     */
    public function dueQueue(int $lookaheadDays = self::DUE_SOON_WINDOW_DAYS): Collection
    {
        $schedule = $this->buildSchedule()
            ->filter(fn ($row) => $row->urgency !== 'scheduled' && $row->days_until <= $lookaheadDays);

        $customers = Customer::whereIn('id', $schedule->pluck('customer_id')->unique())
            ->get()
            ->keyBy('id');

        $lastCompletedByCustomer = Appointment::with('staff:id,name')
            ->whereIn('customer_id', $schedule->pluck('customer_id')->unique())
            ->where('status', 'completed')
            ->orderByDesc('appointment_datetime')
            ->get(['id', 'customer_id', 'staff_id', 'branch', 'service_name', 'appointment_datetime'])
            ->groupBy('customer_id');

        return $schedule
            ->map(function ($row) use ($customers, $lastCompletedByCustomer) {
                $row->customer = $customers->get($row->customer_id);
                $clientCompleted = $lastCompletedByCustomer->get($row->customer_id, collect());
                $matchingCompleted = $clientCompleted->first(
                    fn ($a) => stripos((string) $a->service_name, (string) $row->service_name) !== false
                ) ?: $clientCompleted->first();
                $row->last_staff_name = $matchingCompleted?->staff?->name;
                $row->branch = $matchingCompleted?->branch;
                return $row;
            })
            ->filter(fn ($row) => $row->customer !== null)
            ->values();
    }
}
