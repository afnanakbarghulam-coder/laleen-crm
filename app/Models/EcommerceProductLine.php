<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EcommerceProductLine extends Model
{
    protected $fillable = [
        'name',
    ];

    public function rawMaterials()
    {
        return $this->hasMany(EcommerceRawMaterial::class, 'product_line_id');
    }

    public function products()
    {
        return $this->hasMany(EcommerceProduct::class, 'product_line_id');
    }
}
