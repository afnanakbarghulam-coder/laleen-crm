<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class KpiMonthlyAd extends Model
{
    const PHASES = ['Planning', 'Production', 'Testing', 'Scaling', 'Killed'];

    /** Phases shown as Kanban columns on the board — Killed ads drop off the board once marked. */
    const BOARD_PHASES = ['Planning', 'Production', 'Testing', 'Scaling'];

    protected $table = 'monthly_ads';

    protected $fillable = [
        'ad_name',
        'person_responsible',
        'pre_filled_message',
        'offer',
        'production_person_responsible',
        'editing_deadline',
        'approved_by',
        'live_date',
        'total_budget',
        'phase',
    ];

    protected $casts = [
        'editing_deadline' => 'date',
        'live_date' => 'date',
        'total_budget' => 'decimal:2',
    ];

    public function toEditPayload(): array
    {
        return $this->only([
            'id',
            'ad_name',
            'person_responsible',
            'pre_filled_message',
            'offer',
            'production_person_responsible',
            'editing_deadline',
            'approved_by',
            'live_date',
            'total_budget',
            'phase',
        ]);
    }

    /** The nearer of editing_deadline / live_date that's still upcoming (or the nearer of the two if both are past). Null if neither is set. */
    public function nextDeadline(): ?array
    {
        $candidates = collect([
            $this->editing_deadline ? ['label' => 'Editing', 'date' => $this->editing_deadline] : null,
            $this->live_date ? ['label' => 'Live', 'date' => $this->live_date] : null,
        ])->filter();

        if ($candidates->isEmpty()) {
            return null;
        }

        $upcoming = $candidates->filter(fn ($c) => $c['date']->isFuture());

        return ($upcoming->isNotEmpty() ? $upcoming : $candidates)->sortBy('date')->first();
    }
}
