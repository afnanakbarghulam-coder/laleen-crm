<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EcommercePricingModel extends Model
{
    protected $fillable = [
        'ecommerce_product_id',
        'liquid_cost',
        'bottle_cost',
        'label_cost',
        'pump_cost',
        'box_cost',
        'unit_cogs_qar',
        'selling_price',
    ];

    protected $casts = [
        'liquid_cost' => 'decimal:2',
        'bottle_cost' => 'decimal:2',
        'label_cost' => 'decimal:2',
        'pump_cost' => 'decimal:2',
        'box_cost' => 'decimal:2',
        'unit_cogs_qar' => 'decimal:2',
        'selling_price' => 'decimal:2',
    ];

    public function product()
    {
        return $this->belongsTo(EcommerceProduct::class, 'ecommerce_product_id');
    }

    public static function pkrPerQarRate(): float
    {
        return max(1.0, (float) config('services.ecommerce.pkr_per_qar', 76.5));
    }

    /**
     * The product's BOM cost per unit in PKR (Liquid + Bottle + Label + Box + Pump).
     * Single source of truth for Pakistan manufacturing BOM cost.
     */
    public function bomUnitCost(): float
    {
        return (float) $this->liquid_cost
            + (float) $this->bottle_cost
            + (float) $this->label_cost
            + (float) $this->box_cost
            + (float) $this->pump_cost;
    }

    /**
     * Resolve unit COGS strictly in the target region's currency ('PKR' for 'pk', 'QAR' for 'qa').
     * Never returns raw PKR BOM cost on a Qatar ('qa') order.
     */
    public function unitCogsForRegion(string $region = 'pk'): float
    {
        $normalized = EcommerceSale::normalizeRegion($region);
        $bomPkr = $this->bomUnitCost();

        if ($normalized === 'qa') {
            if ($this->unit_cogs_qar !== null && (float) $this->unit_cogs_qar > 0) {
                return round((float) $this->unit_cogs_qar, 2);
            }

            return $bomPkr > 0
                ? round($bomPkr / self::pkrPerQarRate(), 2)
                : 0.0;
        }

        return round($bomPkr, 2);
    }

    /**
     * Resolve unit COGS and individual BOM component costs scaled to the target region's currency.
     *
     * @return array{
     *     unit_cogs: float,
     *     unit_liquid_cost: float,
     *     unit_bottle_cost: float,
     *     unit_label_cost: float,
     *     unit_pump_cost: float,
     *     unit_outer_box_cost: float
     * }
     */
    public function componentCostsForRegion(string $region = 'pk'): array
    {
        $normalized = EcommerceSale::normalizeRegion($region);
        $bomPkr = $this->bomUnitCost();

        if ($normalized !== 'qa') {
            return [
                'unit_cogs' => round($bomPkr, 2),
                'unit_liquid_cost' => round((float) ($this->liquid_cost ?? 0), 2),
                'unit_bottle_cost' => round((float) ($this->bottle_cost ?? 0), 2),
                'unit_label_cost' => round((float) ($this->label_cost ?? 0), 2),
                'unit_pump_cost' => round((float) ($this->pump_cost ?? 0), 2),
                'unit_outer_box_cost' => round((float) ($this->box_cost ?? 0), 2),
            ];
        }

        $unitCogsQar = $this->unitCogsForRegion('qa');
        $scale = $bomPkr > 0
            ? ($unitCogsQar / $bomPkr)
            : (1.0 / self::pkrPerQarRate());

        return [
            'unit_cogs' => $unitCogsQar,
            'unit_liquid_cost' => round((float) ($this->liquid_cost ?? 0) * $scale, 2),
            'unit_bottle_cost' => round((float) ($this->bottle_cost ?? 0) * $scale, 2),
            'unit_label_cost' => round((float) ($this->label_cost ?? 0) * $scale, 2),
            'unit_pump_cost' => round((float) ($this->pump_cost ?? 0) * $scale, 2),
            'unit_outer_box_cost' => round((float) ($this->box_cost ?? 0) * $scale, 2),
        ];
    }
}
