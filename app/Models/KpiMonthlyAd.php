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
        'live_date',
        'raw_data_deadline',
        'final_creative_deadline',
        'total_budget',
        'phase',
    ];

    protected $casts = [
        'live_date' => 'date',
        'raw_data_deadline' => 'date',
        'final_creative_deadline' => 'date',
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
            'live_date',
            'raw_data_deadline',
            'final_creative_deadline',
            'total_budget',
            'phase',
        ]);
    }

    /** The upcoming (or, if already past, the most recent) live date. Null if none is set. */
    public function nextDeadline(): ?array
    {
        if (!$this->live_date) {
            return null;
        }

        return ['label' => 'Live', 'date' => $this->live_date];
    }
}
