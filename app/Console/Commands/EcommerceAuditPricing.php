<?php

namespace App\Console\Commands;

use App\Models\EcommerceProduct;
use Illuminate\Console\Command;

class EcommerceAuditPricing extends Command
{
    protected $signature = 'ecommerce:audit-pricing {--json : Output machine-readable JSON instead of a table}';

    protected $description = 'List ecommerce products missing a real selling price or a real unit COGS baseline.';

    /**
     * Note on schema: there is no base_price or unit_cogs column on
     * ecommerce_products. The canonical selling price and the BOM cost both
     * live on the 1:1 ecommerce_pricing_models row
     * (EcommercePricingModel::bomUnitCost()). ecommerce_sales.unit_cogs is
     * only the snapshot taken at the moment of sale — it is sourced FROM the
     * pricing model, so a product with no pricing model silently sells at
     * PKR 0 COGS and reports a fake ~100% margin.
     */
    public function handle(): int
    {
        $products = EcommerceProduct::with('pricingModel')->orderBy('name')->get();

        $flagged = $products->map(function (EcommerceProduct $product) {
            $model = $product->pricingModel;
            $sellingPrice = (float) ($model->selling_price ?? 0);
            $unitCogs = $model?->bomUnitCost() ?? 0.0;

            $issues = [];
            if (!$model) {
                $issues[] = 'NO PRICING MODEL';
            }
            if ($sellingPrice <= 0) {
                $issues[] = 'price = 0';
            }
            if ($unitCogs <= 0) {
                $issues[] = 'COGS = 0';
            }

            return [
                'product' => $product,
                'sku' => $product->sku,
                'selling_price' => $sellingPrice,
                'unit_cogs' => $unitCogs,
                'issues' => $issues,
            ];
        })->filter(fn ($row) => !empty($row['issues']))->values();

        if ($this->option('json')) {
            $this->line($flagged->map(fn ($r) => [
                'id' => $r['product']->id,
                'name' => $r['product']->name,
                'sku' => $r['sku'],
                'selling_price' => $r['selling_price'],
                'unit_cogs' => $r['unit_cogs'],
                'issues' => $r['issues'],
            ])->toJson(JSON_PRETTY_PRINT));

            return $flagged->isEmpty() ? self::SUCCESS : self::FAILURE;
        }

        $this->newLine();
        $this->info('Ecommerce Pricing & COGS Baseline Audit');
        $this->line('Scanned ' . $products->count() . ' product(s). Source of truth: ecommerce_pricing_models.');
        $this->newLine();

        if ($flagged->isEmpty()) {
            $this->info('✓ Every product has a non-zero selling price and a non-zero unit COGS.');

            return self::SUCCESS;
        }

        $this->table(
            ['ID', 'Product', 'SKU', 'Selling Price', 'Unit COGS', 'Problem'],
            $flagged->map(fn ($r) => [
                $r['product']->id,
                $r['product']->name,
                $r['sku'] ?: '—',
                number_format($r['selling_price'], 2),
                number_format($r['unit_cogs'], 2),
                implode(' + ', $r['issues']),
            ])->all()
        );

        $this->newLine();
        $this->error($flagged->count() . ' of ' . $products->count() . ' product(s) are missing real financial data.');
        $this->warn('Impact: every sale of these products snapshots unit_cogs = 0, so Net Profit overstates');
        $this->warn('gross profit by their true BOM cost and reports an inflated margin.');
        $this->line('Fix: set a selling price and component costs on the Pricing & Profit tab.');
        $this->newLine();

        // Non-zero exit so this can gate a deploy or run in CI.
        return self::FAILURE;
    }
}
