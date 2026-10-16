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
     * same query. Without this the total_injected / total_distributed
     * accessors each fire their own SUM per partner on every serialization
     * (N+1) — $appends means that happens on every page render.
     */
    public function scopeWithTransactionTotals(Builder $query): Builder
    {
        return $query
            ->withSum(['transactions as total_injected_sum' => fn ($q) => $q->where('type', 'injection')], 'amount')
            ->withSum(['transactions as total_distributed_sum' => fn ($q) => $q->where('type', 'distribution')], 'amount');
    }

    public function getTotalInjectedAttribute(): float
    {
        // Prefer the preloaded aggregate from withTransactionTotals(); fall
        // back to a direct SUM so a Partner fetched without the scope (e.g.
        // route-model binding) still reports the right number.
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

    /**
     * Capital matching across all partners: the target is the highest amount
     * any single partner has injected, so every other partner's balance is
     * what they still owe to match that top investor (never negative).
     *
     * Pass an already-loaded collection (from withTransactionTotals()) to
     * reuse it instead of issuing a second round of queries.
     */
    public static function equalizationSummary(?Collection $partners = null)
    {
        $partners ??= static::withTransactionTotals()->get();

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
