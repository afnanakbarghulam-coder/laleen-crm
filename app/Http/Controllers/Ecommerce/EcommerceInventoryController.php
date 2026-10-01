<?php

namespace App\Http\Controllers\Ecommerce;

use App\Http\Controllers\Controller;
use App\Models\EcommerceProduct;
use App\Models\EcommerceProductLine;
use App\Models\EcommerceRawMaterial;

class EcommerceInventoryController extends Controller
{
    public function index()
    {
        $rawMaterials = EcommerceRawMaterial::with(['productLine', 'componentType'])
            ->get()
            ->sortBy(function ($material) {
                return ($material->productLine->name ?? 'ZZZ_Uncategorized')
                    . ' - ' . ($material->componentType->name ?? 'ZZZ_Uncategorized');
            })
            ->values();

        $products = EcommerceProduct::orderBy('name')->get();
        $productLines = EcommerceProductLine::orderBy('name')->get();

        $productsForRecipe = $products->mapWithKeys(function ($product) {
            return [$product->id => [
                'product_line_id' => $product->product_line_id,
                'unit_size' => $product->unit_size,
            ]];
        });

        $rawMaterialsForRecipe = $rawMaterials->mapWithKeys(function ($material) {
            return [$material->id => [
                'product_line_id' => $material->product_line_id,
                'component_type_name' => $material->componentType->name ?? null,
            ]];
        });

        return view('ecommerce.inventory', [
            'rawMaterials' => $rawMaterials,
            'products' => $products,
            'productLines' => $productLines,
            'productsForRecipe' => $productsForRecipe,
            'rawMaterialsForRecipe' => $rawMaterialsForRecipe,
        ]);
    }

    public function destroyRawMaterial(EcommerceRawMaterial $ecommerceRawMaterial)
    {
        $ecommerceRawMaterial->delete();

        return back()->with('success', 'Raw material deleted.');
    }
}
