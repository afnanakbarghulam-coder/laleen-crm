<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EcommercePricingModel extends Model
{
    protected $fillable = [
        'ecommerce_product_id',
        'liquid_cost',
        'packaging_cost',
        'fulfillment_cost',
        'selling_price',
    ];

    protected $casts = [
        'liquid_cost' => 'decimal:2',
        'packaging_cost' => 'decimal:2',
        'fulfillment_cost' => 'decimal:2',
        'selling_price' => 'decimal:2',
    ];

    public function product()
    {
        return $this->belongsTo(EcommerceProduct::class, 'ecommerce_product_id');
    }
}
