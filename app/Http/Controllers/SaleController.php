<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\SalePayment;
use App\Models\Staff;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SaleController extends Controller
{
    public function create()
    {
        $products = Product::orderBy('name')->get();
        $staffList = Staff::active()->orderBy('name')->get();
        return view('revenue.new-sale', compact('products', 'staffList'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'customer_id' => 'nullable|exists:customers,id',
            'customer_phone' => 'nullable|string|max:20',
            'customer_name' => 'nullable|string|max:255',
            'staff_id' => 'nullable|exists:staff,id',
            'branch' => 'required|in:old_airport,wakrah,home_service',
            'products' => 'required|array|min:1',
            'products.*.product_id' => 'required|exists:products,id',
            'products.*.quantity' => 'required|integer|min:1',
            'products.*.staff_id' => 'nullable|exists:staff,id',
            'discount_type' => 'nullable|in:flat,percent',
            'discount_value' => 'nullable|numeric|min:0',
            'tip_amount' => 'nullable|numeric|min:0',
            'payments.cash' => 'nullable|numeric|min:0',
            'payments.card' => 'nullable|numeric|min:0',
            'payments.online_transfer' => 'nullable|numeric|min:0',
        ]);

        $saleStaffId = $request->filled('staff_id') ? (int) $request->staff_id : null;
        $productLines = [];
        $productsTotal = 0;
        foreach ($request->input('products', []) as $line) {
            $product = Product::find($line['product_id']);
            if (!$product) continue;
            $qty = (int) $line['quantity'];
            $lineTotal = $product->price * $qty;
            $productsTotal += $lineTotal;
            $lineStaffId = !empty($line['staff_id']) ? (int) $line['staff_id'] : $saleStaffId;
            $productLines[] = [
                'product' => $product,
                'product_id' => $product->id,
                'staff_id' => $lineStaffId,
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

        // An existing client picked from search already has a real id - use
        // it directly rather than re-matching by phone, which could land on
        // the wrong record if two customers share overlapping digits. The
        // phone-based lookup below only applies to a brand-new client typed
        // into the "Add new client" form, which has no id yet.
        $customer = null;
        if ($request->filled('customer_id')) {
            $customer = \App\Models\Customer::find($request->customer_id);
        }

        if (!$customer && $request->filled('customer_phone')) {
            $rawPhone = preg_replace('/\D/', '', $request->customer_phone);
            $normalizedPhone = \App\Models\Customer::normalizePhone($request->customer_phone);
            $phoneCandidates = array_values(array_unique(array_filter([$normalizedPhone, $rawPhone])));
            if (!empty($phoneCandidates)) {
                $customer = \App\Models\Customer::whereIn('phone', $phoneCandidates)->first()
                    ?: new \App\Models\Customer(['phone' => $normalizedPhone !== '' ? $normalizedPhone : $rawPhone]);
                if ($request->customer_name) {
                    $customer->name = $request->customer_name;
                }
                $customer->save();
            }
        }

        DB::transaction(function () use (
            $customer,
            $saleStaffId,
            $request,
            $productsTotal,
            $discountType,
            $discountValue,
            $discountAmount,
            $tipAmount,
            $totalAmount,
            $productLines,
            $payments
        ) {
            $sale = Sale::create([
                'customer_id' => $customer?->id,
                'staff_id' => $saleStaffId,
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
                    'staff_id' => $line['staff_id'] ?? null,
                    'name' => $line['name'],
                    'price' => $line['price'],
                    'quantity' => $line['quantity'],
                    'total' => $line['total'],
                ]);

                /** @var Product $productModel */
                $productModel = $line['product'];
                $productModel->update([
                    'stock_quantity' => max(0, (int) $productModel->stock_quantity - (int) $line['quantity']),
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
        });

        return redirect()->route('appointments.revenue.index')
            ->with('success', 'Sale recorded successfully.');
    }
}
