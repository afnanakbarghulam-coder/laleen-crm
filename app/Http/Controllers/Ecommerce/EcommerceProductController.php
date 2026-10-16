<?php

namespace App\Http\Controllers\Ecommerce;

use App\Http\Controllers\Controller;
use App\Models\EcommerceProduct;
use App\Models\EcommerceRawMaterial;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class EcommerceProductController extends Controller
{
    /**
     * The standalone "Unit Economics" screen this used to render exposed a
     * second, conflicting cost model (labor / shipping / gateway fee) that no
     * financial figure in the module actually consumed. Redirected so
     * EcommercePricingModel::bomUnitCost() stays the single source of truth
     * for COGS.
     */
    public function index()
    {
        return redirect()->route('ecommerce.inventory.index');
    }

    /**
     * A product is created together with its pricing model in one transaction.
     *
     * Previously a product could be born with no ecommerce_pricing_models row
     * at all, which made every future sale of it snapshot unit_cogs = 0 and
     * report a fake ~100% margin. Base Price and Default COGS are now
     * mandatory at creation so that can never happen again.
     */
    public function store(Request $request)
    {
        $validated = $this->validated($request);

        $basePrice = (float) $validated['base_price'];
        $defaultCogs = (float) $validated['default_cogs'];
        unset($validated['base_price'], $validated['default_cogs']);

        // Keep the legacy products.selling_price column in step with the
        // canonical pricing model so the two never disagree.
        $validated['selling_price'] = $basePrice;

        DB::transaction(function () use ($validated, $basePrice, $defaultCogs) {
            $product = EcommerceProduct::create($validated);

            // The baseline lands on liquid_cost; the Pricing & Profit tab
            // itemizes it across bottle/label/pump/box later. What matters
            // here is that bomUnitCost() is non-zero from day one.
            $product->pricingModel()->create([
                'liquid_cost' => $defaultCogs,
                'bottle_cost' => 0,
                'label_cost' => 0,
                'pump_cost' => 0,
                'box_cost' => 0,
                'selling_price' => $basePrice,
            ]);
        });

        return back()->with('success', 'Product created with its pricing baseline. Itemize the COGS on Pricing & Profit when ready.');
    }

    public function update(Request $request, EcommerceProduct $ecommerceProduct)
    {
        $validated = $this->validated($request, $ecommerceProduct->id);

        $ecommerceProduct->update($validated);

        return back()->with('success', 'Product cost sheet updated.');
    }

    /**
     * Deleting a finished product refunds every raw material it ever consumed:
     * each of its production runs is walked, the quantity_used on each line
     * item is added back to that raw material's current_stock, then the run
     * history and the product itself are removed.
     */
    public function destroy(EcommerceProduct $ecommerceProduct)
    {
        // ecommerce_sales.ecommerce_product_id is ON DELETE CASCADE, so
        // removing a product that has sold would silently erase its entire
        // revenue history and retroactively change Net Profit. Refuse.
        if ($ecommerceProduct->sales()->exists()) {
            return back()->withErrors(['error' => 'Cannot delete this product because it has recorded sales history. Deleting it would corrupt historical revenue and Net Profit.']);
        }

        DB::transaction(function () use ($ecommerceProduct) {
            $productionRuns = $ecommerceProduct->productionRuns()->with('materials')->get();

            foreach ($productionRuns as $run) {
                foreach ($run->materials as $material) {
                    $rawMaterial = EcommerceRawMaterial::lockForUpdate()->find($material->ecommerce_raw_material_id);

                    if ($rawMaterial) {
                        $rawMaterial->increment('current_stock', (float) $material->quantity_used);
                    }
                }

                $run->materials()->delete();
                $run->delete();
            }

            $ecommerceProduct->delete();
        });

        return back()->with('success', 'Product deleted and raw materials refunded.');
    }

    private function validated(Request $request, ?int $ignoreId = null): array
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'sku' => [
                'required',
                'string',
                'max:100',
                Rule::unique('ecommerce_products', 'sku')->ignore($ignoreId),
            ],
            'product_line_id' => 'required|exists:ecommerce_product_lines,id',
            'unit_size' => 'nullable|numeric|min:0',
            // Mandatory financial baseline — these seed the product's 1:1
            // pricing model so no product can exist with a zero price or a
            // zero COGS.
            'base_price' => 'required|numeric|min:0.01',
            'default_cogs' => 'required|numeric|min:0.01',
            'liquid_cost_per_ml' => 'nullable|numeric|min:0',
            'volume_ml' => 'nullable|numeric|min:0',
            'bottle_cost' => 'nullable|numeric|min:0',
            'pump_cost' => 'nullable|numeric|min:0',
            'label_cost' => 'nullable|numeric|min:0',
            'box_cost' => 'nullable|numeric|min:0',
            'labor_cost' => 'nullable|numeric|min:0',
            'shipping_cost' => 'nullable|numeric|min:0',
            'payment_gateway_fee_percent' => 'nullable|numeric|min:0|max:100',
        ], [
            'base_price.required' => 'Base Price is required — a zero-price product corrupts Net Profit.',
            'base_price.min' => 'Base Price must be greater than zero.',
            'default_cogs.required' => 'Default COGS is required — without it every sale snapshots a zero cost and reports a fake 100% margin.',
            'default_cogs.min' => 'Default COGS must be greater than zero.',
        ]);

        // The "Add Finished Product" modal only collects the numeric amount —
        // the unit is fixed to ml, so it's appended here rather than trusted
        // from the client.
        if (isset($validated['unit_size'])) {
            $validated['unit_size'] = $validated['unit_size'] . 'ml';
        }

        return $validated;
    }
}
