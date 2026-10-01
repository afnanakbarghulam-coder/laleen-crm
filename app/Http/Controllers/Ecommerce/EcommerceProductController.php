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
    public function index()
    {
        $products = EcommerceProduct::orderBy('name')->get();

        return view('ecommerce.products', compact('products'));
    }

    public function store(Request $request)
    {
        $validated = $this->validated($request);

        EcommerceProduct::create($validated);

        return back()->with('success', 'Product cost sheet saved.');
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
        return $request->validate([
            'name' => 'required|string|max:255',
            'sku' => [
                'required',
                'string',
                'max:100',
                Rule::unique('ecommerce_products', 'sku')->ignore($ignoreId),
            ],
            'product_line_id' => 'required|exists:ecommerce_product_lines,id',
            'unit_size' => 'nullable|string|max:50',
            'selling_price' => 'nullable|numeric|min:0',
            'liquid_cost_per_ml' => 'nullable|numeric|min:0',
            'volume_ml' => 'nullable|numeric|min:0',
            'bottle_cost' => 'nullable|numeric|min:0',
            'pump_cost' => 'nullable|numeric|min:0',
            'label_cost' => 'nullable|numeric|min:0',
            'box_cost' => 'nullable|numeric|min:0',
            'labor_cost' => 'nullable|numeric|min:0',
            'shipping_cost' => 'nullable|numeric|min:0',
            'payment_gateway_fee_percent' => 'nullable|numeric|min:0|max:100',
        ]);
    }
}
