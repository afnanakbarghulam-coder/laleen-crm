<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ServiceCategory extends Model
{
    protected $fillable = [
        'name',
        'color',
        'sort_order',
    ];

    public function services()
    {
        return $this->hasMany(Service::class, 'category_id');
    }
}
