<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class KpiStaffSalesReport extends Model
{
    const DEFAULT_MONTHLY_TARGET = 1700;
    const DAYS_IN_MONTH = 31;

    protected $fillable = [
        'branch',
        'date_from',
        'date_to',
        'monthly_target_per_staff',
        'created_by',
    ];

    protected $casts = [
        'date_from' => 'date',
        'date_to' => 'date',
        'monthly_target_per_staff' => 'float',
    ];

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function entries()
    {
        return $this->hasMany(KpiStaffSalesEntry::class, 'report_id');
    }

    public function daysElapsed(): int
    {
        return max($this->date_from->diffInDays($this->date_to) + 1, 1);
    }

    public function proratedTarget(): float
    {
        return round($this->monthly_target_per_staff * $this->daysElapsed() / self::DAYS_IN_MONTH, 2);
    }

    public function borderFor(float $pct): string
    {
        return match (true) {
            $pct >= 85 => 'green',
            $pct >= 50 => 'amber',
            default => 'red',
        };
    }

    /**
     * Staff entries enriched with % of prorated target, gap, and border color.
     */
    public function computedStaff()
    {
        $prorated = $this->proratedTarget();

        return $this->entries->map(function ($entry) use ($prorated) {
            $pct = $prorated > 0 ? round($entry->total_upsell / $prorated * 100, 1) : 0.0;

            return [
                'name' => $entry->staff_name,
                'upsell' => (float) $entry->total_upsell,
                'prorated_target' => $prorated,
                'pct' => $pct,
                'gap' => round($prorated - $entry->total_upsell, 2),
                'border' => $this->borderFor($pct),
            ];
        });
    }

    public function totals(): array
    {
        $staff = $this->computedStaff();
        $prorated = $this->proratedTarget();
        $teamTotal = round($staff->sum('upsell'), 2);
        $teamTarget = round($prorated * max($staff->count(), 1), 2);
        $teamPct = $teamTarget > 0 ? round($teamTotal / $teamTarget * 100, 1) : 0.0;
        $top = $staff->sortByDesc('upsell')->first();

        return [
            'team_total' => $teamTotal,
            'team_target' => $teamTarget,
            'team_pct' => $teamPct,
            'top_performer' => $top['name'] ?? '—',
            'top_performer_amount' => $top['upsell'] ?? 0,
            'zero_upsell_count' => $staff->where('upsell', 0)->count(),
        ];
    }
}
