<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ClientPackage extends Model
{
    protected $fillable = [
        'customer_id',
        'combo_id',
        'sale_id',
        'appointment_id',
        'combo_name',
        'price_paid',
        'quantity_included',
        'validity_days',
        'purchased_at',
        'expires_at',
        'status',
    ];

    protected $casts = [
        'purchased_at' => 'datetime',
        'expires_at' => 'datetime',
        'price_paid' => 'float',
        'validity_days' => 'integer',
    ];

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function combo()
    {
        return $this->belongsTo(Combo::class);
    }

    public function sale()
    {
        return $this->belongsTo(Sale::class);
    }

    /**
     * The appointment at which this package was purchased (not necessarily
     * where every service in it gets redeemed).
     */
    public function appointment()
    {
        return $this->belongsTo(Appointment::class);
    }

    public function services()
    {
        return $this->hasMany(ClientPackageService::class);
    }

    public function redeemedServices()
    {
        return $this->services()->where('status', 'redeemed');
    }

    public function getIsExpiredAttribute(): bool
    {
        if ($this->expires_at === null) {
            return false;
        }

        return now()->gt($this->expires_at);
    }

    public function getEffectiveValidityDaysAttribute(): int
    {
        $configured = (int) ($this->validity_days ?: ($this->combo?->validity_days ?: 7));

        return $configured > 0 ? $configured : 7;
    }

    public function getEffectiveQuantityIncludedAttribute(): int
    {
        $qty = (int) ($this->quantity_included ?: ($this->combo?->services?->count() ?: 1));

        return max(1, $qty);
    }

    public function getPerSlotShareAttribute(): float
    {
        return round((float) $this->price_paid / $this->effective_quantity_included, 2);
    }

    /**
     * How many of this package's entitled services have actually been used
     * (either marked "Do today" at purchase, or redeemed on a later visit).
     */
    public function getRedeemedCountAttribute(): int
    {
        return $this->redeemedServices()->count();
    }

    /**
     * Still-unused slots on this package - not tied to any specific service,
     * since which pool service fills each one is only decided at redemption
     * time. This is what the "N pending" balance on a client's profile
     * actually counts.
     */
    public function getRemainingCountAttribute(): int
    {
        return max(0, $this->quantity_included - $this->redeemed_count);
    }

    /**
     * A package can only be redeemed from while it's both within the 7-day
     * (or combo-configured) validity window and still has an unused slot -
     * this is the one place that decision is made, called from every
     * redemption path so the rule can't be bypassed. Unpaid booking packages
     * (sale_id === null) can only be redeemed on their originating
     * appointment ($forAppointmentId) and never across unrelated bookings.
     */
    public function canRedeem(?int $forAppointmentId = null): bool
    {
        $isPaidOrOriginatingAppointment = $this->sale_id !== null
            || $this->appointment_id === null
            || ($forAppointmentId !== null && (int) $this->appointment_id === (int) $forAppointmentId);

        return $isPaidOrOriginatingAppointment
            && $this->status !== 'expired'
            && !$this->is_expired
            && $this->remaining_count > 0;
    }

    /**
     * The actual expiration engine: sweeps every package (or just one
     * customer's, when called from a checkout/profile page load) whose
     * stored status hasn't caught up with real time yet, and locks their
     * still-pending services. Safe to call as often as needed - a package
     * that's already reconciled is a no-op. Returns how many it locked.
     */
    public static function expireDue(?int $customerId = null): int
    {
        $query = static::where('status', '!=', 'expired')
            ->whereNotNull('sale_id')
            ->whereNotNull('expires_at')
            ->where('expires_at', '<', now());

        if ($customerId) {
            $query->where('customer_id', $customerId);
        }

        $locked = 0;
        foreach ($query->get() as $package) {
            $package->refreshStatus();
            $locked++;
        }

        return $locked;
    }

    /**
     * Lazily reconciles this package's stored status against real time and
     * its remaining balance, including reactivating an expired package when an
     * admin extends expires_at into the future while unused slots remain.
     */
    public function refreshStatus(): void
    {
        if ($this->is_expired) {
            $this->services()->where('status', 'pending')->update(['status' => 'expired']);
            if ($this->status !== 'expired') {
                $this->status = 'expired';
                $this->save();
            }

            return;
        }

        $this->services()->where('status', 'expired')->update(['status' => 'pending']);

        $targetStatus = $this->remaining_count > 0 ? 'active' : 'completed';
        if ($this->status !== $targetStatus) {
            $this->status = $targetStatus;
            $this->save();
        }
    }
}
