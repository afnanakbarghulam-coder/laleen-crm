<?php

namespace App\Http\Controllers\Ecommerce;

use App\Http\Controllers\Controller;
use App\Models\EcommerceProduct;
use App\Models\EcommerceProductLine;
use App\Models\EcommerceRawMaterial;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use RuntimeException;

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

        try {
            DB::transaction(function () use ($validated, $quantityProduced, $materialsUsed) {
                // Lock the finished product row first so two concurrent runs
                // against the same product can't both pass validation before
                // either one's stock decrements land.
                $product = EcommerceProduct::with('productLine')->lockForUpdate()->findOrFail($validated['ecommerce_product_id']);
                $productLineName = $product->productLine->name ?? null;
                $requiredComponentTypes = EcommerceProductLine::COMPONENT_TYPES_BY_PRODUCT_LINE[$productLineName] ?? [];

                $submittedRawMaterialIds = collect($materialsUsed)->pluck('ecommerce_raw_material_id');

                // Lock every raw material row this run could touch: the ones
                // explicitly submitted, plus every component the product
                // line's BOM requires (so a required component the form
                // never offered a row for still gets checked and locked).
                $rawMaterialsById = EcommerceRawMaterial::with(['productLine', 'componentType'])
                    ->where(function ($query) use ($submittedRawMaterialIds, $product, $requiredComponentTypes) {
                        $query->whereIn('id', $submittedRawMaterialIds);

                        if (!empty($requiredComponentTypes)) {
                            $query->orWhere(function ($bomQuery) use ($product, $requiredComponentTypes) {
                                $bomQuery->where('product_line_id', $product->product_line_id)
                                    ->whereHas('componentType', fn ($q) => $q->whereIn('name', $requiredComponentTypes));
                            });
                        }
                    })
                    ->lockForUpdate()
                    ->get()
                    ->keyBy('id');

                // The form now submits the batch TOTAL for each material directly
                // (quantity_used), not a per-unit rate — so it's used as-is here
                // rather than multiplied by $quantityProduced again, which would
                // double-deduct stock.
                $requestedAmountByRawMaterialId = collect($materialsUsed)
                    ->mapWithKeys(fn ($item) => [$item['ecommerce_raw_material_id'] => (float) $item['quantity_used']]);

                if (!empty($requiredComponentTypes)) {
                    $rawMaterialByComponentType = $rawMaterialsById
                        ->filter(fn (EcommerceRawMaterial $material) => $material->product_line_id === $product->product_line_id)
                        ->keyBy(fn (EcommerceRawMaterial $material) => $material->componentType->name ?? null);

                    $insufficientComponentTypes = collect($requiredComponentTypes)->filter(function (string $componentTypeName) use ($rawMaterialByComponentType, $requestedAmountByRawMaterialId) {
                        $rawMaterial = $rawMaterialByComponentType->get($componentTypeName);

                        if (!$rawMaterial || (float) $rawMaterial->current_stock <= 0) {
                            return true;
                        }

                        $requestedAmount = $requestedAmountByRawMaterialId->get($rawMaterial->id, 0.0);

                        return $requestedAmount > (float) $rawMaterial->current_stock;
                    })->values();

                    if ($insufficientComponentTypes->isNotEmpty()) {
                        throw new RuntimeException(
                            'Production failed: Insufficient ' . $this->joinWithAnd($insufficientComponentTypes->all())
                                . " stock for this {$productLineName} run."
                        );
                    }
                }

                foreach ($materialsUsed as $item) {
                    $rawMaterial = $rawMaterialsById->get($item['ecommerce_raw_material_id']);
                    $totalRequired = $requestedAmountByRawMaterialId->get($item['ecommerce_raw_material_id']);

                    if (!$rawMaterial || $totalRequired > (float) $rawMaterial->current_stock) {
                        $productLineLabel = $rawMaterial->productLine->name ?? 'Uncategorized';
                        $componentTypeLabel = $rawMaterial->componentType->name ?? 'Uncategorized';

                        throw new RuntimeException("Insufficient stock for {$productLineLabel} - {$componentTypeLabel}. Required: " . number_format($totalRequired, 2) . ', Available: ' . number_format((float) $rawMaterial->current_stock, 2) . '.');
                    }
                }

                $product->increment('stock_pakistan', $quantityProduced);

                $run = $product->productionRuns()->create([
                    'quantity_produced' => $quantityProduced,
                ]);

                foreach ($materialsUsed as $item) {
                    $totalQuantityUsed = $requestedAmountByRawMaterialId->get($item['ecommerce_raw_material_id']);
                    $rawMaterial = $rawMaterialsById->get($item['ecommerce_raw_material_id']);
                    $rawMaterial->decrement('current_stock', $totalQuantityUsed);

                    $run->materials()->create([
                        'ecommerce_raw_material_id' => $rawMaterial->id,
                        'quantity_used' => $totalQuantityUsed,
                    ]);
                }
            });
        } catch (RuntimeException $e) {
            return back()->withErrors(['error' => $e->getMessage()]);
        }

        return back()->with('success', 'Production run logged and stock levels updated.');
    }

    private function joinWithAnd(array $items): string
    {
        if (count($items) <= 1) {
            return $items[0] ?? '';
        }

        $last = array_pop($items);

        return implode(', ', $items) . ' and ' . $last;
    }
}
