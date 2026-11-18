<?php

namespace App\Http\Controllers\Kpi;

use App\Http\Controllers\Controller;
use App\Models\AdCampaignMetric;
use App\Models\AdLeadEntry;
use App\Models\AdMetricImportLog;
use App\Models\EcommerceSale;
use App\Services\AdMetricsScreenshotParser;
use App\Services\ExchangeRateService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class AdCampaignMetricsController extends Controller
{
    /**
     * Standalone Ecommerce Marketing 25-Metric Campaign Tracker page
     * (`GET /ecommerce/marketing/ad-metrics`).
     */
    public function ecommerceIndex(Request $request)
    {
        $region = EcommerceSale::normalizeRegion($request->query('region', 'pk'));
        $trackerData = self::buildTrackerViewData($request, 'ecommerce', $region);

        return view('ecommerce.marketing.ad-metrics', array_merge($trackerData, [
            'region' => $region,
            'categories' => AdLeadEntry::availableCategories(),
            'branches' => AdLeadEntry::BRANCHES,
        ]));
    }

    /**
     * Reusable data builder for the rolling single-snapshot Ad Metrics Tracker view
     * (used in both `KPIs -> Ads` and `Ecommerce -> Marketing -> Ad Metrics`).
     */
    public static function buildTrackerViewData(Request $request, string $defaultWorkspace = 'salon', ?string $forcedRegion = null): array
    {
        $workspace = in_array($request->query('workspace'), ['salon', 'ecommerce'], true)
            ? (string) $request->query('workspace')
            : $defaultWorkspace;

        $region = $forcedRegion
            ?? strtolower(trim((string) $request->query('region', $workspace === 'ecommerce' ? 'pk' : 'qa')));
        if (!in_array($region, ['qa', 'pk'], true)) {
            $region = 'qa';
        }

        $branchFilter = trim((string) $request->query('metrics_branch', ''));
        if (!in_array($branchFilter, ['old_airport', 'wakrah', 'both'], true)) {
            $branchFilter = '';
        }

        $baseQuery = AdCampaignMetric::query()
            ->where('workspace', $workspace)
            ->when($workspace === 'ecommerce', fn ($q) => $q->where('region', $region));

        $hasBranchSpecificRows = (clone $baseQuery)
            ->whereIn('branch', ['old_airport', 'wakrah'])
            ->exists();

        $campaignMetrics = (clone $baseQuery)
            ->when($branchFilter !== '', fn ($q) => $q->where('branch', $branchFilter))
            ->orderByDesc('amount_spent_qar')
            ->orderByDesc('impressions')
            ->orderBy('campaign_name')
            ->get();

        // Compute rolling snapshot period header & whether rows share a uniform period
        $distinctPeriods = $campaignMetrics
            ->filter(fn (AdCampaignMetric $r) => $r->date_from !== null && $r->date_to !== null)
            ->map(fn (AdCampaignMetric $r) => $r->date_from->toDateString() . '|' . $r->date_to->toDateString())
            ->unique()
            ->values();

        $hasUniformPeriod = $distinctPeriods->count() <= 1;
        $snapshotPeriodLabel = null;
        if ($distinctPeriods->count() === 1) {
            [$pFrom, $pTo] = explode('|', (string) $distinctPeriods->first());
            $snapshotPeriodLabel = Carbon::parse($pFrom)->format('d M Y') . ' – ' . Carbon::parse($pTo)->format('d M Y');
        } elseif ($distinctPeriods->count() > 1) {
            $latestDated = $campaignMetrics
                ->filter(fn (AdCampaignMetric $r) => $r->date_from !== null && $r->date_to !== null)
                ->sortByDesc(fn (AdCampaignMetric $r) => $r->updated_at?->timestamp ?? 0)
                ->first();
            if ($latestDated) {
                $snapshotPeriodLabel = $latestDated->date_from->format('d M Y') . ' – ' . $latestDated->date_to->format('d M Y');
            }
        }

        $latestUpdatedAt = $campaignMetrics
            ->map(fn (AdCampaignMetric $r) => $r->updated_at)
            ->filter()
            ->max();
        $lastUpdatedHuman = $latestUpdatedAt ? $latestUpdatedAt->diffForHumans() : null;

        $localCurrency = AdCampaignMetric::defaultLocalCurrencyFor($workspace, $region);
        $fxInfo = app(ExchangeRateService::class)->getRateInfo('USD', $localCurrency);
        $defaultFx = $fxInfo['rate'];
        $summaryCards = self::computeSummaryCards($campaignMetrics, $localCurrency);
        $populatedMetricColumns = self::resolvePopulatedMetricColumns($campaignMetrics);

        $recentImports = AdMetricImportLog::query()
            ->where('workspace', $workspace)
            ->when($workspace === 'ecommerce', fn ($q) => $q->where('region', $region))
            ->where('status', 'confirmed')
            ->orderByDesc('id')
            ->limit(5)
            ->get();

        return [
            'metricsWorkspace' => $workspace,
            'metricsRegion' => $region,
            'metricsBranch' => $branchFilter,
            'showBranchFilter' => $workspace === 'salon' && ($hasBranchSpecificRows || $branchFilter !== ''),
            'snapshotPeriodLabel' => $snapshotPeriodLabel,
            'hasUniformPeriod' => $hasUniformPeriod,
            'lastUpdatedHuman' => $lastUpdatedHuman,
            'metricsLocalCurrency' => $localCurrency,
            'metricsDefaultFx' => $defaultFx,
            'metricsFxSource' => $fxInfo['source'],
            'campaignMetrics' => $campaignMetrics,
            'populatedMetricColumns' => $populatedMetricColumns,
            'metricsSummary' => $summaryCards,
            'recentMetricImports' => $recentImports,
            'metricDefinitions' => AdCampaignMetric::METRIC_DEFINITIONS,
        ];
    }

    /**
     * Return ONLY metric definitions that have at least one non-null value across `$rows`.
     *
     * @return array<string, array<string, mixed>>
     */
    public static function resolvePopulatedMetricColumns(Collection $rows): array
    {
        if ($rows->isEmpty()) {
            return [];
        }

        $populated = [];
        foreach (AdCampaignMetric::METRIC_DEFINITIONS as $mKey => $mDef) {
            $hasValue = $rows->contains(function (AdCampaignMetric $row) use ($mKey): bool {
                if ($mKey === 'amount_spent') {
                    return $row->amount_spent !== null || $row->est_amount_spent !== null;
                }
                if ($mKey === 'revenue') {
                    return $row->revenue !== null || $row->revenue_local !== null;
                }

                return $row->{$mKey} !== null;
            });

            if ($hasValue) {
                $populated[$mKey] = $mDef;
            }
        }

        return $populated;
    }

    /**
     * Compute top executive summary KPI cards across the filtered campaign rows.
     */
    public static function computeSummaryCards(Collection $rows, string $localCurrency = 'QAR'): array
    {
        $actualSpendAccount = 0.0;
        $estSpendAccount = 0.0;
        $actualSpendLocal = 0.0;
        $estSpendLocal = 0.0;
        $actualSpendQar = 0.0;
        $estSpendQar = 0.0;
        $hasActualSpend = false;
        $hasEstSpend = false;

        $totalRevenueAccount = 0.0;
        $totalRevenueLocal = 0.0;
        $totalRevenueQar = 0.0;
        $hasRevenue = false;

        $impressions = 0;
        $hasImpressions = false;
        $reach = 0;
        $hasReach = false;
        $linkClicks = 0;
        $hasLinkClicks = false;
        $lpv = 0;
        $purchases = 0;
        $hasPurchases = false;
        $resultsCount = 0;
        $leadsCount = 0;
        $thruplays = 0;
        $videoViews = 0;

        $accountCurrencies = [];

        foreach ($rows as $r) {
            /** @var AdCampaignMetric $r */
            $accCurr = strtoupper((string) ($r->account_currency ?: 'USD'));
            $accountCurrencies[$accCurr] = true;
            $fx = $r->resolvedFxRate();
            $fxToQar = $r->resolvedFxRateToQar();

            if ($r->amount_spent !== null) {
                $hasActualSpend = true;
                $actualSpendAccount += (float) $r->amount_spent;
                $actualSpendLocal += (float) ($r->amount_spent_local ?? round((float) $r->amount_spent * $fx, 2));
                $actualSpendQar += (float) ($r->amount_spent_qar ?? round((float) $r->amount_spent * $fxToQar, 2));
            } elseif ($r->est_amount_spent !== null) {
                $hasEstSpend = true;
                $estSpendAccount += (float) $r->est_amount_spent;
                $estSpendLocal += (float) ($r->est_amount_spent_local ?? 0);
                $estSpendQar += (float) ($r->est_amount_spent_qar ?? 0);
            }

            if ($r->revenue !== null) {
                $hasRevenue = true;
                $totalRevenueAccount += (float) $r->revenue;
                $totalRevenueLocal += (float) ($r->revenue_local ?? round((float) $r->revenue * $fx, 2));
                $totalRevenueQar += (float) ($r->revenue_qar ?? round((float) $r->revenue * $fxToQar, 2));
            } elseif ($r->revenue_qar !== null) {
                $hasRevenue = true;
                $totalRevenueQar += (float) $r->revenue_qar;
                $totalRevenueLocal += (float) ($r->revenue_local ?? $r->revenue_qar);
                $totalRevenueAccount += $fxToQar > 0 ? ((float) $r->revenue_qar / $fxToQar) : (float) $r->revenue_qar;
            }

            if ($r->impressions !== null) {
                $hasImpressions = true;
                $impressions += (int) $r->impressions;
            }
            if ($r->reach !== null) {
                $hasReach = true;
                $reach += (int) $r->reach;
            }
            if ($r->link_clicks !== null) {
                $hasLinkClicks = true;
                $linkClicks += (int) $r->link_clicks;
            }
            if ($r->landing_page_views !== null) {
                $lpv += (int) $r->landing_page_views;
            }
            if ($r->purchases !== null) {
                $hasPurchases = true;
                $purchases += (int) $r->purchases;
            }
            if ($r->results_count !== null) {
                $resultsCount += (int) $r->results_count;
            }
            if ($r->leads_count !== null) {
                $leadsCount += (int) $r->leads_count;
            }
            if ($r->thruplays !== null) {
                $thruplays += (int) $r->thruplays;
            }
            if ($r->video_views !== null) {
                $videoViews += (int) $r->video_views;
            }
        }

        $primaryAccountCurrency = count($accountCurrencies) === 1
            ? (string) array_key_first($accountCurrencies)
            : 'USD';

        $combinedSpendAccount = $actualSpendAccount + $estSpendAccount;
        $combinedSpendLocal = $actualSpendLocal + $estSpendLocal;
        $combinedSpendQar = $actualSpendQar + $estSpendQar;
        $hasAnySpend = $hasActualSpend || $hasEstSpend;

        // Blended performance ratios
        $roas = ($actualSpendLocal > 0 && $hasRevenue)
            ? round($totalRevenueLocal / $actualSpendLocal, 2)
            : null;
        $roi = ($actualSpendLocal > 0 && $hasRevenue)
            ? round((($totalRevenueLocal - $actualSpendLocal) / $actualSpendLocal) * 100.0, 1)
            : null;

        $frequency = ($hasImpressions && $hasReach && $reach > 0)
            ? round($impressions / $reach, 2)
            : ($rows->whereNotNull('frequency')->isNotEmpty() ? round((float) $rows->whereNotNull('frequency')->avg('frequency'), 2) : null);

        $ctr = ($hasImpressions && $impressions > 0 && $hasLinkClicks)
            ? round(($linkClicks / $impressions) * 100.0, 2)
            : ($rows->whereNotNull('ctr')->isNotEmpty() ? round((float) $rows->whereNotNull('ctr')->avg('ctr'), 2) : null);

        $cpc = ($hasAnySpend && $linkClicks > 0)
            ? round($combinedSpendAccount / $linkClicks, 2)
            : ($rows->whereNotNull('cpc')->isNotEmpty() ? round((float) $rows->whereNotNull('cpc')->avg('cpc'), 2) : null);

        $cpm = ($hasAnySpend && $impressions > 0)
            ? round(($combinedSpendAccount / $impressions) * 1000.0, 2)
            : ($rows->whereNotNull('cpm')->isNotEmpty() ? round((float) $rows->whereNotNull('cpm')->avg('cpm'), 2) : null);

        $primaryOutcomes = $purchases > 0 ? $purchases : ($leadsCount > 0 ? $leadsCount : $resultsCount);
        $cplOrCpr = ($hasAnySpend && $primaryOutcomes > 0)
            ? round($combinedSpendAccount / $primaryOutcomes, 2)
            : ($rows->whereNotNull('cpl')->isNotEmpty()
                ? round((float) $rows->whereNotNull('cpl')->avg('cpl'), 2)
                : ($rows->whereNotNull('cost_per_result')->isNotEmpty() ? round((float) $rows->whereNotNull('cost_per_result')->avg('cost_per_result'), 2) : null));

        $clickDenom = $linkClicks > 0 ? $linkClicks : $lpv;
        $cvr = ($primaryOutcomes > 0 && $clickDenom > 0)
            ? round(($primaryOutcomes / $clickDenom) * 100.0, 2)
            : ($rows->whereNotNull('cvr')->isNotEmpty() ? round((float) $rows->whereNotNull('cvr')->avg('cvr'), 2) : null);

        $aov = ($purchases > 0 && $hasRevenue)
            ? round($totalRevenueAccount / $purchases, 2)
            : ($rows->whereNotNull('aov')->isNotEmpty() ? round((float) $rows->whereNotNull('aov')->avg('aov'), 2) : null);

        return [
            'campaigns_count' => $rows->count(),
            'account_currency' => $primaryAccountCurrency,
            'local_currency' => $localCurrency,
            'has_actual_spend' => $hasActualSpend,
            'has_est_spend' => $hasEstSpend,
            'is_spend_estimated_only' => !$hasActualSpend && $hasEstSpend,
            'spend_account' => $hasAnySpend ? round($combinedSpendAccount, 2) : null,
            'spend_local' => $hasAnySpend ? round($combinedSpendLocal, 2) : null,
            'spend_qar' => $hasAnySpend ? round($combinedSpendQar, 2) : null,
            'actual_spend_account' => $hasActualSpend ? round($actualSpendAccount, 2) : null,
            'est_spend_account' => $hasEstSpend ? round($estSpendAccount, 2) : null,
            'revenue_account' => $hasRevenue ? round($totalRevenueAccount, 2) : null,
            'revenue_local' => $hasRevenue ? round($totalRevenueLocal, 2) : null,
            'revenue_qar' => $hasRevenue ? round($totalRevenueQar, 2) : null,
            'roas' => $roas,
            'roi' => $roi,
            'impressions' => $hasImpressions ? $impressions : null,
            'reach' => $hasReach ? $reach : null,
            'frequency' => $frequency,
            'link_clicks' => $hasLinkClicks ? $linkClicks : null,
            'ctr' => $ctr,
            'cpc' => $cpc,
            'cpm' => $cpm,
            'cpl_or_cpr' => $cplOrCpr,
            'cvr' => $cvr,
            'aov' => $aov,
            'purchases' => $hasPurchases ? $purchases : null,
            'results_count' => $resultsCount > 0 ? $resultsCount : null,
            'leads_count' => $leadsCount > 0 ? $leadsCount : null,
            'thruplays' => $thruplays > 0 ? $thruplays : null,
            'video_views' => $videoViews > 0 ? $videoViews : null,
        ];
    }

    /**
     * Step 1 -> Step 2: Upload 1..N Ads Manager screenshots, run AI Vision extraction,
     * verify against `Total results`, and return editable preview JSON without mutating `ad_campaign_metrics` yet.
     */
    public function extractPreview(Request $request, AdMetricsScreenshotParser $parser)
    {
        if (PHP_SAPI !== 'cli') {
            @set_time_limit(120);
        }

        $workspace = in_array($request->input('workspace'), ['salon', 'ecommerce'], true)
            ? (string) $request->input('workspace')
            : 'salon';

        $this->authorizeWorkspaceEdit($request, $workspace);

        $validated = $request->validate([
            'screenshots' => 'required|array|min:1|max:8',
            'screenshots.*' => 'required|file|mimes:jpg,jpeg,png,webp|max:12288',
            'workspace' => 'nullable|in:salon,ecommerce',
            'region' => 'nullable|in:qa,pk',
            'branch' => 'nullable|in:old_airport,wakrah,both',
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date|after_or_equal:date_from',
            'account_currency' => 'nullable|string|max:10',
            'fx_rate_to_local' => 'nullable|numeric|min:0.0001|max:100000',
        ]);

        $region = strtolower(trim((string) ($validated['region'] ?? ($workspace === 'ecommerce' ? 'pk' : 'qa'))));
        if (!in_array($region, ['qa', 'pk'], true)) {
            $region = 'qa';
        }

        $localCurrency = AdCampaignMetric::defaultLocalCurrencyFor($workspace, $region);
        $requestedCurrency = strtoupper(trim((string) ($validated['account_currency'] ?? 'USD'))) ?: 'USD';

        $uploadedFiles = $request->file('screenshots', []);
        $storedPaths = [];
        foreach ($uploadedFiles as $file) {
            $storedPaths[] = $file->store('ad-screenshots/' . now()->format('Y-m'));
        }

        try {
            $rawParsed = $parser->parseScreenshots($uploadedFiles, [
                'workspace' => $workspace,
                'region' => $region,
                'account_currency' => $requestedCurrency,
            ]);

            // Ensure deterministic normalization even when `parseScreenshots` is mocked in tests
            $normalized = $parser->normalizeExtractionPayload($rawParsed, [
                'account_currency' => $rawParsed['account_currency'] ?? $requestedCurrency,
                'provider' => $rawParsed['provider'] ?? 'gemini',
            ]);
        } catch (\Throwable $e) {
            $technicalDetail = $parser->getLastTechnicalError() ?: $e->getMessage();

            AdMetricImportLog::create([
                'workspace' => $workspace,
                'region' => $region,
                'branch' => $validated['branch'] ?? null,
                'date_from' => $validated['date_from'] ?? null,
                'date_to' => $validated['date_to'] ?? null,
                'account_currency' => $requestedCurrency,
                'fx_rate_to_local' => (float) ($validated['fx_rate_to_local'] ?? AdCampaignMetric::defaultFxRate($requestedCurrency, $localCurrency)),
                'local_currency' => $localCurrency,
                'screenshot_paths' => $storedPaths,
                'raw_extracted_json' => ['error' => $technicalDetail],
                'status' => 'failed',
                'created_by' => $request->user()?->id,
            ]);

            $userMessage = $e->getMessage() !== ''
                ? $e->getMessage()
                : AdMetricsScreenshotParser::BUSY_ERROR_MESSAGE;

            return response()->json([
                'success' => false,
                'message' => $userMessage,
            ], 422);
        }

        $detectedCurrency = strtoupper(trim((string) ($normalized['account_currency'] ?: $requestedCurrency)));
        $fxRate = isset($validated['fx_rate_to_local']) && (float) $validated['fx_rate_to_local'] > 0
            ? (float) $validated['fx_rate_to_local']
            : AdCampaignMetric::defaultFxRate($detectedCurrency, $localCurrency);

        if ($detectedCurrency === $localCurrency) {
            $fxRate = 1.0000;
        } elseif ($detectedCurrency !== $requestedCurrency && abs($fxRate - AdCampaignMetric::defaultFxRate($requestedCurrency, $localCurrency)) < 0.001) {
            $fxRate = AdCampaignMetric::defaultFxRate($detectedCurrency, $localCurrency);
        }

        $dateFrom = !empty($normalized['date_from'])
            ? Carbon::parse((string) $normalized['date_from'])->toDateString()
            : (!empty($validated['date_from']) ? Carbon::parse((string) $validated['date_from'])->toDateString() : null);
        $dateTo = !empty($normalized['date_to'])
            ? Carbon::parse((string) $normalized['date_to'])->toDateString()
            : (!empty($validated['date_to']) ? Carbon::parse((string) $validated['date_to'])->toDateString() : null);

        // Rolling campaign lookup strictly by (workspace, normalized_campaign_key) — never split by date!
        $existingByKey = AdCampaignMetric::query()
            ->where('workspace', $workspace)
            ->get()
            ->keyBy('normalized_campaign_key');

        $previewCampaigns = [];
        $fallbackExistingPeriodLabel = null;

        foreach ($normalized['campaigns'] as $row) {
            $key = AdCampaignMetric::normalizeCampaignKey((string) $row['campaign_name']);
            /** @var AdCampaignMetric|null $existing */
            $existing = $existingByKey->get($key);

            if ($fallbackExistingPeriodLabel === null && $existing && $existing->date_from && $existing->date_to) {
                $fallbackExistingPeriodLabel = $existing->date_from->format('d M Y') . ' – ' . $existing->date_to->format('d M Y');
            }

            $preservedColumns = [];
            if ($existing) {
                foreach (AdCampaignMetric::allExtractableMetricKeys() as $col) {
                    if (($row[$col] ?? null) === null && $existing->{$col} !== null) {
                        $preservedColumns[] = $col;
                    }
                }
            }

            $effImpressions = $row['impressions'] ?? $existing?->impressions;
            $effCpm = $row['cpm'] ?? $existing?->cpm;
            $effSpend = $row['amount_spent'] ?? $existing?->amount_spent;

            $estSpend = ($effSpend === null && $effImpressions !== null && $effCpm !== null)
                ? round(((float) $effImpressions / 1000.0) * (float) $effCpm, 2)
                : null;

            $previewCampaigns[] = array_merge($row, [
                'existing_id' => $existing?->id,
                'merge_mode' => $existing ? 'merge' : 'new',
                'preserved_columns' => $preservedColumns,
                'preserved_count' => count($preservedColumns),
                'branch' => $existing?->branch ?? ($validated['branch'] ?? null),
                'ad_category' => $existing?->ad_category,
                'link_crm_revenue' => (bool) ($existing?->link_crm_revenue ?? false),
                'est_amount_spent' => $estSpend,
                'est_amount_spent_local' => $estSpend !== null ? round($estSpend * $fxRate, 2) : null,
            ]);
        }

        $periodLabel = ($dateFrom && $dateTo)
            ? (Carbon::parse($dateFrom)->format('d M Y') . ' – ' . Carbon::parse($dateTo)->format('d M Y'))
            : ($fallbackExistingPeriodLabel ? ('Keeping period: ' . $fallbackExistingPeriodLabel) : null);

        $importLog = AdMetricImportLog::create([
            'workspace' => $workspace,
            'region' => $region,
            'branch' => $validated['branch'] ?? null,
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
            'account_currency' => $detectedCurrency,
            'fx_rate_to_local' => $fxRate,
            'local_currency' => $localCurrency,
            'screenshot_paths' => $storedPaths,
            'raw_extracted_json' => $normalized['campaigns'],
            'total_results_json' => $normalized['total_results'],
            'verification_json' => $normalized['verification'],
            'ai_provider' => $normalized['provider'],
            'campaigns_count' => count($previewCampaigns),
            'status' => 'previewed',
            'created_by' => $request->user()?->id,
        ]);

        return response()->json([
            'success' => true,
            'import_log_id' => $importLog->id,
            'provider' => $normalized['provider'],
            'workspace' => $workspace,
            'region' => $region,
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
            'period_label' => $periodLabel,
            'account_currency' => $detectedCurrency,
            'local_currency' => $localCurrency,
            'fx_rate_to_local' => $fxRate,
            'campaigns' => $previewCampaigns,
            'total_results' => $normalized['total_results'],
            'verification' => $normalized['verification'],
            'notes' => $normalized['notes'],
        ]);
    }

    /**
     * Step 2 Confirm: Non-destructively upsert reviewed campaign rows into `ad_campaign_metrics`
     * and auto-compute local currency + ROAS / ROI / AOV / CVR formulas.
     */
    public function confirmImport(Request $request)
    {
        $workspace = in_array($request->input('workspace'), ['salon', 'ecommerce'], true)
            ? (string) $request->input('workspace')
            : 'salon';

        $this->authorizeWorkspaceEdit($request, $workspace);

        $rules = [
            'import_log_id' => 'nullable|integer|exists:ad_metric_import_logs,id',
            'workspace' => 'required|in:salon,ecommerce',
            'region' => 'nullable|in:qa,pk',
            'branch' => 'nullable|in:old_airport,wakrah,both',
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date|after_or_equal:date_from',
            'account_currency' => 'nullable|string|max:10',
            'local_currency' => 'nullable|string|max:10',
            'fx_rate_to_local' => 'nullable|numeric|min:0.0001|max:100000',
            'campaigns' => 'required|array|min:1',
            'campaigns.*.campaign_name' => 'required|string|max:255',
            'campaigns.*.branch' => 'nullable|string|max:30',
            'campaigns.*.ad_category' => 'nullable|string|max:120',
            'campaigns.*.link_crm_revenue' => 'nullable',
            'campaigns.*.clear_ad_category' => 'nullable',
        ];
        foreach (AdCampaignMetric::allExtractableMetricKeys() as $metricCol) {
            $rules["campaigns.*.{$metricCol}"] = 'nullable';
        }

        $validated = $request->validate($rules);

        $region = strtolower(trim((string) ($validated['region'] ?? ($workspace === 'ecommerce' ? 'pk' : 'qa'))));
        if (!in_array($region, ['qa', 'pk'], true)) {
            $region = 'qa';
        }

        $localCurrency = strtoupper(trim((string) ($validated['local_currency'] ?? AdCampaignMetric::defaultLocalCurrencyFor($workspace, $region))));
        $accountCurrency = strtoupper(trim((string) ($validated['account_currency'] ?? 'USD'))) ?: 'USD';
        $fxRate = $accountCurrency === $localCurrency
            ? 1.0000
            : (isset($validated['fx_rate_to_local']) && (float) $validated['fx_rate_to_local'] > 0
                ? (float) $validated['fx_rate_to_local']
                : app(ExchangeRateService::class)->getRate($accountCurrency, $localCurrency));

        $importLogId = !empty($validated['import_log_id']) ? (int) $validated['import_log_id'] : null;
        $importLog = $importLogId !== null ? AdMetricImportLog::find($importLogId) : null;

        $dateFrom = !empty($validated['date_from'])
            ? Carbon::parse((string) $validated['date_from'])->toDateString()
            : ($importLog?->date_from ? $importLog->date_from->toDateString() : null);
        $dateTo = !empty($validated['date_to'])
            ? Carbon::parse((string) $validated['date_to'])->toDateString()
            : ($importLog?->date_to ? $importLog->date_to->toDateString() : null);

        $context = [
            'workspace' => $workspace,
            'region' => $region,
            'branch' => $validated['branch'] ?? null,
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
            'account_currency' => $accountCurrency,
            'local_currency' => $localCurrency,
            'fx_rate_to_local' => $fxRate,
        ];

        $userId = $request->user()?->id;

        $savedModels = DB::transaction(function () use ($validated, $context, $userId, $importLogId) {
            $saved = [];

            foreach ($validated['campaigns'] as $row) {
                if (!is_array($row)) {
                    continue;
                }
                $name = (string) ($row['campaign_name'] ?? '');
                if (AdCampaignMetric::isSummaryTotalRow($name)) {
                    continue;
                }

                $saved[] = AdCampaignMetric::mergeAndUpsert($context, $row, $userId, $importLogId);
            }

            // Synchronize all workspace campaign rows to the new extracted snapshot period when non-null
            if ($context['date_from'] !== null && $context['date_to'] !== null) {
                AdCampaignMetric::query()
                    ->where('workspace', $context['workspace'])
                    ->when($context['workspace'] === 'ecommerce', fn ($q) => $q->where('region', $context['region']))
                    ->update([
                        'date_from' => $context['date_from'],
                        'date_to' => $context['date_to'],
                    ]);
            }

            if ($importLogId !== null) {
                $logPatch = [
                    'account_currency' => $context['account_currency'],
                    'local_currency' => $context['local_currency'],
                    'fx_rate_to_local' => $context['fx_rate_to_local'],
                    'campaigns_count' => count($saved),
                    'status' => 'confirmed',
                ];
                if ($context['date_from'] !== null) {
                    $logPatch['date_from'] = $context['date_from'];
                }
                if ($context['date_to'] !== null) {
                    $logPatch['date_to'] = $context['date_to'];
                }
                AdMetricImportLog::whereKey($importLogId)->update($logPatch);
            }

            return $saved;
        });

        $redirectUrl = $workspace === 'ecommerce' && !$request->boolean('stay_in_kpis')
            ? route('ecommerce.marketing.ad-metrics', [
                'region' => $region,
            ])
            : route('kpi.ads.index', [
                'tab' => 'metrics',
            ]);

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'saved_count' => count($savedModels),
                'redirect_url' => $redirectUrl,
                'campaigns' => collect($savedModels)->map(fn (AdCampaignMetric $m) => $m->fresh())->values(),
            ]);
        }

        return redirect($redirectUrl)->with('success', count($savedModels) . ' campaign metric row(s) updated.');
    }

    /**
     * Delete a single campaign metric period record.
     */
    public function destroy(Request $request, AdCampaignMetric $adCampaignMetric)
    {
        $this->authorizeWorkspaceEdit($request, (string) $adCampaignMetric->workspace);

        $workspace = $adCampaignMetric->workspace;
        $region = $adCampaignMetric->region;
        $from = $adCampaignMetric->date_from?->toDateString();
        $to = $adCampaignMetric->date_to?->toDateString();

        $adCampaignMetric->delete();

        if ($request->expectsJson()) {
            return response()->json(['success' => true]);
        }

        return back()->with('success', 'Campaign metrics row removed.');
    }

    protected function authorizeWorkspaceEdit(Request $request, string $workspace): void
    {
        $user = $request->user();
        if (!$user) {
            abort(403);
        }

        $allowed = $workspace === 'ecommerce'
            ? ($user->canEdit('ecommerce') || $user->canEdit('kpis'))
            : $user->canEdit('kpis');

        if (!$allowed) {
            abort(403, 'You do not have edit permission to import or modify campaign metrics in this workspace.');
        }
    }
}
