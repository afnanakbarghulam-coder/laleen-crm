<?php

namespace App\Http\Controllers\Kpi;

use App\Http\Controllers\Controller;
use App\Models\AdCampaignMetric;
use App\Models\AdLeadEntry;
use App\Models\KpiAdsConversionReport;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

class AdsConversionController extends Controller
{
    public const FIXED_MARKETING_FEE_QAR = 1530.00;
    public function index(Request $request)
    {
        $requestedTab = (string) $request->query('tab', '');
        $activeTab = match ($requestedTab) {
            'analytics', 'reports' => 'analytics',
            'metrics' => 'metrics',
            default => 'leads',
        };

        $rawMonth = trim((string) $request->query('month', ''));
        if ($rawMonth !== '' && preg_match('/^\d{4}-\d{2}$/', $rawMonth)) {
            $monthStart = \Illuminate\Support\Carbon::createFromFormat('Y-m-d', $rawMonth . '-01')->startOfMonth()->startOfDay();
            $defaultFrom = $monthStart->copy();
            $defaultTo = $rawMonth === now()->format('Y-m')
                ? now()->copy()->endOfDay()
                : $monthStart->copy()->endOfMonth()->endOfDay();
        } else {
            $defaultFrom = now()->copy()->startOfMonth()->startOfDay();
            $defaultTo = now()->copy()->endOfDay();
        }

        $entriesSearch = trim((string) $request->query('search', ''));
        $searchAllDates = $request->boolean('all_dates')
            && ! $request->filled('entries_from')
            && ! $request->filled('entries_to');

        $entriesFrom = $searchAllDates ? null : ($request->date('entries_from') ?? $defaultFrom->copy());
        $entriesTo = $searchAllDates ? null : ($request->date('entries_to') ?? $defaultTo->copy());

        $hasCustomerId = Schema::hasColumn('ad_lead_entries', 'customer_id');
        $hasLeadId = Schema::hasColumn('ad_lead_entries', 'lead_id');
        $hasAssignedAgentId = Schema::hasColumn('ad_lead_entries', 'assigned_agent_id');

        $eagerRelations = array_values(array_filter([
            $hasCustomerId ? 'customer' : null,
            $hasLeadId ? 'lead:id,needful_done,next_followup_date,assigned_agent_id,customer_remarks' : null,
            $hasAssignedAgentId ? 'assignedAgent:id,name' : null,
        ]));

        $adLeadQuery = AdLeadEntry::query()
            ->when(! empty($eagerRelations), fn ($q) => $q->with($eagerRelations))
            ->when($entriesFrom, fn ($q) => $q->whereDate('date', '>=', $entriesFrom))
            ->when($entriesTo, fn ($q) => $q->whereDate('date', '<=', $entriesTo))
            ->when($entriesSearch !== '', fn ($q) => $q->searchContact($entriesSearch));

        $adLeadEntries = $adLeadQuery
            ->orderByDesc('date')
            ->orderByDesc('id')
            ->paginate(20, ['*'], 'entries_page')
            ->appends(array_filter([
                'entries_from' => $entriesFrom?->format('Y-m-d'),
                'entries_to' => $entriesTo?->format('Y-m-d'),
                'search' => $entriesSearch !== '' ? $entriesSearch : null,
                'all_dates' => $searchAllDates ? '1' : null,
                'month' => $rawMonth !== '' ? $rawMonth : null,
                'tab' => 'leads',
            ], fn ($v) => $v !== null && $v !== ''));

        $categories = AdLeadEntry::availableCategories();
        $branches = AdLeadEntry::BRANCHES;
        $agents = User::where('role', 'agent')->orderBy('name')->get(['id', 'name']);

        // Ads Analytics: when opened without explicit ?analytics_from/?from or ?month filters,
        // automatically align to the active Salon `ad_campaign_metrics` snapshot date range if one exists.
        $explicitFrom = $request->date('analytics_from') ?? $request->date('from');
        $explicitTo = $request->date('analytics_to') ?? $request->date('to');

        $latestSalonSnapshot = Schema::hasTable('ad_campaign_metrics')
            ? AdCampaignMetric::query()
                ->where('workspace', 'salon')
                ->whereNotNull('date_from')
                ->whereNotNull('date_to')
                ->orderByDesc('updated_at')
                ->orderByDesc('id')
                ->first()
            : null;

        if ($explicitFrom !== null || $explicitTo !== null) {
            $analyticsFrom = ($explicitFrom ?? $defaultFrom)->copy()->startOfDay();
            $analyticsTo = ($explicitTo ?? $defaultTo)->copy()->endOfDay();
        } elseif ($rawMonth === '' && $latestSalonSnapshot !== null) {
            $analyticsFrom = Carbon::parse($latestSalonSnapshot->date_from->toDateString())->startOfDay();
            $analyticsTo = Carbon::parse($latestSalonSnapshot->date_to->toDateString())->endOfDay();
        } else {
            $analyticsFrom = $defaultFrom->copy();
            $analyticsTo = $defaultTo->copy();
        }

        $analyticsReport = new KpiAdsConversionReport([
            'date_from' => $analyticsFrom,
            'date_to' => $analyticsTo,
        ]);

        $analyticsTotalsForFin = $analyticsReport->totals();
        $matchedAdMetrics = Schema::hasTable('ad_campaign_metrics')
            ? AdCampaignMetric::query()
                ->where('workspace', 'salon')
                ->where(function ($q) use ($analyticsFrom, $analyticsTo) {
                    $q->where(function ($dated) use ($analyticsFrom, $analyticsTo) {
                        $dated->whereNotNull('date_from')
                            ->whereNotNull('date_to')
                            ->whereDate('date_from', '<=', $analyticsTo->toDateString())
                            ->whereDate('date_to', '>=', $analyticsFrom->toDateString());
                    })->orWhere(function ($undated) {
                        $undated->whereNull('date_from')->whereNull('date_to');
                    });
                })
                ->get()
            : collect();

        $analyticsFinancials = self::computeAnalyticsFinancials(
            $matchedAdMetrics,
            (float) ($analyticsTotalsForFin['total_revenue'] ?? 0.0),
            $analyticsFrom,
            $analyticsTo
        );

        $reports = KpiAdsConversionReport::orderByDesc('date_to')->orderByDesc('id')
            ->paginate(15, ['*'], 'reports_page')
            ->appends(array_filter([
                'analytics_from' => ($explicitFrom !== null) ? $analyticsFrom->format('Y-m-d') : null,
                'analytics_to' => ($explicitTo !== null) ? $analyticsTo->format('Y-m-d') : null,
                'month' => $rawMonth !== '' ? $rawMonth : null,
                'tab' => 'analytics',
            ]));

        $trackerViewData = $activeTab === 'metrics'
            ? AdCampaignMetricsController::buildTrackerViewData($request, 'salon')
            : [];

        return view('kpi.ads.index', array_merge(compact(
            'reports', 'adLeadEntries', 'categories', 'branches', 'agents', 'entriesFrom', 'entriesTo',
            'entriesSearch', 'searchAllDates',
            'analyticsReport', 'analyticsFrom', 'analyticsTo', 'activeTab', 'analyticsFinancials'
        ), $trackerViewData));
    }

