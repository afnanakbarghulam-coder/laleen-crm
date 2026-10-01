<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductionRunMaterial extends Model
{
    protected $fillable = [
        'production_run_id',
        'ecommerce_raw_material_id',
        'quantity_used',
    ];

    protected $casts = [
        'quantity_used' => 'decimal:2',
    ];

    public function productionRun()
    {
        return $this->belongsTo(ProductionRun::class);
    }

    public function rawMaterial()
    {
        return $this->belongsTo(EcommerceRawMaterial::class, 'ecommerce_raw_material_id');
    }
}
