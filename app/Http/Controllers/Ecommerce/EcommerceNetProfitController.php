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
        $region = EcommerceSale::normalizeRegion($request->query('region', 'pk'));
        $currency = EcommerceSale::currencyForRegion($region);
        $regionChannels = EcommerceSale::channelsForRegion($region);

        $from = $request->filled('start_date') ? Carbon::parse($request->start_date)->startOfDay() : now()->startOfMonth();
        $to = $request->filled('end_date') ? Carbon::parse($request->end_date)->startOfDay() : now()->startOfDay();
        if ($from->gt($to)) {
            [$from, $to] = [$to->copy(), $from->copy()];
        }

        $sales = EcommerceSale::with('product')
            ->whereIn('channel', $regionChannels)
            ->whereBetween('created_at', [$from, $to->copy()->endOfDay()])
            ->orderByDesc('created_at')
            ->get();

        // Single source of truth for operating expenses: monthly EcommerceExpense records in this currency,
        // excluding physical BOM inventory restocks (which hit profit via unit_cogs on sale).
        $deductionExpenses = EcommerceExpense::where('currency', $currency)
            ->whereNotIn('category', EcommerceExpense::BOM_INVENTORY_CATEGORIES)
            ->whereBetween('expense_date', [$from->toDateString(), $to->toDateString()])
            ->get();

        // Realized Revenue Sales ONLY (paid or delivered, excluding unpaid_cod, cancelled, rto, and non-revenue channels)
        $revenueSales = $sales->filter(fn (EcommerceSale $sale) => $sale->isRealizedRevenue());

        // Pending COD Pipeline Sales (excluded from realized revenue until delivered/paid)
        $pendingCodSales = $sales->filter(fn (EcommerceSale $sale) => $sale->isPendingCod());
        $pendingCodRevenue = (float) $pendingCodSales->sum('total_price');
        $pendingCodOrders = EcommerceSale::countDistinctOrders($pendingCodSales);

        // Non-revenue inventory usage/shrinkage (Backbar Use, Damage/Expiry) excluding cancelled/rto
        $nonRevenueSales = $sales->filter(
            fn (EcommerceSale $sale) => in_array($sale->channel, EcommerceSale::NON_REVENUE_CHANNELS, true)
                && !in_array($sale->order_status, EcommerceSale::INACTIVE_ORDER_STATUSES, true)
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

        // Per-sale shipping, tax, and Meta ad fields are kept strictly as Unit Economics Reference Only
        // and are NOT subtracted from Gross Profit (to prevent double-counting against Monthly Expenses).
        $referencePerSaleShipping = (float) $revenueSales->sum(fn (EcommerceSale $sale) => (float) ($sale->shipping_cost ?? 0));
        $referencePerSaleTax = (float) $revenueSales->sum(fn (EcommerceSale $sale) => (float) ($sale->tax_amount ?? 0));
        $referencePerSaleMetaAds = (float) $revenueSales->sum(fn (EcommerceSale $sale) => (float) ($sale->meta_ad_allocation ?? 0));
        $referenceOrderCostsTotal = $referencePerSaleShipping + $referencePerSaleTax + $referencePerSaleMetaAds;

        $deductionBreakdown = $expenseBreakdown->concat([
            ['category' => 'Backbar & Shrinkage COGS', 'amount' => $nonRevenueShrinkageCogs],
        ])->values();

        $expenseDeductions = (float) $expenseBreakdown->sum('amount');
        $totalDeductions = $expenseDeductions + $nonRevenueShrinkageCogs;
        $trueNetProfit = $grossProfit - $totalDeductions;
        $trueNetMargin = $totalRevenue > 0 ? ($trueNetProfit / $totalRevenue) * 100 : 0;

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
                $dailyExpenseDeductions = (float) $deductionsByBucket->get($key, collect())->sum('amount');

                $chartLabels[] = $bucketLabel($cursor);
                $chartNetProfit[] = round($dailyRevenue - $dailyCogs - $dailyShrinkageCogs - $dailyExpenseDeductions, 2);
            }
            $cursor->addDay();
        }

        $filterText = ($request->filled('start_date') || $request->filled('end_date'))
            ? 'From ' . $from->format('M d, Y') . ' to ' . $to->format('M d, Y')
            : 'This month to date';

        $partners = Partner::withTransactionTotals($currency)->orderBy('id')->get();
        $partnerProfitShare = $partners->map(fn (Partner $partner) => [
            'partner' => $partner,
            'equity_percentage' => (float) $partner->equity_percentage,
            'share' => $trueNetProfit * ((float) $partner->equity_percentage / 100),
        ]);

        return view('ecommerce.net-profit', [
            'region' => $region,
            'currency' => $currency,
            'sales' => $sales,
            'totalRevenue' => $totalRevenue,
            'pendingCodRevenue' => $pendingCodRevenue,
            'pendingCodOrders' => $pendingCodOrders,
            'totalCogs' => $totalCogs,
            'grossProfit' => $grossProfit,
            'grossMargin' => $grossMargin,
            'totalDeductions' => $totalDeductions,
            'nonRevenueShrinkageCogs' => $nonRevenueShrinkageCogs,
            'expenseDeductions' => $expenseDeductions,
            'referenceOrderCostsTotal' => $referenceOrderCostsTotal,
            'saleLevelDeductions' => $nonRevenueShrinkageCogs,
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
        $region = EcommerceSale::normalizeRegion($request->query('region', 'pk'));
        $currency = EcommerceSale::currencyForRegion($region);
        $regionChannels = EcommerceSale::channelsForRegion($region);

        $from = $request->filled('start_date') ? Carbon::parse($request->start_date)->startOfDay() : now()->startOfMonth();
        $to = $request->filled('end_date') ? Carbon::parse($request->end_date)->startOfDay() : now()->startOfDay();
        if ($from->gt($to)) {
            [$from, $to] = [$to->copy(), $from->copy()];
        }

        $sales = EcommerceSale::with('product')
            ->whereIn('channel', $regionChannels)
            ->whereBetween('created_at', [$from, $to->copy()->endOfDay()])
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(50)
            ->withQueryString();

        $filterText = ($request->filled('start_date') || $request->filled('end_date'))
            ? 'From ' . $from->format('M d, Y') . ' to ' . $to->format('M d, Y')
            : 'This month to date';

        return view('ecommerce.net-profit-transactions', [
            'region' => $region,
            'currency' => $currency,
            'sales' => $sales,
            'filterText' => $filterText,
            'startDate' => $request->input('start_date'),
            'endDate' => $request->input('end_date'),
        ]);
    }
}