    /**
     * Compute QAR Ad Spend (from `ad_campaign_metrics` actual or CPM-derived spend),
     * Fixed Marketing Fee (`1,530 QAR`), Total Marketing Cost, Net Profit, ROAS, and ROI.
     */
    public static function computeAnalyticsFinancials(
        Collection $matchedMetrics,
        float $adRevenueQar,
        Carbon $analyticsFrom,
        Carbon $analyticsTo
    ): array {
        $adSpendQar = 0.0;
        $adSpendUsd = 0.0;
        $hasActualSpend = false;
        $hasEstSpend = false;

        foreach ($matchedMetrics as $row) {
            /** @var AdCampaignMetric $row */
            $fxToQar = $row->resolvedFxRateToQar();

            if ($row->amount_spent !== null) {
                $hasActualSpend = true;
                $adSpendUsd += (float) $row->amount_spent;
                $adSpendQar += (float) ($row->amount_spent_qar ?? round((float) $row->amount_spent * $fxToQar, 2));
            } elseif ($row->est_amount_spent !== null) {
                $hasEstSpend = true;
                $adSpendUsd += (float) $row->est_amount_spent;
                $adSpendQar += (float) ($row->est_amount_spent_qar ?? round((float) $row->est_amount_spent * $fxToQar, 2));
            }
        }

        $adRevenueQar = round($adRevenueQar, 2);
        $adSpendQar = round($adSpendQar, 2);
        $adSpendUsd = round($adSpendUsd, 2);
        $marketingFeeQar = self::FIXED_MARKETING_FEE_QAR;
        $totalMarketingCostQar = round($adSpendQar + $marketingFeeQar, 2);
        $netProfitQar = round($adRevenueQar - $totalMarketingCostQar, 2);

        $roas = $adSpendQar > 0
            ? round($adRevenueQar / $adSpendQar, 2)
            : null;

        $roiPercent = ($totalMarketingCostQar > 0 && ($adSpendQar > 0 || $adRevenueQar > 0))
            ? round(($netProfitQar / $totalMarketingCostQar) * 100.0, 1)
            : null;

        $datedRows = $matchedMetrics->filter(fn (AdCampaignMetric $r) => $r->date_from !== null && $r->date_to !== null);
        $snapMinFrom = $datedRows->isNotEmpty()
            ? $datedRows->map(fn (AdCampaignMetric $r) => $r->date_from->toDateString())->min()
            : null;
        $snapMaxTo = $datedRows->isNotEmpty()
            ? $datedRows->map(fn (AdCampaignMetric $r) => $r->date_to->toDateString())->max()
            : null;

        $snapshotDiffers = ($snapMinFrom !== null && $snapMaxTo !== null)
            && ($snapMinFrom !== $analyticsFrom->toDateString() || $snapMaxTo !== $analyticsTo->toDateString());

        $snapshotRangeShort = ($snapMinFrom && $snapMaxTo)
            ? (Carbon::parse($snapMinFrom)->format('d M') . ' – ' . Carbon::parse($snapMaxTo)->format('d M'))
            : null;
        $snapshotRangeFull = ($snapMinFrom && $snapMaxTo)
            ? (Carbon::parse($snapMinFrom)->format('d M') . ' – ' . Carbon::parse($snapMaxTo)->format('d M Y'))
            : null;

        return [
            'campaigns_count' => $matchedMetrics->count(),
            'ad_revenue_qar' => $adRevenueQar,
            'ad_spend_qar' => $adSpendQar,
            'ad_spend_usd' => $adSpendUsd,
            'has_actual_spend' => $hasActualSpend,
            'has_est_spend' => $hasEstSpend,
            'is_spend_estimated' => !$hasActualSpend && $hasEstSpend,
            'marketing_fee_qar' => $marketingFeeQar,
            'total_marketing_cost_qar' => $totalMarketingCostQar,
            'net_profit_qar' => $netProfitQar,
            'roas' => $roas,
            'roi_percent' => $roiPercent,
            'snapshot_from' => $snapMinFrom,
            'snapshot_to' => $snapMaxTo,
            'snapshot_differs' => $snapshotDiffers,
            'snapshot_range_short' => $snapshotRangeShort,
            'snapshot_range_full' => $snapshotRangeFull,
        ];
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'date_from' => 'required|date',
            'date_to' => 'required|date|after_or_equal:date_from',
        ]);

        KpiAdsConversionReport::create([
            'date_from' => $validated['date_from'],
            'date_to' => $validated['date_to'],
            'created_by' => auth()->id(),
        ]);

        return redirect()->route('kpi.ads.index', [
            'tab' => 'analytics',
            'analytics_from' => $validated['date_from'],
            'analytics_to' => $validated['date_to'],
            'open' => 'snapshots',
        ])->with('success', 'Snapshot saved.');
    }

    public function show(KpiAdsConversionReport $report)
    {
        return view('kpi.ads.show', compact('report'));
    }

    public function destroy(KpiAdsConversionReport $report)
    {
        $report->delete();

        return redirect()->route('kpi.ads.index', [
            'tab' => 'analytics',
            'open' => 'snapshots',
        ])->with('success', 'Snapshot deleted.');
    }
}
