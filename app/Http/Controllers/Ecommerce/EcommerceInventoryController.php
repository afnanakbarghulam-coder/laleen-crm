<?php

namespace App\Http\Controllers\Ecommerce;

use App\Http\Controllers\Controller;
use App\Models\EcommerceProduct;
use App\Models\EcommerceRawMaterial;

class EcommerceInventoryController extends Controller
{
    public function index()
    {
        $rawMaterials = EcommerceRawMaterial::orderBy('name')->get();
        $products = EcommerceProduct::orderBy('name')->get();

        return view('ecommerce.inventory', [
            'rawMaterials' => $rawMaterials,
            'products' => $products,
        ]);
    }

    public function destroyRawMaterial(EcommerceRawMaterial $ecommerceRawMaterial)
    {
        $ecommerceRawMaterial->delete();

        return back()->with('success', 'Raw material deleted.');
    }
}
