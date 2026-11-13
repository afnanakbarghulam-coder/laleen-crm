<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class Appointment extends Model
{
    use HasFactory;
    protected $table = 'appointments';

    protected $fillable = [
        'customer_name',
        'phone',
        'customer_id',
        'appointment_datetime',
        'booking_date',
        'start_time',
        // 'service_id',
        'service_name',
        'notes',
        'branch',
        'price',
        'lifetime_revenue',
        'booking_agent_id',
        'created_by',
        'staff_id',
        'status',
        'payment_method',
        'paid_at'
    ];

    protected $casts = [
        'appointment_datetime' => 'datetime',
        'paid_at' => 'datetime',
    ];

    public function getAppointmentDateAttribute(): Carbon
    {
        return Carbon::parse($this->appointment_datetime ?: $this->booking_date ?: $this->created_at ?: now());
    }

    public function agent()
    {
        return $this->belongsTo(User::class, 'booking_agent_id');
    }

    /**
     * The account that physically created this booking record — distinct
     * from agent()/booking_agent_id, which is who the booking is credited
     * to. Used to verify a booking was self-created by an agent (not a
     * manager/admin acting on their behalf) for shift-window attribution.
     */
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function staff()
    {
        return $this->belongsTo(Staff::class, 'staff_id');
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }

    public function sale()
    {
        return $this->hasOne(Sale::class);
    }

    /**
     * Scope to appointments where the sale/checkout is actually paid or completed.
     * Unpaid scheduled/confirmed appointments are excluded until checkout/payment.
     */
    public function scopePaidOrCompleted($query)
    {
        return $query->whereNotIn('status', ['cancelled', 'no_show'])
            ->where(function ($q) {
                $q->where('status', 'completed')
                  ->orWhereNotNull('paid_at')
                  ->orWhereHas('sale');
            });
    }

    public function appointmentServices()
    {
        return $this->hasMany(AppointmentService::class)->orderBy('start_time');
    }

    public function upsells()
    {
        return $this->hasMany(AppointmentUpsell::class)->orderBy('created_at');
    }

    /**
     * Permanent audit trail of every manual price override/discount applied
     * to this booking's services.
     */
    public function priceOverrides()
    {
        return $this->hasMany(AppointmentPriceOverride::class)->orderByDesc('created_at');
    }

    /**
     * Recompute the appointment's summary fields (service_name, price,
     * appointment_datetime, staff_id) from its line items. Call this any
     * time appointment_services rows are added, edited, or removed so the
     * calendar block and every place that reads these flat fields stays
     * accurate. The earliest-starting service's staff is treated as the
     * "primary" staff for calendar column placement.
     */
    public function syncFromServices(): void
    {
        $services = $this->appointmentServices()->get();

        if ($services->isEmpty()) {
            return;
        }

        $first = $services->first();

        $this->service_name = $services->pluck('name')->implode(', ');
        $this->price = $services->sum(fn($s) => $s->final_price);
        if ($first->start_time) {
            $this->appointment_datetime = $first->start_time;
            $this->booking_date = $first->start_time->toDateString();
            $this->start_time = $first->start_time->format('H:i:s');
        }
        $this->staff_id = $first->staff_id ?? $this->staff_id;
        $this->save();
    }

    // public function service()
    // {
    //     return $this->belongsTo(Service::class);
    // }
    public function service()
    {
        return $this->belongsTo(Service::class, 'service_id');
    }

    /**
     * Scope to confirmed/scheduled Chat Agent bookings eligible for booking quota credit:
     * - Explicit `booking_agent_id` belonging to a Chat Agent (`role = 'agent'`) ONLY
     *   (no `created_by` fallback).
     * - Scheduled onto the calendar (`start_time IS NOT NULL` and `staff_id IS NOT NULL`),
     *   excluding unscheduled sidebar queue placeholders until placed on the grid.
     * - Keeps all statuses (`pending`, `confirmed`, `arrived`, `in_progress`, `completed`,
     *   `cancelled`, `no_show`) because once booked onto the calendar, the agent did her job.
     */
    public function scopeScheduledForAgentQuota($query, iterable $agentUserIds)
    {
        $ids = collect($agentUserIds)->map(fn ($id) => (int) $id)->filter()->unique()->values()->all();

        if (empty($ids)) {
            return $query->whereRaw('1 = 0');
        }

        return $query->whereIn('booking_agent_id', $ids)
            ->whereNotNull('start_time')
            ->whereNotNull('staff_id');
    }

    /**
     * Deduplicate Chat Agent quota appointments by `(booking_agent_id, customer_identity, appointment_date)`
     * ordered chronologically (`created_at ASC, id ASC`) so:
     * - Splitting 1 client into 3 separate same-day service rows counts as +1, not +3.
     * - Cancelling and re-booking the same client on the same appointment date counts as +1, not +2.
     * - Booking the same client for two genuine visits on different appointment dates counts as +2.
     *
     * @param  Collection<int, self>  $appointments
     * @return Collection<int, self>
     */
    public static function deduplicateForAgentQuota(Collection $appointments): Collection
    {
        if ($appointments->isEmpty()) {
            return collect();
        }

        $phoneToCustomerId = [];
        foreach ($appointments as $appt) {
            if (!empty($appt->customer_id) && !empty($appt->phone)) {
                $norm = Customer::normalizePhone((string) $appt->phone);
                if ($norm !== '') {
                    $phoneToCustomerId[$norm] = (int) $appt->customer_id;
                }
            }
        }

        return $appointments
            ->sortBy([
                fn (self $a, self $b) => ($a->created_at?->getTimestamp() ?? 0) <=> ($b->created_at?->getTimestamp() ?? 0),
                fn (self $a, self $b) => ((int) $a->id) <=> ((int) $b->id),
            ])
            ->unique(function (self $appt) use ($phoneToCustomerId): string {
                $agentId = (int) $appt->booking_agent_id;
                $normPhone = !empty($appt->phone) ? Customer::normalizePhone((string) $appt->phone) : '';

                if (!empty($appt->customer_id)) {
                    $customerKey = 'cid:' . (int) $appt->customer_id;
                } elseif ($normPhone !== '' && isset($phoneToCustomerId[$normPhone])) {
                    $customerKey = 'cid:' . $phoneToCustomerId[$normPhone];
                } elseif ($normPhone !== '') {
                    $customerKey = 'ph:' . $normPhone;
                } elseif (trim((string) $appt->customer_name) !== '') {
                    $customerKey = 'nm:' . mb_strtolower(trim((string) $appt->customer_name));
                } else {
                    $customerKey = 'id:' . (int) $appt->id;
                }

                if (!empty($appt->booking_date)) {
                    $apptDate = Carbon::parse($appt->booking_date)->toDateString();
                } elseif (!empty($appt->appointment_datetime)) {
                    $apptDate = Carbon::parse($appt->appointment_datetime)->timezone('Asia/Qatar')->toDateString();
                } elseif ($appt->created_at) {
                    $apptDate = $appt->created_at->copy()->timezone('Asia/Qatar')->toDateString();
                } else {
                    $apptDate = now('Asia/Qatar')->toDateString();
                }

                return "{$agentId}|{$customerKey}|{$apptDate}";
            })
            ->values();
    }
}
