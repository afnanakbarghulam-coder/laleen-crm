<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Customer extends Model
{
    /**
     * Loyalty points earned per 1 QAR spent on a checkout (rounded down).
     */
    const POINTS_PER_QAR = 1;

    /**
     * Cooldown window (in days) after retention outreach so two agents never
     * message the same client twice.
     */
    const RETENTION_COOLDOWN_DAYS = 14;

    protected $fillable = [
        'name',
        'phone',
        'email',
        'notes',
        'allergies',
        'loyalty_points',
        'last_retention_contacted_at',
    ];

    protected $casts = [
        'last_retention_contacted_at' => 'datetime',
    ];

    /**
     * Scope to clients who have NOT been contacted for retention within the last $days days.
     */
    public function scopeNotRecentlyContacted($query, int $days = self::RETENTION_COOLDOWN_DAYS)
    {
        return $query->where(function ($q) use ($days) {
            $q->whereNull('last_retention_contacted_at')
                ->orWhere('last_retention_contacted_at', '<', now()->subDays($days));
        });
    }

    /**
     * Check whether this client is currently within the 14-day outreach cooldown window.
     */
    public function isOnRetentionCooldown(int $days = self::RETENTION_COOLDOWN_DAYS): bool
    {
        return $this->last_retention_contacted_at !== null
            && $this->last_retention_contacted_at->gte(now()->subDays($days));
    }

    public function appointments()
    {
        return $this->hasMany(Appointment::class, 'customer_id');
    }

    public function sales()
    {
        return $this->hasMany(Sale::class, 'customer_id');
    }

    public function loyaltyTransactions()
    {
        return $this->hasMany(LoyaltyTransaction::class)->latest();
    }

    public function clientPackages()
    {
        return $this->hasMany(ClientPackage::class)->latest('purchased_at');
    }

    /**
     * Normalize any phone input into the standard digits-only format via
     * Lead::splitPhone() and Lead::normalizePhone() so local 8-digit Qatar
     * numbers ('55123456') and international variants ('+974 5512 3456',
     * '97455123456') resolve to the exact same canonical phone string.
     */
    public static function normalizePhone(?string $phone): string
    {
        $raw = preg_replace('/\D+/', '', (string) $phone);
        if ($raw === '') {
            return '';
        }

        if (preg_match('/^00974(\d{8})$/', $raw, $m)) {
            return $m[1];
        }
        if (preg_match('/^974(\d{8})$/', $raw, $m)) {
            return $m[1];
        }

        [$countryCode, $localNumber] = Lead::splitPhone($raw);

        return Lead::normalizePhone($countryCode, $localNumber);
    }

    /**
     * Packages that can still have services redeemed from them right now -
     * excludes anything already expired, even if a scheduled job hasn't
     * flipped its status column yet (real time is the source of truth).
     * Unpaid booking packages (sale_id is null) are only active for their
     * originating appointment ($forAppointmentId) and never across other
     * separate appointments until paid at checkout.
     */
    public function activeClientPackages(?int $forAppointmentId = null)
    {
        return $this->clientPackages()
            ->where('status', '!=', 'expired')
            ->where('expires_at', '>=', now())
            ->where(function ($q) use ($forAppointmentId) {
                $q->whereNotNull('sale_id')
                    ->orWhereNull('appointment_id');
                if ($forAppointmentId !== null) {
                    $q->orWhere('appointment_id', $forAppointmentId);
                }
            });
    }

    /**
     * Total still-unused slots across every active package this customer
     * owns - the number shown on the profile badge. Not tied to any
     * specific service, since which pool service fills each slot is only
     * decided at redemption time.
     */
    public function getPendingPackageServiceCountAttribute(): int
    {
        return (int) $this->activeClientPackages()->get()->sum('remaining_count');
    }

    /**
     * This customer's still-redeemable choices right now, formatted for
     * both the checkout page and the booking drawer's "Redeem Package
     * Service" pickers. Each active package with an unused slot offers
     * every pool service it hasn't already redeemed - excluding whatever's
     * already been used so the same service can never be picked twice out
     * of one package's lifecycle. Reconciles against real time first, so a
     * package whose validity window has quietly lapsed never shows up here
     * even if a scheduled sweep hasn't caught it yet.
     */
    public function redeemablePackageServices(?int $forAppointmentId = null)
    {
        ClientPackage::expireDue($this->id);

        return $this->activeClientPackages($forAppointmentId)
            ->with(['combo.services', 'redeemedServices'])
            ->get()
            ->filter(fn($pkg) => $pkg->canRedeem($forAppointmentId) && $pkg->combo)
            ->flatMap(function ($pkg) {
                $usedServiceIds = $pkg->redeemedServices->pluck('service_id')->all();

                return $pkg->combo->services
                    ->whereNotIn('id', $usedServiceIds)
                    ->map(fn($service) => [
                        'id' => "{$pkg->id}:{$service->id}",
                        'client_package_id' => $pkg->id,
                        'service_id' => $service->id,
                        'service_name' => $service->name,
                        'duration' => $service->duration,
                        'combo_name' => $pkg->combo_name,
                        'expires_at' => $pkg->expires_at->format('d M Y'),
                        'days_left' => (int) floor(now()->diffInDays($pkg->expires_at, false)),
                    ]);
            })
            ->values();
    }

    /**
     * Award points for a completed sale and log the transaction.
     */
    public function earnPointsForSale(Sale $sale): int
    {
        $points = (int) floor($sale->total_amount * self::POINTS_PER_QAR);
        if ($points <= 0) {
            return 0;
        }

        $this->loyaltyTransactions()->create([
            'sale_id' => $sale->id,
            'type' => 'earn',
            'points' => $points,
            'description' => 'Earned from checkout #' . $sale->id,
        ]);

        $this->increment('loyalty_points', $points);

        return $points;
    }

    /**
     * Redeem points for a reward. Throws if the balance is insufficient.
     */
    public function redeemPoints(int $points, string $description, ?int $userId = null): void
    {
        if ($points <= 0 || $points > $this->loyalty_points) {
            throw new \InvalidArgumentException('Not enough loyalty points to redeem.');
        }

        $this->loyaltyTransactions()->create([
            'type' => 'redeem',
            'points' => -$points,
            'description' => $description,
            'created_by' => $userId,
        ]);

        $this->decrement('loyalty_points', $points);
    }
}
