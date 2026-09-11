<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SaleItem extends Model
{
    protected $fillable = [
        'sale_id',
        'type',
        'product_id',
        'staff_id',
        'name',
        'price',
        'original_price',
        'discount_amount',
        'quantity',
        'total',
    ];

    public function sale()
    {
        return $this->belongsTo(Sale::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function staff()
    {
        return $this->belongsTo(Staff::class);
    }
}
