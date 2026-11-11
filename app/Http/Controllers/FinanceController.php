<?php

namespace App\Http\Controllers;

use App\Models\Expense;
use App\Models\Sale;
use App\Models\SalePayment;
use App\Models\Staff;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class FinanceController extends Controller
{
    const BRANCHES = [
        'old_airport' => 'Old Airport',
        'wakrah' => 'Al Wakrah',
    ];

    public function revenue(Request $request)
    {
        return $this->index($request);
    }

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

        $branch = $request->filled('branch') && (array_key_exists($request->branch, self::BRANCHES) || $request->branch === 'general')
            ? $request->branch
            : null;

        $staffId = $request->filled('staff_id') && Staff::whereKey($request->staff_id)->exists()
            ? (int) $request->staff_id
            : null;

        $salesQuery = Sale::with(['customer', 'staff', 'payments', 'items'])
            ->whereBetween('created_at', [$from, $to]);

        if ($branch === 'general') {
            $salesQuery->whereNull('branch');
        } elseif ($branch) {
            $salesQuery->where('branch', $branch);
        }

        if ($staffId) {
            $salesQuery->where(function ($q) use ($staffId) {
                $q->whereHas('items', fn ($iq) => $iq->where('staff_id', $staffId))
                    ->orWhere(function ($legacy) use ($staffId) {
                        $legacy->where('staff_id', $staffId)
                            ->where(function ($sub) {
                                $sub->whereDoesntHave('items')
                                    ->orWhereDoesntHave('items', fn ($iq) => $iq->whereNotNull('staff_id'));
                            });
                    });
            });
        }

        $expensesQuery = Expense::with('creator')
            ->whereDate('expense_date', '>=', $from->toDateString())
            ->whereDate('expense_date', '<=', $to->toDateString());

        if ($branch === 'general') {
            $expensesQuery->whereNull('branch');
        } elseif ($branch) {
            $expensesQuery->where('branch', $branch);
        }

        $sales = $salesQuery->orderByDesc('created_at')->get();
        $expenses = $expensesQuery->orderByDesc('expense_date')->get();

        $saleFigures = $sales->map(fn (Sale $sale) => $this->saleFiguresForStaff($sale, $staffId));

        $grossServices = round((float) $saleFigures->sum('services'), 2);
        $grossProducts = round((float) $saleFigures->sum('products'), 2);
        $grossPackages = round((float) $saleFigures->sum('packages'), 2);
        $totalDiscounts = round((float) $saleFigures->sum('discount'), 2);
        $totalTips = round((float) $saleFigures->sum('tip'), 2);
        $grossSales = round((float) $saleFigures->sum('net'), 2);
        $totalExpenses = (float) $expenses->sum('amount');
        $netProfit = $grossSales - $totalExpenses;
        $profitMargin = $grossSales > 0 ? ($netProfit / $grossSales) * 100 : 0;

        if ($staffId) {
            $paymentMap = [];
            foreach ($sales as $sale) {
                $fig = $this->saleFiguresForStaff($sale, $staffId);
                $ratio = $fig['ratio'];
                foreach ($sale->payments as $payment) {
                    $method = (string) $payment->method;
                    $paymentMap[$method] = round(($paymentMap[$method] ?? 0.0) + ((float) $payment->amount * $ratio), 2);
                }
            }
            $paymentTotals = collect($paymentMap);
        } else {
            $paymentTotals = SalePayment::whereIn('sale_id', $sales->pluck('id'))
                ->selectRaw('method, SUM(amount) as total')
                ->groupBy('method')
                ->pluck('total', 'method');
        }

        $branchBreakdown = self::branchBreakdown($from, $to);
        $trend = $this->trendBuckets($from, $to, $sales, $expenses, $staffId);

        return view('revenue.index', [
            'from' => $from,
            'to' => $to,
            'branch' => $branch,
            'branches' => self::BRANCHES,
            'staffId' => $staffId,
            'staffList' => Staff::orderBy('name')->get(['id', 'name']),
            'expenseCategories' => Expense::CATEGORIES,
            'sales' => $sales,
            'expenses' => $expenses,
            'grossServices' => $grossServices,
            'grossProducts' => $grossProducts,
            'grossPackages' => $grossPackages,
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
            'branch' => 'nullable|in:old_airport,wakrah',
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
     * Net sales (including packages and after discounts), expenses and net profit
     * per branch (plus General / Shared expenses) for the given date range,
     * independent of any branch filter — powers the branch comparison chart
     * and the Dashboard's P&L cards (UserController::dashboard()).
     */
    public static function branchBreakdown(Carbon $from, Carbon $to): array
    {
        $sales = Sale::whereBetween('created_at', [$from, $to])
            ->get(['branch', 'services_total', 'products_total', 'packages_total', 'discount_amount']);

        $salesByBranch = $sales->groupBy(fn ($s) => $s->branch ?: 'general')
            ->map(fn ($group) => (float) $group->sum(fn ($s) => max(0, (float) $s->services_total + (float) $s->products_total + (float) $s->packages_total - (float) $s->discount_amount)));

        $expensesByBranch = Expense::whereDate('expense_date', '>=', $from->toDateString())
            ->whereDate('expense_date', '<=', $to->toDateString())
            ->get(['branch', 'amount'])
            ->groupBy(fn ($e) => $e->branch ?: 'general')
            ->map(fn ($group) => (float) $group->sum('amount'));

        $breakdown = [];
        foreach (self::BRANCHES as $key => $label) {
            $branchSales = (float) ($salesByBranch[$key] ?? 0);
            $branchExpenses = (float) ($expensesByBranch[$key] ?? 0);

            $breakdown[] = [
                'key' => $key,
                'label' => $label,
                'sales' => $branchSales,
                'expenses' => $branchExpenses,
                'profit' => $branchSales - $branchExpenses,
            ];
        }

        $generalSales = (float) ($salesByBranch['general'] ?? 0);
        $generalExpenses = (float) ($expensesByBranch['general'] ?? 0);

        if ($generalSales > 0 || $generalExpenses > 0) {
            $breakdown[] = [
                'key' => 'general',
                'label' => 'General / Shared',
                'sales' => $generalSales,
                'expenses' => $generalExpenses,
                'profit' => $generalSales - $generalExpenses,
            ];
        }

        return $breakdown;
    }

    /**
     * Compute apportioned sale line figures for a specific staff member (or full sale when $staffId is null).
     *
     * @return array{services: float, products: float, packages: float, discount: float, tip: float, net: float, ratio: float}
     */
    private function saleFiguresForStaff(Sale $sale, ?int $staffId): array
    {
        $fullServices = (float) $sale->services_total;
        $fullProducts = (float) $sale->products_total;
        $fullPackages = (float) $sale->packages_total;
        $fullDiscount = (float) $sale->discount_amount;
        $fullTip = (float) $sale->tip_amount;
        $fullNet = max(0.0, $fullServices + $fullProducts + $fullPackages - $fullDiscount);

        if (!$staffId) {
            return [
                'services' => $fullServices,
                'products' => $fullProducts,
                'packages' => $fullPackages,
                'discount' => $fullDiscount,
                'tip' => $fullTip,
                'net' => $fullNet,
                'ratio' => 1.0,
            ];
        }

        $allItems = $sale->relationLoaded('items') ? $sale->items : $sale->items()->get();
        $staffItems = $allItems->filter(fn ($item) => (int) ($item->staff_id ?? 0) === $staffId);

        if ($staffItems->isNotEmpty()) {
            $services = round((float) $staffItems->where('type', 'service')->sum('total'), 2);
            $products = round((float) $staffItems->where('type', 'product')->sum('total'), 2);
            $packages = round((float) $staffItems->where('type', 'package')->sum('total'), 2);
            $staffGross = $services + $products + $packages;

            $saleItemsGross = (float) $allItems->sum('total');
            if ($saleItemsGross <= 0) {
                $saleItemsGross = $fullServices + $fullProducts + $fullPackages;
            }

            $ratio = $saleItemsGross > 0
                ? min(1.0, max(0.0, $staffGross / $saleItemsGross))
                : 0.0;

            $discount = round($fullDiscount * $ratio, 2);
            $tip = round($fullTip * $ratio, 2);
            $net = round(max(0.0, $staffGross - $discount), 2);

            return [
                'services' => $services,
                'products' => $products,
                'packages' => $packages,
                'discount' => $discount,
                'tip' => $tip,
                'net' => $net,
                'ratio' => $ratio,
            ];
        }

        // Legacy fallback: itemless sale (or items with null staff_id) owned by $sale->staff_id
        $hasStaffAttributedItems = $allItems->contains(fn ($item) => $item->staff_id !== null);
        if (!$hasStaffAttributedItems && (int) ($sale->staff_id ?? 0) === $staffId) {
            return [
                'services' => $fullServices,
                'products' => $fullProducts,
                'packages' => $fullPackages,
                'discount' => $fullDiscount,
                'tip' => $fullTip,
                'net' => $fullNet,
                'ratio' => 1.0,
            ];
        }

        return [
            'services' => 0.0,
            'products' => 0.0,
            'packages' => 0.0,
            'discount' => 0.0,
            'tip' => 0.0,
            'net' => 0.0,
            'ratio' => 0.0,
        ];
    }

    /**
     * Daily revenue/expense series for the trend chart. Falls back to weekly
     * buckets once the range is too wide for a legible daily x-axis.
     */
    private function trendBuckets(Carbon $from, Carbon $to, $sales, $expenses, ?int $staffId = null): array
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
                $buckets[$key]['revenue'] += $this->saleFiguresForStaff($sale, $staffId)['net'];
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
