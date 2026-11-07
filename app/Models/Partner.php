<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class Partner extends Model
{
    protected $fillable = [
        'name',
        'email',
        'equity_percentage',
    ];

    protected $casts = [
        'equity_percentage' => 'decimal:4',
    ];

    protected $appends = [
        'total_injected',
        'total_distributed',
    ];

    public function transactions()
    {
        return $this->hasMany(PartnerTransaction::class);
    }

    /**
     * Preloads both per-partner transaction totals as SQL aggregates on the
     * same query. Optionally scoped by currency ('PKR' or 'QAR').
     */
    public function scopeWithTransactionTotals(Builder $query, ?string $currency = null): Builder
    {
        return $query
            ->withSum([
                'transactions as total_injected_sum' => function ($q) use ($currency) {
                    $q->where('type', 'injection');
                    if ($currency !== null) {
                        $q->where('currency', $currency);
                    }
                },
            ], 'amount')
            ->withSum([
                'transactions as total_distributed_sum' => function ($q) use ($currency) {
                    $q->where('type', 'distribution');
                    if ($currency !== null) {
                        $q->where('currency', $currency);
                    }
                },
            ], 'amount');
    }

    public function getTotalInjectedAttribute(): float
    {
        if (array_key_exists('total_injected_sum', $this->attributes)) {
            return (float) ($this->attributes['total_injected_sum'] ?? 0);
        }

        return (float) $this->transactions()->where('type', 'injection')->sum('amount');
    }

    public function getTotalDistributedAttribute(): float
    {
        if (array_key_exists('total_distributed_sum', $this->attributes)) {
            return (float) ($this->attributes['total_distributed_sum'] ?? 0);
        }

        return (float) $this->transactions()->where('type', 'distribution')->sum('amount');
    }

    public static function equalizationSummary(?Collection $partners = null, ?string $currency = null)
    {
        $partners ??= static::withTransactionTotals($currency)->get();

        $pool = (float) $partners->sum('total_injected');
        $maxInjected = (float) $partners->max('total_injected');

        return $partners->map(function (Partner $partner) use ($pool, $maxInjected) {
            $contributed = $partner->total_injected;

            return [
                'partner' => $partner,
                'contributed' => $contributed,
                'distributed' => $partner->total_distributed,
                'percent' => $pool > 0 ? ($contributed / $pool) * 100 : 0,
                'max_injected' => $maxInjected,
                'balance' => $maxInjected - $contributed,
            ];
        });
    }
}
