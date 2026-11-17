<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProductRdSupplier extends Model
{
    protected $fillable = [
        'name',
        'contact_person',
        'phone_or_whatsapp',
        'country',
    ];

    public function samples(): HasMany
    {
        return $this->hasMany(ProductRdSample::class, 'product_rd_supplier_id');
    }

    public function wonItems(): HasMany
    {
        return $this->hasMany(ProductRdItem::class, 'winning_supplier_id');
    }
}
