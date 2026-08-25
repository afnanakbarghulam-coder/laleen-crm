<?php

namespace App\Http\Controllers;

use App\Models\Expense;
use App\Models\Sale;
use App\Models\SalePayment;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class FinanceController extends Controller
{
    const BRANCHES = [
        'old_airport' => 'Old Airport',
        'wakrah' => 'Al Wakrah',
        'home_service' => 'Home Service',
    ];

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

        $branch = $request->filled('branch') && array_key_exists($request->branch, self::BRANCHES)
            ? $request->branch
            : null;

        $salesQuery = Sale::with(['customer', 'staff', 'payments', 'items'])
            ->whereBetween('created_at', [$from, $to]);

        $expensesQuery = Expense::with('creator')
            ->whereDate('expense_date', '>=', $from->toDateString())
            ->whereDate('expense_date', '<=', $to->toDateString());

        if ($branch) {
            $salesQuery->where('branch', $branch);
            $expensesQuery->where('branch', $branch);
        }

        $sales = $salesQuery->orderByDesc('created_at')->get();
        $expenses = $expensesQuery->orderByDesc('expense_date')->get();

        $grossServices = (float) $sales->sum('services_total');
        $grossProducts = (float) $sales->sum('products_total');
        $grossSales = $grossServices + $grossProducts;
        $totalDiscounts = (float) $sales->sum('discount_amount');
        $totalTips = (float) $sales->sum('tip_amount');
        $totalExpenses = (float) $expenses->sum('amount');
        $netProfit = $grossSales - $totalExpenses;
        $profitMargin = $grossSales > 0 ? ($netProfit / $grossSales) * 100 : 0;

        $paymentTotals = SalePayment::whereIn('sale_id', $sales->pluck('id'))
            ->selectRaw('method, SUM(amount) as total')
            ->groupBy('method')
            ->pluck('total', 'method');

        $branchBreakdown = $this->branchBreakdown($from, $to);
        $trend = $this->trendBuckets($from, $to, $sales, $expenses);

        return view('revenue.index', [
            'from' => $from,
            'to' => $to,
            'branch' => $branch,
            'branches' => self::BRANCHES,
            'expenseCategories' => Expense::CATEGORIES,
            'sales' => $sales,
            'expenses' => $expenses,
            'grossServices' => $grossServices,
            'grossProducts' => $grossProducts,
            'grossSales' => $grossSales,
            'totalDiscounts' => $totalDiscounts,
            'totalTips' => $totalTips,
            'totalExpenses' => $totalExpenses,
            'netProfit' => $netProfit,
            'profitMargin' => $profitMargin,
            'paymentTotals' => $paymentTotals,
            'branchBreakdown' => $branchBreakdown,
            'trendLabels' => $trend['labels'],
            'trendRevenue' => $trend['revenue'],
            'trendExpenses' => $trend['expenses'],
        ]);
    }

    public function storeExpense(Request $request)
    {
        $validated = $request->validate([
            'branch' => 'nullable|in:old_airport,wakrah,home_service',
            'category' => 'required|string|max:100',
            'amount' => 'required|numeric|min:0.01',
            'expense_date' => 'required|date',
            'description' => 'nullable|string|max:1000',
        ]);

        Expense::create($validated + ['created_by' => auth()->id()]);

        return back()->with('success', 'Expense added successfully.');
    }

    public function destroyExpense(Expense $expense)
    {
        $expense->delete();

        return back()->with('success', 'Expense deleted successfully.');
    }

    /**
     * Gross sales, expenses and net profit per branch for the given date range,
     * independent of any branch filter — powers the branch comparison chart.
     */
    private function branchBreakdown(Carbon $from, Carbon $to): array
    {
        $salesByBranch = Sale::whereBetween('created_at', [$from, $to])
            ->selectRaw('branch, SUM(services_total) as services_total, SUM(products_total) as products_total')
            ->groupBy('branch')
            ->get()
            ->keyBy('branch');

        $expensesByBranch = Expense::whereDate('expense_date', '>=', $from->toDateString())
            ->whereDate('expense_date', '<=', $to->toDateString())
            ->selectRaw('branch, SUM(amount) as total')
            ->groupBy('branch')
            ->pluck('total', 'branch');

        $breakdown = [];
        foreach (self::BRANCHES as $key => $label) {
            $row = $salesByBranch->get($key);
            $branchSales = $row ? (float) $row->services_total + (float) $row->products_total : 0;
            $branchExpenses = (float) ($expensesByBranch[$key] ?? 0);

            $breakdown[] = [
                'key' => $key,
                'label' => $label,
                'sales' => $branchSales,
                'expenses' => $branchExpenses,
                'profit' => $branchSales - $branchExpenses,
            ];
        }

        return $breakdown;
    }

    /**
     * Daily revenue/expense series for the trend chart. Falls back to weekly
     * buckets once the range is too wide for a legible daily x-axis.
     */
    private function trendBuckets(Carbon $from, Carbon $to, $sales, $expenses): array
    {
        $spanDays = $from->diffInDays($to) + 1;
        $weekly = $spanDays > 62;

        $buckets = [];
        $cursor = $from->copy()->startOfDay();
        $end = $to->copy()->startOfDay();

        while ($cursor->lte($end)) {
            $key = $weekly ? $cursor->format('o-W') : $cursor->format('Y-m-d');
            if (!isset($buckets[$key])) {
                $buckets[$key] = [
                    'label' => $weekly ? 'Wk ' . $cursor->format('W M') : $cursor->format('d M'),
                    'revenue' => 0.0,
                    'expenses' => 0.0,
                ];
            }
            $cursor->addDay();
        }

        foreach ($sales as $sale) {
            $key = $weekly ? $sale->created_at->format('o-W') : $sale->created_at->format('Y-m-d');
            if (isset($buckets[$key])) {
                $buckets[$key]['revenue'] += (float) $sale->services_total + (float) $sale->products_total;
            }
        }

        foreach ($expenses as $expense) {
            $key = $weekly ? $expense->expense_date->format('o-W') : $expense->expense_date->format('Y-m-d');
            if (isset($buckets[$key])) {
                $buckets[$key]['expenses'] += (float) $expense->amount;
            }
        }

        return [
            'labels' => array_values(array_column($buckets, 'label')),
            'revenue' => array_values(array_column($buckets, 'revenue')),
            'expenses' => array_values(array_column($buckets, 'expenses')),
        ];
    }
}
