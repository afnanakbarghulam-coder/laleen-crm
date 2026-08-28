<?php

namespace App\Http\Controllers\Kpi;

use App\Http\Controllers\Controller;
use App\Support\StaffSalesAnalytics;
use Illuminate\Http\Request;

class StaffSalesController extends Controller
{
    const BRANCHES = StaffSalesAnalytics::BRANCHES;

    /**
     * Everything lives on one page now: "Sales & Upsells" (per-branch,
     * per-staff live breakdown) and "Analytics" (cross-branch comparison +
     * rankings). Nothing is generated or saved — both tabs recompute
     * straight from appointment_upsells for whichever filters are set.
     */
    public function index(Request $request)
    {
        $activeTab = in_array($request->query('tab'), ['sales', 'analytics'], true)
            ? $request->query('tab')
            : 'sales';

        $branch = $request->filled('sales_branch') && array_key_exists($request->sales_branch, self::BRANCHES)
            ? $request->sales_branch
            : array_key_first(self::BRANCHES);

        $salesTarget = $request->filled('sales_target') && is_numeric($request->sales_target)
            ? (float) $request->sales_target
            : StaffSalesAnalytics::DEFAULT_MONTHLY_TARGET;

        $salesFrom = $request->date('sales_from') ?? now()->startOfMonth();
        $salesTo = $request->date('sales_to') ?? now()->endOfDay();

        $salesAnalytics = new StaffSalesAnalytics($salesFrom, $salesTo, $salesTarget);

        $analyticsTarget = $request->filled('analytics_target') && is_numeric($request->analytics_target)
            ? (float) $request->analytics_target
            : StaffSalesAnalytics::DEFAULT_MONTHLY_TARGET;

        $analyticsFrom = $request->date('analytics_from') ?? now()->startOfMonth();
        $analyticsTo = $request->date('analytics_to') ?? now()->endOfDay();

        $analytics = new StaffSalesAnalytics($analyticsFrom, $analyticsTo, $analyticsTarget);

        return view('kpi.staff-sales.index', [
            'branches' => self::BRANCHES,
            'activeTab' => $activeTab,
            'branch' => $branch,
            'salesTarget' => $salesTarget,
            'salesFrom' => $salesFrom,
            'salesTo' => $salesTo,
            'salesAnalytics' => $salesAnalytics,
            'analyticsTarget' => $analyticsTarget,
            'analyticsFrom' => $analyticsFrom,
            'analyticsTo' => $analyticsTo,
            'analytics' => $analytics,
        ]);
    }
}
