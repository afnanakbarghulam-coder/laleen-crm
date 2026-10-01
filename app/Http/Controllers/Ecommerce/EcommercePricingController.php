<?php

namespace App\Http\Controllers\Ecommerce;

use App\Http\Controllers\Controller;
use App\Models\EcommercePricingModel;
use App\Models\EcommerceProduct;
use App\Models\EcommerceRawMaterial;
use Illuminate\Http\Request;

class EcommercePricingController extends Controller
{
    public function index(Request $request)
    {
        $products = EcommerceProduct::orderBy('name')->get();
        $rawMaterials = EcommerceRawMaterial::orderBy('name')->get();

        $pricingModels = EcommercePricingModel::all()->keyBy('ecommerce_product_id')->map(function ($model) {
            return [
                'liquid_cost' => (float) $model->liquid_cost,
                'packaging_cost' => (float) $model->packaging_cost,
                'fulfillment_cost' => (float) $model->fulfillment_cost,
                'selling_price' => (float) $model->selling_price,
            ];
        });

        $rawMaterialCosts = $rawMaterials->keyBy('id')->map(function ($material) {
            return [
                'name' => $material->name,
                'unit_of_measure' => $material->unit_of_measure,
                'last_purchased_unit_cost' => (float) $material->last_purchased_unit_cost,
            ];
        });

        return view('ecommerce.pricing', [
            'products' => $products,
            'pricingModels' => $pricingModels,
            'rawMaterials' => $rawMaterials,
            'rawMaterialCosts' => $rawMaterialCosts,
            'selectedProductId' => $request->query('product'),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'ecommerce_product_id' => 'required|exists:ecommerce_products,id',
            'liquid_cost' => 'nullable|numeric|min:0',
            'packaging_cost' => 'nullable|numeric|min:0',
            'fulfillment_cost' => 'nullable|numeric|min:0',
            'selling_price' => 'nullable|numeric|min:0',
        ]);

        EcommercePricingModel::updateOrCreate(
            ['ecommerce_product_id' => $validated['ecommerce_product_id']],
            [
                'liquid_cost' => $validated['liquid_cost'] ?? 0,
                'packaging_cost' => $validated['packaging_cost'] ?? 0,
                'fulfillment_cost' => $validated['fulfillment_cost'] ?? 0,
                'selling_price' => $validated['selling_price'] ?? 0,
            ]
        );

        return redirect()
            ->route('ecommerce.pricing.index', ['product' => $validated['ecommerce_product_id']])
            ->with('success', 'Pricing model saved.');
    }
}
