<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AppointmentService extends Model
{
    protected $fillable = [
        'appointment_id',
        'service_id',
        'staff_id',
        'name',
        'price',
        'duration',
        'start_time',
        'discount_type',
        'discount_value',
    ];

    protected $casts = [
        'start_time' => 'datetime',
    ];

    public function appointment()
    {
        return $this->belongsTo(Appointment::class);
    }

    public function service()
    {
        return $this->belongsTo(Service::class);
    }

    public function staff()
    {
        return $this->belongsTo(Staff::class);
    }

    public function getEndTimeAttribute()
    {
        return $this->start_time->copy()->addMinutes($this->duration);
    }

    public function getFinalPriceAttribute(): float
    {
        $price = (float) $this->price;

        if ($this->discount_type === 'percent') {
            return round(max(0, $price * (1 - min((float) $this->discount_value, 100) / 100)), 2);
        }

        if ($this->discount_type === 'flat') {
            return round(max(0, $price - (float) $this->discount_value), 2);
        }

        return round($price, 2);
    }
}
