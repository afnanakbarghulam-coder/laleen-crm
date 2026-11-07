<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use RuntimeException;

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

    public const PAKISTAN_CHANNELS = [
        'Shopify (Pakistan)',
        'Organic (Pakistan)',
        'Damage/Expiry (Pakistan)',
    ];

    public const QATAR_CHANNELS = [
        'Salon (Old Airport)',
        'Salon (Wakrah)',
        'Organic (Qatar)',
        'Backbar Use (Qatar)',
        'Damage/Expiry (Qatar)',
    ];

    public const PAKISTAN_REVENUE_CHANNELS = [
        'Shopify (Pakistan)',
        'Organic (Pakistan)',
    ];

    public const QATAR_REVENUE_CHANNELS = [
        'Salon (Old Airport)',
        'Salon (Wakrah)',
        'Organic (Qatar)',
    ];

    public const BACKBAR_CHANNELS = [
        'Backbar Use (Qatar)',
    ];

    public const SALON_RETAIL_CHANNELS = [
        'Salon (Old Airport)',
        'Salon (Wakrah)',
    ];

    public const ORDER_STATUSES = [
        'pending',
        'shipped',
        'delivered',
        'cancelled',
        'rto',
    ];

    public const ACTIVE_ORDER_STATUSES = [
        'pending',
        'shipped',
        'delivered',
    ];

    public const INACTIVE_ORDER_STATUSES = [
        'cancelled',
        'rto',
    ];

    public const PAYMENT_STATUSES = [
        'unpaid_cod',
        'paid',
        'refunded',
    ];

    public const CHANNEL_GROUPS = [
        'Pakistan' => self::PAKISTAN_CHANNELS,
        'Qatar' => self::QATAR_CHANNELS,
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
        'shopify_order_id',
        'shopify_line_item_id',
        'reference_id',
        'order_group_id',
        'customer_name',
        'customer_phone',
        'quantity',
        'channel',
        'order_status',
        'payment_status',
        'stock_deducted',
        'is_ai_upsell',
        'cod_confirmed_at',
        'reason',
        'delivery_notes',
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
        'stock_deducted' => 'boolean',
        'is_ai_upsell' => 'boolean',
        'cod_confirmed_at' => 'datetime',
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

    public static function normalizeRegion(?string $region): string
    {
        return in_array(strtolower(trim((string) $region)), ['qa', 'qar', 'qatar'], true) ? 'qa' : 'pk';
    }

    public static function currencyForRegion(?string $region): string
    {
        return self::normalizeRegion($region) === 'qa' ? 'QAR' : 'PKR';
    }

    public static function channelsForRegion(?string $region): array
    {
        return self::normalizeRegion($region) === 'qa' ? self::QATAR_CHANNELS : self::PAKISTAN_CHANNELS;
    }

    public static function revenueChannelsForRegion(?string $region): array
    {
        return self::normalizeRegion($region) === 'qa' ? self::QATAR_REVENUE_CHANNELS : self::PAKISTAN_REVENUE_CHANNELS;
    }

    public static function currencyForChannel(?string $channel): string
    {
        return in_array((string) $channel, self::QATAR_CHANNELS, true) ? 'QAR' : 'PKR';
    }

    public static function regionForChannel(?string $channel): string
    {
        return in_array((string) $channel, self::QATAR_CHANNELS, true) ? 'qa' : 'pk';
    }

    public function getRegionAttribute(): string
    {
        return self::regionForChannel($this->channel);
    }

    public static function isRevenueChannel(?string $channel): bool
    {
        return !in_array((string) $channel, self::NON_REVENUE_CHANNELS, true);
    }

    public function scopeForRegion(Builder $query, ?string $region): Builder
    {
        return $query->whereIn('channel', self::channelsForRegion($region));
    }

    public function scopeRevenueSale(Builder $query): Builder
    {
        return $query->whereIn('channel', self::REVENUE_CHANNELS);
    }

    /**
     * Realized Revenue: ONLY revenue-channel orders that are paid (or delivered)
     * and NOT cancelled or RTO.
     */
    public function scopeRealizedRevenue(Builder $query): Builder
    {
        return $query
            ->whereIn('channel', self::REVENUE_CHANNELS)
            ->whereNotIn('order_status', self::INACTIVE_ORDER_STATUSES)
            ->where(function (Builder $q) {
                $q->where('payment_status', 'paid')
                    ->orWhere('order_status', 'delivered');
            });
    }

    /**
     * Pending COD / Pipeline Revenue: revenue-channel orders in pending or shipped
     * state with unpaid_cod payment status.
     */
    public function scopePendingCodPipeline(Builder $query): Builder
    {
        return $query
            ->whereIn('channel', self::REVENUE_CHANNELS)
            ->whereIn('order_status', ['pending', 'shipped'])
            ->where('payment_status', 'unpaid_cod');
    }

    public function isRealizedRevenue(): bool
    {
        return in_array($this->channel, self::REVENUE_CHANNELS, true)
            && !in_array($this->order_status, self::INACTIVE_ORDER_STATUSES, true)
            && ($this->payment_status === 'paid' || $this->order_status === 'delivered');
    }

    public function isPendingCod(): bool
    {
        return in_array($this->channel, self::REVENUE_CHANNELS, true)
            && in_array($this->order_status, ['pending', 'shipped'], true)
            && $this->payment_status === 'unpaid_cod';
    }

    public function resolvedOrderGroupKey(): string
    {
        if (!empty($this->order_group_id)) {
            return (string) $this->order_group_id;
        }
        if (!empty($this->reference_id)) {
            return 'REF-' . $this->reference_id;
        }
        if (!empty($this->ecommerce_bundle_id)) {
            return 'BUNDLE-' . $this->ecommerce_bundle_id . '-' . $this->created_at;
        }

        return 'LEGACY-' . $this->id;
    }

    /**
     * Count distinct atomic orders in a collection of EcommerceSale models.
     */
    public static function countDistinctOrders($salesCollection): int
    {
        return collect($salesCollection)
            ->map(fn (self $s) => $s->resolvedOrderGroupKey())
            ->unique()
            ->count();
    }

    /**
     * Transition all rows sharing an order_group_id (or single sale fallback) to a new
     * order_status & payment_status, handling stock restoration or re-deduction and
     * customer LTV recalculation atomically.
     */
    public static function transitionOrderGroup(
        string $orderGroupId,
        string $newOrderStatus,
        ?string $newPaymentStatus = null
    ): int {
        return DB::transaction(function () use ($orderGroupId, $newOrderStatus, $newPaymentStatus) {
            $sales = self::lockForUpdate()
                ->where('order_group_id', $orderGroupId)
                ->get();

            if ($sales->isEmpty() && str_starts_with($orderGroupId, 'LEGACY-')) {
                $legacyId = (int) substr($orderGroupId, 7);
                $sales = self::lockForUpdate()->where('id', $legacyId)->get();
            }

            if ($sales->isEmpty()) {
                return 0;
            }

            $isTargetInactive = in_array($newOrderStatus, self::INACTIVE_ORDER_STATUSES, true);

            if ($newOrderStatus === 'delivered') {
                $resolvedPaymentStatus = 'paid';
            } elseif ($isTargetInactive) {
                $resolvedPaymentStatus = $newPaymentStatus ?? 'refunded';
            } else {
                $resolvedPaymentStatus = $newPaymentStatus ?? ($sales->first()->payment_status ?: 'unpaid_cod');
            }

            $affectedCustomerIds = [];

            foreach ($sales as $sale) {
                $isPakistan = str_contains((string) $sale->channel, 'Pakistan');
                $stockField = $isPakistan ? 'stock_pakistan' : 'stock_qatar';
                $soldField = $isPakistan ? 'sold_pakistan' : 'sold_qatar';
                $locationName = $isPakistan ? 'Pakistan' : 'Qatar';

                $product = EcommerceProduct::lockForUpdate()->find($sale->ecommerce_product_id);

                if ($isTargetInactive && $sale->stock_deducted) {
                    if ($product) {
                        $product->{$stockField} = (float) $product->{$stockField} + (int) $sale->quantity;
                        $product->{$soldField} = max(0, (float) $product->{$soldField} - (int) $sale->quantity);
                        $product->save();
                    }
                    $sale->stock_deducted = false;
                } elseif (!$isTargetInactive && !$sale->stock_deducted) {
                    if ($product) {
                        if ((float) $product->{$stockField} < (int) $sale->quantity) {
                            throw new RuntimeException(
                                "Cannot reactivate order: insufficient stock in {$locationName} for {$product->name}. "
                                . "Required: {$sale->quantity}, Available: " . number_format((float) $product->{$stockField}, 0) . '.'
                            );
                        }
                        $product->{$stockField} = (float) $product->{$stockField} - (int) $sale->quantity;
                        $product->{$soldField} = (float) $product->{$soldField} + (int) $sale->quantity;
                        $product->save();
                    }
                    $sale->stock_deducted = true;
                }

                $sale->order_status = $newOrderStatus;
                $sale->payment_status = $resolvedPaymentStatus;
                $sale->save();

                if ($sale->ecommerce_customer_id) {
                    $affectedCustomerIds[$sale->ecommerce_customer_id] = $sale->ecommerce_customer_id;
                }
            }

            foreach ($affectedCustomerIds as $customerId) {
                $customer = EcommerceCustomer::lockForUpdate()->find($customerId);
                $customer?->recalculateLtv();
            }

            return $sales->count();
        });
    }

    /** Digits only, for building a wa.me deep link. */
    public function whatsappNumber(): ?string
    {
        $digits = EcommerceCustomer::normalizePhone($this->customer_phone);

        return $digits !== '' ? $digits : null;
    }
}
