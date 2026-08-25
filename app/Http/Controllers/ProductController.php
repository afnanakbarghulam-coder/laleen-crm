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
        $request->validate([
            'name'  => 'required|string|max:255',
            'sku'   => 'nullable|string|max:100',
            'price' => 'required|numeric|min:0',
        ]);

        Product::create($request->only('name', 'sku', 'price'));

        return back()->with('success', 'Product added successfully.');
    }

    public function update(Request $request, Product $product)
    {
        $request->validate([
            'name'  => 'required|string|max:255',
            'sku'   => 'nullable|string|max:100',
            'price' => 'required|numeric|min:0',
        ]);

        $product->update($request->only('name', 'sku', 'price'));

        return back()->with('success', 'Product updated successfully.');
    }

    public function destroy(Product $product)
    {
        $product->delete();
        return back()->with('success', 'Product deleted successfully.');
    }
}
