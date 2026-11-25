<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AppointmentService extends Model
{
    protected $fillable = [
        'appointment_id',
        'service_id',
        'combo_id',
        'combo_name',
        'client_package_id',
        'freebie_package_id',
        'freebie_credit_id',
        'is_freebie_redemption',
        'staff_id',
        'sort_order',
        'is_parallel',
        'name',
        'price',
        'original_price',
        'duration',
        'start_time',
        'slice_end_at',
        'is_overtime',
        'overtime_minutes',
        'status',
        'is_pending',
        'discount_type',
        'discount_value',
        'discount_amount',
        'discount_reason',
    ];

    protected $casts = [
        'sort_order' => 'integer',
        'is_parallel' => 'boolean',
        'is_freebie_redemption' => 'boolean',
        'price' => 'float',
        'original_price' => 'float',
        'discount_value' => 'float',
        'discount_amount' => 'float',
        'start_time' => 'datetime',
        'slice_end_at' => 'datetime',
        'is_overtime' => 'boolean',
        'overtime_minutes' => 'integer',
        'is_pending' => 'boolean',
    ];

    /**
     * Keeps slice_end_at synced with [start_time + duration] for active slices
     * and keeps discount_amount in lockstep with original_price vs. final_price.
     */
    protected static function booted(): void
    {
        static::saving(function (self $line) {
            if ($line->is_pending) {
                $line->is_parallel = false;
                $line->slice_end_at = null;
            } elseif ($line->start_time !== null) {
                $start = $line->start_time instanceof \Illuminate\Support\Carbon
                    ? $line->start_time->copy()
                    : \Illuminate\Support\Carbon::parse($line->start_time);
                $line->slice_end_at = $start->addMinutes(max(1, (int) ($line->duration ?: 30)));
            } else {
                $line->slice_end_at = null;
            }

            if ($line->isPackageRedemptionLine()) {
                $line->discount_amount = 0;

                return;
            }

            if ($line->original_price !== null) {
                $line->discount_amount = max(0, round((float) $line->original_price - $line->final_price, 2));
            }
        });
    }

    public function isPackageRedemptionLine(): bool
    {
        if (
            $this->is_pending
            || $this->is_freebie_redemption
            || $this->freebie_credit_id !== null
            || $this->client_package_id !== null
            || $this->combo_id !== null
            || !empty($this->combo_name)
        ) {
            return true;
        }

        $reason = trim((string) $this->discount_reason);
        if ($reason !== '' && (stripos($reason, 'Redeemed from ') === 0 || stripos($reason, 'Freebie from ') === 0)) {
            return true;
        }

        if ($this->relationLoaded('clientPackageService')) {
            return $this->clientPackageService !== null;
        }

        return $this->exists && ClientPackageService::where('appointment_service_id', $this->id)->exists();
    }

    public function freebiePackage()
    {
        return $this->belongsTo(ServiceFreebiePackage::class, 'freebie_package_id');
    }

    public function freebieCredit()
    {
        return $this->belongsTo(ClientFreebieCredit::class, 'freebie_credit_id');
    }

    public function combo()
    {
        return $this->belongsTo(Combo::class);
    }

    public function clientPackage()
    {
        return $this->belongsTo(ClientPackage::class);
    }

    public function clientPackageService()
    {
        return $this->hasOne(ClientPackageService::class, 'appointment_service_id');
    }

    public function appointment()
    {
        return $this->belongsTo(Appointment::class);
    }

    public function priceOverrides()
    {
        return $this->hasMany(AppointmentPriceOverride::class);
    }

    public function service()
    {
        return $this->belongsTo(Service::class);
    }

    public function staff()
    {
        return $this->belongsTo(Staff::class);
    }

    public function getEndTimeAttribute()
    {
        if ($this->slice_end_at) {
            return $this->slice_end_at->copy();
        }

        return $this->start_time ? $this->start_time->copy()->addMinutes((int) ($this->duration ?: 30)) : null;
    }

    public function getFinalPriceAttribute(): float
    {
        $price = round(max(0.0, (float) $this->price), 2);
        $discountType = strtolower(trim((string) ($this->discount_type ?? '')));
        $discountVal = max(0.0, (float) $this->discount_value);
        $original = $this->original_price !== null ? round(max(0.0, (float) $this->original_price), 2) : null;

        if ($discountType === 'percent') {
            $expectedNetFromOriginal = $original !== null
                ? round(max(0.0, $original * (1.0 - min($discountVal, 100.0) / 100.0)), 2)
                : null;

            // Net price is already stored in `price` (new standard) — never double-discount.
            if ($expectedNetFromOriginal !== null && abs($price - $expectedNetFromOriginal) <= 0.02) {
                return $price;
            }

            // Legacy gross-price rows where `price` still holds the pre-discount value.
            return round(max(0.0, $price * (1.0 - min($discountVal, 100.0) / 100.0)), 2);
        }

        if ($discountType === 'fixed' || $discountType === 'flat') {
            $expectedNetFromOriginal = $original !== null
                ? round(max(0.0, $original - $discountVal), 2)
                : null;

            // Net price is already stored in `price` (new standard) — never double-discount.
            if ($expectedNetFromOriginal !== null && abs($price - $expectedNetFromOriginal) <= 0.02) {
                return $price;
            }

            // Legacy gross-price rows where `price` still holds the pre-discount value.
            return round(max(0.0, $price - $discountVal), 2);
        }

        return $price;
    }
}
