<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductionRun extends Model
{
    protected $fillable = [
        'ecommerce_product_id',
        'quantity_produced',
        'created_by',
    ];

    protected $casts = [
        'quantity_produced' => 'decimal:2',
    ];

    public function product()
    {
        return $this->belongsTo(EcommerceProduct::class, 'ecommerce_product_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function materials()
    {
        return $this->hasMany(ProductionRunMaterial::class);
    }
}
