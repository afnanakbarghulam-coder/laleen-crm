<?php

namespace App\Models;

use Carbon\Carbon;
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
        'last_retention_contacted_at',
        'last_retention_stage',
    ];

    protected $casts = [
        'total_spend' => 'decimal:2',
        'total_orders' => 'integer',
        'last_purchase_at' => 'datetime',
        'last_retention_contacted_at' => 'datetime',
    ];

    public function sales(): HasMany
    {
        return $this->hasMany(EcommerceSale::class, 'ecommerce_customer_id');
    }

    public function whatsappThreads(): HasMany
    {
        return $this->hasMany(EcommerceWhatsappThread::class, 'ecommerce_customer_id');
    }

    /**
     * Resolve customer's total order count (`orders_count` alias for lifecycle memory).
     */
    public function getOrdersCountAttribute(): int
    {
        if (array_key_exists('orders_count', $this->attributes) && $this->attributes['orders_count'] !== null) {
            return (int) $this->attributes['orders_count'];
        }

        $realizedCount = (int) ($this->total_orders ?? 0);
        if ($realizedCount > 0) {
            return $realizedCount;
        }

        if ($this->relationLoaded('sales')) {
            return EcommerceSale::countDistinctOrders(
                $this->sales->whereNotIn('order_status', EcommerceSale::INACTIVE_ORDER_STATUSES)
            );
        }

        if ($this->id) {
            $activeSales = $this->sales()
                ->whereNotIn('order_status', EcommerceSale::INACTIVE_ORDER_STATUSES)
                ->get(['id', 'order_group_id', 'reference_id', 'ecommerce_bundle_id', 'created_at']);

            return EcommerceSale::countDistinctOrders($activeSales);
        }

        return 0;
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
     * Check whether customer was contacted for retention within the given window.
     */
    public function wasRecentlyContacted(int $withinDays = 7): bool
    {
        if (!$this->last_retention_contacted_at) {
            return false;
        }

        return $this->last_retention_contacted_at->greaterThanOrEqualTo(now()->subDays($withinDays));
    }

    /**
     * Recalculate customer's realized LTV (total_spend, total_orders, last_purchase_at)
     * strictly from EcommerceSale::realizedRevenue() rows.
     */
    public function recalculateLtv(): void
    {
        $realizedSales = EcommerceSale::where('ecommerce_customer_id', $this->id)
            ->realizedRevenue()
            ->get(['id', 'order_group_id', 'reference_id', 'ecommerce_bundle_id', 'created_at', 'total_price']);

        $this->total_spend = round((float) $realizedSales->sum('total_price'), 2);
        $this->total_orders = EcommerceSale::countDistinctOrders($realizedSales);

        $latestSaleAt = $realizedSales->max('created_at');
        $this->last_purchase_at = $latestSaleAt ? Carbon::parse($latestSaleAt) : null;
        $this->save();
    }

    /**
     * Central phone normalization used across Shopify webhooks, manual sales, and customer lookups:
     * - Strips all non-digits.
     * - Strips leading '00'.
     * - Converts 11-digit Pakistan local format '03XXXXXXXXX' -> '923XXXXXXXXX'.
     * - Converts 10-digit Pakistan subscriber '3XXXXXXXXX' -> '923XXXXXXXXX'.
     * - Converts 8-digit Qatar local format starting with 3, 5, 6, or 7 -> '974XXXXXXXX'.
     * - If an explicit $defaultCountryCode is provided and the number has a leading '0', strips '0' and prepends country code.
     */
    public static function normalizePhone(?string $rawPhone, ?string $defaultCountryCode = '92'): string
    {
        $digits = preg_replace('/\D+/', '', (string) $rawPhone);
        if ($digits === '') {
            return '';
        }

        if (str_starts_with($digits, '00')) {
            $digits = substr($digits, 2);
        }

        // 11-digit Pakistan mobile format starting with 03 (e.g., 03001234567 -> 923001234567)
        if (strlen($digits) === 11 && str_starts_with($digits, '03')) {
            return '92' . substr($digits, 1);
        }

        // 8-digit Qatar local mobile/landline format starting with 3, 5, 6, or 7 (e.g., 55123456 -> 97455123456)
        if (strlen($digits) === 8 && in_array($digits[0], ['3', '5', '6', '7'], true)) {
            return '974' . $digits;
        }

        // 10-digit Pakistan subscriber format starting with 3 (e.g., 3001234567 -> 923001234567)
        if (strlen($digits) === 10 && str_starts_with($digits, '3')) {
            return '92' . $digits;
        }

        $codeDigits = preg_replace('/\D+/', '', (string) $defaultCountryCode);
        if (str_starts_with($codeDigits, '00')) {
            $codeDigits = substr($codeDigits, 2);
        }

        if ($codeDigits !== '') {
            if (str_starts_with($digits, $codeDigits) && strlen($digits) >= strlen($codeDigits) + 7) {
                return $digits;
            }
            if (str_starts_with($digits, '0')) {
                return $codeDigits . ltrim($digits, '0');
            }
            if (strlen($digits) <= 10 && !str_starts_with($digits, '92') && !str_starts_with($digits, '974')) {
                return $codeDigits . $digits;
            }
        }

        return $digits;
    }
}
