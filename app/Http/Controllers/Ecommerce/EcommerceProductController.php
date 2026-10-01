<?php

namespace App\Http\Controllers\Ecommerce;

use App\Http\Controllers\Controller;
use App\Models\EcommerceProduct;
use Illuminate\Http\Request;
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
