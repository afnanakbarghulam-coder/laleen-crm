<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductRdSample extends Model
{
    public const STATUSES = [
        'waiting_sample'  => 'Waiting Sample',
        'received'        => 'Received',
        'testing'         => 'Testing',
        'revision_needed' => 'Revision Needed',
    ];

    public const VERDICTS = [
        'pending'  => 'Pending',
        'selected' => 'Selected',
        'rejected' => 'Rejected',
    ];

    public const ALLOWED_SCORES = [1, 5];

    public const SCORE_FIELDS = [
        'score_smell'         => 'Smell',
        'score_cost'          => 'Cost',
        'score_texture'       => 'Texture',
        'score_results'       => 'Results',
        'score_concept_clear' => 'Concept Clear',
    ];

    protected $fillable = [
        'product_rd_item_id',
        'product_rd_supplier_id',
        'version',
        'status',
        'verdict',
        'tracking_ref',
        'expected_at',
        'next_followup_at',
        'quoted_unit_cost',
        'quoted_currency',
        'cost_note',
        'score_smell',
        'score_cost',
        'score_texture',
        'score_results',
        'score_concept_clear',
        'evaluation_notes',
    ];

    protected $casts = [
        'expected_at'         => 'date',
        'next_followup_at'    => 'date',
        'quoted_unit_cost'    => 'decimal:2',
        'score_smell'         => 'integer',
        'score_cost'          => 'integer',
        'score_texture'       => 'integer',
        'score_results'       => 'integer',
        'score_concept_clear' => 'integer',
    ];

    public function item(): BelongsTo
    {
        return $this->belongsTo(ProductRdItem::class, 'product_rd_item_id');
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(ProductRdSupplier::class, 'product_rd_supplier_id');
    }

    public static function normalizeStatus(?string $status): string
    {
        $clean = strtolower(trim((string) $status));

        return array_key_exists($clean, self::STATUSES) ? $clean : 'waiting_sample';
    }

    public static function normalizeVerdict(?string $verdict): string
    {
        $clean = strtolower(trim((string) $verdict));

        return array_key_exists($clean, self::VERDICTS) ? $clean : 'pending';
    }

    public function statusLabel(): string
    {
        $norm = self::normalizeStatus($this->status);

        return self::STATUSES[$norm] ?? 'Waiting Sample';
    }

    public function verdictLabel(): string
    {
        $norm = self::normalizeVerdict($this->verdict);

        return self::VERDICTS[$norm] ?? 'Pending';
    }

    public function costDisplay(): string
    {
        if ($this->cost_note !== null && trim($this->cost_note) !== '') {
            return trim($this->cost_note);
        }

        if ($this->score_cost !== null) {
            return (string) $this->score_cost;
        }

        if ($this->quoted_unit_cost !== null) {
            return number_format((float) $this->quoted_unit_cost, 2) . ' ' . ($this->quoted_currency ?: 'QAR');
        }

        return '';
    }

    /**
     * @return array<string, int|null>
     */
    public function scoreValues(): array
    {
        $scores = [];
        foreach (array_keys(self::SCORE_FIELDS) as $field) {
            $val = $this->{$field};
            $scores[$field] = ($val !== null && in_array((int) $val, self::ALLOWED_SCORES, true)) ? (int) $val : null;
        }

        return $scores;
    }

    public function averageScore(): ?float
    {
        $valid = array_filter($this->scoreValues(), static fn ($v) => $v !== null);
        if (empty($valid)) {
            return null;
        }

        return round(array_sum($valid) / count($valid), 1);
    }
}
