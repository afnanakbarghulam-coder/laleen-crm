<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\SalePayment;
use Illuminate\Http\Request;

class SaleController extends Controller
{
    public function create()
    {
        $products = Product::orderBy('name')->get();
        return view('revenue.new-sale', compact('products'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'customer_phone' => 'nullable|string|max:20',
            'customer_name' => 'nullable|string|max:255',
            'branch' => 'required|in:old_airport,wakrah,home_service',
            'products' => 'required|array|min:1',
            'products.*.product_id' => 'required|exists:products,id',
            'products.*.quantity' => 'required|integer|min:1',
            'discount_type' => 'nullable|in:flat,percent',
            'discount_value' => 'nullable|numeric|min:0',
            'tip_amount' => 'nullable|numeric|min:0',
            'payments.cash' => 'nullable|numeric|min:0',
            'payments.card' => 'nullable|numeric|min:0',
            'payments.online_transfer' => 'nullable|numeric|min:0',
        ]);

        $productLines = [];
        $productsTotal = 0;
        foreach ($request->input('products', []) as $line) {
            $product = Product::find($line['product_id']);
            if (!$product) continue;
            $qty = (int) $line['quantity'];
            $lineTotal = $product->price * $qty;
            $productsTotal += $lineTotal;
            $productLines[] = [
                'product_id' => $product->id,
                'name' => $product->name,
                'price' => $product->price,
                'quantity' => $qty,
                'total' => $lineTotal,
            ];
        }

        $discountType = $request->discount_type;
        $discountValue = (float) ($request->discount_value ?? 0);
        $discountAmount = 0;
        if ($discountType === 'percent') {
            $discountAmount = round($productsTotal * min($discountValue, 100) / 100, 2);
        } elseif ($discountType === 'flat') {
            $discountAmount = round(min($discountValue, $productsTotal), 2);
        }

        $tipAmount = round((float) ($request->tip_amount ?? 0), 2);
        $totalAmount = round(max(0, $productsTotal - $discountAmount) + $tipAmount, 2);

        $payments = array_filter([
            'cash' => (float) ($request->input('payments.cash', 0)),
            'card' => (float) ($request->input('payments.card', 0)),
            'online_transfer' => (float) ($request->input('payments.online_transfer', 0)),
        ], fn($amount) => $amount > 0);

        $paidTotal = round(array_sum($payments), 2);

        if (empty($payments) || abs($paidTotal - $totalAmount) > 0.01) {
            return redirect()->back()
                ->with('error', sprintf(
                    'Payment total (%.2f QAR) does not match the amount due (%.2f QAR).',
                    $paidTotal,
                    $totalAmount
                ))->withInput();
        }

        $customer = null;
        if ($request->filled('customer_phone')) {
            $phone = preg_replace('/\D/', '', $request->customer_phone);
            $customer = \App\Models\Customer::firstOrNew(['phone' => $phone]);
            if ($request->customer_name) {
                $customer->name = $request->customer_name;
            }
            $customer->save();
        }

        $sale = Sale::create([
            'customer_id' => $customer?->id,
            'created_by' => auth()->id(),
            'branch' => $request->branch,
            'services_total' => 0,
            'products_total' => $productsTotal,
            'discount_type' => $discountType,
            'discount_value' => $discountValue,
            'discount_amount' => $discountAmount,
            'tip_amount' => $tipAmount,
            'total_amount' => $totalAmount,
        ]);

        foreach ($productLines as $line) {
            SaleItem::create([
                'sale_id' => $sale->id,
                'type' => 'product',
                'product_id' => $line['product_id'],
                'name' => $line['name'],
                'price' => $line['price'],
                'quantity' => $line['quantity'],
                'total' => $line['total'],
            ]);
        }

        foreach ($payments as $method => $amount) {
            SalePayment::create([
                'sale_id' => $sale->id,
                'method' => $method,
                'amount' => round($amount, 2),
            ]);
        }

        if ($customer) {
            $customer->earnPointsForSale($sale);
        }

        return redirect()->route('appointments.revenue.index')
            ->with('success', 'Sale recorded successfully.');
    }
}
