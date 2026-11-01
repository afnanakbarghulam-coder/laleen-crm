<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    protected $fillable = [
        'name',
        'sku',
        'price',
        'stock_quantity',
        'low_stock_threshold',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'stock_quantity' => 'integer',
        'low_stock_threshold' => 'integer',
    ];

    public function saleItems()
    {
        return $this->hasMany(SaleItem::class, 'product_id');
    }

    public function upsells()
    {
        return $this->hasMany(AppointmentUpsell::class, 'product_id');
    }

    public function isLowStock(): bool
    {
        return (int) $this->stock_quantity > 0
            && (int) $this->stock_quantity <= (int) ($this->low_stock_threshold ?? 3);
    }

    public function isOutOfStock(): bool
    {
        return (int) $this->stock_quantity <= 0;
    }
}
