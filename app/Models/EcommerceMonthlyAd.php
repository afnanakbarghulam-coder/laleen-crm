<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EcommerceMonthlyAd extends Model
{
    protected $table = 'ecommerce_monthly_ads';

    public const PHASES = ['Planning', 'Production', 'Testing', 'Scaling', 'Killed'];

    /** Killed ads drop off the board into the graveyard drawer. */
    public const BOARD_PHASES = ['Planning', 'Production', 'Testing', 'Scaling'];

    public const SCALING_STATUSES = [
        'Scale Aggressively',
        'Scale Slowly',
        'Hold & Optimise',
    ];

    protected $fillable = [
        'ad_name',
        'person_responsible',
        'offer',
        'total_budget',
        'pre_filled_message',
        'live_date',
        'phase',
        'raw_data_deadline',
        'final_creative_deadline',
        'creative_asset_link',
        'chk_hook', 'txt_hook',
        'chk_value', 'txt_value',
        'chk_risk', 'txt_risk',
        'chk_cta', 'txt_cta',
        'test_spend',
        'leads_generated',
        'target_cpl',
        'scaling_status',
    ];

    protected $casts = [
        'live_date' => 'date',
        'raw_data_deadline' => 'date',
        'final_creative_deadline' => 'date',
        'total_budget' => 'decimal:2',
        'test_spend' => 'decimal:2',
        'target_cpl' => 'decimal:2',
        'leads_generated' => 'integer',
        'chk_hook' => 'boolean',
        'chk_value' => 'boolean',
        'chk_risk' => 'boolean',
        'chk_cta' => 'boolean',
    ];

    /**
     * Actual cost per lead. Null (not zero) when no leads have come in —
     * "no data" and "free leads" must not look the same on the board.
     */
    public function actualCpl(): ?float
    {
        $leads = (int) $this->leads_generated;

        return $leads > 0 ? (float) $this->test_spend / $leads : null;
    }

    /** True only when a real CPL exists and it beats target. */
    public function isCplHealthy(): ?bool
    {
        $actual = $this->actualCpl();
        $target = (float) $this->target_cpl;

        if ($actual === null || $target <= 0) {
            return null;
        }

        return $actual <= $target;
    }

    public function cplVariancePercent(): ?float
    {
        $actual = $this->actualCpl();
        $target = (float) $this->target_cpl;

        if ($actual === null || $target <= 0) {
            return null;
        }

        return (($actual - $target) / $target) * 100;
    }

    public function completedChecklistCount(): int
    {
        return (int) $this->chk_hook + (int) $this->chk_value + (int) $this->chk_risk + (int) $this->chk_cta;
    }

    /**
     * A QC point only counts when it is BOTH ticked and justified in writing.
     * Returns the labels of whatever is still outstanding.
     */
    public function missingChecklistPoints(): array
    {
        $points = [
            'Hook' => [$this->chk_hook, $this->txt_hook],
            'Value' => [$this->chk_value, $this->txt_value],
            'Risk' => [$this->chk_risk, $this->txt_risk],
            'CTA' => [$this->chk_cta, $this->txt_cta],
        ];

        $missing = [];
        foreach ($points as $label => [$checked, $text]) {
            if (!$checked || trim((string) $text) === '') {
                $missing[] = $label;
            }
        }

        return $missing;
    }

    public function toEditPayload(): array
    {
        $payload = $this->only([
            'id', 'ad_name', 'person_responsible', 'offer', 'total_budget',
            'pre_filled_message', 'phase', 'creative_asset_link',
            'chk_hook', 'txt_hook', 'chk_value', 'txt_value',
            'chk_risk', 'txt_risk', 'chk_cta', 'txt_cta',
            'test_spend', 'leads_generated', 'target_cpl', 'scaling_status',
        ]);

        foreach (['live_date', 'raw_data_deadline', 'final_creative_deadline'] as $field) {
            $payload[$field] = $this->{$field}?->format('Y-m-d');
        }

        return $payload;
    }
}
