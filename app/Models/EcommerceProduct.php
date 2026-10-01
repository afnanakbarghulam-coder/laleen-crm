<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EcommerceProduct extends Model
{
    protected $fillable = [
        'name',
        'sku',
        'current_stock',
        'selling_price',
        'liquid_cost_per_ml',
        'volume_ml',
        'bottle_cost',
        'pump_cost',
        'label_cost',
        'box_cost',
        'labor_cost',
        'shipping_cost',
        'payment_gateway_fee_percent',
    ];

    protected $casts = [
        'current_stock' => 'decimal:2',
        'selling_price' => 'decimal:2',
        'liquid_cost_per_ml' => 'decimal:4',
        'volume_ml' => 'decimal:2',
        'bottle_cost' => 'decimal:2',
        'pump_cost' => 'decimal:2',
        'label_cost' => 'decimal:2',
        'box_cost' => 'decimal:2',
        'labor_cost' => 'decimal:2',
        'shipping_cost' => 'decimal:2',
        'payment_gateway_fee_percent' => 'decimal:2',
    ];

    protected $appends = [
        'liquid_cost',
        'total_cogs',
        'payment_gateway_fee',
        'gross_profit',
        'gross_margin_percent',
        'breakeven_cac',
    ];

    /**
     * Bill-of-materials recipe: each raw material consumed per unit produced,
     * via the quantity_required pivot column.
     */
    public function rawMaterials()
    {
        return $this->belongsToMany(EcommerceRawMaterial::class, 'ecommerce_product_raw_material')
            ->withPivot('quantity_required')
            ->withTimestamps();
    }

    public function getLiquidCostAttribute(): float
    {
        return (float) $this->liquid_cost_per_ml * (float) $this->volume_ml;
    }

    public function getTotalCogsAttribute(): float
    {
        return $this->liquid_cost
            + (float) $this->bottle_cost
            + (float) $this->pump_cost
            + (float) $this->label_cost
            + (float) $this->box_cost
            + (float) $this->labor_cost
            + (float) $this->shipping_cost;
    }

    public function getPaymentGatewayFeeAttribute(): float
    {
        return (float) $this->selling_price * ((float) $this->payment_gateway_fee_percent / 100);
    }

    public function getGrossProfitAttribute(): float
    {
        return (float) $this->selling_price - $this->total_cogs - $this->payment_gateway_fee;
    }

    public function getGrossMarginPercentAttribute(): float
    {
        return (float) $this->selling_price > 0
            ? ($this->gross_profit / (float) $this->selling_price) * 100
            : 0.0;
    }

    /**
     * Maximum customer acquisition cost the unit economics can absorb and
     * still break even on the first purchase.
     */
    public function getBreakevenCacAttribute(): float
    {
        return $this->gross_profit;
    }
}
