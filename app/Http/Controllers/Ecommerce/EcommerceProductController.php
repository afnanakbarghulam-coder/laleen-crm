<?php

namespace App\Http\Controllers\Ecommerce;

use App\Http\Controllers\Controller;
use App\Models\EcommerceProduct;
use App\Models\EcommerceRawMaterial;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class EcommerceProductController extends Controller
{
    public function index()
    {
        $products = EcommerceProduct::with('rawMaterials')->orderBy('name')->get();
        $rawMaterials = EcommerceRawMaterial::orderBy('name')->get();

        return view('ecommerce.products', compact('products', 'rawMaterials'));
    }

    public function store(Request $request)
    {
        $validated = $this->validated($request);
        $recipe = $validated['recipe'] ?? [];
        unset($validated['recipe']);

        $product = EcommerceProduct::create($validated);
        $this->syncRecipe($product, $recipe);

        return back()->with('success', 'Product cost sheet saved.');
    }

    public function update(Request $request, EcommerceProduct $ecommerceProduct)
    {
        $validated = $this->validated($request, $ecommerceProduct->id);
        $recipe = $validated['recipe'] ?? [];
        unset($validated['recipe']);

        $ecommerceProduct->update($validated);
        $this->syncRecipe($ecommerceProduct, $recipe);

        return back()->with('success', 'Product cost sheet updated.');
    }

    public function destroy(EcommerceProduct $ecommerceProduct)
    {
        $ecommerceProduct->delete();

        return back()->with('success', 'Product deleted.');
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
            'selling_price' => 'required|numeric|min:0',
            'liquid_cost_per_ml' => 'required|numeric|min:0',
            'volume_ml' => 'required|numeric|min:0',
            'bottle_cost' => 'required|numeric|min:0',
            'pump_cost' => 'required|numeric|min:0',
            'label_cost' => 'required|numeric|min:0',
            'box_cost' => 'required|numeric|min:0',
            'labor_cost' => 'required|numeric|min:0',
            'shipping_cost' => 'required|numeric|min:0',
            'payment_gateway_fee_percent' => 'required|numeric|min:0|max:100',
            'recipe' => 'nullable|array',
            'recipe.*.ecommerce_raw_material_id' => 'required|exists:ecommerce_raw_materials,id',
            'recipe.*.quantity_required' => 'required|numeric|min:0.0001',
        ]);
    }

    /**
     * Replace the product's recipe wholesale with what the form submitted —
     * the recipe builder is the authoritative editor, so a row removed from
     * the form is detached here too.
     */
    private function syncRecipe(EcommerceProduct $product, array $recipe): void
    {
        $syncData = [];

        foreach ($recipe as $item) {
            $syncData[$item['ecommerce_raw_material_id']] = [
                'quantity_required' => $item['quantity_required'],
            ];
        }

        $product->rawMaterials()->sync($syncData);
    }
}
