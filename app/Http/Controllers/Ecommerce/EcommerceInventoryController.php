<?php

namespace App\Http\Controllers\Ecommerce;

use App\Http\Controllers\Controller;
use App\Models\EcommerceProduct;
use App\Models\EcommerceRawMaterial;
use Illuminate\Http\Request;

class EcommerceInventoryController extends Controller
{
    public function index()
    {
        $rawMaterials = EcommerceRawMaterial::orderBy('name')->get();
        $products = EcommerceProduct::with('rawMaterials')->orderBy('name')->get();

        $rawMaterialTypes = EcommerceRawMaterial::query()
            ->whereNotNull('type')
            ->where('type', '!=', '')
            ->distinct()
            ->orderBy('type')
            ->pluck('type');

        return view('ecommerce.inventory', [
            'rawMaterials' => $rawMaterials,
            'products' => $products,
            'rawMaterialTypes' => $rawMaterialTypes,
        ]);
    }

    public function storeRawMaterial(Request $request)
    {
        $validated = $request->validate([
            'type' => 'nullable|string|max:100',
            'current_stock' => 'required|numeric|min:0',
            'unit_of_measure' => 'required|string|max:50',
        ]);

        // The 'name' column is still required at the database level, but the
        // UI no longer collects it — fall back to the category so it's never null.
        $validated['name'] = ($validated['type'] ?? '') !== '' ? $validated['type'] : 'Uncategorized';

        EcommerceRawMaterial::create($validated);

        return back()->with('success', 'Raw material added.');
    }

    public function destroyRawMaterial(EcommerceRawMaterial $ecommerceRawMaterial)
    {
        $ecommerceRawMaterial->delete();

        return back()->with('success', 'Raw material deleted.');
    }

    public function storeRecipeItem(Request $request, EcommerceProduct $product)
    {
        $validated = $request->validate([
            'ecommerce_raw_material_id' => 'required|exists:ecommerce_raw_materials,id',
            'quantity_required' => 'required|numeric|min:0.0001',
        ]);

        $product->rawMaterials()->syncWithoutDetaching([
            $validated['ecommerce_raw_material_id'] => ['quantity_required' => $validated['quantity_required']],
        ]);

        return back()->with('success', 'Recipe updated.');
    }

    public function destroyRecipeItem(EcommerceProduct $product, EcommerceRawMaterial $rawMaterial)
    {
        $product->rawMaterials()->detach($rawMaterial->id);

        return back()->with('success', 'Raw material removed from recipe.');
    }
}
