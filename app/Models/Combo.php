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
        'status',
    ];

    public function services()
    {
        return $this->belongsToMany(Service::class, 'combo_services');
    }

    public function clientPackages()
    {
        return $this->hasMany(ClientPackage::class);
    }
}
