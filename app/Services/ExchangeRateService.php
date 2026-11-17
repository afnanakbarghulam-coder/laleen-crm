<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ExchangeRateService
{
    public const ENDPOINT_USD = 'https://open.er-api.com/v6/latest/USD';
    public const CACHE_KEY_USD_RATES = 'laleen_market_fx_usd_rates_v1';
    public const CACHE_TTL_SECONDS = 3600;

    public const FALLBACK_USD_RATES = [
        'USD' => 1.0000,
        'QAR' => 3.6415,
        'PKR' => 278.5000,
        'AED' => 3.6725,
        'SAR' => 3.7500,
        'EUR' => 0.9200,
        'GBP' => 0.7900,
    ];

    /**
     * Return the live market FX rate converting 1 unit of `$fromCurrency` into `$toCurrency`,
     * along with metadata (`source`: 'live' | 'fallback').
     *
     * @return array{rate: float, source: string, from: string, to: string}
     */
    public function getRateInfo(string $fromCurrency = 'USD', string $toCurrency = 'QAR'): array
    {
        $from = strtoupper(trim($fromCurrency ?: 'USD'));
        $to = strtoupper(trim($toCurrency ?: 'QAR'));

        if ($from === $to) {
            return [
                'rate' => 1.0000,
                'source' => 'identity',
                'from' => $from,
                'to' => $to,
            ];
        }

        $bundle = $this->fetchUsdRatesBundle();
        $rates = $bundle['rates'];
        $source = $bundle['source'];

        $fromPerUsd = (float) ($rates[$from] ?? self::FALLBACK_USD_RATES[$from] ?? 1.0);
        $toPerUsd = (float) ($rates[$to] ?? self::FALLBACK_USD_RATES[$to] ?? 1.0);

        if ($from === 'USD') {
            $computed = $toPerUsd > 0 ? $toPerUsd : (self::FALLBACK_USD_RATES[$to] ?? 1.0);
        } elseif ($to === 'USD') {
            $computed = $fromPerUsd > 0 ? (1.0 / $fromPerUsd) : 1.0;
        } else {
            $computed = ($fromPerUsd > 0 && $toPerUsd > 0) ? ($toPerUsd / $fromPerUsd) : 1.0;
        }

        return [
            'rate' => round($computed, 4),
            'source' => $source,
            'from' => $from,
            'to' => $to,
        ];
    }

    public function getRate(string $fromCurrency = 'USD', string $toCurrency = 'QAR'): float
    {
        return $this->getRateInfo($fromCurrency, $toCurrency)['rate'];
    }

    /**
     * Fetch USD market exchange rates with a 1-hour cache and offline-safe fallback (`3.6415` QAR).
     *
     * @return array{rates: array<string, float>, source: string}
     */
    protected function fetchUsdRatesBundle(): array
    {
        $cached = Cache::get(self::CACHE_KEY_USD_RATES);
        if (is_array($cached) && !empty($cached['rates']) && is_array($cached['rates'])) {
            return $cached;
        }

        // Avoid real outbound internet latency in unrelated unit tests unless Http::fake() is active
        if (app()->runningUnitTests() && !$this->isHttpClientFaked()) {
            return [
                'rates' => self::FALLBACK_USD_RATES,
                'source' => 'fallback',
            ];
        }

        try {
            $response = Http::connectTimeout(2)
                ->timeout(3)
                ->when(app()->environment('local'), fn ($h) => $h->withoutVerifying())
                ->get(self::ENDPOINT_USD);

            if ($response->successful()) {
                $payload = $response->json();
                $rawRates = $payload['rates'] ?? $payload['conversion_rates'] ?? null;

                if (is_array($rawRates) && isset($rawRates['QAR']) && (float) $rawRates['QAR'] > 0) {
                    $normalized = self::FALLBACK_USD_RATES;
                    foreach ($rawRates as $code => $val) {
                        if (is_string($code) && is_numeric($val) && (float) $val > 0) {
                            $normalized[strtoupper($code)] = round((float) $val, 6);
                        }
                    }

                    $bundle = [
                        'rates' => $normalized,
                        'source' => 'live',
                    ];

                    Cache::put(self::CACHE_KEY_USD_RATES, $bundle, self::CACHE_TTL_SECONDS);

                    return $bundle;
                }
            }
        } catch (\Throwable $e) {
            Log::debug('ExchangeRateService live fetch fallback used: ' . $e->getMessage());
        }

        return [
            'rates' => self::FALLBACK_USD_RATES,
            'source' => 'fallback',
        ];
    }

    protected function isHttpClientFaked(): bool
    {
        try {
            $root = Http::getFacadeRoot();
            if (!is_object($root)) {
                return false;
            }

            return (bool) (fn () => $this->recording ?? false)->call($root);
        } catch (\Throwable) {
            return false;
        }
    }
}
