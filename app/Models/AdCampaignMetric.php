<?php

namespace App\Models;

use App\Services\ExchangeRateService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

class AdCampaignMetric extends Model
{
    public const DEFAULT_USD_TO_QAR = 3.6415;
    public const DEFAULT_USD_TO_PKR = 278.5000;

    /**
     * Integer count metric columns (nullable unsigned big integers).
     */
    public const INTEGER_METRIC_COLUMNS = [
        'reach',
        'impressions',
        'atc',
        'ic',
        'purchases',
        'results_count',
        'leads_count',
        'link_clicks',
        'clicks_all',
        'shop_clicks',
        'landing_page_views',
        'video_views',
        'thruplays',
    ];

    /**
     * Decimal metric columns (nullable floats).
     */
    public const DECIMAL_METRIC_COLUMNS = [
        'cpm',
        'cpc',
        'cpc_all',
        'ctr',
        'ctr_all',
        'cpa',
        'cpl',
        'roas',
        'roi',
        'frequency',
        'cvr',
        'aov',
        'cost_per_purchase',
        'cost_per_lpv',
        'engagement_rate',
        'cost_per_result',
        'amount_spent',
        'revenue',
    ];

    /**
     * All 25 core metrics + Meta secondary variants in natural Ads Manager scan order.
     */
    public const METRIC_DEFINITIONS = [
        'amount_spent' => ['label' => 'Amount Spent', 'short' => 'Spend', 'type' => 'currency', 'groups' => ['overview', 'conversions']],
        'impressions' => ['label' => 'Impressions', 'short' => 'Impressions', 'type' => 'integer', 'groups' => ['overview', 'traffic']],
        'reach' => ['label' => 'Reach', 'short' => 'Reach', 'type' => 'integer', 'groups' => ['overview', 'traffic']],
        'frequency' => ['label' => 'Frequency', 'short' => 'Frequency', 'type' => 'decimal', 'groups' => ['overview', 'traffic']],
        'cpm' => ['label' => 'CPM', 'short' => 'CPM', 'type' => 'currency', 'groups' => ['overview', 'traffic']],
        'link_clicks' => ['label' => 'Link Clicks', 'short' => 'Link Clicks', 'type' => 'integer', 'groups' => ['overview', 'traffic']],
        'cpc' => ['label' => 'CPC (Link)', 'short' => 'CPC', 'type' => 'currency', 'groups' => ['overview', 'traffic']],
        'ctr' => ['label' => 'CTR (Link %)', 'short' => 'CTR', 'type' => 'percent', 'groups' => ['overview', 'traffic']],
        'clicks_all' => ['label' => 'Clicks (All)', 'short' => 'Clicks (All)', 'type' => 'integer', 'groups' => ['traffic']],
        'ctr_all' => ['label' => 'CTR (All %)', 'short' => 'CTR (All)', 'type' => 'percent', 'groups' => ['traffic']],
        'cpc_all' => ['label' => 'CPC (All)', 'short' => 'CPC (All)', 'type' => 'currency', 'groups' => ['traffic']],
        'shop_clicks' => ['label' => 'Shop Clicks', 'short' => 'Shop Clicks', 'type' => 'integer', 'groups' => ['traffic']],
        'landing_page_views' => ['label' => 'Landing Page Views', 'short' => 'Landing Page Views', 'type' => 'integer', 'groups' => ['traffic']],
        'cost_per_lpv' => ['label' => 'Cost / LPV', 'short' => 'Cost/LPV', 'type' => 'currency', 'groups' => ['traffic']],
        'results_count' => ['label' => 'Results', 'short' => 'Results', 'type' => 'integer', 'groups' => ['overview', 'conversions']],
        'cost_per_result' => ['label' => 'Cost / Result', 'short' => 'Cost/Result', 'type' => 'currency', 'groups' => ['overview', 'conversions']],
        'leads_count' => ['label' => 'Leads', 'short' => 'Leads', 'type' => 'integer', 'groups' => ['conversions']],
        'cpl' => ['label' => 'CPL', 'short' => 'CPL', 'type' => 'currency', 'groups' => ['overview', 'conversions']],
        'atc' => ['label' => 'Add to Cart', 'short' => 'ATC', 'type' => 'integer', 'groups' => ['conversions']],
        'ic' => ['label' => 'Initiate Checkout', 'short' => 'IC', 'type' => 'integer', 'groups' => ['conversions']],
        'purchases' => ['label' => 'Purchases', 'short' => 'Purchases', 'type' => 'integer', 'groups' => ['overview', 'conversions']],
        'cost_per_purchase' => ['label' => 'Cost / Purchase', 'short' => 'Cost/Purchase', 'type' => 'currency', 'groups' => ['conversions']],
        'cpa' => ['label' => 'CPA', 'short' => 'CPA', 'type' => 'currency', 'groups' => ['conversions']],
        'cvr' => ['label' => 'CVR %', 'short' => 'CVR', 'type' => 'percent', 'groups' => ['overview', 'conversions']],
        'aov' => ['label' => 'AOV', 'short' => 'AOV', 'type' => 'currency', 'groups' => ['overview', 'conversions']],
        'revenue' => ['label' => 'Revenue', 'short' => 'Revenue', 'type' => 'currency', 'groups' => ['overview', 'conversions']],
        'roas' => ['label' => 'ROAS', 'short' => 'ROAS', 'type' => 'ratio', 'groups' => ['overview', 'conversions']],
        'roi' => ['label' => 'ROI %', 'short' => 'ROI', 'type' => 'percent', 'groups' => ['overview', 'conversions']],
        'video_views' => ['label' => 'Video Views', 'short' => 'Video Views', 'type' => 'integer', 'groups' => ['video']],
        'thruplays' => ['label' => 'ThruPlays', 'short' => 'ThruPlays', 'type' => 'integer', 'groups' => ['video']],
        'engagement_rate' => ['label' => 'Engagement Rate %', 'short' => 'Eng. Rate', 'type' => 'percent', 'groups' => ['video']],
    ];

