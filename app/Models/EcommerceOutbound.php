<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EcommerceOutbound extends Model
{
    protected $fillable = [
        'ecommerce_product_id',
        'customer_name',
        'contact_number',
        'quantity',
        'price',
        'reason',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'price' => 'decimal:2',
    ];

    public function product()
    {
        return $this->belongsTo(EcommerceProduct::class, 'ecommerce_product_id');
    }
}
