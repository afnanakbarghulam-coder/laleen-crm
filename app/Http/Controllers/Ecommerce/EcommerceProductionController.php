<?php

namespace App\Http\Controllers\Ecommerce;

use App\Http\Controllers\Controller;
use App\Models\EcommerceProduct;
use App\Models\EcommerceRawMaterial;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class EcommerceProductionController extends Controller
{
    /**
     * Component types every production run must include, keyed by the
     * finished product's Product Line name. Product lines not listed here
     * have no required Bill of Materials.
     */
    private const REQUIRED_COMPONENT_TYPES_BY_PRODUCT_LINE = [
        'Hair Oil' => ['Liquid Base', 'Bottle/Jar', 'Label'],
        'Shampoo' => ['Liquid Base', 'Bottle/Jar', 'Label', 'Pump/Cap'],
    ];

    public function store(Request $request)
    {
        $validated = $request->validate([
            'ecommerce_product_id' => 'required|exists:ecommerce_products,id',
            'quantity_produced' => 'required|numeric|min:0.01',
            'materials_used' => 'nullable|array',
            'materials_used.*.ecommerce_raw_material_id' => 'required|exists:ecommerce_raw_materials,id',
            'materials_used.*.amount_per_unit' => 'required|numeric|min:0.01',
        ]);

        $quantityProduced = (float) $validated['quantity_produced'];
        $materialsUsed = $validated['materials_used'] ?? [];

        $product = EcommerceProduct::with('productLine')->findOrFail($validated['ecommerce_product_id']);
        $requiredComponentTypes = self::REQUIRED_COMPONENT_TYPES_BY_PRODUCT_LINE[$product->productLine->name ?? ''] ?? null;

        if ($requiredComponentTypes) {
            $submittedComponentTypes = EcommerceRawMaterial::whereIn('id', collect($materialsUsed)->pluck('ecommerce_raw_material_id'))
                ->with('componentType')
                ->get()
                ->pluck('componentType.name')
                ->filter()
                ->unique();

            $missingComponentTypes = collect($requiredComponentTypes)->diff($submittedComponentTypes);

            if ($missingComponentTypes->isNotEmpty()) {
                return back()->withErrors(['error' => 'Production blocked: You are missing required components for this product line (e.g., Label, Pump). Please restock Tier 1 inventory first.']);
            }
        }

        $rawMaterialsById = EcommerceRawMaterial::with(['productLine', 'componentType'])
            ->whereIn('id', collect($materialsUsed)->pluck('ecommerce_raw_material_id'))
            ->get()
            ->keyBy('id');

        foreach ($materialsUsed as $item) {
            $rawMaterial = $rawMaterialsById->get($item['ecommerce_raw_material_id']);
            $totalRequired = (float) $item['amount_per_unit'] * $quantityProduced;

            if ($totalRequired > (float) $rawMaterial->current_stock) {
                $productLineName = $rawMaterial->productLine->name ?? 'Uncategorized';
                $componentTypeName = $rawMaterial->componentType->name ?? 'Uncategorized';

                return back()->withErrors(['error' => "Insufficient stock for {$productLineName} - {$componentTypeName}. Required: " . number_format($totalRequired, 2) . ', Available: ' . number_format((float) $rawMaterial->current_stock, 2) . '.']);
            }
        }

        DB::transaction(function () use ($validated, $quantityProduced, $materialsUsed) {
            $product = EcommerceProduct::lockForUpdate()->findOrFail($validated['ecommerce_product_id']);
            $product->increment('current_stock', $quantityProduced);

            $run = $product->productionRuns()->create([
                'quantity_produced' => $quantityProduced,
            ]);

            foreach ($materialsUsed as $item) {
                $totalQuantityUsed = (float) $item['amount_per_unit'] * $quantityProduced;

                $rawMaterial = EcommerceRawMaterial::lockForUpdate()->findOrFail($item['ecommerce_raw_material_id']);
                $rawMaterial->decrement('current_stock', $totalQuantityUsed);

                $run->materials()->create([
                    'ecommerce_raw_material_id' => $rawMaterial->id,
                    'quantity_used' => $totalQuantityUsed,
                ]);
            }
        });

        return back()->with('success', 'Production run logged and stock levels updated.');
    }
}
