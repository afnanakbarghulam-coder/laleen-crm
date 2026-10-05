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
        'selling_price',
    ];

    protected $casts = [
        'liquid_cost' => 'decimal:2',
        'bottle_cost' => 'decimal:2',
        'label_cost' => 'decimal:2',
        'pump_cost' => 'decimal:2',
        'box_cost' => 'decimal:2',
        'selling_price' => 'decimal:2',
    ];

    public function product()
    {
        return $this->belongsTo(EcommerceProduct::class, 'ecommerce_product_id');
    }

    /**
     * The product's BOM cost per unit (Liquid + Bottle + Label + Box + Pump).
     * Single source of truth for this formula — used by Stock Levels'
     * capital valuation and as the COGS snapshot taken at time of sale.
     */
    public function bomUnitCost(): float
    {
        return (float) $this->liquid_cost
            + (float) $this->bottle_cost
            + (float) $this->label_cost
            + (float) $this->box_cost
            + (float) $this->pump_cost;
    }
}
