<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AppointmentPriceOverride extends Model
{
    protected $fillable = [
        'appointment_id',
        'appointment_service_id',
        'service_name',
        'original_price',
        'new_price',
        'discount_amount',
        'discount_reason',
        'changed_by',
    ];

    public function appointment()
    {
        return $this->belongsTo(Appointment::class);
    }

    public function appointmentService()
    {
        return $this->belongsTo(AppointmentService::class);
    }

    public function changedBy()
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
