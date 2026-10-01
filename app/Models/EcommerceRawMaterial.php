<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EcommerceRawMaterial extends Model
{
    protected $fillable = [
        'name',
        'type',
        'current_stock',
        'initial_stock',
        'unit_of_measure',
        'last_purchased_unit_cost',
    ];

    protected $casts = [
        'current_stock' => 'decimal:2',
        'initial_stock' => 'decimal:2',
        'last_purchased_unit_cost' => 'decimal:4',
    ];

    protected static function booted(): void
    {
        static::creating(function (EcommerceRawMaterial $material) {
            $material->initial_stock = $material->current_stock;
        });
    }
}
