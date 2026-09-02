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
        'original_price',
        'duration',
        'start_time',
        'discount_type',
        'discount_value',
        'discount_amount',
        'discount_reason',
    ];

    protected $casts = [
        'start_time' => 'datetime',
    ];

    /**
     * Keeps discount_amount in lockstep with original_price vs. the actually
     * charged final_price (raw price override + any flat/percent discount
     * combined), so every write path - booking, "Add service", "Edit
     * service" - reports the same number without repeating the math.
     */
    protected static function booted(): void
    {
        static::saving(function (self $line) {
            if ($line->original_price !== null) {
                $line->discount_amount = max(0, round((float) $line->original_price - $line->final_price, 2));
            }
        });
    }

    public function appointment()
    {
        return $this->belongsTo(Appointment::class);
    }

    public function priceOverrides()
    {
        return $this->hasMany(AppointmentPriceOverride::class);
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
