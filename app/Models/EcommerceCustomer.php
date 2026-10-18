<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EcommerceCustomer extends Model
{
    use HasFactory;
    protected $fillable = [
        'name',
        'phone',
        'email',
        'total_spend',
        'total_orders',
        'last_purchase_at',
    ];

    protected $casts = [
        'total_spend' => 'decimal:2',
        'total_orders' => 'integer',
        'last_purchase_at' => 'datetime',
    ];

    public function sales(): HasMany
    {
        return $this->hasMany(EcommerceSale::class, 'ecommerce_customer_id');
    }

    /**
     * Name of the most recently purchased product for WhatsApp outreach templates.
     */
    public function getLatestProductAttribute(): string
    {
        if ($this->relationLoaded('sales')) {
            $latestSale = $this->sales
                ->sortByDesc(fn ($s) => optional($s->created_at)->timestamp ?? 0)
                ->first(fn ($s) => !empty($s->product?->name));

            if ($latestSale && $latestSale->product?->name) {
                return $latestSale->product->name;
            }
        } else {
            $latestSale = $this->sales()
                ->with('product')
                ->orderByDesc('created_at')
                ->orderByDesc('id')
                ->first();

            if ($latestSale && $latestSale->product?->name) {
                return $latestSale->product->name;
            }
        }

        return 'Laleen Hair Care';
    }

    /**
     * Normalize any phone string to digits only.
     */
    public static function normalizePhone(?string $phone): string
    {
        return preg_replace('/\D+/', '', (string) $phone);
    }
}
