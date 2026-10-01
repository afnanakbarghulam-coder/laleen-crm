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
     * Capital parity across all partners: each partner's cash contributed
     * weighed against an equal fair share of the total pool, so a partner
     * who has put in less than their share shows a positive "owes" balance.
     */
    public static function equalizationSummary()
    {
        $partners = static::all();
        $pool = (float) $partners->sum('total_injected');
        $count = max($partners->count(), 1);
        $fairShare = $pool / $count;

        return $partners->map(function (Partner $partner) use ($pool, $fairShare) {
            $contributed = $partner->total_injected;

            return [
                'partner' => $partner,
                'contributed' => $contributed,
                'percent' => $pool > 0 ? ($contributed / $pool) * 100 : 0,
                'fair_share' => $fairShare,
                'balance' => $fairShare - $contributed,
            ];
        });
    }
}
