<?php

namespace App\Http\Controllers\Ecommerce;

use App\Http\Controllers\Controller;
use App\Models\EcommerceComponentType;
use App\Models\EcommercePricingModel;
use App\Models\EcommerceProduct;
use App\Models\EcommerceProductLine;
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
                'bottle_cost' => (float) $model->bottle_cost,
                'label_cost' => (float) $model->label_cost,
                'pump_cost' => (float) $model->pump_cost,
                'box_cost' => (float) $model->box_cost,
                'selling_price' => (float) $model->selling_price,
            ];
        });

        $productsForPricing = $products->mapWithKeys(function ($product) {
            return [$product->id => [
                'product_line_id' => $product->product_line_id,
                'unit_size' => $product->unit_size,
            ]];
        });

        $rawMaterialsForPricing = $rawMaterials->mapWithKeys(function ($material) {
            return [$material->id => [
                'product_line_id' => $material->product_line_id,
                'component_type_id' => $material->component_type_id,
                'last_purchased_unit_cost' => (float) $material->last_purchased_unit_cost,
            ]];
        });

        $componentTypeNames = EcommerceComponentType::pluck('name', 'id');
        $productLineNames = EcommerceProductLine::pluck('name', 'id');

        return view('ecommerce.pricing', [
            'products' => $products,
            'pricingModels' => $pricingModels,
            'rawMaterials' => $rawMaterials,
            'productsForPricing' => $productsForPricing,
            'rawMaterialsForPricing' => $rawMaterialsForPricing,
            'componentTypeNames' => $componentTypeNames,
            'productLineNames' => $productLineNames,
            'selectedProductId' => $request->query('product'),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'ecommerce_product_id' => 'required|exists:ecommerce_products,id',
            'liquid_cost' => 'nullable|numeric|min:0',
            'bottle_cost' => 'nullable|numeric|min:0',
            'label_cost' => 'nullable|numeric|min:0',
            'pump_cost' => 'nullable|numeric|min:0',
            'box_cost' => 'nullable|numeric|min:0',
            'selling_price' => 'nullable|numeric|min:0',
        ]);

        EcommercePricingModel::updateOrCreate(
            ['ecommerce_product_id' => $validated['ecommerce_product_id']],
            [
                'liquid_cost' => $validated['liquid_cost'] ?? 0,
                'bottle_cost' => $validated['bottle_cost'] ?? 0,
                'label_cost' => $validated['label_cost'] ?? 0,
                'pump_cost' => $validated['pump_cost'] ?? 0,
                'box_cost' => $validated['box_cost'] ?? 0,
                'selling_price' => $validated['selling_price'] ?? 0,
            ]
        );

        return redirect()
            ->route('ecommerce.pricing.index', ['product' => $validated['ecommerce_product_id']])
            ->with('success', 'Pricing model saved.');
    }
}
