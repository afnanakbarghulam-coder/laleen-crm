<?php

namespace App\Http\Controllers\Ecommerce;

use App\Http\Controllers\Controller;
use App\Models\EcommerceExpense;
use App\Models\EcommerceProduct;
use App\Models\Partner;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class EcommerceDashboardController extends Controller
{
    public function index(Request $request)
    {
        $from = $request->filled('from')
            ? Carbon::parse($request->from)->startOfDay()
            : now()->startOfMonth();

        $to = $request->filled('to')
            ? Carbon::parse($request->to)->endOfDay()
            : now()->endOfDay();

        if ($from->gt($to)) {
            [$from, $to] = [$to->copy()->startOfDay(), $from->copy()->endOfDay()];
        }

        $products = EcommerceProduct::orderBy('name')->get();

        $productId = $request->filled('product_id') && $products->contains('id', (int) $request->product_id)
            ? (int) $request->product_id
            : optional($products->first())->id;

        $selectedProduct = $products->firstWhere('id', $productId);

        $grossRevenue = (float) $request->input('gross_revenue', 0);
        $unitsSold = (int) $request->input('units_sold', 0);

        $unitCost = $selectedProduct ? $selectedProduct->total_cogs : 0.0;
        $totalCogs = $unitsSold * $unitCost;
        $grossOperatingProfit = $grossRevenue - $totalCogs;

        $expenses = EcommerceExpense::whereDate('expense_date', '>=', $from->toDateString())
            ->whereDate('expense_date', '<=', $to->toDateString())
            ->get();
        $totalOpex = (float) $expenses->sum('amount');

        $netProfit = $grossOperatingProfit - $totalOpex;

        $partners = Partner::orderBy('name')->get();
        $partnerCount = max($partners->count(), 1);
        $equalShare = $netProfit / $partnerCount;

        return view('ecommerce.dashboard', [
            'from' => $from,
            'to' => $to,
            'products' => $products,
            'selectedProduct' => $selectedProduct,
            'productId' => $productId,
            'grossRevenue' => $grossRevenue,
            'unitsSold' => $unitsSold,
            'unitCost' => $unitCost,
            'totalCogs' => $totalCogs,
            'grossOperatingProfit' => $grossOperatingProfit,
            'totalOpex' => $totalOpex,
            'netProfit' => $netProfit,
            'partners' => $partners,
            'equalShare' => $equalShare,
        ]);
    }
}
