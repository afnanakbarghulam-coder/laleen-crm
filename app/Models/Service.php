<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Service extends Model
{
    protected $fillable = [
        'name',
        'category_id',
        'description',
        'steps',
        'treatment_type',
        'photo',
        'price',
        'price_type',
        'duration',
        'rebooking_interval_days',
    ];

    protected $casts = [
        'steps' => 'array',
    ];

    /**
     * Normalize an incoming steps list into a clean, ordered array of non-empty strings.
     *
     * @return list<string>
     */
    public static function normalizeSteps(mixed $rawSteps): array
    {
        if (!is_array($rawSteps)) {
            return [];
        }

        $clean = [];
        foreach ($rawSteps as $step) {
            if (!is_scalar($step)) {
                continue;
            }
            $trimmed = trim((string) $step);
            if ($trimmed !== '') {
                $clean[] = $trimmed;
            }
        }

        return array_values($clean);
    }

    /**
     * @return list<string>
     */
    public function normalizedSteps(): array
    {
        return self::normalizeSteps($this->steps);
    }

    public function category()
    {
        return $this->belongsTo(ServiceCategory::class, 'category_id');
    }

    public function staff()
    {
        return $this->belongsToMany(Staff::class, 'service_staff');
    }

    public function combos()
    {
        return $this->belongsToMany(Combo::class, 'combo_services');
    }

    public function appointmentServices()
    {
        return $this->hasMany(AppointmentService::class, 'service_id');
    }

    public function upsells()
    {
        return $this->hasMany(AppointmentUpsell::class, 'service_id');
    }

    public function complaints()
    {
        return $this->hasMany(StaffComplaint::class, 'service_id');
    }

    public function clientPackageServices()
    {
        return $this->hasMany(ClientPackageService::class, 'service_id');
    }

    public function anchoredFreebiePackages()
    {
        return $this->hasMany(ServiceFreebiePackage::class, 'anchor_service_id');
    }

    public function freebiePackages()
    {
        return $this->belongsToMany(ServiceFreebiePackage::class, 'service_freebie_package_items', 'service_id', 'package_id');
    }

    public function freebieCredits()
    {
        return $this->hasMany(ClientFreebieCredit::class, 'redeemed_service_id');
    }

    public function monthlyOffers()
    {
        return $this->belongsToMany(MonthlyOffer::class, 'monthly_offer_service', 'service_id', 'monthly_offer_id')
            ->withTimestamps();
    }
}
