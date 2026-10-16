<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class KpiMonthlyAd extends Model
{
    const PHASES = ['Planning', 'Production', 'Testing', 'Scaling', 'Killed'];

    /** Phases shown as Kanban columns on the board — Killed ads drop off the board once marked. */
    const BOARD_PHASES = ['Planning', 'Production', 'Testing', 'Scaling'];

    const BRANCHES = ['All Branches', 'Old Airport', 'Wakrah'];

    const RAW_DATA_STATUSES = ['available', 'scheduled'];

    const WINNING_CREATIVES = ['Creative 1', 'Creative 2', 'Creative 3', 'None'];

    const SALES_VERDICTS = [
        'High Messages + Sales (Scale)',
        'Audience Only / No Sales',
        'Too Expensive / Dead',
    ];

    /** Date columns formatted as Y-m-d strings in toEditPayload() for the modal's JS to consume directly. */
    const DATE_FIELDS = [
        'live_date',
        'raw_data_deadline',
        'final_creative_deadline',
        'production_started_at',
        'testing_started_at',
        'testing_ends_at',
        'verdict_at',
    ];

    protected $table = 'monthly_ads';

    protected $fillable = [
        'ad_name',
        'person_responsible',
        'pre_filled_message',
        'offer',
        'plan_month',
        'target_branch',
        'offer_price',
        'live_date',
        'raw_data_status',
        'raw_data_deadline',
        'final_creative_deadline',
        'total_budget',
        'phase',
        'creative_1_name',
        'creative_asset_link',
        'creative_2_name',
        'creative_asset_link_2',
        'creative_3_name',
        'creative_asset_link_3',
        'chk_hook',
        'chk_value',
        'chk_risk',
        'chk_cta',
        'txt_hook',
        'txt_value',
        'txt_risk',
        'txt_cta',
        'production_started_at',
        'testing_started_at',
        'testing_ends_at',
        'verdict_at',
        'winning_creative',
        'cost_per_message',
        'sales_verdict',
        'testing_notes',
    ];

    protected $casts = [
        'live_date' => 'date',
        'raw_data_deadline' => 'date',
        'final_creative_deadline' => 'date',
        'total_budget' => 'decimal:2',
        'offer_price' => 'decimal:2',
        'cost_per_message' => 'decimal:2',
        'production_started_at' => 'date',
        'testing_started_at' => 'date',
        'testing_ends_at' => 'date',
        'verdict_at' => 'date',
        'chk_hook' => 'boolean',
        'chk_value' => 'boolean',
        'chk_risk' => 'boolean',
        'chk_cta' => 'boolean',
    ];

    public function toEditPayload(): array
    {
        $payload = $this->only([
            'id',
            'ad_name',
            'person_responsible',
            'pre_filled_message',
            'offer',
            'plan_month',
            'target_branch',
            'offer_price',
            'live_date',
            'raw_data_status',
            'raw_data_deadline',
            'final_creative_deadline',
            'total_budget',
            'phase',
            'creative_1_name',
            'creative_asset_link',
            'creative_2_name',
            'creative_asset_link_2',
            'creative_3_name',
            'creative_asset_link_3',
            'chk_hook',
            'chk_value',
            'chk_risk',
            'chk_cta',
            'txt_hook',
            'txt_value',
            'txt_risk',
            'txt_cta',
            'production_started_at',
            'testing_started_at',
            'testing_ends_at',
            'verdict_at',
            'winning_creative',
            'cost_per_message',
            'sales_verdict',
            'testing_notes',
        ]);

        foreach (self::DATE_FIELDS as $field) {
            $payload[$field] = $this->{$field}?->format('Y-m-d');
        }

        return $payload;
    }

    /** The upcoming (or, if already past, the most recent) deadline for the ad's current phase. Null if none applies. */
    public function nextDeadline(): ?array
    {
        if ($this->phase === 'Planning') {
            return $this->live_date ? ['label' => 'Go Live', 'date' => $this->live_date] : null;
        }

        if ($this->phase === 'Production') {
            $creativesLinked = !empty($this->creative_asset_link) && !empty($this->creative_asset_link_2) && !empty($this->creative_asset_link_3);

            if ($this->raw_data_status === 'scheduled' && $this->raw_data_deadline && !$creativesLinked) {
                return ['label' => 'Raw Data Due', 'date' => $this->raw_data_deadline];
            }

            $date = $this->final_creative_deadline ?? $this->live_date;

            return $date ? ['label' => 'Final Creative Due', 'date' => $date] : null;
        }

        if ($this->phase === 'Testing') {
            $date = $this->testing_ends_at ?? ($this->testing_started_at ? $this->testing_started_at->copy()->addDays(7) : $this->live_date);

            return $date ? ['label' => '7-Day Test Ends', 'date' => $date] : null;
        }

        if ($this->phase === 'Scaling') {
            $date = $this->verdict_at ?? $this->live_date;

            return $date ? ['label' => 'Scaled On', 'date' => $date] : null;
        }

        return null;
    }
}
