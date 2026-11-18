<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

class ClientFreebieCredit extends Model
{
    public const STATUS_LOCKED_UNTIL_PAID = 'locked_until_paid';
    public const STATUS_AVAILABLE = 'available';
    public const STATUS_SCHEDULED = 'scheduled';
    public const STATUS_REDEEMED = 'redeemed';
    public const STATUS_VOIDED = 'voided';
    public const STATUS_EXPIRED = 'expired';

    protected $fillable = [
        'customer_id',
        'client_id',
        'package_id',
        'anchor_appointment_id',
        'anchor_booking_id',
        'slot_number',
        'status',
        'redeemed_service_id',
        'redemption_appointment_id',
        'redemption_booking_id',
        'redemption_appointment_service_id',
        'unlocked_at',
        'redeemed_at',
        'expires_at',
    ];

    protected $casts = [
        'customer_id' => 'integer',
        'package_id' => 'integer',
        'anchor_appointment_id' => 'integer',
        'slot_number' => 'integer',
        'redeemed_service_id' => 'integer',
        'redemption_appointment_id' => 'integer',
        'redemption_appointment_service_id' => 'integer',
        'unlocked_at' => 'datetime',
        'redeemed_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    public static function computeExpiresAt($anchorBookingDate, int $validityDays = 7): Carbon
    {
        $days = $validityDays > 0 ? $validityDays : 7;
        $dateString = $anchorBookingDate instanceof Carbon
            ? $anchorBookingDate->copy()->timezone('Asia/Qatar')->toDateString()
            : Carbon::parse($anchorBookingDate, 'Asia/Qatar')->toDateString();

        return Carbon::parse($dateString, 'Asia/Qatar')->addDays($days)->endOfDay();
    }

    public function scopeAvailableNotExpired(Builder $query): Builder
    {
        return $query
            ->where('status', self::STATUS_AVAILABLE)
            ->where('expires_at', '>=', now('Asia/Qatar'));
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }

    public function client()
    {
        return $this->customer();
    }

    public function package()
    {
        return $this->belongsTo(ServiceFreebiePackage::class, 'package_id');
    }

    public function anchorAppointment()
    {
        return $this->belongsTo(Appointment::class, 'anchor_appointment_id');
    }

    public function redeemedService()
    {
        return $this->belongsTo(Service::class, 'redeemed_service_id');
    }

    public function redemptionAppointment()
    {
        return $this->belongsTo(Appointment::class, 'redemption_appointment_id');
    }

    public function redemptionAppointmentService()
    {
        return $this->belongsTo(AppointmentService::class, 'redemption_appointment_service_id');
    }

    public function getClientIdAttribute(): ?int
    {
        return $this->customer_id;
    }

    public function setClientIdAttribute($value): void
    {
        $this->attributes['customer_id'] = $value !== null ? (int) $value : null;
    }

    public function getAnchorBookingIdAttribute(): ?int
    {
        return $this->anchor_appointment_id;
    }

    public function setAnchorBookingIdAttribute($value): void
    {
        $this->attributes['anchor_appointment_id'] = $value !== null ? (int) $value : null;
    }

    public function getRedemptionBookingIdAttribute(): ?int
    {
        return $this->redemption_appointment_id;
    }

    public function setRedemptionBookingIdAttribute($value): void
    {
        $this->attributes['redemption_appointment_id'] = $value !== null ? (int) $value : null;
    }

    /**
     * Dynamic Expiry Helper: Any slot with status === 'available' where
     * now('Asia/Qatar')->gt($credit->expires_at) automatically evaluates as Expired
     * in both UI scopes and backend booking validation without needing a cron job.
     */
    public function isDynamicallyExpired(?Carbon $referenceTime = null): bool
    {
        if ($this->expires_at === null) {
            return false;
        }

        $nowQatar = ($referenceTime ? $referenceTime->copy() : now('Asia/Qatar'))->timezone('Asia/Qatar');
        $expiresQatar = $this->expires_at->copy()->timezone('Asia/Qatar');

        return $nowQatar->gt($expiresQatar);
    }

    public function getIsExpiredAttribute(): bool
    {
        return $this->status === self::STATUS_AVAILABLE && $this->isDynamicallyExpired();
    }

    public function getEffectiveStatusAttribute(): string
    {
        if ($this->status === self::STATUS_AVAILABLE && $this->isDynamicallyExpired()) {
            return self::STATUS_EXPIRED;
        }

        return (string) $this->status;
    }

    public function getEffectiveStatusLabelAttribute(): string
    {
        return match ($this->effective_status) {
            self::STATUS_LOCKED_UNTIL_PAID => 'Pending Payment',
            self::STATUS_AVAILABLE => 'Available',
            self::STATUS_SCHEDULED => 'Scheduled',
            self::STATUS_REDEEMED => 'Redeemed',
            self::STATUS_EXPIRED => 'Expired',
            self::STATUS_VOIDED => 'Voided',
            default => ucfirst(str_replace('_', ' ', $this->effective_status)),
        };
    }

    public function getDaysLeftAttribute(): int
    {
        if (!$this->expires_at) {
            return 0;
        }

        $todayStart = now('Asia/Qatar')->startOfDay();
        $expiryDayStart = $this->expires_at->copy()->timezone('Asia/Qatar')->startOfDay();

        return max(0, (int) $todayStart->diffInDays($expiryDayStart, false));
    }

    public function canRedeem(?int $forAppointmentId = null, ?Carbon $forAppointmentDate = null): bool
    {
        if ($forAppointmentId !== null && (int) $this->redemption_appointment_id === (int) $forAppointmentId && $this->status === self::STATUS_SCHEDULED) {
            if ($forAppointmentDate !== null && $this->expires_at !== null) {
                $targetDayStart = $forAppointmentDate->copy()->timezone('Asia/Qatar')->startOfDay();
                if ($targetDayStart->gt($this->expires_at->copy()->timezone('Asia/Qatar'))) {
                    return false;
                }
            }

            return !$this->isDynamicallyExpired();
        }

        if ($this->status !== self::STATUS_AVAILABLE) {
            return false;
        }

        if ($this->isDynamicallyExpired()) {
            return false;
        }

        if ($forAppointmentDate !== null && $this->expires_at !== null) {
            $targetDayStart = $forAppointmentDate->copy()->timezone('Asia/Qatar')->startOfDay();
            if ($targetDayStart->gt($this->expires_at->copy()->timezone('Asia/Qatar'))) {
                return false;
            }
        }

        return true;
    }
}
