<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Combo extends Model
{
    protected $fillable = [
        'name',
        'price',
        'quantity_included',
        'validity_days',
        'default_duration',
        'status',
    ];

    protected $casts = [
        'price' => 'float',
        'quantity_included' => 'integer',
        'validity_days' => 'integer',
        'default_duration' => 'integer',
    ];

    public function getEffectiveDurationAttribute(): int
    {
        $configured = (int) ($this->default_duration ?: 60);

        return $configured > 0 ? $configured : 60;
    }

    public function services()
    {
        return $this->belongsToMany(Service::class, 'combo_services');
    }

    public function clientPackages()
    {
        return $this->hasMany(ClientPackage::class);
    }
}
