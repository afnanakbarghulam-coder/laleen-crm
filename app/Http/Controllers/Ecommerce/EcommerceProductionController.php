<?php

namespace App\Http\Controllers\Ecommerce;

use App\Http\Controllers\Controller;
use App\Models\EcommerceProduct;
use App\Models\EcommerceRawMaterial;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class EcommerceProductionController extends Controller
{
    public function store(Request $request)
    {
        $validated = $request->validate([
            'ecommerce_product_id' => 'required|exists:ecommerce_products,id',
            'quantity_produced' => 'required|numeric|min:0.01',
            'materials_used' => 'nullable|array',
            'materials_used.*.ecommerce_raw_material_id' => 'required|exists:ecommerce_raw_materials,id',
            'materials_used.*.quantity_used' => 'required|numeric|min:0.01',
        ]);

        $quantityProduced = (float) $validated['quantity_produced'];
        $materialsUsed = $validated['materials_used'] ?? [];

        DB::transaction(function () use ($validated, $quantityProduced, $materialsUsed) {
            $product = EcommerceProduct::lockForUpdate()->findOrFail($validated['ecommerce_product_id']);
            $product->increment('current_stock', $quantityProduced);

            foreach ($materialsUsed as $item) {
                $rawMaterial = EcommerceRawMaterial::lockForUpdate()->findOrFail($item['ecommerce_raw_material_id']);
                $rawMaterial->decrement('current_stock', (float) $item['quantity_used']);
            }
        });

        return back()->with('success', 'Production run logged and stock levels updated.');
    }
}
