<?php

namespace App\Http\Controllers\Ecommerce;

use App\Http\Controllers\Controller;
use App\Models\EcommerceProduct;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class EcommerceProductionController extends Controller
{
    public function store(Request $request)
    {
        $validated = $request->validate([
            'ecommerce_product_id' => 'required|exists:ecommerce_products,id',
            'quantity_produced' => 'required|numeric|min:0.01',
        ]);

        $quantityProduced = (float) $validated['quantity_produced'];

        DB::transaction(function () use ($validated, $quantityProduced) {
            $product = EcommerceProduct::with('rawMaterials')
                ->lockForUpdate()
                ->findOrFail($validated['ecommerce_product_id']);

            $product->increment('current_stock', $quantityProduced);

            foreach ($product->rawMaterials as $rawMaterial) {
                $requiredAmount = $quantityProduced * (float) $rawMaterial->pivot->quantity_required;

                $rawMaterial->decrement('current_stock', $requiredAmount);
            }
        });

        return back()->with('success', 'Production run logged and stock levels updated.');
    }
}
