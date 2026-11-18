<?php

namespace App\Services;

use App\Models\SocialContentPlan;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class WeeklyContentPlanPdfParser
{
    public const BUSY_ERROR_MESSAGE = 'AI PDF parser is temporarily busy. Please click Upload Plan PDF again in a few seconds.';

    protected ?string $lastTechnicalError = null;

    public function getLastTechnicalError(): ?string
    {
        return $this->lastTechnicalError;
    }

    /**
     * Parse an uploaded 1-Week Social Media Content Plan PDF using Gemini structured JSON extraction.
     *
     * @return array{
     *   provider: string,
     *   explicit_week_start_date: string|null,
     *   strategy_objective: string|null,
     *   pro_tips: array<int, array{title: string, body: string}>,
     *   hashtags: string|null,
     *   days: array<string, array{
     *     day_number: int,
     *     day_name: string,
     *     focus: string|null,
     *     format: string|null,
     *     visual_concept: string|null,
     *     caption_cta: string|null
     *   }>
     * }
     */
    public function parsePdf(UploadedFile|string $pdfSource): array
    {
        if (PHP_SAPI !== 'cli') {
            @set_time_limit(120);
        }

        $this->lastTechnicalError = null;

        if ($pdfSource instanceof UploadedFile) {
            $rawBytes = file_get_contents($pdfSource->getRealPath());
        } elseif (is_string($pdfSource) && is_file($pdfSource)) {
            $rawBytes = file_get_contents($pdfSource);
        } else {
            throw new RuntimeException('Invalid PDF file provided for content plan import.');
        }

        if ($rawBytes === false || $rawBytes === '') {
            throw new RuntimeException('Uploaded PDF file appears to be empty.');
        }

        $base64Pdf = base64_encode($rawBytes);

        $geminiKey = trim((string) (config('services.gemini.api_key') ?: config('services.gemini.key')));
        if ($geminiKey === '' && app()->runningUnitTests()) {
            $geminiKey = 'test-gemini-api-key';
        }

        if ($geminiKey === '') {
            throw new RuntimeException(
                'No Gemini API key configured. Please set GEMINI_API_KEY in .env to enable AI PDF Plan Import.'
            );
        }

        [$decoded, $modelUsed] = $this->callGeminiPdfExtractor($base64Pdf, $geminiKey);

        return $this->normalizeExtractedPlan($decoded, "gemini:{$modelUsed}");
    }

    /**
     * Send base64 PDF inline_data (`application/pdf`) to Gemini with `responseMimeType: "application/json"`,
     * mirroring AdMetricsScreenshotParser's battle-tested HTTP + failover pattern.
     *
     * @return array{0: array<string, mixed>, 1: string}
     */
    protected function callGeminiPdfExtractor(string $base64Pdf, string $apiKey): array
    {
        $configuredModel = trim((string) config('services.gemini.vision_model', 'gemini-2.5-flash'));
        $candidateModels = array_values(array_unique(array_filter([
            $configuredModel,
            'gemini-2.5-flash',
            'gemini-2.0-flash',
            'gemini-2.5-flash-lite',
        ])));

        $parts = [
            [
                'inline_data' => [
                    'mime_type' => 'application/pdf',
                    'data' => $base64Pdf,
                ],
            ],
            [
                'text' => $this->buildExtractionPrompt(),
            ],
        ];

        $verifySsl = (bool) config('services.gemini.verify_ssl', true);
        if (app()->environment('local')) {
            $verifySsl = false;
        }

        $attemptErrors = [];

        foreach ($candidateModels as $model) {
            $endpoint = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$apiKey}";

            $generationConfig = [
                'temperature' => 0,
                'maxOutputTokens' => 8192,
                'responseMimeType' => 'application/json',
            ];

            if (str_starts_with($model, 'gemini-2.5-')) {
                $generationConfig['thinkingConfig'] = [
                    'thinkingBudget' => 0,
                ];
            }

            try {
                $response = Http::connectTimeout(6)
                    ->timeout(35)
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
                Log::warning("Gemini PDF import failed on {$model}, failing over", ['error' => $e->getMessage()]);
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
            Log::warning("Gemini PDF import HTTP {$status} on {$model}", ['status' => $status]);

            if (in_array($status, [400, 404, 408, 429, 500, 502, 503, 504], true)) {
                continue;
            }

            break;
        }

        $this->lastTechnicalError = implode(' ; ', $attemptErrors);
        throw new RuntimeException(self::BUSY_ERROR_MESSAGE);
    }

    protected function buildExtractionPrompt(): string
    {
        return <<<'PROMPT'
You are a precise structured data extractor for Laleen Beauty Salon's 1-Week Social Media Content Plan PDF.

Extract the full weekly plan from the attached PDF into strict JSON matching this exact schema:
{
  "explicit_week_start_date": null,
  "strategy_objective": "string or null — preserve exact English and/or Roman Urdu text verbatim",
  "pro_tips": [
    {
      "title": "Pin Best Performers",
      "body": "Exact recommendation text..."
    }
  ],
  "hashtags": "#LaleenBeautySalon #DohaSalons #QatarBeauty",
  "days": [
    {
      "day_number": 1,
      "day_name": "Monday",
      "focus": "7 Services 99 QAR",
      "format": "Reel / Short Video",
      "visual_concept": "Full visual / video concept brief...",
      "caption_cta": "Full caption hook & call to action without surrounding outer quotes"
    }
  ]
}

CRITICAL EXTRACTION RULES:
1. `explicit_week_start_date`: Only set a "YYYY-MM-DD" date if explicit calendar dates (e.g., "16 Nov 2026" or "Nov 16 – Nov 22, 2026") are written in the PDF. If the PDF only says "Day 1: Monday" .. "Day 7: Sunday" with no calendar dates, return `null`.
2. `strategy_objective`: Preserve exact English and Roman Urdu sentences verbatim without translating or summarizing.
3. `pro_tips`: Extract every Pro-Tip / Section 2 item as `{ "title": "...", "body": "..." }`. Return `[]` if none exist.
4. `hashtags`: Return a single space-separated string of `#hashtag` tags if present in the PDF, else `null`.
5. `days`: Return all 7 days (`Monday` through `Sunday`, `day_number` 1 through 7):
   - `focus`: Extract ONLY the offer/topic under the day label, stripped of prefixes like `Day 1: Monday`, `Day 1 - Monday:`, or `Monday —`.
   - `format`: Preserve the exact format label (e.g., `Reel / Short Video`, `Carousel (Before & After)`, `Before / After Reel`, `Static Graphic / Story`, `POV Reel / Trendy Audio`, `Story Series (3 Frames)`, `Static Offer Post`).
   - `visual_concept`: Full visual / video concept text.
   - `caption_cta`: Full caption hook & CTA text with surrounding outer quotation marks (`"..."` or `“...”`) stripped.
6. Output valid JSON only, with no markdown code fences.
PROMPT;
    }

    /**
     * Normalize Gemini JSON output into a guaranteed 7-day Monday..Sunday map + clean metadata.
     *
     * @param  array<string, mixed>  $decoded
     * @return array<string, mixed>
     */
    protected function normalizeExtractedPlan(array $decoded, string $provider): array
    {
        $explicitWeekStart = null;
        $rawExplicit = trim((string) ($decoded['explicit_week_start_date'] ?? ''));
        if ($rawExplicit !== '' && strtolower($rawExplicit) !== 'null') {
            try {
                $explicitWeekStart = Carbon::parse($rawExplicit)->startOfWeek(Carbon::MONDAY)->toDateString();
            } catch (\Throwable) {
                $explicitWeekStart = null;
            }
        }

        $strategyObjective = trim((string) ($decoded['strategy_objective'] ?? ''));
        $strategyObjective = $strategyObjective !== '' ? $strategyObjective : null;

        $proTips = [];
        $rawTips = $decoded['pro_tips'] ?? [];
        if (is_array($rawTips)) {
            foreach ($rawTips as $tip) {
                if (is_string($tip) && trim($tip) !== '') {
                    $proTips[] = ['title' => 'Pro-Tip', 'body' => trim($tip)];
                } elseif (is_array($tip)) {
                    $title = trim((string) ($tip['title'] ?? $tip['heading'] ?? ''));
                    $body = trim((string) ($tip['body'] ?? $tip['description'] ?? $tip['text'] ?? ''));
                    if ($title !== '' || $body !== '') {
                        $proTips[] = [
                            'title' => $title !== '' ? $title : 'Pro-Tip',
                            'body' => $body,
                        ];
                    }
                }
            }
        }

        $rawHashtags = $decoded['hashtags'] ?? null;
        if (is_array($rawHashtags)) {
            $tags = array_filter(array_map(function ($tag) {
                $clean = trim((string) $tag);
                if ($clean === '') {
                    return null;
                }

                return str_starts_with($clean, '#') ? $clean : '#' . $clean;
            }, $rawHashtags));
            $hashtags = !empty($tags) ? implode(' ', $tags) : null;
        } else {
            $cleanTags = trim((string) $rawHashtags);
            $hashtags = $cleanTags !== '' ? $cleanTags : null;
        }

        $daysMap = [];
        foreach (SocialContentPlan::DAYS_OF_WEEK as $idx => $dayName) {
            $daysMap[$dayName] = [
                'day_number' => $idx + 1,
                'day_name' => $dayName,
                'focus' => null,
                'format' => null,
                'visual_concept' => null,
                'caption_cta' => null,
            ];
        }

        $rawDays = $decoded['days'] ?? [];
        if (is_array($rawDays)) {
            foreach (array_values($rawDays) as $idx => $item) {
                if (!is_array($item)) {
                    continue;
                }

                $resolvedDay = $this->resolveCanonicalDayName($item, $idx);
                if ($resolvedDay === null) {
                    continue;
                }

                $dayNumber = array_search($resolvedDay, SocialContentPlan::DAYS_OF_WEEK, true) + 1;

                $daysMap[$resolvedDay] = [
                    'day_number' => $dayNumber,
                    'day_name' => $resolvedDay,
                    'focus' => $this->cleanFocus($item['focus'] ?? null, $resolvedDay),
                    'format' => $this->cleanNullableString($item['format'] ?? null),
                    'visual_concept' => $this->cleanNullableString($item['visual_concept'] ?? null),
                    'caption_cta' => $this->stripOuterQuotes($item['caption_cta'] ?? null),
                ];
            }
        }

        return [
            'provider' => $provider,
            'explicit_week_start_date' => $explicitWeekStart,
            'strategy_objective' => $strategyObjective,
            'pro_tips' => $proTips,
            'hashtags' => $hashtags,
            'days' => $daysMap,
        ];
    }

    /**
     * @param  array<string, mixed>  $item
     */
    protected function resolveCanonicalDayName(array $item, int $fallbackIndex): ?string
    {
        $rawName = strtolower(trim((string) ($item['day_name'] ?? $item['day_of_week'] ?? $item['day'] ?? '')));
        foreach (SocialContentPlan::DAYS_OF_WEEK as $canonical) {
            if ($rawName !== '' && str_contains($rawName, strtolower($canonical))) {
                return $canonical;
            }
        }

        $dayNum = (int) ($item['day_number'] ?? 0);
        if ($dayNum >= 1 && $dayNum <= 7) {
            return SocialContentPlan::DAYS_OF_WEEK[$dayNum - 1];
        }

        return SocialContentPlan::DAYS_OF_WEEK[$fallbackIndex] ?? null;
    }

    protected function cleanFocus(mixed $value, string $dayName): ?string
    {
        $str = trim((string) $value);
        if ($str === '') {
            return null;
        }

        // Strip prefixes like "Day 1: Monday - ", "Day 1 (Monday):", "Monday — "
        $pattern = '/^(?:day\s*\d+\s*[:\-–—()]?\s*)?(?:' . preg_quote($dayName, '/') . '\s*[:\-–—()]?\s*)?/i';
        $cleaned = trim((string) preg_replace($pattern, '', $str));
        $cleaned = ltrim($cleaned, ":-–—) \t\n\r");

        return $cleaned !== '' ? $cleaned : $str;
    }

    protected function cleanNullableString(mixed $value): ?string
    {
        $str = trim((string) $value);

        return $str !== '' ? $str : null;
    }

    protected function stripOuterQuotes(mixed $value): ?string
    {
        $str = trim((string) $value);
        if ($str === '') {
            return null;
        }

        // Strip one layer of outer straight or curly quotes if wrapping the entire caption
        if (
            (str_starts_with($str, '"') && str_ends_with($str, '"') && mb_strlen($str) >= 2)
            || (str_starts_with($str, '“') && str_ends_with($str, '”') && mb_strlen($str) >= 2)
            || (str_starts_with($str, "'") && str_ends_with($str, "'") && mb_strlen($str) >= 2)
        ) {
            $str = trim(mb_substr($str, 1, mb_strlen($str) - 2));
        }

        return $str !== '' ? $str : null;
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function decodeJsonFromText(string $rawText): ?array
    {
        if ($rawText === '') {
            return null;
        }

        $cleaned = preg_replace('/^```(?:json)?\s*|\s*```$/i', '', trim($rawText)) ?? $rawText;
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
