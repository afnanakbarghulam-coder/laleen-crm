<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Customer extends Model
{
    /**
     * Loyalty points earned per 1 QAR spent on a checkout (rounded down).
     */
    const POINTS_PER_QAR = 1;

    protected $fillable = [
        'name',
        'phone',
        'email',
        'notes',
        'allergies',
        'loyalty_points',
    ];

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
     * Packages that can still have services redeemed from them right now -
     * excludes anything already expired, even if a scheduled job hasn't
     * flipped its status column yet (real time is the source of truth).
     */
    public function activeClientPackages()
    {
        return $this->clientPackages()
            ->where('status', '!=', 'expired')
            ->where('expires_at', '>=', now());
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
    public function redeemablePackageServices()
    {
        ClientPackage::expireDue($this->id);

        return $this->activeClientPackages()
            ->with(['combo.services', 'redeemedServices'])
            ->get()
            ->filter(fn($pkg) => $pkg->remaining_count > 0 && $pkg->combo)
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
