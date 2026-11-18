<?php

namespace App\Services;

use App\Models\AdCampaignMetric;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class AdMetricsScreenshotParser
{
    public const BUSY_ERROR_MESSAGE = 'AI vision servers are temporarily busy. Please click Extract Metrics again in a few seconds.';

    protected ?string $lastTechnicalError = null;

    public function getLastTechnicalError(): ?string
    {
        return $this->lastTechnicalError;
    }
    /**
     * Alias map translating common Meta Ads Manager / TikTok / Google Ads column names
     * to our canonical 25-metric schema columns.
     */
    public const METRIC_KEY_ALIASES = [
        'cpm' => 'cpm',
        'cost_per_1000_impressions' => 'cpm',
        'cost_per_1_000_impressions' => 'cpm',
        'cpc' => 'cpc',
        'cpc_link' => 'cpc',
        'cpc_cost_per_link_click' => 'cpc',
        'cost_per_link_click' => 'cpc',
        'cpc_all' => 'cpc_all',
        'ctr' => 'ctr',
        'ctr_link' => 'ctr',
        'ctr_link_click_through_rate' => 'ctr',
        'link_click_through_rate' => 'ctr',
        'ctr_all' => 'ctr_all',
        'cpa' => 'cpa',
        'cost_per_acquisition' => 'cpa',
        'cost_per_action' => 'cpa',
        'cpl' => 'cpl',
        'cost_per_lead' => 'cpl',
        'roas' => 'roas',
        'purchase_roas' => 'roas',
        'website_purchase_roas' => 'roas',
        'return_on_ad_spend' => 'roas',
        'roi' => 'roi',
        'return_on_investment' => 'roi',
        'frequency' => 'frequency',
        'reach' => 'reach',
        'impressions' => 'impressions',
        'cvr' => 'cvr',
        'conversion_rate' => 'cvr',
        'aov' => 'aov',
        'average_order_value' => 'aov',
        'atc' => 'atc',
        'add_to_cart' => 'atc',
        'adds_to_cart' => 'atc',
        'website_adds_to_cart' => 'atc',
        'ic' => 'ic',
        'initiate_checkout' => 'ic',
        'checkouts_initiated' => 'ic',
        'website_checkouts_initiated' => 'ic',
        'purchases' => 'purchases',
        'website_purchases' => 'purchases',
        'results_count' => 'results_count',
        'results' => 'results_count',
        'leads_count' => 'leads_count',
        'leads' => 'leads_count',
        'messaging_conversations_started' => 'results_count',
        'cost_per_purchase' => 'cost_per_purchase',
        'link_clicks' => 'link_clicks',
        'clicks_all' => 'clicks_all',
        'shop_clicks' => 'shop_clicks',
        'landing_page_views' => 'landing_page_views',
        'lpv' => 'landing_page_views',
        'cost_per_lpv' => 'cost_per_lpv',
        'cost_per_landing_page_view' => 'cost_per_lpv',
        'engagement_rate' => 'engagement_rate',
        'post_engagement_rate' => 'engagement_rate',
        'video_views' => 'video_views',
        '3_second_video_plays' => 'video_views',
        'three_second_video_plays' => 'video_views',
        'video_plays' => 'video_views',
        'thruplays' => 'thruplays',
        'thruplay' => 'thruplays',
        'cost_per_result' => 'cost_per_result',
        'amount_spent' => 'amount_spent',
        'spend' => 'amount_spent',
        'cost' => 'amount_spent',
        'revenue' => 'revenue',
        'purchase_conversion_value' => 'revenue',
        'purchases_conversion_value' => 'revenue',
        'conversion_value' => 'revenue',
    ];

    /**
     * Additive columns suitable for verifying sum(campaigns) === Total results row.
     */
    public const ADDITIVE_VERIFICATION_COLUMNS = [
        'amount_spent' => 'Amount Spent',
        'revenue' => 'Revenue',
        'impressions' => 'Impressions',
        'link_clicks' => 'Link Clicks',
        'clicks_all' => 'Clicks (All)',
        'shop_clicks' => 'Shop Clicks',
        'landing_page_views' => 'Landing Page Views',
        'purchases' => 'Purchases',
        'results_count' => 'Results',
        'leads_count' => 'Leads',
        'atc' => 'Add to Cart',
        'ic' => 'Initiate Checkout',
        'video_views' => 'Video Views',
        'thruplays' => 'ThruPlays',
    ];

    /**
     * Parse 1 or multiple Ads Manager screenshot images into normalized campaign metrics + Total results verification.
     *
     * @param  array<int, UploadedFile|string|array{mime_type:string,data:string}>  $screenshots
     * @param  array<string, mixed>  $options
     * @return array{
     *   provider: string,
     *   account_currency: string,
     *   campaigns: array<int, array<string, mixed>>,
     *   total_results: array<string, mixed>|null,
     *   verification: array<string, mixed>,
     *   notes: string|null
     * }
     */
    public function parse(array $screenshots, array $options = []): array
    {
        return $this->parseScreenshots($screenshots, $options);
    }

    public function parseScreenshots(array $screenshots, array $options = []): array
    {
        if (PHP_SAPI !== 'cli') {
            @set_time_limit(120);
        }
        $this->lastTechnicalError = null;

        $encodedImages = $this->encodeImages($screenshots);
        if ($encodedImages === []) {
            throw new RuntimeException('Please attach at least one valid screenshot image (PNG, JPG, or WEBP).');
        }

        $geminiKey = trim((string) (config('services.gemini.api_key') ?: config('services.gemini.key')));
        $openAiKey = trim((string) config('services.openai.key'));

        if ($geminiKey === '' && $openAiKey === '') {
            throw new RuntimeException('AI Vision is not configured yet. Please set GEMINI_API_KEY (or OPENAI_API_KEY) in your .env file to extract screenshot metrics.');
        }

        $errors = [];

        if ($geminiKey !== '') {
            try {
                [$raw, $usedModel] = $this->callGeminiVision($encodedImages, $geminiKey, $options);

                return $this->normalizeExtractionPayload($raw, array_merge($options, [
                    'provider' => 'gemini',
                    'model' => $usedModel,
                ]));
            } catch (\Throwable $e) {
                $errors[] = $e->getMessage();
                Log::warning('Gemini Ads Screenshot Vision failed across all candidate models', ['error' => $e->getMessage()]);
            }
        }

        if ($openAiKey !== '') {
            try {
                [$raw, $usedModel] = $this->callOpenAiVision($encodedImages, $openAiKey, $options);

                return $this->normalizeExtractionPayload($raw, array_merge($options, [
                    'provider' => 'openai',
                    'model' => $usedModel,
                ]));
            } catch (\Throwable $e) {
                $errors[] = $e->getMessage();
                Log::warning('OpenAI Ads Screenshot Vision failed across all candidate models', ['error' => $e->getMessage()]);
            }
        }

        $this->lastTechnicalError = implode(' | ', $errors);

        throw new RuntimeException(self::BUSY_ERROR_MESSAGE);
    }

    /**
     * Deterministically sanitize, deduplicate/merge multi-screenshot rows, strip summary rows,
     * detect currency, and verify math against Meta's `Total results` footer row.
     */
    public function normalizeExtractionPayload(array $rawPayload, array $options = []): array
    {
        $detectedCurrency = $this->detectCurrencyFromPayload($rawPayload, (string) ($options['account_currency'] ?? 'USD'));
        [$extractedFrom, $extractedTo] = $this->extractDatesFromPayload($rawPayload);
        $rawCampaigns = is_array($rawPayload['campaigns'] ?? null) ? $rawPayload['campaigns'] : [];
        $rawTotalResults = is_array($rawPayload['total_results'] ?? null) ? $rawPayload['total_results'] : [];

        $mergedCampaignsByKey = [];
        $mergedTotalResults = $this->normalizeMetricBag($rawTotalResults);

        foreach ($rawCampaigns as $row) {
            if (!is_array($row)) {
                continue;
            }

            $rawName = (string) ($row['campaign_name'] ?? $row['name'] ?? $row['campaign'] ?? '');
            $cleanName = AdCampaignMetric::cleanCampaignName($rawName);
            if ($cleanName === '') {
                continue;
            }

            $normalizedMetrics = $this->normalizeMetricBag($row);

            // CRITICAL GUARD: Never allow "Total results" / "Results from X campaigns" into campaign rows!
            if (AdCampaignMetric::isSummaryTotalRow($cleanName)) {
                foreach ($normalizedMetrics as $k => $v) {
                    if ($v !== null && ($mergedTotalResults[$k] ?? null) === null) {
                        $mergedTotalResults[$k] = $v;
                    }
                }

                continue;
            }

            $key = AdCampaignMetric::normalizeCampaignKey($cleanName);
            if (!isset($mergedCampaignsByKey[$key])) {
                $mergedCampaignsByKey[$key] = array_merge(
                    ['campaign_name' => $cleanName],
                    array_fill_keys(AdCampaignMetric::allExtractableMetricKeys(), null)
                );
            }

            // Merge non-null metrics across horizontal screenshots in the same batch
            foreach ($normalizedMetrics as $metricCol => $val) {
                if ($val !== null) {
                    $mergedCampaignsByKey[$key][$metricCol] = $val;
                }
            }
        }

        $campaigns = array_values($mergedCampaignsByKey);

        $hasAnyTotalMetric = false;
        foreach ($mergedTotalResults as $v) {
            if ($v !== null) {
                $hasAnyTotalMetric = true;
                break;
            }
        }
        $cleanTotalResults = $hasAnyTotalMetric ? $mergedTotalResults : null;

        $verification = $this->verifyAgainstTotalResults($campaigns, $cleanTotalResults);
        $fxInfoQar = app(ExchangeRateService::class)->getRateInfo($detectedCurrency, 'QAR');

        $periodLabel = ($extractedFrom && $extractedTo)
            ? (Carbon::parse($extractedFrom)->format('d M Y') . ' – ' . Carbon::parse($extractedTo)->format('d M Y'))
            : null;

        return [
            'provider' => (string) ($options['provider'] ?? $rawPayload['provider'] ?? 'gemini'),
            'account_currency' => $detectedCurrency,
            'date_from' => $extractedFrom,
            'date_to' => $extractedTo,
            'period_label' => $periodLabel,
            'fx_rate_to_qar' => $fxInfoQar['rate'],
            'fx_source' => $fxInfoQar['source'],
            'campaigns' => $campaigns,
            'total_results' => $cleanTotalResults,
            'verification' => $verification,
            'notes' => isset($rawPayload['notes']) && is_string($rawPayload['notes']) ? trim($rawPayload['notes']) : null,
        ];
    }

    /**
     * Compare summed campaign metrics against Meta's `Total results` row for the Step 2 Review badge.
     */
    public function verifyAgainstTotalResults(array $campaigns, ?array $totalResults): array
    {
        if ($totalResults === null || $campaigns === []) {
            return [
                'status' => 'no_total_row',
                'label' => 'No summary row in screenshot — please glance over extracted numbers below.',
                'checks' => [],
            ];
        }

        $checks = [];
        $mismatches = [];

        foreach (self::ADDITIVE_VERIFICATION_COLUMNS as $col => $label) {
            $reportedTotal = $totalResults[$col] ?? null;
            if ($reportedTotal === null) {
                continue;
            }

            $hasAnyRowVal = false;
            $sum = 0.0;
            foreach ($campaigns as $row) {
                if (($row[$col] ?? null) !== null) {
                    $hasAnyRowVal = true;
                    $sum += (float) $row[$col];
                }
            }

            if (!$hasAnyRowVal) {
                continue;
            }

            $isInteger = in_array($col, AdCampaignMetric::INTEGER_METRIC_COLUMNS, true);
            $sumRounded = $isInteger ? (int) round($sum) : round($sum, 2);
            $reportedRounded = $isInteger ? (int) round((float) $reportedTotal) : round((float) $reportedTotal, 2);

            $diff = abs((float) $sumRounded - (float) $reportedRounded);
            $tolerance = $isInteger
                ? max(2.0, abs((float) $reportedRounded) * 0.005)
                : max(0.25, abs((float) $reportedRounded) * 0.015);

            // Note: Meta's "Total results" can exceed visible rows if screenshot scrolled past inactive rows,
            // so flag mismatch clearly in amber so the user knows!
            $matches = $diff <= $tolerance;

            $check = [
                'metric' => $col,
                'label' => $label,
                'sum' => $sumRounded,
                'reported_total' => $reportedRounded,
                'matches' => $matches,
            ];
            $checks[] = $check;

            if (!$matches) {
                $mismatches[] = "{$label} (rows sum: {$sumRounded} vs total row: {$reportedRounded})";
            }
        }

        if ($checks === []) {
            return [
                'status' => 'no_total_row',
                'label' => 'Summary row detected (rate metrics only).',
                'checks' => [],
            ];
        }

        if ($mismatches === []) {
            return [
                'status' => 'verified',
                'label' => 'Verified against Total results (' . count($checks) . ' metric column' . (count($checks) === 1 ? '' : 's') . ' matched 100%).',
                'checks' => $checks,
            ];
        }

        return [
            'status' => 'mismatch',
            'label' => 'Heads-up vs Total results: ' . implode(' · ', array_slice($mismatches, 0, 3)) . '. (Check if any off-screen campaign rows were cropped.)',
            'checks' => $checks,
        ];
    }

    /**
     * Map raw key/value bag (plus nested `metrics` sub-bag if present) to canonical schema columns.
     *
     * @return array<string, int|float|null>
     */
    protected function normalizeMetricBag(array $bag): array
    {
        $source = $bag;
        if (isset($bag['metrics']) && is_array($bag['metrics'])) {
            $source = array_merge($bag, $bag['metrics']);
        }

        $canonical = array_fill_keys(AdCampaignMetric::allExtractableMetricKeys(), null);

        foreach ($source as $rawKey => $rawVal) {
            if (!is_string($rawKey) || in_array($rawKey, ['campaign_name', 'name', 'campaign', 'metrics', 'branch', 'ad_category', 'date_from', 'date_to', 'date_range', 'period'], true)) {
                continue;
            }

            $slug = strtolower((string) preg_replace('/[^a-zA-Z0-9]+/', '_', trim($rawKey)));
            $slug = trim($slug, '_');

            // Never map Meta qualitative ad relevance diagnostic ranking columns (Quality / Engagement rate / Conversion rate ranking)
            if (str_contains($slug, 'ranking')) {
                continue;
            }

            $targetCol = self::METRIC_KEY_ALIASES[$slug] ?? null;

            if ($targetCol === null || !array_key_exists($targetCol, $canonical)) {
                continue;
            }

            $sanitized = AdCampaignMetric::sanitizeMetricValue($targetCol, $rawVal);
            if ($sanitized !== null) {
                $canonical[$targetCol] = $sanitized;
            }
        }

        return $canonical;
    }

    protected function detectCurrencyFromPayload(array $rawPayload, string $fallback = 'USD'): string
    {
        $candidates = [
            $rawPayload['account_currency'] ?? null,
            $rawPayload['detected_currency'] ?? null,
            $rawPayload['currency'] ?? null,
        ];

        foreach ($candidates as $cand) {
            if (!is_string($cand) || trim($cand) === '') {
                continue;
            }
            $upper = strtoupper(trim($cand));
            if ($upper === '$' || str_contains($upper, 'USD') || str_contains($upper, 'US$')) {
                return 'USD';
            }
            if (str_contains($upper, 'QAR') || str_contains($upper, 'QR')) {
                return 'QAR';
            }
            if (str_contains($upper, 'PKR') || str_contains($upper, 'RS')) {
                return 'PKR';
            }
            if (in_array($upper, ['AED', 'SAR', 'EUR', 'GBP'], true)) {
                return $upper;
            }
        }

        // Fallback: inspect raw string values inside campaigns for currency prefixes
        $serialized = json_encode($rawPayload['campaigns'] ?? []);
        if (is_string($serialized)) {
            if (preg_match('/\bQAR\b/i', $serialized)) {
                return 'QAR';
            }
            if (preg_match('/\bPKR\b/i', $serialized)) {
                return 'PKR';
            }
            if (str_contains($serialized, '$')) {
                return 'USD';
            }
        }

        return strtoupper(trim($fallback ?: 'USD'));
    }

    /**
     * Parse top-level `date_from` and `date_to` (or combined `date_range` string like `1 Nov 2026 - 16 Nov 2026`)
     * into `['YYYY-MM-DD'|null, 'YYYY-MM-DD'|null]`.
     *
     * @return array{0: string|null, 1: string|null}
     */
    protected function extractDatesFromPayload(array $rawPayload): array
    {
        $from = $this->parseSingleDateValue($rawPayload['date_from'] ?? null);
        $to = $this->parseSingleDateValue($rawPayload['date_to'] ?? null);

        if ($from === null && $to === null) {
            $rangeStr = $rawPayload['date_range'] ?? $rawPayload['period'] ?? null;
            if (is_string($rangeStr) && trim($rangeStr) !== '') {
                // Match ranges like "1 Nov 2026 - 16 Nov 2026" or "Nov 1, 2026 – Nov 17, 2026"
                $parts = preg_split('/\s*(?:–|—|\s-\s|\sto\s)\s*/iu', trim($rangeStr));
                if (is_array($parts) && count($parts) >= 2) {
                    $p1 = trim($parts[0]);
                    $p2 = trim($parts[1]);
                    $parsedTo = $this->parseSingleDateValue($p2);
                    // Handle shorthand like "1 Nov - 16 Nov 2026" where year is only on the second date
                    if ($parsedTo !== null && !preg_match('/\b20\d{2}\b/', $p1)) {
                        $year = Carbon::parse($parsedTo)->format('Y');
                        $p1 .= ' ' . $year;
                    }
                    $from = $this->parseSingleDateValue($p1);
                    $to = $parsedTo;
                }
            }
        }

        if ($from !== null && $to === null) {
            $to = $from;
        } elseif ($to !== null && $from === null) {
            $from = $to;
        }

        if ($from !== null && $to !== null && $from > $to) {
            [$from, $to] = [$to, $from];
        }

        return [$from, $to];
    }

    protected function parseSingleDateValue(mixed $val): ?string
    {
        if (!is_string($val) && !is_numeric($val)) {
            return null;
        }

        $str = trim((string) $val);
        $lower = mb_strtolower($str, 'UTF-8');
        if ($str === '' || in_array($lower, ['null', 'none', 'n/a', '—', '-', '--', 'today', 'yesterday', 'lifetime', 'maximum'], true)) {
            return null;
        }
        if (str_contains($lower, 'yyyy-mm-dd')) {
            return null;
        }

        // Clean Meta prefixes like "Reporting: 1 Nov 2026" or "Last 7 days: 1 Nov 2026"
        $str = trim((string) preg_replace('/^[a-z0-9\s]+:\s*/i', '', $str));
        if ($str === '') {
            return null;
        }

        // 1. Strict ISO YYYY-MM-DD
        if (preg_match('/^(\d{4})-(\d{1,2})-(\d{1,2})$/', $str, $m)) {
            if (checkdate((int) $m[2], (int) $m[3], (int) $m[1])) {
                return sprintf('%04d-%02d-%02d', (int) $m[1], (int) $m[2], (int) $m[3]);
            }

            return null;
        }

        // 2. Explicit month name + day + 4-digit year (e.g., "1 Nov 2026", "Nov 16, 2026") — never falls back to today()
        $hasMonthName = (bool) preg_match('/\b(jan|feb|mar|apr|may|jun|jul|aug|sep|sept|oct|nov|dec)[a-z]*\b/i', $str);
        $hasYear = (bool) preg_match('/\b20\d{2}\b/', $str);
        $hasDay = (bool) preg_match('/\b\d{1,2}\b/', $str);

        if ($hasMonthName && $hasDay && $hasYear) {
            try {
                return Carbon::parse($str)->toDateString();
            } catch (\Throwable) {
                return null;
            }
        }

        // 3. Numeric slash/dot date with 4-digit year (e.g., "01/11/2026" or "11/16/2026")
        if (preg_match('/^(\d{1,2})[\/\.](\d{1,2})[\/\.](\d{4})$/', $str, $m)) {
            $a = (int) $m[1];
            $b = (int) $m[2];
            $y = (int) $m[3];
            // Default to DD/MM/YYYY (Meta UK/Qatar locale) unless second part > 12 (MM/DD/YYYY)
            if ($b <= 12 && checkdate($b, $a, $y)) {
                return sprintf('%04d-%02d-%02d', $y, $b, $a);
            }
            if ($a <= 12 && checkdate($a, $b, $y)) {
                return sprintf('%04d-%02d-%02d', $y, $a, $b);
            }
        }

        return null;
    }

    /**
     * @param  array<int, UploadedFile|string|array{mime_type:string,data:string}>  $screenshots
     * @return array<int, array{mime_type: string, data: string}>
     */
    protected function encodeImages(array $screenshots): array
    {
        $encoded = [];

        foreach ($screenshots as $item) {
            if (is_array($item) && !empty($item['data']) && !empty($item['mime_type'])) {
                $encoded[] = [
                    'mime_type' => (string) $item['mime_type'],
                    'data' => (string) $item['data'],
                ];
                continue;
            }

            if ($item instanceof UploadedFile) {
                $realPath = $item->getRealPath();
                if ($realPath === false || !is_file($realPath)) {
                    continue;
                }
                $bytes = file_get_contents($realPath);
                if ($bytes === false || $bytes === '') {
                    continue;
                }
                $mime = $item->getMimeType() ?: 'image/png';
                if ($mime === 'image/jpg') {
                    $mime = 'image/jpeg';
                }
                $encoded[] = [
                    'mime_type' => $mime,
                    'data' => base64_encode($bytes),
                ];
                continue;
            }

            if (is_string($item) && is_file($item)) {
                $bytes = file_get_contents($item);
                if ($bytes === false || $bytes === '') {
                    continue;
                }
                $mime = mime_content_type($item) ?: 'image/png';
                if ($mime === 'image/jpg') {
                    $mime = 'image/jpeg';
                }
                $encoded[] = [
                    'mime_type' => $mime,
                    'data' => base64_encode($bytes),
                ];
            }
        }

        return $encoded;
    }

    protected function buildVisionPrompt(int $imageCount): string
    {
        return <<<PROMPT
You are a precision Marketing Analytics OCR & Table Extractor specialized in Meta Ads Manager, TikTok Ads Manager, and Google Ads tables.
You are given {$imageCount} screenshot(s) of an Ads Manager campaign table (which may represent horizontal scroll segments of the same table).

STRICT EXTRACTION RULES:
1. Extract EVERY individual campaign row into `"campaigns"`. Keep exact campaign names (trimmed of leading/trailing whitespace, e.g. "Frizzy, dry, ads", "hair color offer", "6 Services 99 QAR", "7 services offer 99").
2. NEVER include summary/footer rows ("Total results", "Results from X campaigns", "Overall total", "Summary") inside `"campaigns"`. Instead, extract that summary row into `"total_results"` so we can verify row sums!
3. Extract the reporting date range shown in the Meta Ads Manager date picker pill (usually top-right header, e.g. `1 Nov 2026 - 16 Nov 2026` or `Nov 1, 2026 – Nov 17, 2026`) into top-level `"date_from"` (`"YYYY-MM-DD"`) and `"date_to"` (`"YYYY-MM-DD"`). If the date picker header is cropped out and not visible in any screenshot, return `null` for both `"date_from"` and `"date_to"`.
4. Detect the ad account currency from symbols (`$` = `"USD"`, `QAR` = `"QAR"`, `PKR` / `Rs` = `"PKR"`, `AED` = `"AED"`, `€` = `"EUR"`, `£` = `"GBP"`) and return it as `"account_currency"`.
5. Distinguish Meta's specific click & CTR columns carefully:
   - `"CPC (cost per link click)"` -> `"cpc"`
   - `"CPC (all)"` -> `"cpc_all"`
   - `"CTR (link click-through rate)"` -> `"ctr"`
   - `"CTR (all)"` -> `"ctr_all"`
   - `"Link clicks"` -> `"link_clicks"`
   - `"Clicks (all)"` -> `"clicks_all"`
   - `"Shop clicks"` -> `"shop_clicks"`
6. DO NOT confuse Meta's qualitative ad relevance diagnostic columns (`"Quality ranking"`, `"Engagement rate ranking"`, `"Conversion rate ranking"`, which show text like `"Above average"`, `"Average"`, `"Below average"`, or `"—"`) with numeric `"cvr"` or `"engagement_rate"`. Strictly IGNORE all `*_ranking` columns! Only populate `"cvr"` or `"engagement_rate"` when an explicit numeric percentage column is present.
7. CRITICAL NULL RULE: If a column is NOT visible in the screenshot, OR a cell shows `—`, `--`, `-`, `N/A`, or is blank, output `null` for that field — NEVER output `0`! Only output `0` when the screenshot explicitly shows numeric `0` or `0.00`.
8. Output numbers as clean numeric values without currency symbols, commas, or `%` signs (e.g., `$1.32` -> `1.32`, `3.05%` -> `3.05`, `19,324` -> `19324`).
9. If multiple screenshots show the same campaign scrolled horizontally, merge its visible columns into a single campaign object per campaign name.

Return ONLY valid JSON matching this exact structure:
{
  "account_currency": "USD",
  "date_from": "YYYY-MM-DD or null",
  "date_to": "YYYY-MM-DD or null",
  "campaigns": [
    {
      "campaign_name": "string",
      "amount_spent": null,
      "revenue": null,
      "roas": null,
      "roi": null,
      "impressions": null,
      "reach": null,
      "frequency": null,
      "cpm": null,
      "link_clicks": null,
      "clicks_all": null,
      "shop_clicks": null,
      "cpc": null,
      "cpc_all": null,
      "ctr": null,
      "ctr_all": null,
      "landing_page_views": null,
      "cost_per_lpv": null,
      "results_count": null,
      "cost_per_result": null,
      "leads_count": null,
      "cpl": null,
      "atc": null,
      "ic": null,
      "purchases": null,
      "cost_per_purchase": null,
      "cpa": null,
      "cvr": null,
      "aov": null,
      "engagement_rate": null,
      "video_views": null,
      "thruplays": null
    }
  ],
  "total_results": {
    "amount_spent": null,
    "revenue": null,
    "impressions": null,
    "reach": null,
    "cpm": null,
    "link_clicks": null,
    "clicks_all": null,
    "shop_clicks": null,
    "cpc": null,
    "cpc_all": null,
    "ctr": null,
    "ctr_all": null,
    "landing_page_views": null,
    "results_count": null,
    "leads_count": null,
    "atc": null,
    "ic": null,
    "purchases": null,
    "video_views": null,
    "thruplays": null
  },
  "notes": "optional brief note on columns detected"
}
PROMPT;
    }

    /**
     * Call Gemini Vision with fast per-attempt timeout (6s connect / 20s read),
     * zero thinking overhead on 2.5 models, and automatic failover across:
     * `gemini-2.5-flash` -> `gemini-2.0-flash` -> `gemini-2.5-flash-lite`.
     *
     * @return array{0: array<string, mixed>, 1: string}
     */
    protected function callGeminiVision(array $encodedImages, string $apiKey, array $options = []): array
    {
        $configuredVisionModel = trim((string) config('services.gemini.vision_model', 'gemini-2.5-flash'));
        $candidateModels = array_values(array_unique(array_filter([
            $configuredVisionModel,
            'gemini-2.5-flash',
            'gemini-2.0-flash',
            'gemini-2.5-flash-lite',
        ])));

        $parts = [];
        foreach ($encodedImages as $img) {
            $parts[] = [
                'inlineData' => [
                    'mimeType' => $img['mime_type'],
                    'data' => $img['data'],
                ],
            ];
        }
        $parts[] = ['text' => $this->buildVisionPrompt(count($encodedImages))];

        $verifySsl = (bool) config('services.gemini.verify_ssl', true);
        if (app()->environment('local')) {
            $verifySsl = false;
        }

        $attemptErrors = [];

        foreach ($candidateModels as $model) {
            $endpoint = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$apiKey}";

            $generationConfig = [
                'temperature' => 0,
                'maxOutputTokens' => 4096,
                'responseMimeType' => 'application/json',
            ];

            // Disable chain-of-thought reasoning ONLY on gemini-2.5-* models (cuts table OCR latency to ~3-5s;
            // never send thinkingConfig to gemini-2.0-* which rejects the field).
            if (str_starts_with($model, 'gemini-2.5-')) {
                $generationConfig['thinkingConfig'] = [
                    'thinkingBudget' => 0,
                ];
            }

            try {
                $response = Http::connectTimeout(6)
                    ->timeout(20)
                    ->withOptions(['verify' => $verifySsl])
                    ->post($endpoint, [
                        'contents' => [
                            [
                                'role' => 'user',
                                'parts' => $parts,
                            ],
                        ],
                        'generationConfig' => $generationConfig,
                    ]);
            } catch (\Throwable $e) {
                $attemptErrors[] = "{$model} timeout/connection error: {$e->getMessage()}";
                Log::warning("Gemini Vision attempt failed on {$model}, failing over", ['error' => $e->getMessage()]);
                continue;
            }

            if ($response->successful()) {
                $rawText = trim((string) data_get($response->json(), 'candidates.0.content.parts.0.text', ''));
                $decoded = $this->decodeJsonFromText($rawText);
                if (is_array($decoded)) {
                    return [$decoded, $model];
                }
                $attemptErrors[] = "{$model} returned malformed JSON.";
                continue;
            }

            $status = $response->status();
            $attemptErrors[] = "{$model} HTTP {$status}: " . mb_substr($response->body(), 0, 200);
            Log::warning("Gemini Vision HTTP {$status} on {$model}", ['status' => $status]);

            // Fail over immediately on capacity/rate/gateway/not-found errors (503, 429, 502, 504, 500, 404)
            if (in_array($status, [404, 408, 429, 500, 502, 503, 504], true)) {
                continue;
            }

            // Hard auth/key error (400/401/403) — only continue if 400 might be model-specific parameter
            if ($status === 400) {
                continue;
            }

            break;
        }

        throw new RuntimeException(implode(' ; ', $attemptErrors));
    }

    /**
     * Fallback Vision extractor using OpenAI (`gpt-4o-mini` -> `gpt-4o`) when configured.
     *
     * @return array{0: array<string, mixed>, 1: string}
     */
    protected function callOpenAiVision(array $encodedImages, string $apiKey, array $options = []): array
    {
        $configuredModel = trim((string) config('services.openai.model', 'gpt-4o-mini'));
        $candidateModels = array_values(array_unique(array_filter([
            $configuredModel,
            'gpt-4o-mini',
            'gpt-4o',
        ])));

        $userContent = [
            ['type' => 'text', 'text' => $this->buildVisionPrompt(count($encodedImages))],
        ];

        foreach ($encodedImages as $img) {
            $userContent[] = [
                'type' => 'image_url',
                'image_url' => [
                    'url' => "data:{$img['mime_type']};base64,{$img['data']}",
                    'detail' => 'high',
                ],
            ];
        }

        $attemptErrors = [];

        foreach ($candidateModels as $model) {
            try {
                $response = Http::withToken($apiKey)
                    ->connectTimeout(6)
                    ->timeout(20)
                    ->when(app()->environment('local'), fn ($h) => $h->withoutVerifying())
                    ->post('https://api.openai.com/v1/chat/completions', [
                        'model' => $model,
                        'messages' => [
                            ['role' => 'user', 'content' => $userContent],
                        ],
                        'temperature' => 0,
                        'max_tokens' => 4096,
                        'response_format' => ['type' => 'json_object'],
                    ]);
            } catch (\Throwable $e) {
                $attemptErrors[] = "OpenAI {$model} timeout/error: {$e->getMessage()}";
                continue;
            }

            if ($response->successful()) {
                $rawText = trim((string) data_get($response->json(), 'choices.0.message.content', ''));
                $decoded = $this->decodeJsonFromText($rawText);
                if (is_array($decoded)) {
                    return [$decoded, $model];
                }
                $attemptErrors[] = "OpenAI {$model} malformed JSON.";
                continue;
            }

            $status = $response->status();
            $attemptErrors[] = "OpenAI {$model} HTTP {$status}: " . mb_substr($response->body(), 0, 200);
            if (!in_array($status, [404, 408, 429, 500, 502, 503, 504], true)) {
                break;
            }
        }

        throw new RuntimeException(implode(' ; ', $attemptErrors));
    }

    protected function decodeJsonFromText(string $rawText): ?array
    {
        if ($rawText === '') {
            return null;
        }

        $cleaned = trim((string) preg_replace('/^```(?:json)?\s*|\s*```$/is', '', $rawText));
        $decoded = json_decode($cleaned, true);
        if (is_array($decoded)) {
            return $decoded;
        }

        $firstBrace = strpos($cleaned, '{');
        $lastBrace = strrpos($cleaned, '}');
        if ($firstBrace !== false && $lastBrace !== false && $lastBrace > $firstBrace) {
            $slice = substr($cleaned, $firstBrace, $lastBrace - $firstBrace + 1);
            $decodedSlice = json_decode($slice, true);
            if (is_array($decodedSlice)) {
                return $decodedSlice;
            }
        }

        return null;
    }
}
