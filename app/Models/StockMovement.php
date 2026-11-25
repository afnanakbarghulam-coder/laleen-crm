<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockMovement extends Model
{
    use HasFactory;

    public const TYPE_RECEIVED = 'received';
    public const TYPE_TRANSFER_IN = 'transfer_in';
    public const TYPE_TRANSFER_OUT = 'transfer_out';
    public const TYPE_OPENING_ADJUSTMENT = 'opening_adjustment';

    public const TYPES = [
        self::TYPE_RECEIVED => 'Restock Received',
        self::TYPE_TRANSFER_IN => 'Transfer In',
        self::TYPE_TRANSFER_OUT => 'Transfer Out',
        self::TYPE_OPENING_ADJUSTMENT => 'Opening Adjustment',
    ];

    protected $table = 'stock_movements';

    protected $fillable = [
        'stock_week_id',
        'stock_item_id',
        'branch',
        'type',
        'quantity',
        'counterparty_branch',
        'reference_note',
        'created_by',
    ];

    protected $casts = [
        'quantity' => 'float',
    ];

    public function week(): BelongsTo
    {
        return $this->belongsTo(StockWeek::class, 'stock_week_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(StockItem::class, 'stock_item_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function typeLabel(): string
    {
        return self::TYPES[$this->type] ?? ucwords(str_replace('_', ' ', (string) $this->type));
    }
}
