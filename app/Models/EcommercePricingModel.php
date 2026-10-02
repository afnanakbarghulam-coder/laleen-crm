<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EcommercePricingModel extends Model
{
    protected $fillable = [
        'ecommerce_product_id',
        'liquid_cost',
        'bottle_cost',
        'label_cost',
        'pump_cost',
        'box_cost',
        'fulfillment_cost',
        'selling_price',
    ];

    protected $casts = [
        'liquid_cost' => 'decimal:2',
        'bottle_cost' => 'decimal:2',
        'label_cost' => 'decimal:2',
        'pump_cost' => 'decimal:2',
        'box_cost' => 'decimal:2',
        'fulfillment_cost' => 'decimal:2',
        'selling_price' => 'decimal:2',
    ];

    public function product()
    {
        return $this->belongsTo(EcommerceProduct::class, 'ecommerce_product_id');
    }
}
