<?php

namespace App\Http\Controllers\Ecommerce;

use App\Http\Controllers\Controller;
use App\Models\EcommerceProduct;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class EcommerceStockController extends Controller
{
    private const PAKISTAN_CHANNELS = ['Shopify (Pakistan)', 'Organic (Pakistan)'];
    private const QATAR_CHANNELS = ['Salon (Qatar)'];

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

    public function sale(Request $request)
    {
        $validated = $request->validate([
            'ecommerce_product_id' => 'required|exists:ecommerce_products,id',
            'quantity' => 'required|numeric|min:0.01',
            'sales_channel' => 'required|in:' . implode(',', [...self::PAKISTAN_CHANNELS, ...self::QATAR_CHANNELS]),
        ]);

        $quantity = (float) $validated['quantity'];
        $isPakistanChannel = in_array($validated['sales_channel'], self::PAKISTAN_CHANNELS, true);
        $stockField = $isPakistanChannel ? 'stock_pakistan' : 'stock_qatar';
        $locationName = $isPakistanChannel ? 'Pakistan' : 'Qatar';

        $product = EcommerceProduct::findOrFail($validated['ecommerce_product_id']);

        if ($quantity > (float) $product->{$stockField}) {
            return back()->withErrors(['error' => "Insufficient stock in {$locationName} for {$product->name}. Requested: " . number_format($quantity, 2) . ', Available: ' . number_format((float) $product->{$stockField}, 2) . '.']);
        }

        DB::transaction(function () use ($validated, $quantity, $stockField) {
            $product = EcommerceProduct::lockForUpdate()->findOrFail($validated['ecommerce_product_id']);
            $product->decrement($stockField, $quantity);
        });

        return back()->with('success', 'Sale logged and stock updated.');
    }
}
