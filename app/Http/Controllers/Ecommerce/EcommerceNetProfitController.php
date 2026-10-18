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

        // Patch 1: Exclude BOM inventory restocks instead of relying on a hardcoded inclusion
        // whitelist, so custom operating expense categories are properly deducted.
        $deductionExpenses = EcommerceExpense::whereNotIn('category', EcommerceExpense::BOM_INVENTORY_CATEGORIES)
            ->whereBetween('expense_date', [$from->toDateString(), $to->toDateString()])
            ->get();

        // Patch 4: Separate revenue sales from non-revenue usage/shrinkage so Backbar Use
        // and Damage/Expiry do not contaminate Gross Profit and Gross Margin %.
        $revenueSales = $sales->reject(
            fn (EcommerceSale $sale) => in_array($sale->channel, EcommerceSale::NON_REVENUE_CHANNELS, true)
        );
        $nonRevenueSales = $sales->filter(
            fn (EcommerceSale $sale) => in_array($sale->channel, EcommerceSale::NON_REVENUE_CHANNELS, true)
        );

        $totalRevenue = (float) $revenueSales->sum('total_price');
        $totalCogs = (float) $revenueSales->sum(
            fn (EcommerceSale $sale) => (float) $sale->quantity * (float) ($sale->unit_cogs ?? 0)
        );
        $grossProfit = $totalRevenue - $totalCogs;
        $grossMargin = $totalRevenue > 0 ? ($grossProfit / $totalRevenue) * 100 : 0;

        $nonRevenueShrinkageCogs = (float) $nonRevenueSales->sum(
            fn (EcommerceSale $sale) => (float) $sale->quantity * (float) ($sale->unit_cogs ?? 0)
        );

        $expenseBreakdown = $deductionExpenses
            ->groupBy(fn (EcommerceExpense $expense) => $expense->category ?: 'Other Operating')
            ->map(fn ($group, $category) => [
                'category' => $category,
                'amount' => (float) $group->sum('amount'),
            ])
            ->values();

        // Order-level costs captured on revenue sales plus non-revenue shrinkage COGS.
        $perSaleShipping = (float) $revenueSales->sum(fn (EcommerceSale $sale) => (float) ($sale->shipping_cost ?? 0));
        $perSaleTax = (float) $revenueSales->sum(fn (EcommerceSale $sale) => (float) ($sale->tax_amount ?? 0));
        $perSaleMetaAds = (float) $revenueSales->sum(fn (EcommerceSale $sale) => (float) ($sale->meta_ad_allocation ?? 0));
        $saleLevelDeductions = $perSaleShipping + $perSaleTax + $perSaleMetaAds + $nonRevenueShrinkageCogs;

        $deductionBreakdown = $expenseBreakdown->concat([
            ['category' => 'Per-Sale Shipping', 'amount' => $perSaleShipping],
            ['category' => 'Per-Sale Tax', 'amount' => $perSaleTax],
            ['category' => 'Per-Sale Meta Ads', 'amount' => $perSaleMetaAds],
            ['category' => 'Backbar & Shrinkage COGS', 'amount' => $nonRevenueShrinkageCogs],
        ])->values();

        $totalDeductions = (float) $deductionBreakdown->sum('amount');
        $trueNetProfit = $grossProfit - $totalDeductions;
        $trueNetMargin = $totalRevenue > 0 ? ($trueNetProfit / $totalRevenue) * 100 : 0;

        // Falls back to weekly buckets once the range is too wide for a legible daily x-axis,
        // mirroring the Sales Trend and Bookings Trend charts so every module buckets consistently.
        $weekly = $from->diffInDays($to) > 62;
        $bucketKey = fn ($date) => $weekly ? $date->format('o-W') : $date->format('Y-m-d');
        $bucketLabel = fn ($date) => $weekly ? 'Wk ' . $date->format('W M') : $date->format('d M');

        $revenueSalesByBucket = $revenueSales->groupBy(fn ($sale) => $bucketKey($sale->created_at));
        $nonRevenueSalesByBucket = $nonRevenueSales->groupBy(fn ($sale) => $bucketKey($sale->created_at));
        $deductionsByBucket = $deductionExpenses->groupBy(fn ($expense) => $bucketKey($expense->expense_date));

        $chartLabels = [];
        $chartNetProfit = [];
        $seenBuckets = [];
        $cursor = $from->copy();
        while ($cursor->lte($to)) {
            $key = $bucketKey($cursor);
            if (!in_array($key, $seenBuckets, true)) {
                $seenBuckets[] = $key;
                $bucketRevSales = $revenueSalesByBucket->get($key, collect());
                $bucketNonRevSales = $nonRevenueSalesByBucket->get($key, collect());

                $dailyRevenue = (float) $bucketRevSales->sum('total_price');
                $dailyCogs = (float) $bucketRevSales->sum(fn (EcommerceSale $sale) => (float) $sale->quantity * (float) ($sale->unit_cogs ?? 0));
                $dailyShrinkageCogs = (float) $bucketNonRevSales->sum(fn (EcommerceSale $sale) => (float) $sale->quantity * (float) ($sale->unit_cogs ?? 0));
                $dailySaleDeductions = (float) $bucketRevSales->sum(fn (EcommerceSale $sale) => (float) ($sale->shipping_cost ?? 0)
                    + (float) ($sale->tax_amount ?? 0)
                    + (float) ($sale->meta_ad_allocation ?? 0));
                $dailyExpenseDeductions = (float) $deductionsByBucket->get($key, collect())->sum('amount');

                $chartLabels[] = $bucketLabel($cursor);
                $chartNetProfit[] = round($dailyRevenue - $dailyCogs - $dailyShrinkageCogs - $dailySaleDeductions - $dailyExpenseDeductions, 2);
            }
            $cursor->addDay();
        }

        $filterText = ($request->filled('start_date') || $request->filled('end_date'))
            ? 'From ' . $from->format('M d, Y') . ' to ' . $to->format('M d, Y')
            : 'This month to date';

        $partners = Partner::withTransactionTotals()->orderBy('id')->get();
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

    public function transactions(Request $request)
    {
        $from = $request->filled('start_date') ? Carbon::parse($request->start_date)->startOfDay() : now()->startOfMonth();
        $to = $request->filled('end_date') ? Carbon::parse($request->end_date)->startOfDay() : now()->startOfDay();
        if ($from->gt($to)) {
            [$from, $to] = [$to->copy(), $from->copy()];
        }

        $sales = EcommerceSale::with('product')
            ->whereBetween('created_at', [$from, $to->copy()->endOfDay()])
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(50)
            ->withQueryString();

        $filterText = ($request->filled('start_date') || $request->filled('end_date'))
            ? 'From ' . $from->format('M d, Y') . ' to ' . $to->format('M d, Y')
            : 'This month to date';

        return view('ecommerce.net-profit-transactions', [
            'sales' => $sales,
            'filterText' => $filterText,
            'startDate' => $request->input('start_date'),
            'endDate' => $request->input('end_date'),
        ]);
    }
}
