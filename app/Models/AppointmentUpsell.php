<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AppointmentUpsell extends Model
{
    protected $fillable = [
        'appointment_id',
        'staff_id',
        'type',
        'service_id',
        'product_id',
        'name',
        'amount',
        'created_by',
    ];

    protected $casts = [
        'amount' => 'float',
    ];

    public function appointment()
    {
        return $this->belongsTo(Appointment::class);
    }

    public function staff()
    {
        return $this->belongsTo(Staff::class);
    }

    public function service()
    {
        return $this->belongsTo(Service::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
