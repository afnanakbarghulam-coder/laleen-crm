<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EcommerceSale extends Model
{
    protected $fillable = [
        'ecommerce_product_id',
        'reference_id',
        'customer_name',
        'quantity',
        'channel',
        'reason',
        'unit_price',
        'total_price',
        'unit_cogs',
        'unit_liquid_cost',
        'unit_bottle_cost',
        'unit_label_cost',
        'unit_pump_cost',
        'unit_outer_box_cost',
        'shipping_cost',
        'tax_amount',
        'meta_ad_allocation',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'unit_price' => 'decimal:2',
        'total_price' => 'decimal:2',
        'unit_cogs' => 'decimal:2',
        'unit_liquid_cost' => 'decimal:2',
        'unit_bottle_cost' => 'decimal:2',
        'unit_label_cost' => 'decimal:2',
        'unit_pump_cost' => 'decimal:2',
        'unit_outer_box_cost' => 'decimal:2',
        'shipping_cost' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'meta_ad_allocation' => 'decimal:2',
    ];

    public function product()
    {
        return $this->belongsTo(EcommerceProduct::class, 'ecommerce_product_id');
    }
}
