<?php

namespace App\Http\Controllers\Ecommerce;

use App\Http\Controllers\Controller;
use App\Models\EcommerceProduct;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class EcommerceSalesController extends Controller
{
    private const PAKISTAN_CHANNELS = ['Shopify (Pakistan)', 'Organic (Pakistan)'];
    private const QATAR_CHANNELS = ['Salon (Qatar)'];

    public function store(Request $request)
    {
        $validated = $request->validate([
            'ecommerce_product_id' => 'required|exists:ecommerce_products,id',
            'quantity' => 'required|numeric|min:0.01',
            'sales_channel' => 'required|in:' . implode(',', [...self::PAKISTAN_CHANNELS, ...self::QATAR_CHANNELS]),
        ]);

        $quantity = (float) $validated['quantity'];
        $isPakistanChannel = in_array($validated['sales_channel'], self::PAKISTAN_CHANNELS, true);
        $stockField = $isPakistanChannel ? 'stock_pakistan' : 'stock_qatar';
        $soldField = $isPakistanChannel ? 'sold_pakistan' : 'sold_qatar';
        $locationName = $isPakistanChannel ? 'Pakistan' : 'Qatar';

        $product = EcommerceProduct::findOrFail($validated['ecommerce_product_id']);

        if ($quantity > (float) $product->{$stockField}) {
            return back()->withErrors(['error' => "Insufficient stock in {$locationName} for {$product->name}. Requested: " . number_format($quantity, 2) . ', Available: ' . number_format((float) $product->{$stockField}, 2) . '.']);
        }

        DB::transaction(function () use ($validated, $quantity, $stockField, $soldField) {
            $product = EcommerceProduct::lockForUpdate()->findOrFail($validated['ecommerce_product_id']);
            $product->decrement($stockField, $quantity);
            $product->increment($soldField, $quantity);
        });

        return back()->with('success', 'Sale logged and stock updated.');
    }
}
