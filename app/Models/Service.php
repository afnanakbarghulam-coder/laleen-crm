<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Service extends Model
{
    protected $fillable = [
        'name',
        'category_id',
        'description',
        'treatment_type',
        'photo',
        'price',
        'price_type',
        'duration',
        'rebooking_interval_days',
    ];

    public function category()
    {
        return $this->belongsTo(ServiceCategory::class, 'category_id');
    }

    public function staff()
    {
        return $this->belongsToMany(Staff::class, 'service_staff');
    }

    public function combos()
    {
        return $this->belongsToMany(Combo::class, 'combo_services');
    }

    public function appointmentServices()
    {
        return $this->hasMany(AppointmentService::class, 'service_id');
    }

    public function upsells()
    {
        return $this->hasMany(AppointmentUpsell::class, 'service_id');
    }

    public function complaints()
    {
        return $this->hasMany(StaffComplaint::class, 'service_id');
    }

    public function clientPackageServices()
    {
        return $this->hasMany(ClientPackageService::class, 'service_id');
    }
}
