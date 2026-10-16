<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EcommerceBundle extends Model
{
    protected $fillable = [
        'name',
        'bundle_price',
    ];

    protected $casts = [
        'bundle_price' => 'decimal:2',
    ];

    public function items()
    {
        return $this->hasMany(EcommerceBundleItem::class, 'bundle_id');
    }

    public function sales()
    {
        return $this->hasMany(EcommerceSale::class, 'ecommerce_bundle_id');
    }

    /**
     * What the components would cost the customer bought separately. Uses the
     * saved pricing model's selling price, falling back to the product's own
     * selling_price when no pricing model exists yet.
     */
    public function standaloneValue(): float
    {
        return (float) $this->items->sum(function (EcommerceBundleItem $item) {
            return $item->standaloneUnitPrice() * (int) $item->quantity;
        });
    }

    /** Real BOM cost of every unit inside one bundle. */
    public function bundleUnitCogs(): float
    {
        return (float) $this->items->sum(function (EcommerceBundleItem $item) {
            return ($item->product?->pricingModel?->bomUnitCost() ?? 0.0) * (int) $item->quantity;
        });
    }

    public function savingsVersusStandalone(): float
    {
        return $this->standaloneValue() - (float) $this->bundle_price;
    }

    public function grossProfit(): float
    {
        return (float) $this->bundle_price - $this->bundleUnitCogs();
    }

    public function grossMarginPercent(): float
    {
        $price = (float) $this->bundle_price;

        return $price > 0 ? ($this->grossProfit() / $price) * 100 : 0.0;
    }

    /**
     * Splits one bundle's price across its children in proportion to what each
     * child is worth standalone, so revenue still lands on the right product
     * rows. Returns [bundle_item_id => allocated_revenue].
     *
     * Rounding remainder goes to the last line so the allocation always sums
     * back to exactly $quantity x bundle_price — Net Profit must not drift.
     */
    public function allocateRevenue(int $quantity = 1): array
    {
        $totalRevenue = round((float) $this->bundle_price * $quantity, 2);
        $items = $this->items;

        if ($items->isEmpty() || $totalRevenue <= 0) {
            return $items->mapWithKeys(fn ($item) => [$item->id => 0.0])->all();
        }

        $weights = $items->mapWithKeys(fn (EcommerceBundleItem $item) => [
            $item->id => $item->standaloneUnitPrice() * (int) $item->quantity,
        ]);

        // Value-weighting only works when EVERY component has a standalone
        // price. If even one is unpriced it would be weighted at zero and
        // receive no revenue at all — leaving its sale rows showing real COGS
        // against zero income, i.e. a permanent fake loss on that product.
        // Fall back to a single coherent basis (unit count) for the whole
        // bundle instead of mixing a priced and unpriced basis.
        if ($weights->contains(fn ($weight) => $weight <= 0)) {
            $weights = $items->mapWithKeys(fn (EcommerceBundleItem $item) => [$item->id => (int) $item->quantity]);
        }

        $totalWeight = (float) $weights->sum();
        $allocated = [];
        $running = 0.0;
        $lastKey = $items->last()->id;

        foreach ($items as $item) {
            if ($item->id === $lastKey) {
                $allocated[$item->id] = round($totalRevenue - $running, 2);
                continue;
            }

            $share = round($totalRevenue * ((float) $weights[$item->id] / $totalWeight), 2);
            $allocated[$item->id] = $share;
            $running = round($running + $share, 2);
        }

        return $allocated;
    }
}
