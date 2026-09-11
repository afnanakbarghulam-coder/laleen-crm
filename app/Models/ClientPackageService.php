<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ClientPackageService extends Model
{
    protected $fillable = [
        'client_package_id',
        'service_id',
        'service_name',
        'status',
        'appointment_service_id',
        'redeemed_at',
    ];

    protected $casts = [
        'redeemed_at' => 'datetime',
    ];

    public function clientPackage()
    {
        return $this->belongsTo(ClientPackage::class);
    }

    public function service()
    {
        return $this->belongsTo(Service::class);
    }

    public function appointmentService()
    {
        return $this->belongsTo(AppointmentService::class);
    }
}
