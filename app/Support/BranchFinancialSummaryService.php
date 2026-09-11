<?php

namespace App\Support;

use App\Models\Expense;
use App\Models\Sale;
use Illuminate\Support\Carbon;

/**
 * Nova's own read-only copy of the CRM's branch profit/margin
 * calculation - not shared code with FinanceController. See the NOVA
 * READ-ONLY INTEGRATION RULE in app/NovaAI/README.md: Nova must not
 * require changes to existing CRM controllers, so this class
 * independently reproduces FinanceController::branchBreakdown()'s
 * formula rather than FinanceController delegating to it (that
 * delegation existed briefly in Stage 3C and was reverted). Parity
 * between the two is enforced by tests/Unit/BranchFinancialParityTest.php
 * - if that test fails, this class has drifted from CRM truth and should
 * be fixed to match, never the other way around.
 *
 * Scope, matching FinanceController::branchBreakdown() exactly:
 * - Revenue = SUM(sales.services_total) + SUM(sales.products_total) over
 *   [from, to] by Sale.created_at. packages_total was never included in
 *   this branch calculation before this extraction and still isn't - that
 *   is an existing characteristic of the app's business logic, not
 *   something this class changes.
 * - Expenses = SUM(expenses.amount) over [from, to] by Expense.expense_date
 *   (a date, not a timestamp - compared with whereDate on both ends).
 * - Only the two branches the finance dashboard has ever reported on
 *   (Old Airport, Al Wakrah) are covered. home_service was never part of
 *   this breakdown and this class does not add it.
 *
 * "Net profit" means exactly revenue minus recorded expenses - it is not
 * gross margin, contribution margin, or accounting profit. No
 * product/service cost data exists anywhere in this schema, so none of
 * those figures are derivable from this class or any other.
 */
class BranchFinancialSummaryService
{
    public const BRANCHES = [
        'old_airport' => 'Old Airport',
        'wakrah' => 'Al Wakrah',
    ];

    /**
     * Revenue, recorded expenses, CRM net profit, and CRM profit margin
     * per supported branch for [$from, $to].
     *
     * Returns one array per branch:
     *   ['key' => ..., 'label' => ..., 'sales' => float, 'expenses' => float,
     *    'profit' => float, 'margin_percent' => float|null]
     *
     * margin_percent is null - not 0 - when revenue is zero, since a
     * margin is mathematically undefined there, not zero. Each caller
     * decides how to render that for its own audience (the existing
     * finance dashboard has its own established zero-revenue display
     * convention and is untouched by this class either way).
     */
    public function summarize(Carbon $from, Carbon $to): array
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

        $summaries = [];

        foreach (self::BRANCHES as $key => $label) {
            $row = $salesByBranch->get($key);
            $revenue = $row ? (float) $row->services_total + (float) $row->products_total : 0.0;
            $expenses = (float) ($expensesByBranch[$key] ?? 0);
            $netProfit = $revenue - $expenses;

            $summaries[] = [
                'key' => $key,
                'label' => $label,
                'sales' => $revenue,
                'expenses' => $expenses,
                'profit' => $netProfit,
                'margin_percent' => $revenue > 0 ? ($netProfit / $revenue) * 100 : null,
            ];
        }

        return $summaries;
    }
}
