<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EcommerceRawMaterial extends Model
{
    protected $fillable = [
        'name',
        'type',
        'current_stock',
        'unit_of_measure',
    ];

    protected $casts = [
        'current_stock' => 'decimal:2',
    ];

    public function products()
    {
        return $this->belongsToMany(EcommerceProduct::class, 'ecommerce_product_raw_material')
            ->withPivot('quantity_required')
            ->withTimestamps();
    }
}
