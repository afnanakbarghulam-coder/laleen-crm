<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

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

    public function getTotalInjectedAttribute(): float
    {
        return (float) $this->transactions()->where('type', 'injection')->sum('amount');
    }

    public function getTotalDistributedAttribute(): float
    {
        return (float) $this->transactions()->where('type', 'distribution')->sum('amount');
    }

    /**
     * Capital matching across all partners: the target is the highest amount
     * any single partner has injected, so every other partner's balance is
     * what they still owe to match that top investor (never negative).
     */
    public static function equalizationSummary()
    {
        $partners = static::all();
        $pool = (float) $partners->sum('total_injected');
        $maxInjected = (float) $partners->max('total_injected');

        return $partners->map(function (Partner $partner) use ($pool, $maxInjected) {
            $contributed = $partner->total_injected;

            return [
                'partner' => $partner,
                'contributed' => $contributed,
                'percent' => $pool > 0 ? ($contributed / $pool) * 100 : 0,
                'max_injected' => $maxInjected,
                'balance' => $maxInjected - $contributed,
            ];
        });
    }
}
