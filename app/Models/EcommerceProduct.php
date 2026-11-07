<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EcommerceProduct extends Model
{
    protected $fillable = [
        'name',
        'product_line_id',
        'sku',
        'shopify_url',
        'shopify_variant_id',
        'unit_size',
        'stock_pakistan',
        'stock_qatar',
        'sold_pakistan',
        'sold_qatar',
        'selling_price',
        'liquid_cost_per_ml',
        'volume_ml',
        'bottle_cost',
        'pump_cost',
        'label_cost',
        'box_cost',
        'labor_cost',
        'shipping_cost',
        'payment_gateway_fee_percent',
    ];

    protected $casts = [
        'stock_pakistan' => 'decimal:2',
        'stock_qatar' => 'decimal:2',
        'sold_pakistan' => 'decimal:2',
        'sold_qatar' => 'decimal:2',
        'selling_price' => 'decimal:2',
        'liquid_cost_per_ml' => 'decimal:4',
        'volume_ml' => 'decimal:2',
        'bottle_cost' => 'decimal:2',
        'pump_cost' => 'decimal:2',
        'label_cost' => 'decimal:2',
        'box_cost' => 'decimal:2',
        'labor_cost' => 'decimal:2',
        'shipping_cost' => 'decimal:2',
        'payment_gateway_fee_percent' => 'decimal:2',
    ];

    protected $appends = [
        'base_price',
        'liquid_cost',
        'total_cogs',
        'unit_cogs',
        'payment_gateway_fee',
        'gross_profit',
        'gross_margin_percent',
        'breakeven_cac',
    ];

    public function productionRuns()
    {
        return $this->hasMany(ProductionRun::class, 'ecommerce_product_id');
    }

    public function sales()
    {
        return $this->hasMany(EcommerceSale::class, 'ecommerce_product_id');
    }

    public function pricingModel()
    {
        return $this->hasOne(EcommercePricingModel::class, 'ecommerce_product_id');
    }

    public function bundleItems()
    {
        return $this->hasMany(EcommerceBundleItem::class, 'ecommerce_product_id');
    }

    public function productLine()
    {
        return $this->belongsTo(EcommerceProductLine::class, 'product_line_id');
    }

    public function getBasePriceAttribute(): float
    {
        return $this->resolveBasePrice('PK');
    }

    /**
     * Resolve a strictly positive (> 0) base price for this product.
     * If selling_price / base_price on ecommerce_products is 0 or null, falls back in exact order:
     *   1. $this->pricingModel?->selling_price (if > 0)
     *   2. Most recent positive unit_price in ecommerce_sales for this product
     *   3. Regional default fallback (1200.00 for PK / 65.00 for QA)
     */
    public function resolveBasePrice(?string $region = 'PK'): float
    {
        if (array_key_exists('base_price', $this->attributes) && (float) $this->attributes['base_price'] > 0) {
            return (float) $this->attributes['base_price'];
        }

        if ((float) ($this->selling_price ?? 0) > 0) {
            return (float) $this->selling_price;
        }

        $model = $this->relationLoaded('pricingModel') ? $this->pricingModel : $this->pricingModel()->first();
        if ($model && (float) $model->selling_price > 0) {
            return (float) $model->selling_price;
        }

        if ($this->id) {
            $recentUnitPrice = EcommerceSale::where('ecommerce_product_id', $this->id)
                ->where('unit_price', '>', 0)
                ->latest('id')
                ->value('unit_price');

            if ($recentUnitPrice && (float) $recentUnitPrice > 0) {
                return (float) $recentUnitPrice;
            }
        }

        $isQatar = in_array(strtoupper(trim((string) $region)), ['QA', 'QAR', 'QATAR'], true);

        return $isQatar ? 65.00 : 1200.00;
    }

    /**
     * Build a direct Shopify discount checkout URL or cart permalink for this product.
     */
    public function getShopifyCheckoutUrl(?string $discountCode = null, ?string $storeDomain = null): string
    {
        $rawDomain = trim((string) ($storeDomain ?: (EcommerceAiSetting::current()->shopify_store_domain ?? 'laleenrituals.com')));
        $cleanDomain = preg_replace('#^https?://#i', '', $rawDomain);
        $cleanDomain = rtrim((string) $cleanDomain, '/');
        if ($cleanDomain === '') {
            $cleanDomain = 'laleenrituals.com';
        }

        $code = trim((string) $discountCode);

        // 1. If a Shopify variant ID is set, return a direct cart permalink
        $variantId = trim((string) ($this->shopify_variant_id ?? ''));
        if ($variantId !== '') {
            $cartUrl = "https://{$cleanDomain}/cart/{$variantId}:1";

            return $code !== '' ? ($cartUrl . '?discount=' . urlencode($code)) : $cartUrl;
        }

        // 2. Resolve product path from shopify_url or product name slug
        $customUrl = trim((string) ($this->shopify_url ?? ''));
        if ($customUrl !== '') {
            if (preg_match('#^https?://#i', $customUrl)) {
                $parsedPath = parse_url($customUrl, PHP_URL_PATH) ?: ('/products/' . \Illuminate\Support\Str::slug($this->name));
                $productPath = '/' . ltrim($parsedPath, '/');
            } else {
                $productPath = '/' . ltrim($customUrl, '/');
            }
        } else {
            $slug = \Illuminate\Support\Str::slug($this->name ?: 'laleen-ritual');
            $productPath = "/products/{$slug}";
        }

        if ($code !== '') {
            return "https://{$cleanDomain}/discount/" . urlencode($code) . '?redirect=' . $productPath;
        }

        return "https://{$cleanDomain}{$productPath}";
    }

    public function getLiquidCostAttribute(): float
    {
        return (float) $this->liquid_cost_per_ml * (float) $this->volume_ml;
    }

    public function getTotalCogsAttribute(): float
    {
        return $this->liquid_cost
            + (float) $this->bottle_cost
            + (float) $this->pump_cost
            + (float) $this->label_cost
            + (float) $this->box_cost
            + (float) $this->labor_cost
            + (float) $this->shipping_cost;
    }

    public function getUnitCogsAttribute(): float
    {
        if (array_key_exists('unit_cogs', $this->attributes) && $this->attributes['unit_cogs'] !== null) {
            return (float) $this->attributes['unit_cogs'];
        }

        $model = $this->relationLoaded('pricingModel') ? $this->pricingModel : $this->pricingModel()->first();
        if ($model && $model->bomUnitCost() > 0) {
            return (float) $model->bomUnitCost();
        }

        return (float) $this->total_cogs;
    }

    public function getPaymentGatewayFeeAttribute(): float
    {
        return (float) $this->selling_price * ((float) $this->payment_gateway_fee_percent / 100);
    }

    public function getGrossProfitAttribute(): float
    {
        return (float) $this->selling_price - $this->total_cogs - $this->payment_gateway_fee;
    }

    public function getGrossMarginPercentAttribute(): float
    {
        return (float) $this->selling_price > 0
            ? ($this->gross_profit / (float) $this->selling_price) * 100
            : 0.0;
    }

    /**
     * Maximum customer acquisition cost the unit economics can absorb and
     * still break even on the first purchase.
     */
    public function getBreakevenCacAttribute(): float
    {
        return $this->gross_profit;
    }
}
