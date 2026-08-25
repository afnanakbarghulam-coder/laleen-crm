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
