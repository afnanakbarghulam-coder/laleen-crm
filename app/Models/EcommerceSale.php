<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EcommerceSale extends Model
{
    use HasFactory;

    public const REVENUE_CHANNELS = [
        'Shopify (Pakistan)',
        'Organic (Pakistan)',
        'Salon (Old Airport)',
        'Salon (Wakrah)',
        'Organic (Qatar)',
    ];

    public const NON_REVENUE_CHANNELS = [
        'Backbar Use (Qatar)',
        'Damage/Expiry (Pakistan)',
        'Damage/Expiry (Qatar)',
    ];

    public const CHANNELS = [
        'Shopify (Pakistan)',
        'Organic (Pakistan)',
        'Salon (Old Airport)',
        'Salon (Wakrah)',
        'Organic (Qatar)',
        'Backbar Use (Qatar)',
        'Damage/Expiry (Pakistan)',
        'Damage/Expiry (Qatar)',
    ];

    public const BACKBAR_CHANNELS = [
        'Backbar Use (Qatar)',
    ];

    public const SALON_RETAIL_CHANNELS = [
        'Salon (Old Airport)',
        'Salon (Wakrah)',
    ];

    public const CHANNEL_GROUPS = [
        'Pakistan' => [
            'Shopify (Pakistan)',
            'Organic (Pakistan)',
            'Damage/Expiry (Pakistan)',
        ],
        'Qatar' => [
            'Salon (Old Airport)',
            'Salon (Wakrah)',
            'Organic (Qatar)',
            'Backbar Use (Qatar)',
            'Damage/Expiry (Qatar)',
        ],
    ];

    public const CHANNEL_BADGE_CLASSES = [
        'Shopify (Pakistan)' => 'ec-channel-shopify',
        'Organic (Pakistan)' => 'ec-channel-organic',
        'Salon (Old Airport)' => 'ec-channel-salon',
        'Salon (Wakrah)' => 'ec-channel-salon',
        'Organic (Qatar)' => 'ec-channel-organic',
        'Backbar Use (Qatar)' => 'ec-channel-backbar',
        'Damage/Expiry (Pakistan)' => 'ec-channel-damage',
        'Damage/Expiry (Qatar)' => 'ec-channel-damage',
    ];

    protected $fillable = [
        'ecommerce_product_id',
        'ecommerce_bundle_id',
        'ecommerce_customer_id',
        'reference_id',
        'customer_name',
        'customer_phone',
        'quantity',
        'channel',
        'reason',
        'unit_price',
        'total_price',
        'unit_cogs',
        'unit_liquid_cost',
        'unit_bottle_cost',
        'unit_label_cost',
        'unit_pump_cost',
        'unit_outer_box_cost',
        'shipping_cost',
        'tax_amount',
        'meta_ad_allocation',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'unit_price' => 'decimal:2',
        'total_price' => 'decimal:2',
        'unit_cogs' => 'decimal:2',
        'unit_liquid_cost' => 'decimal:2',
        'unit_bottle_cost' => 'decimal:2',
        'unit_label_cost' => 'decimal:2',
        'unit_pump_cost' => 'decimal:2',
        'unit_outer_box_cost' => 'decimal:2',
        'shipping_cost' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'meta_ad_allocation' => 'decimal:2',
    ];

    public function product()
    {
        return $this->belongsTo(EcommerceProduct::class, 'ecommerce_product_id');
    }

    public function bundle()
    {
        return $this->belongsTo(EcommerceBundle::class, 'ecommerce_bundle_id');
    }

    public function customer()
    {
        return $this->belongsTo(EcommerceCustomer::class, 'ecommerce_customer_id');
    }

    public static function isRevenueChannel(?string $channel): bool
    {
        return !in_array((string) $channel, self::NON_REVENUE_CHANNELS, true);
    }

    /** Digits only, for building a wa.me deep link. */
    public function whatsappNumber(): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $this->customer_phone);

        return $digits !== '' ? $digits : null;
    }
}
