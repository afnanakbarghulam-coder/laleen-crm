<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EcommerceProductLine extends Model
{
    public const SHAMPOO = 'Shampoo';
    public const HAIR_OIL = 'Hair Oil';

    /**
     * Component types that make up the Bill of Materials for each sellable
     * product line. Single source of truth for which raw material component
     * types may be logged against a product line (expense restock dropdown)
     * and which components a production run must have in stock (BOM lock).
     */
    public const COMPONENT_TYPES_BY_PRODUCT_LINE = [
        self::SHAMPOO => ['Bottle/Jar', 'Pump/Cap', 'Label', 'Liquid Base', 'Outer Box'],
        self::HAIR_OIL => ['Bottle/Jar', 'Label', 'Outer Box'],
    ];

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
