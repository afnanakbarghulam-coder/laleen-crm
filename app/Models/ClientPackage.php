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
        'purchased_at',
        'expires_at',
        'status',
    ];

    protected $casts = [
        'purchased_at' => 'datetime',
        'expires_at' => 'datetime',
        'price_paid' => 'float',
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
        return now()->gt($this->expires_at);
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
     * redemption path so the rule can't be bypassed.
     */
    public function canRedeem(): bool
    {
        return $this->status !== 'expired' && !$this->is_expired && $this->remaining_count > 0;
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
        $query = static::where('status', '!=', 'expired')->where('expires_at', '<', now());

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
     * its remaining balance, rather than trusting a status column that a
     * scheduled job may not have touched yet. An expired package simply
     * forfeits whatever balance it had left - there's no specific pending
     * row to lock, since an unused slot was never tied to one service.
     */
    public function refreshStatus(): void
    {
        if ($this->is_expired && $this->status !== 'expired') {
            $this->status = 'expired';
            $this->save();

            return;
        }

        if (!$this->is_expired && $this->status === 'active' && $this->remaining_count <= 0) {
            $this->status = 'completed';
            $this->save();
        }
    }
}
