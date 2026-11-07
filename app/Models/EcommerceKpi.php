<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class EcommerceKpi extends Model
{
    protected $table = 'ecommerce_kpis';

    /**
     * Media Efficiency Ratio below this is treated as unhealthy — at a ~30%
     * blended margin a business roughly breaks even around 3x.
     */
    public const MER_TARGET = 3.0;

    /** Below this share of orders coming from returning buyers is a retention problem. */
    public const REPEAT_RATE_TARGET = 20.0;

    protected $fillable = [
        'date',
        'currency',
        'total_revenue',
        'total_ad_spend',
        'total_orders',
        'new_customers',
        'repeat_customers',
    ];

    protected $casts = [
        'date' => 'date',
        'total_revenue' => 'decimal:2',
        'total_ad_spend' => 'decimal:2',
        'total_orders' => 'integer',
        'new_customers' => 'integer',
        'repeat_customers' => 'integer',
    ];

    public function scopeForRegion(Builder $query, ?string $region): Builder
    {
        $currency = EcommerceSale::currencyForRegion($region);

        return $query->where('currency', $currency);
    }

    /**
     * Blended metrics for a set of daily rows.
     */
    public static function summarize(Collection $rows, ?Collection $newCustomerSales = null): array
    {
        $revenue = (float) $rows->sum('total_revenue');
        $adSpend = (float) $rows->sum('total_ad_spend');
        $orders = (int) $rows->sum('total_orders');
        $newCustomers = (int) $rows->sum('new_customers');
        $repeatCustomers = (int) $rows->sum('repeat_customers');

        $mer = $adSpend > 0 ? $revenue / $adSpend : null;
        $cac = $newCustomers > 0 ? $adSpend / $newCustomers : null;
        $aov = $orders > 0 ? $revenue / $orders : null;
        $repeatRate = $orders > 0 ? ($repeatCustomers / $orders) * 100 : null;

        $salesCollection = $newCustomerSales ?? collect();
        if ($salesCollection->isNotEmpty()) {
            $newCustomerRevenue = (float) $salesCollection->sum('total_price');
            $newCustomerCogs = (float) $salesCollection->sum(
                fn ($sale) => (float) $sale->quantity * (float) $sale->unit_cogs
            );
            $newCustomerShipping = (float) $salesCollection->sum('shipping_cost');
        } else {
            $newCustomerShare = $orders > 0
                ? min(1.0, $newCustomers / $orders)
                : ($newCustomers > 0 ? 1.0 : 0.0);
            $newCustomerRevenue = $revenue * $newCustomerShare;
            $newCustomerCogs = 0.0;
            $newCustomerShipping = 0.0;
        }

        $day0NetTotal = null;
        $day0PerOrderMargin = null;
        $day0Healthy = null;

        if ($newCustomers > 0) {
            $day0NetTotal = $newCustomerRevenue - ($newCustomerCogs + $newCustomerShipping + $adSpend);
            $day0PerOrderMargin = $day0NetTotal / $newCustomers;
            if ($day0PerOrderMargin > 0) {
                $day0Healthy = true;
            } elseif ($day0PerOrderMargin < 0) {
                $day0Healthy = false;
            }
        }

        return [
            'has_data' => $rows->isNotEmpty(),
            'day_count' => $rows->count(),
            'total_revenue' => $revenue,
            'total_ad_spend' => $adSpend,
            'total_orders' => $orders,
            'new_customers' => $newCustomers,
            'repeat_customers' => $repeatCustomers,

            'mer' => $mer,
            'cac' => $cac,
            'aov' => $aov,
            'repeat_rate' => $repeatRate,

            'new_customer_revenue' => $newCustomerRevenue,
            'new_customer_cogs' => $newCustomerCogs,
            'new_customer_shipping' => $newCustomerShipping,
            'day0_net_total' => $day0NetTotal,
            'day0_net_contribution' => $day0PerOrderMargin,
            'day0_healthy' => $day0Healthy,

            'mer_healthy' => $mer === null ? null : $mer >= self::MER_TARGET,
            'cac_healthy' => ($cac === null || $aov === null) ? null : $cac < $aov,
            'aov_healthy' => null,
            'repeat_rate_healthy' => $repeatRate === null ? null : $repeatRate >= self::REPEAT_RATE_TARGET,
        ];
    }
}
