<?php

namespace App\Http\Controllers\Ecommerce;

use App\Http\Controllers\Controller;
use App\Models\EcommercePricingModel;
use App\Models\EcommerceProduct;
use Illuminate\Http\Request;

class EcommercePricingController extends Controller
{
    public function index()
    {
        $products = EcommerceProduct::orderBy('name')->get();

        $pricingModels = EcommercePricingModel::all()->keyBy('ecommerce_product_id')->map(function ($model) {
            return [
                'liquid_cost' => (float) $model->liquid_cost,
                'packaging_cost' => (float) $model->packaging_cost,
                'fulfillment_cost' => (float) $model->fulfillment_cost,
                'selling_price' => (float) $model->selling_price,
            ];
        });

        return view('ecommerce.pricing', [
            'products' => $products,
            'pricingModels' => $pricingModels,
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

        return back()->with('success', 'Pricing model saved.');
    }
}
