<?php

namespace App\Http\Controllers\Ecommerce;

use App\Http\Controllers\Controller;
use App\Models\EcommercePricingModel;
use App\Models\EcommerceProduct;
use App\Models\EcommerceSale;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class EcommerceSalesController extends Controller
{
    private const CHANNELS = [
        'Shopify (Pakistan)',
        'Organic (Pakistan)',
        'Salon (Old Airport)',
        'Salon (Wakrah)',
        'Organic (Qatar)',
        'Backbar Use (Qatar)',
        'Damage/Expiry (Pakistan)',
        'Damage/Expiry (Qatar)',
    ];

    private const NON_REVENUE_CHANNELS = [
        'Backbar Use (Qatar)',
        'Damage/Expiry (Pakistan)',
        'Damage/Expiry (Qatar)',
    ];

    public function index(Request $request)
    {
        $selectedChannels = [];
        if ($request->has('channels') && is_array($request->channels) && count($request->channels) > 0) {
            $selectedChannels = array_values(array_intersect($request->channels, self::CHANNELS));
        }

        $salesQuery = EcommerceSale::with('product')->orderByDesc('created_at');

        if (!empty($selectedChannels)) {
            $salesQuery->whereIn('channel', $selectedChannels);
        }

        if ($request->filled('start_date')) {
            $salesQuery->whereDate('created_at', '>=', $request->start_date);
        }

        if ($request->filled('end_date')) {
            $salesQuery->whereDate('created_at', '<=', $request->end_date);
        }

        $sales = $salesQuery->get();
        $products = EcommerceProduct::orderBy('name')->get();
        $salePricesByProduct = EcommercePricingModel::pluck('selling_price', 'ecommerce_product_id');

        $totalRevenue = $sales->sum('total_price');
        $totalItemsSoldRevenue = $sales->whereNotIn('channel', self::NON_REVENUE_CHANNELS)->sum('quantity');
        $totalItemsUsedDamaged = $sales->whereIn('channel', self::NON_REVENUE_CHANNELS)->sum('quantity');

        $filterText = !empty($selectedChannels)
            ? 'Filtered: ' . implode(', ', $selectedChannels)
            : 'Across all channels';

        if ($request->filled('start_date') || $request->filled('end_date')) {
            if ($request->filled('start_date') && $request->filled('end_date')) {
                $dateRangeText = 'From ' . Carbon::parse($request->start_date)->format('M d, Y')
                    . ' to ' . Carbon::parse($request->end_date)->format('M d, Y');
            } elseif ($request->filled('start_date')) {
                $dateRangeText = 'From ' . Carbon::parse($request->start_date)->format('M d, Y');
            } else {
                $dateRangeText = 'Up to ' . Carbon::parse($request->end_date)->format('M d, Y');
            }
            $filterText .= ' | ' . $dateRangeText;
        } else {
            $filterText .= ' | All time';
        }

        return view('ecommerce.sales', [
            'sales' => $sales,
            'products' => $products,
            'salePricesByProduct' => $salePricesByProduct,
            'totalRevenue' => $totalRevenue,
            'totalItemsSoldRevenue' => $totalItemsSoldRevenue,
            'totalItemsUsedDamaged' => $totalItemsUsedDamaged,
            'filterText' => $filterText,
            'channels' => self::CHANNELS,
            'selectedChannels' => $selectedChannels,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'ecommerce_product_id' => 'required|exists:ecommerce_products,id',
            'reference_id' => 'nullable|string|max:255',
            'customer_name' => 'nullable|string|max:255',
            'quantity' => 'required|integer|min:1',
            'channel' => 'required|in:' . implode(',', self::CHANNELS),
            'reason' => 'nullable|string|max:255',
            'unit_price' => 'nullable|numeric|min:0',
        ]);

        $quantity = (int) $validated['quantity'];
        $isNonRevenue = in_array($validated['channel'], self::NON_REVENUE_CHANNELS, true);
        $unitPrice = $isNonRevenue ? (float) ($validated['unit_price'] ?? 0) : (float) $validated['unit_price'];
        $totalPrice = $quantity * $unitPrice;

        $isPakistan = str_contains($validated['channel'], 'Pakistan');
        $source = $isPakistan ? 'pakistan' : 'qatar';
        $stockField = "stock_{$source}";
        $soldField = "sold_{$source}";
        $locationName = $isPakistan ? 'Pakistan' : 'Qatar';

        $product = EcommerceProduct::findOrFail($validated['ecommerce_product_id']);

        if ((float) $product->{$stockField} < $quantity) {
            return back()->withErrors(['error' => "Insufficient stock in {$locationName} for this sale."]);
        }

        DB::transaction(function () use ($validated, $quantity, $unitPrice, $totalPrice, $stockField, $soldField) {
            EcommerceSale::create([
                'ecommerce_product_id' => $validated['ecommerce_product_id'],
                'reference_id' => $validated['reference_id'] ?? null,
                'customer_name' => $validated['customer_name'] ?? null,
                'quantity' => $quantity,
                'channel' => $validated['channel'],
                'reason' => $validated['reason'] ?? null,
                'unit_price' => $unitPrice,
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
