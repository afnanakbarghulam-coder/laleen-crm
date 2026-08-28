<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Appointment extends Model
{
    use HasFactory;
    protected $table = 'appointments';

    protected $fillable = [
        'customer_name',
        'phone',
        'customer_id',
        'appointment_datetime',
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

    public function appointmentServices()
    {
        return $this->hasMany(AppointmentService::class)->orderBy('start_time');
    }

    public function upsells()
    {
        return $this->hasMany(AppointmentUpsell::class)->orderBy('created_at');
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
        $this->appointment_datetime = $first->start_time;
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
}
