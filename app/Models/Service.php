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
    ];

    public function category()
    {
        return $this->belongsTo(ServiceCategory::class, 'category_id');
    }

    public function staff()
    {
        return $this->belongsToMany(Staff::class, 'service_staff');
    }
}
