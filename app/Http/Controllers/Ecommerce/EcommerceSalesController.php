<?php

namespace App\Http\Controllers\Ecommerce;

use App\Http\Controllers\Controller;
use App\Models\EcommerceProduct;
use App\Models\EcommerceSale;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class EcommerceSalesController extends Controller
{
    private const CHANNELS = [
        'Shopify (Pakistan)',
        'Organic (Pakistan)',
        'Salon (Qatar)',
        'Organic (Qatar)',
    ];

    public function store(Request $request)
    {
        $validated = $request->validate([
            'ecommerce_product_id' => 'required|exists:ecommerce_products,id',
            'quantity' => 'required|integer|min:1',
            'channel' => 'required|in:' . implode(',', self::CHANNELS),
            'unit_price' => 'required|numeric|min:0',
        ]);

        $quantity = (int) $validated['quantity'];
        $totalPrice = $quantity * (float) $validated['unit_price'];

        $isPakistan = str_contains($validated['channel'], 'Pakistan');
        $source = $isPakistan ? 'pakistan' : 'qatar';
        $stockField = "stock_{$source}";
        $soldField = "sold_{$source}";
        $locationName = $isPakistan ? 'Pakistan' : 'Qatar';

        $product = EcommerceProduct::findOrFail($validated['ecommerce_product_id']);

        if ((float) $product->{$stockField} < $quantity) {
            return back()->withErrors(['error' => "Insufficient stock in {$locationName} for this sale."]);
        }

        DB::transaction(function () use ($validated, $quantity, $totalPrice, $stockField, $soldField) {
            EcommerceSale::create([
                'ecommerce_product_id' => $validated['ecommerce_product_id'],
                'quantity' => $quantity,
                'channel' => $validated['channel'],
                'unit_price' => $validated['unit_price'],
                'total_price' => $totalPrice,
            ]);

            $product = EcommerceProduct::lockForUpdate()->findOrFail($validated['ecommerce_product_id']);
            $product->{$stockField} -= $quantity;
            $product->{$soldField} += $quantity;
            $product->save();
        });

        return back()->with('success', 'Sale logged and stock updated.');
    }
}
