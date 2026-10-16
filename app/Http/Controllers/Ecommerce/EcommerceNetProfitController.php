<?php

namespace App\Http\Controllers\Ecommerce;

use App\Http\Controllers\Controller;
use App\Models\EcommerceExpense;
use App\Models\EcommerceSale;
use App\Models\Partner;
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

        $sales = EcommerceSale::with('product')
            ->whereBetween('created_at', [$from, $to->copy()->endOfDay()])
            ->orderByDesc('created_at')
            ->get();

        $deductionCategories = EcommerceExpense::NET_PROFIT_DEDUCTION_CATEGORIES;
        $deductionExpenses = EcommerceExpense::whereIn('category', $deductionCategories)
            ->whereBetween('expense_date', [$from->toDateString(), $to->toDateString()])
            ->get();

        $totalRevenue = (float) $sales->sum('total_price');

        // COGS is read from each sale's own unit_cogs snapshot (taken at the
        // moment it was logged), not recalculated against current pricing —
        // so this stays accurate even after the BOM cost model changes later.
        // Sales logged before this column existed have a null unit_cogs and
        // correctly contribute PKR 0 COGS (their cost was never captured).
        $totalCogs = (float) $sales->sum(fn (EcommerceSale $sale) => (float) $sale->quantity * (float) ($sale->unit_cogs ?? 0));
        $grossProfit = $totalRevenue - $totalCogs;
        $grossMargin = $totalRevenue > 0 ? ($grossProfit / $totalRevenue) * 100 : 0;

        $amountsByCategory = $deductionExpenses->groupBy('category')->map->sum('amount');
        $expenseBreakdown = collect($deductionCategories)->map(fn ($category) => [
            'category' => $category,
            'amount' => (float) ($amountsByCategory[$category] ?? 0),
        ]);

        // Order-level costs captured on the sale itself. These were previously
        // shown only in the per-transaction table, so the headline KPI ignored
        // them entirely — they now roll up alongside the operating expenses.
        $perSaleShipping = (float) $sales->sum(fn (EcommerceSale $sale) => (float) ($sale->shipping_cost ?? 0));
        $perSaleTax = (float) $sales->sum(fn (EcommerceSale $sale) => (float) ($sale->tax_amount ?? 0));
        $perSaleMetaAds = (float) $sales->sum(fn (EcommerceSale $sale) => (float) ($sale->meta_ad_allocation ?? 0));
        $saleLevelDeductions = $perSaleShipping + $perSaleTax + $perSaleMetaAds;

        $deductionBreakdown = $expenseBreakdown->concat([
            ['category' => 'Per-Sale Shipping', 'amount' => $perSaleShipping],
            ['category' => 'Per-Sale Tax', 'amount' => $perSaleTax],
            ['category' => 'Per-Sale Meta Ads', 'amount' => $perSaleMetaAds],
        ])->values();

        $totalDeductions = (float) $deductionBreakdown->sum('amount');
        $trueNetProfit = $grossProfit - $totalDeductions;
        $trueNetMargin = $totalRevenue > 0 ? ($trueNetProfit / $totalRevenue) * 100 : 0;

        // Falls back to weekly buckets once the range is too wide for a legible daily x-axis,
        // mirroring the Sales Trend and Bookings Trend charts so every module buckets consistently.
        $weekly = $from->diffInDays($to) > 62;
        $bucketKey = fn ($date) => $weekly ? $date->format('o-W') : $date->format('Y-m-d');
        $bucketLabel = fn ($date) => $weekly ? 'Wk ' . $date->format('W M') : $date->format('d M');

        $salesByBucket = $sales->groupBy(fn ($sale) => $bucketKey($sale->created_at));
        $deductionsByBucket = $deductionExpenses->groupBy(fn ($expense) => $bucketKey($expense->expense_date));

        // Each bucket mirrors the headline formula exactly —
        // Revenue - COGS - Per-Sale Deductions - Operating Expenses — so the
        // trend line, the per-transaction table and the True Net Profit KPI
        // all reconcile to the rupee instead of the chart silently omitting
        // COGS and reading high.
        $chartLabels = [];
        $chartNetProfit = [];
        $seenBuckets = [];
        $cursor = $from->copy();
        while ($cursor->lte($to)) {
            $key = $bucketKey($cursor);
            if (!in_array($key, $seenBuckets, true)) {
                $seenBuckets[] = $key;
                $bucketSales = $salesByBucket->get($key, collect());

                $dailyRevenue = (float) $bucketSales->sum('total_price');
                $dailyCogs = (float) $bucketSales->sum(fn (EcommerceSale $sale) => (float) $sale->quantity * (float) ($sale->unit_cogs ?? 0));
                $dailySaleDeductions = (float) $bucketSales->sum(fn (EcommerceSale $sale) => (float) ($sale->shipping_cost ?? 0)
                    + (float) ($sale->tax_amount ?? 0)
                    + (float) ($sale->meta_ad_allocation ?? 0));
                $dailyExpenseDeductions = (float) $deductionsByBucket->get($key, collect())->sum('amount');

                $chartLabels[] = $bucketLabel($cursor);
                $chartNetProfit[] = round($dailyRevenue - $dailyCogs - $dailySaleDeductions - $dailyExpenseDeductions, 2);
            }
            $cursor->addDay();
        }

        $filterText = ($request->filled('start_date') || $request->filled('end_date'))
            ? 'From ' . $from->format('M d, Y') . ' to ' . $to->format('M d, Y')
            : 'This month to date';

        $partners = Partner::orderBy('id')->get();
        $partnerProfitShare = $partners->map(fn (Partner $partner) => [
            'partner' => $partner,
            'equity_percentage' => (float) $partner->equity_percentage,
            'share' => $trueNetProfit * ((float) $partner->equity_percentage / 100),
        ]);

        return view('ecommerce.net-profit', [
            'sales' => $sales,
            'totalRevenue' => $totalRevenue,
            'totalCogs' => $totalCogs,
            'grossProfit' => $grossProfit,
            'grossMargin' => $grossMargin,
            'totalDeductions' => $totalDeductions,
            'saleLevelDeductions' => $saleLevelDeductions,
            'expenseDeductions' => (float) $expenseBreakdown->sum('amount'),
            'trueNetProfit' => $trueNetProfit,
            'trueNetMargin' => $trueNetMargin,
            'deductionBreakdown' => $deductionBreakdown,
            'partners' => $partners,
            'partnerProfitShare' => $partnerProfitShare,
            'bomAccountingNote' => EcommerceExpense::BOM_ACCOUNTING_NOTE,
            'filterText' => $filterText,
            'startDate' => $request->input('start_date'),
            'endDate' => $request->input('end_date'),
            'chartLabels' => $chartLabels,
            'chartNetProfit' => $chartNetProfit,
        ]);
    }
}
