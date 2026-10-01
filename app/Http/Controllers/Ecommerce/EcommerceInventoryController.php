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

        return view('ecommerce.inventory', [
            'rawMaterials' => $rawMaterials,
            'products' => $products,
            'productLines' => $productLines,
        ]);
    }

    public function destroyRawMaterial(EcommerceRawMaterial $ecommerceRawMaterial)
    {
        $ecommerceRawMaterial->delete();

        return back()->with('success', 'Raw material deleted.');
    }
}
