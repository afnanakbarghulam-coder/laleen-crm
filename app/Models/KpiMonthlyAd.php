<?php

namespace App\Models;

use App\Models\Concerns\HasMonthlyAdCreatives;
use Illuminate\Database\Eloquent\Model;

class KpiMonthlyAd extends Model
{
    use HasMonthlyAdCreatives;

    const PHASES = ['Planning', 'Testing', 'Scaling', 'Killed'];

    /** Phases shown as the 3 Kanban columns on the board — Killed ads sit in the Graveyard below. */
    const BOARD_PHASES = ['Planning', 'Testing', 'Scaling'];

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
        'creatives',
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
        'aggressive_reaction',
        'cpm',
        'ctr',
        'lead_conversion_status',
    ];

    protected $casts = [
        'creatives' => 'array',
        'live_date' => 'date',
        'raw_data_deadline' => 'date',
        'final_creative_deadline' => 'date',
        'total_budget' => 'decimal:2',
        'offer_price' => 'decimal:2',
        'cost_per_message' => 'decimal:2',
        'cpm' => 'decimal:2',
        'ctr' => 'decimal:2',
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
            'aggressive_reaction',
            'cpm',
            'ctr',
            'lead_conversion_status',
        ]);

        $payload['phase'] = $this->normalizedPhase();
        $payload['creatives'] = $this->resolvedCreatives();

        foreach (self::DATE_FIELDS as $field) {
            $payload[$field] = $this->{$field}?->format('Y-m-d');
        }

        return $payload;
    }

    /** The upcoming (or, if already past, the most recent) deadline for the ad's current phase. Null if none applies. */
    public function nextDeadline(): ?array
    {
        $phase = $this->normalizedPhase();

        if ($phase === 'Planning') {
            if ($this->final_creative_deadline) {
                return ['label' => 'Creative Due', 'date' => $this->final_creative_deadline];
            }

            return $this->live_date ? ['label' => 'Go Live', 'date' => $this->live_date] : null;
        }

        if ($phase === 'Testing') {
            $date = $this->testing_ends_at ?? ($this->testing_started_at ? $this->testing_started_at->copy()->addDays(7) : $this->live_date);

            return $date ? ['label' => 'Test Ends', 'date' => $date] : null;
        }

        if ($phase === 'Scaling') {
            $date = $this->verdict_at ?? $this->live_date;

            return $date ? ['label' => 'Scaled On', 'date' => $date] : null;
        }

        return null;
    }
}