    protected $fillable = [
        'workspace',
        'region',
        'branch',
        'campaign_name',
        'normalized_campaign_key',
        'ad_category',
        'link_crm_revenue',
        'date_from',
        'date_to',
        'account_currency',
        'fx_rate_to_local',
        'local_currency',
        'cpm',
        'cpc',
        'cpc_all',
        'ctr',
        'ctr_all',
        'cpa',
        'cpl',
        'roas',
        'roi',
        'frequency',
        'reach',
        'impressions',
        'cvr',
        'aov',
        'atc',
        'ic',
        'purchases',
        'results_count',
        'leads_count',
        'cost_per_purchase',
        'link_clicks',
        'clicks_all',
        'shop_clicks',
        'landing_page_views',
        'cost_per_lpv',
        'engagement_rate',
        'video_views',
        'thruplays',
        'cost_per_result',
        'amount_spent',
        'amount_spent_local',
        'amount_spent_qar',
        'revenue',
        'revenue_local',
        'revenue_qar',
        'last_import_log_id',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'date_from' => 'date:Y-m-d',
        'date_to' => 'date:Y-m-d',
        'link_crm_revenue' => 'boolean',
        'fx_rate_to_local' => 'float',
        'cpm' => 'float',
        'cpc' => 'float',
        'cpc_all' => 'float',
        'ctr' => 'float',
        'ctr_all' => 'float',
        'cpa' => 'float',
        'cpl' => 'float',
        'roas' => 'float',
        'roi' => 'float',
        'frequency' => 'float',
        'reach' => 'integer',
        'impressions' => 'integer',
        'cvr' => 'float',
        'aov' => 'float',
        'atc' => 'integer',
        'ic' => 'integer',
        'purchases' => 'integer',
        'results_count' => 'integer',
        'leads_count' => 'integer',
        'cost_per_purchase' => 'float',
        'link_clicks' => 'integer',
        'clicks_all' => 'integer',
        'shop_clicks' => 'integer',
        'landing_page_views' => 'integer',
        'cost_per_lpv' => 'float',
        'engagement_rate' => 'float',
        'video_views' => 'integer',
        'thruplays' => 'integer',
        'cost_per_result' => 'float',
        'amount_spent' => 'float',
        'amount_spent_local' => 'float',
        'amount_spent_qar' => 'float',
        'revenue' => 'float',
        'revenue_local' => 'float',
        'revenue_qar' => 'float',
    ];

    protected $appends = [
        'est_amount_spent',
        'est_amount_spent_local',
        'est_amount_spent_qar',
    ];

    public static function allExtractableMetricKeys(): array
    {
        return array_merge(self::DECIMAL_METRIC_COLUMNS, self::INTEGER_METRIC_COLUMNS);
    }

