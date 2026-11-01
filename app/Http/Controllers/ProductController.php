<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    public function index()
    {
        $products = Product::orderBy('name')->get();
        return view('products.index', compact('products'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name'                => 'required|string|max:255',
            'sku'                 => 'nullable|string|max:100',
            'price'               => 'required|numeric|min:0',
            'stock_quantity'      => 'nullable|integer|min:0',
            'low_stock_threshold' => 'nullable|integer|min:0',
        ]);

        Product::create([
            'name'                => $validated['name'],
            'sku'                 => $validated['sku'] ?? null,
            'price'               => $validated['price'],
            'stock_quantity'      => (int) ($validated['stock_quantity'] ?? 0),
            'low_stock_threshold' => (int) ($validated['low_stock_threshold'] ?? 3),
        ]);

        return back()->with('success', 'Product added successfully.');
    }

    public function update(Request $request, Product $product)
    {
        $validated = $request->validate([
            'name'                => 'required|string|max:255',
            'sku'                 => 'nullable|string|max:100',
            'price'               => 'required|numeric|min:0',
            'stock_quantity'      => 'nullable|integer|min:0',
            'low_stock_threshold' => 'nullable|integer|min:0',
        ]);

        $payload = [
            'name'  => $validated['name'],
            'sku'   => $validated['sku'] ?? null,
            'price' => $validated['price'],
        ];

        if ($request->has('stock_quantity') && $validated['stock_quantity'] !== null) {
            $payload['stock_quantity'] = (int) $validated['stock_quantity'];
        }
        if ($request->has('low_stock_threshold') && $validated['low_stock_threshold'] !== null) {
            $payload['low_stock_threshold'] = (int) $validated['low_stock_threshold'];
        }

        $product->update($payload);

        return back()->with('success', 'Product updated successfully.');
    }

    public function destroy(Product $product)
    {
        $usages = [];
        if ($product->saleItems()->exists()) {
            $usages[] = 'completed sales';
        }
        if ($product->upsells()->exists()) {
            $usages[] = 'appointment upsells';
        }

        if (!empty($usages)) {
            return back()->with(
                'error',
                sprintf('Cannot delete "%s" because it is referenced in %s.', $product->name, implode(' and ', $usages))
            );
        }

        $product->delete();
        return back()->with('success', 'Product deleted successfully.');
    }
}
