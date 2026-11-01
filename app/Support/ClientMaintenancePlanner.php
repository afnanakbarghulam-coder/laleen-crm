<?php

namespace App\Support;

use App\Models\Appointment;
use App\Models\AppointmentService;
use App\Models\Customer;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Live, non-persisted Service-Specific Maintenance Window computation. A
 * client's "next due" date for a given service is simply their most recent
 * completed visit for that service plus the service's configured
 * rebooking_interval_days — nothing here is ever written to the database;
 * every schedule is derived fresh from appointment_services + services.
 */
class ClientMaintenancePlanner
{
    const DUE_SOON_WINDOW_DAYS = 7;

    /**
     * One row per (customer, service) pair that has maintenance tracking
     * enabled and at least one past, non-cancelled visit. Pass $customerId
     * to scope to a single client's profile timeline.
     */
    public function buildSchedule(?int $customerId = null): Collection
    {
        $query = AppointmentService::query()
            ->join('appointments', 'appointments.id', '=', 'appointment_services.appointment_id')
            ->join('services', 'services.id', '=', 'appointment_services.service_id')
            ->whereNotNull('services.rebooking_interval_days')
            ->whereNotNull('appointments.customer_id')
            ->where('appointments.status', 'completed')
            ->where('appointment_services.start_time', '<=', now());

        if ($customerId) {
            $query->where('appointments.customer_id', $customerId);
        }

        $rows = $query
            ->selectRaw('appointments.customer_id as customer_id, appointment_services.service_id as service_id, services.name as service_name, services.rebooking_interval_days as interval_days, MAX(appointment_services.start_time) as last_visit')
            ->groupBy('appointments.customer_id', 'appointment_services.service_id', 'services.name', 'services.rebooking_interval_days')
            ->get();

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

        return $rows->map(function ($row) use ($today, $upcomingAppointments) {
            $lastVisit = Carbon::parse($row->last_visit);
            $nextDue = $lastVisit->copy()->startOfDay()->addDays((int) $row->interval_days);

            // Whole-day difference via raw timestamps: negative = overdue,
            // 0 = due today, positive = still days away. Avoids relying on
            // Carbon::diffInDays()'s sign convention, which varies by version.
            $daysUntil = (int) floor(($nextDue->timestamp - $today->timestamp) / 86400);

            $clientUpcoming = $upcomingAppointments->get((int) $row->customer_id, collect());
            $matchingUpcoming = $clientUpcoming->first(function (Appointment $appt) use ($row) {
                foreach ($appt->appointmentServices as $line) {
                    if ((int) $line->service_id === (int) $row->service_id || strcasecmp(trim((string) $line->name), trim((string) $row->service_name)) === 0) {
                        return true;
                    }
                }
                $flatNames = array_filter(array_map('trim', explode(',', (string) $appt->service_name)));
                foreach ($flatNames as $flatName) {
                    if (strcasecmp($flatName, trim((string) $row->service_name)) === 0) {
                        return true;
                    }
                }

                return false;
            });

            $upcomingDate = $matchingUpcoming ? Carbon::parse($matchingUpcoming->appointment_datetime) : null;

            return (object) [
                'customer_id' => (int) $row->customer_id,
                'service_id' => (int) $row->service_id,
                'service_name' => $row->service_name,
                'interval_days' => (int) $row->interval_days,
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

        return $schedule
            ->map(function ($row) use ($customers) {
                $row->customer = $customers->get($row->customer_id);
                return $row;
            })
            ->filter(fn ($row) => $row->customer !== null)
            ->values();
    }
}