    public static function cleanCampaignName(string $name): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', $name));
    }

    public static function normalizeCampaignKey(string $name): string
    {
        return mb_strtolower(self::cleanCampaignName($name), 'UTF-8');
    }

    /**
     * Detect Meta summary / footer rows that must NEVER be imported as a campaign row.
     */
    public static function isSummaryTotalRow(string $name): bool
    {
        $clean = self::cleanCampaignName($name);
        if ($clean === '') {
            return true;
        }

        return (bool) preg_match(
            '/^(total\s*results?|results?\s+from\s+\d+\s+campaigns?|overall\s*total|grand\s*total|account\s*total|summary|totals?)$/iu',
            $clean
        ) || (bool) preg_match('/^results?\s+from\s+\d+/iu', $clean);
    }

    public static function defaultLocalCurrencyFor(string $workspace, ?string $region = null): string
    {
        if ($workspace === 'ecommerce' && strtolower((string) $region) === 'pk') {
            return 'PKR';
        }

        return 'QAR';
    }

    public static function defaultFxRate(string $accountCurrency, string $localCurrency): float
    {
        $acc = strtoupper(trim($accountCurrency ?: 'USD'));
        $loc = strtoupper(trim($localCurrency ?: 'QAR'));

        if ($acc === $loc) {
            return 1.0000;
        }

        return app(ExchangeRateService::class)->getRate($acc, $loc);
    }

    /**
     * Sanitize a single extracted or user-edited metric value.
     * Placeholder strings ('—', '--', '-', 'N/A', '') strictly resolve to null, NEVER 0.
     */
    public static function sanitizeMetricValue(string $key, mixed $value): int|float|null
    {
        if ($value === null || is_bool($value) || is_array($value) || is_object($value)) {
            return null;
        }

        if (is_int($value) || is_float($value)) {
            if (is_nan((float) $value) || is_infinite((float) $value)) {
                return null;
            }

            return in_array($key, self::INTEGER_METRIC_COLUMNS, true)
                ? (int) round((float) $value)
                : round((float) $value, 4);
        }

        $str = trim((string) $value);
        if ($str === '') {
            return null;
        }

        // Strip common currency codes/symbols, % signs, 'x' ROAS suffix, and thousand separators
        $normalizedCheck = mb_strtolower($str, 'UTF-8');
        if (in_array($normalizedCheck, ['—', '–', '-', '--', '---', 'n/a', 'na', 'null', 'none', 'nil', 'not available'], true)) {
            return null;
        }

        // Remove currency symbols/codes ($ QAR PKR AED EUR GBP £ €), commas, spaces, % and trailing x
        $cleaned = preg_replace('/^(?:usd|qar|pkr|aed|sar|eur|gbp)\s*/iu', '', $str);
        $cleaned = preg_replace('/\s*(?:usd|qar|pkr|aed|sar|eur|gbp)$/iu', '', (string) $cleaned);
        $cleaned = str_replace(['$', '€', '£', ',', ' ', "\u{00A0}"], '', (string) $cleaned);
        $cleaned = preg_replace('/[%xX]$/u', '', (string) $cleaned);
        $cleaned = trim((string) $cleaned);

        if ($cleaned === '' || in_array($cleaned, ['-', '--', '—', '–', '.'], true) || !is_numeric($cleaned)) {
            return null;
        }

        $numeric = (float) $cleaned;

        return in_array($key, self::INTEGER_METRIC_COLUMNS, true)
            ? (int) round($numeric)
            : round($numeric, 4);
    }

    /**
     * Estimated spend when `amount_spent` was not visible in Screenshot 1
     * (`(impressions / 1000) * cpm`), without overwriting `amount_spent`.
     */
    public function getEstAmountSpentAttribute(): ?float
    {
        if ($this->amount_spent !== null) {
            return null;
        }

        if ($this->impressions !== null && $this->cpm !== null) {
            return round(((float) $this->impressions / 1000.0) * (float) $this->cpm, 2);
        }

        return null;
    }

    public function getEstAmountSpentLocalAttribute(): ?float
    {
        $est = $this->est_amount_spent;
        if ($est === null) {
            return null;
        }

        $fx = $this->resolvedFxRate();

        return round($est * $fx, 2);
    }

    public function getEstAmountSpentQarAttribute(): ?float
    {
        $est = $this->est_amount_spent;
        if ($est === null) {
            return null;
        }

        return round($est * $this->resolvedFxRateToQar(), 2);
    }

    public function resolvedFxRate(): float
    {
        $acc = strtoupper(trim((string) ($this->account_currency ?: 'USD')));
        $loc = strtoupper(trim((string) ($this->local_currency ?: 'QAR')));

        if ($acc === $loc) {
            return 1.0000;
        }

        $fx = (float) ($this->fx_rate_to_local ?: 0);

        return $fx > 0 ? $fx : self::defaultFxRate($acc, $loc);
    }

    public function resolvedFxRateToQar(): float
    {
        $acc = strtoupper(trim((string) ($this->account_currency ?: 'USD')));
        $loc = strtoupper(trim((string) ($this->local_currency ?: 'QAR')));

        if ($acc === 'QAR') {
            return 1.0000;
        }

        if ($loc === 'QAR' && (float) $this->fx_rate_to_local > 0) {
            return (float) $this->fx_rate_to_local;
        }

        return self::defaultFxRate($acc, 'QAR');
    }

    /**
     * Lookup verified CRM QAR revenue & paid bookings from `AdLeadEntry` when `link_crm_revenue` is enabled.
     *
     * @return array{revenue_qar: float, bookings_count: int, leads_count: int}
     */
    public function resolveLinkedCrmStats(): array
    {
        if (!$this->link_crm_revenue || empty($this->ad_category)) {
            return ['revenue_qar' => 0.0, 'bookings_count' => 0, 'leads_count' => 0];
        }

        $nowQatar = Carbon::now('Asia/Qatar');
        $from = $this->date_from
            ? Carbon::parse($this->date_from)->toDateString()
            : $nowQatar->copy()->startOfMonth()->toDateString();
        $to = $this->date_to
            ? Carbon::parse($this->date_to)->toDateString()
            : $nowQatar->toDateString();

        $entries = AdLeadEntry::query()
            ->whereBetween('date', [$from, $to])
            ->where('category', $this->ad_category)
            ->get();

        $branchFilter = in_array($this->branch, ['old_airport', 'wakrah'], true) ? $this->branch : null;

        $revenueQar = 0.0;
        $bookingsCount = 0;

        foreach ($entries as $entry) {
            if (!$entry->isBooked()) {
                continue;
            }

            if ($branchFilter !== null) {
                $branchRev = $entry->branchRevenue($branchFilter);
                if ($branchRev > 0) {
                    $revenueQar += $branchRev;
                    $bookingsCount++;
                }
            } else {
                $rev = (float) $entry->ticket_amount;
                if ($rev > 0) {
                    $revenueQar += $rev;
                    $bookingsCount++;
                }
            }
        }

        $leadsTotal = $branchFilter !== null
            ? $entries->filter(fn (AdLeadEntry $e) => $e->branch === $branchFilter || $e->branchRevenue($branchFilter) > 0)->count()
            : $entries->count();

        return [
            'revenue_qar' => round($revenueQar, 2),
            'bookings_count' => $bookingsCount,
            'leads_count' => $leadsTotal,
        ];
    }

    /**
     * Recompute local/QAR currency columns and derived formulas (ROAS, ROI, AOV, CVR, and fallback unit costs)
     * without overwriting explicit screenshot values from the incoming batch.
     */
    public function recomputeDerivedMetrics(array $explicitIncomingKeys = [], array $changedKeys = []): void
    {
        $fx = $this->resolvedFxRate();
        $fxToQar = $this->resolvedFxRateToQar();
        $explicitMap = array_fill_keys($explicitIncomingKeys, true);
        $changedMap = array_fill_keys($changedKeys, true);

        // 1. Spend conversions
        if ($this->amount_spent !== null) {
            $spend = (float) $this->amount_spent;
            $this->amount_spent_local = round($spend * $fx, 2);
            $this->amount_spent_qar = round($spend * $fxToQar, 2);
        } else {
            $this->amount_spent_local = null;
            $this->amount_spent_qar = null;
        }

        // 2. Revenue conversions (Meta Purchase Value or Linked CRM QAR Revenue)
        $crmStats = $this->resolveLinkedCrmStats();
        $effectiveRevenueAccount = null;

        if ($this->revenue !== null) {
            $effectiveRevenueAccount = (float) $this->revenue;
            $this->revenue_local = round($effectiveRevenueAccount * $fx, 2);
            $this->revenue_qar = round($effectiveRevenueAccount * $fxToQar, 2);
        } elseif ($this->link_crm_revenue && $crmStats['revenue_qar'] > 0) {
            $this->revenue_qar = $crmStats['revenue_qar'];
            $this->revenue_local = strtoupper((string) $this->local_currency) === 'QAR'
                ? $crmStats['revenue_qar']
                : round(($crmStats['revenue_qar'] / max(0.0001, $fxToQar)) * $fx, 2);
            $effectiveRevenueAccount = $fxToQar > 0
                ? round($crmStats['revenue_qar'] / $fxToQar, 4)
                : $crmStats['revenue_qar'];
        } else {
            $this->revenue_local = null;
            $this->revenue_qar = null;
        }

        $spend = $this->amount_spent !== null ? (float) $this->amount_spent : null;

        // Helper: should a formula column recompute?
        // Recomputes when null OR when one of its underlying inputs changed in this merge (unless explicitly provided in this batch).
        $inputsChangedFor = function (array $inputCols) use ($changedMap): bool {
            foreach ($inputCols as $col) {
                if (isset($changedMap[$col])) {
                    return true;
                }
            }

            return false;
        };

        // 3. ROAS = Revenue / Amount Spent & ROI = ((Revenue - Amount Spent) / Amount Spent) * 100
        if ($spend !== null && $spend > 0 && $effectiveRevenueAccount !== null) {
            $roasRoiInputsChanged = $inputsChangedFor(['amount_spent', 'revenue', 'link_crm_revenue', 'ad_category', 'fx_rate_to_local']);

            if (!isset($explicitMap['roas']) && ($this->roas === null || $roasRoiInputsChanged)) {
                $this->roas = round($effectiveRevenueAccount / $spend, 4);
            }

            if (!isset($explicitMap['roi']) && ($this->roi === null || $roasRoiInputsChanged)) {
                $this->roi = round((($effectiveRevenueAccount - $spend) / $spend) * 100.0, 2);
            }
        }

        // 4. AOV = Revenue / Purchases
        $effectivePurchases = ($this->purchases !== null && (int) $this->purchases > 0)
            ? (int) $this->purchases
            : ($this->link_crm_revenue && $crmStats['bookings_count'] > 0 ? $crmStats['bookings_count'] : null);

        if ($effectivePurchases !== null && $effectivePurchases > 0 && $effectiveRevenueAccount !== null) {
            $aovInputsChanged = $inputsChangedFor(['revenue', 'purchases', 'link_crm_revenue', 'ad_category']);
            if (!isset($explicitMap['aov']) && ($this->aov === null || $aovInputsChanged)) {
                $this->aov = round($effectiveRevenueAccount / $effectivePurchases, 2);
            }
        }

        // 5. CVR = (Purchases or Results / Link Clicks or LPV) * 100
        $conversionCount = $this->purchases
            ?? $this->results_count
            ?? $this->leads_count
            ?? ($this->link_crm_revenue && $crmStats['bookings_count'] > 0 ? $crmStats['bookings_count'] : null);

        $clickDenominator = ($this->link_clicks !== null && (int) $this->link_clicks > 0)
            ? (int) $this->link_clicks
            : (($this->landing_page_views !== null && (int) $this->landing_page_views > 0) ? (int) $this->landing_page_views : null);

        if ($conversionCount !== null && $clickDenominator !== null && $clickDenominator > 0) {
            $cvrInputsChanged = $inputsChangedFor(['purchases', 'results_count', 'leads_count', 'link_clicks', 'landing_page_views', 'link_crm_revenue']);
            if (!isset($explicitMap['cvr']) && ($this->cvr === null || $cvrInputsChanged)) {
                $this->cvr = round(((float) $conversionCount / (float) $clickDenominator) * 100.0, 2);
            }
        }

        // 6. Helpful secondary formula fallbacks when null or when underlying inputs changed (unless explicitly provided in this batch)
        if (!isset($explicitMap['frequency']) && ($this->frequency === null || $inputsChangedFor(['impressions', 'reach'])) && $this->impressions !== null && $this->reach !== null && (int) $this->reach > 0) {
            $this->frequency = round((float) $this->impressions / (float) $this->reach, 2);
        }

        if (!isset($explicitMap['ctr']) && ($this->ctr === null || $inputsChangedFor(['link_clicks', 'impressions'])) && $this->link_clicks !== null && $this->impressions !== null && (int) $this->impressions > 0) {
            $this->ctr = round(((float) $this->link_clicks / (float) $this->impressions) * 100.0, 2);
        }

        if ($spend !== null) {
            if (!isset($explicitMap['cpm']) && ($this->cpm === null || $inputsChangedFor(['amount_spent', 'impressions'])) && $this->impressions !== null && (int) $this->impressions > 0) {
                $this->cpm = round(($spend / (float) $this->impressions) * 1000.0, 2);
            }
            if (!isset($explicitMap['cpc']) && ($this->cpc === null || $inputsChangedFor(['amount_spent', 'link_clicks'])) && $this->link_clicks !== null && (int) $this->link_clicks > 0) {
                $this->cpc = round($spend / (float) $this->link_clicks, 2);
            }
            if (!isset($explicitMap['cost_per_lpv']) && ($this->cost_per_lpv === null || $inputsChangedFor(['amount_spent', 'landing_page_views'])) && $this->landing_page_views !== null && (int) $this->landing_page_views > 0) {
                $this->cost_per_lpv = round($spend / (float) $this->landing_page_views, 2);
            }
            if (!isset($explicitMap['cost_per_purchase']) && ($this->cost_per_purchase === null || $inputsChangedFor(['amount_spent', 'purchases'])) && $this->purchases !== null && (int) $this->purchases > 0) {
                $this->cost_per_purchase = round($spend / (float) $this->purchases, 2);
            }
            if (!isset($explicitMap['cpa']) && ($this->cpa === null || $inputsChangedFor(['amount_spent', 'purchases', 'results_count'])) && ($this->purchases ?? $this->results_count) !== null && (int) ($this->purchases ?? $this->results_count) > 0) {
                $this->cpa = round($spend / (float) ($this->purchases ?? $this->results_count), 2);
            }
            if (!isset($explicitMap['cpl']) && ($this->cpl === null || $inputsChangedFor(['amount_spent', 'leads_count'])) && $this->leads_count !== null && (int) $this->leads_count > 0) {
                $this->cpl = round($spend / (float) $this->leads_count, 2);
            }
            if (!isset($explicitMap['cost_per_result']) && ($this->cost_per_result === null || $inputsChangedFor(['amount_spent', 'results_count'])) && $this->results_count !== null && (int) $this->results_count > 0) {
                $this->cost_per_result = round($spend / (float) $this->results_count, 2);
            }
        }
    }

    /**
     * Rolling in-place upsert matched strictly by `(workspace, normalized_campaign_key)` — NEVER by `date_from`/`date_to`!
     *
     * - Next-day cumulative uploads (e.g., `01–16 Nov` -> `01–17 Nov`) update the existing campaign row in place
     *   with fresh metrics and update `date_from` / `date_to` without creating duplicate rows.
     * - Horizontal multi-screenshot merges overwrite non-null columns while preserving `null` / absent columns
     *   and preserving existing `date_from` / `date_to` when the screenshot header is cropped (`null`).
     */
    public static function mergeAndUpsert(array $context, array $campaignRow, ?int $userId = null, ?int $importLogId = null): self
    {
        $campaignName = self::cleanCampaignName((string) ($campaignRow['campaign_name'] ?? ''));
        $normalizedKey = self::normalizeCampaignKey($campaignName);

        $workspace = in_array(($context['workspace'] ?? 'salon'), ['salon', 'ecommerce'], true)
            ? $context['workspace']
            : 'salon';
        $region = strtolower(trim((string) ($context['region'] ?? ($workspace === 'ecommerce' ? 'pk' : 'qa'))));
        if (!in_array($region, ['qa', 'pk'], true)) {
            $region = 'qa';
        }

        $rawFrom = $context['date_from'] ?? $campaignRow['date_from'] ?? null;
        $rawTo = $context['date_to'] ?? $campaignRow['date_to'] ?? null;
        $dateFrom = ($rawFrom !== null && trim((string) $rawFrom) !== '')
            ? Carbon::parse((string) $rawFrom)->toDateString()
            : null;
        $dateTo = ($rawTo !== null && trim((string) $rawTo) !== '')
            ? Carbon::parse((string) $rawTo)->toDateString()
            : null;

        $accountCurrency = strtoupper(trim((string) ($context['account_currency'] ?? 'USD'))) ?: 'USD';
        $localCurrency = strtoupper(trim((string) ($context['local_currency'] ?? self::defaultLocalCurrencyFor($workspace, $region))));
        $fxRate = isset($context['fx_rate_to_local']) && (float) $context['fx_rate_to_local'] > 0
            ? (float) $context['fx_rate_to_local']
            : self::defaultFxRate($accountCurrency, $localCurrency);

        $metric = self::query()
            ->where('workspace', $workspace)
            ->where('normalized_campaign_key', $normalizedKey)
            ->first();

        $isNew = !$metric;
        $changedKeys = [];

        if ($isNew) {
            $metric = new self([
                'workspace' => $workspace,
                'region' => $region,
                'campaign_name' => $campaignName,
                'normalized_campaign_key' => $normalizedKey,
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
                'created_by' => $userId,
            ]);
        } else {
            if ($campaignName !== '') {
                $metric->campaign_name = $campaignName;
            }
            if ($metric->region !== $region) {
                $metric->region = $region;
            }
            // Update dates ONLY when non-null (preserves existing period if horizontal screenshot cropped top bar)
            if ($dateFrom !== null && $metric->date_from?->toDateString() !== $dateFrom) {
                $metric->date_from = $dateFrom;
                $changedKeys[] = 'date_from';
            }
            if ($dateTo !== null && $metric->date_to?->toDateString() !== $dateTo) {
                $metric->date_to = $dateTo;
                $changedKeys[] = 'date_to';
            }
        }

        if ((string) $metric->account_currency !== $accountCurrency) {
            $metric->account_currency = $accountCurrency;
            $changedKeys[] = 'account_currency';
        }
        if ((string) $metric->local_currency !== $localCurrency) {
            $metric->local_currency = $localCurrency;
            $changedKeys[] = 'local_currency';
        }
        if (abs((float) $metric->fx_rate_to_local - $fxRate) > 0.00001) {
            $metric->fx_rate_to_local = $fxRate;
            $changedKeys[] = 'fx_rate_to_local';
        }

        if (array_key_exists('branch', $campaignRow) && $campaignRow['branch'] !== null && $campaignRow['branch'] !== '') {
            $branchVal = (string) $campaignRow['branch'];
            if (in_array($branchVal, ['old_airport', 'wakrah', 'both'], true) && $metric->branch !== $branchVal) {
                $metric->branch = $branchVal;
                $changedKeys[] = 'branch';
            }
        } elseif ($isNew && !empty($context['branch']) && in_array($context['branch'], ['old_airport', 'wakrah', 'both'], true)) {
            $metric->branch = $context['branch'];
        }

        if (array_key_exists('ad_category', $campaignRow)) {
            $catVal = trim((string) ($campaignRow['ad_category'] ?? ''));
            if ($catVal !== '' && $metric->ad_category !== $catVal) {
                $metric->ad_category = $catVal;
                $changedKeys[] = 'ad_category';
            } elseif ($catVal === '' && !empty($campaignRow['clear_ad_category'])) {
                $metric->ad_category = null;
                $changedKeys[] = 'ad_category';
            }
        }

        if (array_key_exists('link_crm_revenue', $campaignRow) && $campaignRow['link_crm_revenue'] !== null) {
            $linkBool = filter_var($campaignRow['link_crm_revenue'], FILTER_VALIDATE_BOOLEAN);
            if ((bool) $metric->link_crm_revenue !== $linkBool) {
                $metric->link_crm_revenue = $linkBool;
                $changedKeys[] = 'link_crm_revenue';
            }
        }

        $explicitIncomingKeys = [];
        foreach (self::allExtractableMetricKeys() as $col) {
            if (!array_key_exists($col, $campaignRow)) {
                continue;
            }

            $sanitized = self::sanitizeMetricValue($col, $campaignRow[$col]);
            if ($sanitized === null) {
                // CRITICAL: Never overwrite existing metrics with null!
                continue;
            }

            $explicitIncomingKeys[] = $col;
            if ($metric->{$col} === null || (float) $metric->{$col} !== (float) $sanitized) {
                $metric->{$col} = $sanitized;
                $changedKeys[] = $col;
            }
        }

        if ($importLogId !== null) {
            $metric->last_import_log_id = $importLogId;
        }
        if ($userId !== null) {
            $metric->updated_by = $userId;
        }

        $metric->recomputeDerivedMetrics($explicitIncomingKeys, $changedKeys);
        $metric->save();

        return $metric;
    }

    public function lastImportLog()
    {
        return $this->belongsTo(AdMetricImportLog::class, 'last_import_log_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
