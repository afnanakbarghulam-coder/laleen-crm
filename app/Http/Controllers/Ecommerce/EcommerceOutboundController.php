<?php

namespace App\Http\Controllers\Ecommerce;

use App\Http\Controllers\Controller;
use App\Models\EcommerceOutbound;
use App\Models\EcommerceProduct;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class EcommerceOutboundController extends Controller
{
    public function index()
    {
        $outbounds = EcommerceOutbound::with('product')->orderByDesc('created_at')->get();
        $products = EcommerceProduct::orderBy('name')->get();

        $reasons = EcommerceOutbound::query()
            ->whereNotNull('reason')
            ->where('reason', '!=', '')
            ->distinct()
            ->orderBy('reason')
            ->pluck('reason');

        return view('ecommerce.outbound', [
            'outbounds' => $outbounds,
            'products' => $products,
            'reasons' => $reasons,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'customer_name' => 'nullable|string|max:255',
            'contact_number' => 'nullable|string|max:50',
            'reason' => 'required|string|max:100',
            'items' => 'required|array|min:1',
            'items.*.ecommerce_product_id' => 'required|exists:ecommerce_products,id',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.price' => 'nullable|numeric|min:0',
        ]);

        DB::transaction(function () use ($validated) {
            foreach ($validated['items'] as $item) {
                $product = EcommerceProduct::lockForUpdate()->findOrFail($item['ecommerce_product_id']);
                $product->decrement('current_stock', $item['quantity']);

                EcommerceOutbound::create([
                    'ecommerce_product_id' => $item['ecommerce_product_id'],
                    'customer_name' => $validated['customer_name'] ?? null,
                    'contact_number' => $validated['contact_number'] ?? null,
                    'quantity' => $item['quantity'],
                    'price' => $item['price'] ?? null,
                    'reason' => $validated['reason'],
                ]);
            }
        });

        return back()->with('success', 'Sales & usage logged.');
    }
}
