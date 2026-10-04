<?php

namespace App\Http\Controllers\Ecommerce;

use App\Http\Controllers\Controller;
use App\Models\EcommerceExpense;
use App\Models\EcommerceSale;

class EcommerceNetProfitController extends Controller
{
    public function index()
    {
        $totalRevenue = (float) EcommerceSale::sum('total_price');

        $deductionCategories = EcommerceExpense::NET_PROFIT_DEDUCTION_CATEGORIES;

        $amountsByCategory = EcommerceExpense::whereIn('category', $deductionCategories)
            ->selectRaw('category, SUM(amount) as total')
            ->groupBy('category')
            ->pluck('total', 'category');

        $deductionBreakdown = collect($deductionCategories)->map(fn ($category) => [
            'category' => $category,
            'amount' => (float) ($amountsByCategory[$category] ?? 0),
        ]);

        $totalDeductions = (float) $deductionBreakdown->sum('amount');
        $netProfit = $totalRevenue - $totalDeductions;
        $netMargin = $totalRevenue > 0 ? ($netProfit / $totalRevenue) * 100 : 0;

        return view('ecommerce.net-profit', [
            'totalRevenue' => $totalRevenue,
            'totalDeductions' => $totalDeductions,
            'netProfit' => $netProfit,
            'netMargin' => $netMargin,
            'deductionBreakdown' => $deductionBreakdown,
        ]);
    }
}
