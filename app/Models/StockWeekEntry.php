<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockWeekEntry extends Model
{
    use HasFactory;

    protected $table = 'stock_week_entries';

    protected $fillable = [
        'stock_week_id',
        'stock_item_id',
        'opening_qty',
        'received_qty',
        'transfer_net_qty',
        'closing_qty',
        'used_qty',
        'retail_sold_qty',
        'notes',
    ];

    protected $casts = [
        'opening_qty' => 'float',
        'received_qty' => 'float',
        'transfer_net_qty' => 'float',
        'closing_qty' => 'float',
        'used_qty' => 'float',
        'retail_sold_qty' => 'float',
    ];

    public function week(): BelongsTo
    {
        return $this->belongsTo(StockWeek::class, 'stock_week_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(StockItem::class, 'stock_item_id');
    }

    public function netAddedQty(): float
    {
        return round((float) $this->received_qty + (float) $this->transfer_net_qty, 2);
    }

    public function totalAvailableQty(): float
    {
        return round(
            (float) $this->opening_qty
            + (float) $this->received_qty
            + (float) $this->transfer_net_qty,
            2
        );
    }

    /**
     * Server-side math: Used = Opening + Received + TransferNet - Closing.
     */
    public function computeUsedQty(): ?float
    {
        if ($this->closing_qty === null) {
            return null;
        }

        return round($this->totalAvailableQty() - (float) $this->closing_qty, 2);
    }

    public function effectiveUsedQty(): float
    {
        if ($this->used_qty !== null) {
            return round((float) $this->used_qty, 2);
        }

        if ($this->closing_qty !== null) {
            return round(max(0.0, $this->totalAvailableQty() - (float) $this->closing_qty), 2);
        }

        return 0.0;
    }

    public function currentEffectiveQty(): float
    {
        if ($this->closing_qty !== null) {
            return round((float) $this->closing_qty, 2);
        }

        return round(max(0.0, $this->totalAvailableQty() - (float) ($this->used_qty ?? 0)), 2);
    }

    /**
     * Recalculate Remaining (`closing_qty`) after Opening or Added/Transfer changes,
     * preserving existing `used_qty` (defaulting to 0).
     */
    public function syncBalanceFromUsage(): void
    {
        $used = round(max(0.0, (float) ($this->used_qty ?? 0)), 2);
        $total = $this->totalAvailableQty();
        $this->used_qty = $used;
        $this->closing_qty = round(max(0.0, $total - $used), 2);
    }

    public function needToOrderQty(float $parLevel): float
    {
        if ($parLevel <= 0) {
            return 0.0;
        }

        $current = $this->currentEffectiveQty();
        if ($current >= $parLevel) {
            return 0.0;
        }

        return round(max(0.0, $parLevel - $current), 2);
    }

    public function hasNegativeUsage(): bool
    {
        return $this->used_qty !== null && (float) $this->used_qty < -0.005;
    }

    public function hasRetailShrinkage(): bool
    {
        if (!$this->item || $this->item->item_type !== StockItem::TYPE_RETAIL || $this->used_qty === null) {
            return false;
        }

        return ((float) $this->used_qty - (float) $this->retail_sold_qty) > 0.005;
    }

    public static function formatQty(?float $val): string
    {
        if ($val === null) {
            return '—';
        }
        $rounded = round($val, 2);

        return rtrim(rtrim(number_format($rounded, 2, '.', ''), '0'), '.');
    }
}
