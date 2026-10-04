<?php

namespace App\Http\Controllers\Ecommerce;

use App\Http\Controllers\Controller;
use App\Models\EcommerceProduct;
use App\Models\EcommerceProductLine;
use App\Models\EcommerceRawMaterial;
use Illuminate\Http\Request;

class EcommerceInventoryController extends Controller
{
    public function index(Request $request)
    {
        $filterProductLine = $request->string('product_line')->toString() ?: null;

        $sortMaterials = fn ($materials) => $materials
            ->sortBy(fn ($material) => ($material->productLine->name ?? 'ZZZ_Uncategorized')
                . ' - ' . ($material->componentType->name ?? 'ZZZ_Uncategorized'))
            ->values();

        $rawMaterials = $sortMaterials(
            EcommerceRawMaterial::with(['productLine', 'componentType'])
                ->when($filterProductLine, function ($query, $value) {
                    $query->whereHas('productLine', fn ($q) => $q->where('name', $value));
                })
                ->get()
        );

        // Unfiltered — the production modal's smart recipe needs every raw
        // material regardless of what the Tier 1 table filter is showing.
        $allRawMaterials = $filterProductLine
            ? $sortMaterials(EcommerceRawMaterial::with(['productLine', 'componentType'])->get())
            : $rawMaterials;

        $products = EcommerceProduct::orderBy('name')->get();
        $productLines = EcommerceProductLine::orderBy('name')->get();

        // Product lines with a defined Bill of Materials — the only ones the
        // Tier 1 filter dropdown and its production guidelines apply to.
        $filterableProductLines = EcommerceProductLine::whereIn('name', array_keys(EcommerceProductLine::COMPONENT_TYPES_BY_PRODUCT_LINE))
            ->orderBy('name')
            ->get();

        $productsForRecipe = $products->mapWithKeys(function ($product) {
            return [$product->id => [
                'product_line_id' => $product->product_line_id,
                'unit_size' => $product->unit_size,
            ]];
        });

        $rawMaterialsForRecipe = $allRawMaterials->mapWithKeys(function ($material) {
            return [$material->id => [
                'product_line_id' => $material->product_line_id,
                'component_type_name' => $material->componentType->name ?? null,
            ]];
        });

        return view('ecommerce.inventory', [
            'rawMaterials' => $rawMaterials,
            'allRawMaterials' => $allRawMaterials,
            'products' => $products,
            'productLines' => $productLines,
            'filterableProductLines' => $filterableProductLines,
            'filterProductLine' => $filterProductLine,
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
