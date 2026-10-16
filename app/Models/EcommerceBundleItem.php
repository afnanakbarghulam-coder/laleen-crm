<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EcommerceBundleItem extends Model
{
    protected $fillable = [
        'bundle_id',
        'ecommerce_product_id',
        'quantity',
    ];

    protected $casts = [
        'quantity' => 'integer',
    ];

    public function bundle()
    {
        return $this->belongsTo(EcommerceBundle::class, 'bundle_id');
    }

    public function product()
    {
        return $this->belongsTo(EcommerceProduct::class, 'ecommerce_product_id');
    }

    /**
     * Price this component sells for on its own — the saved pricing model is
     * the source of truth, with the product's own selling_price as fallback.
     */
    public function standaloneUnitPrice(): float
    {
        $product = $this->product;

        if (!$product) {
            return 0.0;
        }

        return (float) ($product->pricingModel?->selling_price ?? $product->selling_price ?? 0);
    }
}
