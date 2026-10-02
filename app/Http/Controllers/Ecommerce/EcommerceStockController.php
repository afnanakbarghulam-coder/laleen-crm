<?php

namespace App\Http\Controllers\Ecommerce;

use App\Http\Controllers\Controller;
use App\Models\EcommerceProduct;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class EcommerceStockController extends Controller
{
    public function index()
    {
        $products = EcommerceProduct::orderBy('name')->get();

        return view('ecommerce.stock', [
            'products' => $products,
        ]);
    }

    public function transfer(Request $request)
    {
        $validated = $request->validate([
            'ecommerce_product_id' => 'required|exists:ecommerce_products,id',
            'quantity' => 'required|numeric|min:0.01',
        ]);

        $quantity = (float) $validated['quantity'];
        $product = EcommerceProduct::findOrFail($validated['ecommerce_product_id']);

        if ($quantity > (float) $product->stock_pakistan) {
            return back()->withErrors(['error' => "Insufficient stock in Pakistan for {$product->name}. Requested: " . number_format($quantity, 2) . ', Available: ' . number_format((float) $product->stock_pakistan, 2) . '.']);
        }

        DB::transaction(function () use ($validated, $quantity) {
            $product = EcommerceProduct::lockForUpdate()->findOrFail($validated['ecommerce_product_id']);
            $product->decrement('stock_pakistan', $quantity);
            $product->increment('stock_qatar', $quantity);
        });

        return back()->with('success', 'Stock transferred to Qatar.');
    }
}
