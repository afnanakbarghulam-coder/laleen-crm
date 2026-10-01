<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EcommerceOutbound extends Model
{
    const REASONS = [
        'Retail Sale - Old Airport',
        'Retail Sale - Wakrah',
        'Backbar / Internal Use',
        'Promotional / Giveaway',
        'Damaged / Expired',
    ];

    protected $fillable = [
        'ecommerce_product_id',
        'quantity',
        'reason',
    ];

    protected $casts = [
        'quantity' => 'integer',
    ];

    public function product()
    {
        return $this->belongsTo(EcommerceProduct::class, 'ecommerce_product_id');
    }
}
