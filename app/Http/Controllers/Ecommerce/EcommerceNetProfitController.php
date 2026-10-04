<?php

namespace App\Http\Controllers\Ecommerce;

use App\Http\Controllers\Controller;
use App\Models\EcommerceExpense;
use App\Models\EcommerceSale;
use Carbon\Carbon;
use Illuminate\Http\Request;

class EcommerceNetProfitController extends Controller
{
    public function index(Request $request)
    {
        $from = $request->filled('start_date') ? Carbon::parse($request->start_date)->startOfDay() : now()->startOfMonth();
        $to = $request->filled('end_date') ? Carbon::parse($request->end_date)->startOfDay() : now()->startOfDay();
        if ($from->gt($to)) {
            [$from, $to] = [$to->copy(), $from->copy()];
        }

        $sales = EcommerceSale::whereBetween('created_at', [$from, $to->copy()->endOfDay()])->get();

        $deductionCategories = EcommerceExpense::NET_PROFIT_DEDUCTION_CATEGORIES;
        $deductionExpenses = EcommerceExpense::whereIn('category', $deductionCategories)
            ->whereBetween('expense_date', [$from->toDateString(), $to->toDateString()])
            ->get();

        $totalRevenue = (float) $sales->sum('total_price');

        $amountsByCategory = $deductionExpenses->groupBy('category')->map->sum('amount');
        $deductionBreakdown = collect($deductionCategories)->map(fn ($category) => [
            'category' => $category,
            'amount' => (float) ($amountsByCategory[$category] ?? 0),
        ]);

        $totalDeductions = (float) $deductionBreakdown->sum('amount');
        $netProfit = $totalRevenue - $totalDeductions;
        $netMargin = $totalRevenue > 0 ? ($netProfit / $totalRevenue) * 100 : 0;

        // Falls back to weekly buckets once the range is too wide for a legible daily x-axis,
        // mirroring the Sales Trend and Bookings Trend charts so every module buckets consistently.
        $weekly = $from->diffInDays($to) > 62;
        $bucketKey = fn ($date) => $weekly ? $date->format('o-W') : $date->format('Y-m-d');
        $bucketLabel = fn ($date) => $weekly ? 'Wk ' . $date->format('W M') : $date->format('d M');

        $revenueByBucket = $sales->groupBy(fn ($sale) => $bucketKey($sale->created_at));
        $deductionsByBucket = $deductionExpenses->groupBy(fn ($expense) => $bucketKey($expense->expense_date));

        $chartLabels = [];
        $chartNetProfit = [];
        $seenBuckets = [];
        $cursor = $from->copy();
        while ($cursor->lte($to)) {
            $key = $bucketKey($cursor);
            if (!in_array($key, $seenBuckets, true)) {
                $seenBuckets[] = $key;
                $dailyRevenue = (float) $revenueByBucket->get($key, collect())->sum('total_price');
                $dailyDeductions = (float) $deductionsByBucket->get($key, collect())->sum('amount');
                $chartLabels[] = $bucketLabel($cursor);
                $chartNetProfit[] = round($dailyRevenue - $dailyDeductions, 2);
            }
            $cursor->addDay();
        }

        $filterText = ($request->filled('start_date') || $request->filled('end_date'))
            ? 'From ' . $from->format('M d, Y') . ' to ' . $to->format('M d, Y')
            : 'This month to date';

        return view('ecommerce.net-profit', [
            'totalRevenue' => $totalRevenue,
            'totalDeductions' => $totalDeductions,
            'netProfit' => $netProfit,
            'netMargin' => $netMargin,
            'deductionBreakdown' => $deductionBreakdown,
            'filterText' => $filterText,
            'startDate' => $request->input('start_date'),
            'endDate' => $request->input('end_date'),
            'chartLabels' => $chartLabels,
            'chartNetProfit' => $chartNetProfit,
        ]);
    }
}
