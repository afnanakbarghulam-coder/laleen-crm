<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EcommerceSale extends Model
{
    protected $fillable = [
        'ecommerce_product_id',
        'customer_name',
        'quantity',
        'channel',
        'reason',
        'unit_price',
        'total_price',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'unit_price' => 'decimal:2',
        'total_price' => 'decimal:2',
    ];

    public function product()
    {
        return $this->belongsTo(EcommerceProduct::class, 'ecommerce_product_id');
    }
}
