<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProductRdItem extends Model
{
    public const BOTTLE_STATUSES = [
        'pending'   => 'Pending',
        'finalized' => 'Finalized',
    ];

    public const STICKER_UNLOCKED_STATUSES = [
        'ready_for_dieline' => 'Ready for Die-Line',
        'in_design'         => 'In Design',
        'finalized'         => 'Finalized',
    ];

    public const STICKER_ALL_STATUSES = [
        'blocked_by_bottle' => 'Blocked by Bottle',
        'ready_for_dieline' => 'Ready for Die-Line',
        'in_design'         => 'In Design',
        'finalized'         => 'Finalized',
    ];

    protected $fillable = [
        'name',
        'slug',
        'target_launch_date',
        'winning_supplier_id',
        'notes',
        'qatar_enabled',
        'qatar_bottle_size',
        'qatar_bottle_status',
        'qatar_sticker_status',
        'pakistan_enabled',
        'pakistan_bottle_size',
        'pakistan_bottle_status',
        'pakistan_sticker_status',
    ];

    protected $casts = [
        'target_launch_date' => 'date',
        'qatar_enabled'      => 'boolean',
        'pakistan_enabled'   => 'boolean',
    ];

    public function winningSupplier(): BelongsTo
    {
        return $this->belongsTo(ProductRdSupplier::class, 'winning_supplier_id');
    }

    public function samples(): HasMany
    {
        return $this->hasMany(ProductRdSample::class, 'product_rd_item_id');
    }

    public static function normalizeBottleStatus(?string $status): string
    {
        return match (strtolower(trim((string) $status))) {
            'finalized', 'done' => 'finalized',
            default             => 'pending',
        };
    }

    public static function normalizeStickerStatus(?string $stickerStatus, string $normalizedBottleStatus): string
    {
        if ($normalizedBottleStatus !== 'finalized') {
            return 'blocked_by_bottle';
        }

        return match (strtolower(trim((string) $stickerStatus))) {
            'in_design', 'designing' => 'in_design',
            'finalized', 'done'      => 'finalized',
            default                  => 'ready_for_dieline',
        };
    }

    public function normalizedBottleStatus(string $market): string
    {
        $field = $market === 'pakistan' ? 'pakistan_bottle_status' : 'qatar_bottle_status';

        return self::normalizeBottleStatus($this->{$field});
    }

    public function normalizedStickerStatus(string $market): string
    {
        $bottle = $this->normalizedBottleStatus($market);
        $field = $market === 'pakistan' ? 'pakistan_sticker_status' : 'qatar_sticker_status';

        return self::normalizeStickerStatus($this->{$field}, $bottle);
    }

    public function bottleStatusLabel(string $market): string
    {
        $norm = $this->normalizedBottleStatus($market);

        return self::BOTTLE_STATUSES[$norm] ?? 'Pending';
    }

    public function stickerStatusLabel(string $market): string
    {
        $norm = $this->normalizedStickerStatus($market);

        return self::STICKER_ALL_STATUSES[$norm] ?? 'Blocked by Bottle';
    }
}
