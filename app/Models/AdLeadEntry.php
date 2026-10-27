<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

class AdLeadEntry extends Model
{
    /**
     * Ad type / campaign categories offered in the data-entry dropdown.
     * Keep this in sync with whatever campaigns are currently running.
     */
    const CATEGORIES = [
        'Ramadan Deal',
        'Hair Color',
        'Hair Treatment',
        'Makeup',
        'Facial',
        'Nails',
        'Spa & Massage',
        'Bridal Package',
        'Other',
    ];

    const BRANCHES = [
        'old_airport' => 'Old Airport',
        'wakrah' => 'Al Wakrah',
    ];

    protected $fillable = [
        'date',
        'phone',
        'category',
        'ticket_amount',
        'branch',
        'remarks',
        'created_by',
    ];

    protected $casts = [
        'date' => 'date',
        'ticket_amount' => 'decimal:2',
    ];

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * A lead is only counted as a booking once it's assigned a branch —
     * unbooked leads are logged with branch left blank.
     */
    public function isBooked(): bool
    {
        return !is_null($this->branch);
    }

    /**
     * All available ad categories (from the ad_categories table, default constants,
     * and any distinct categories logged in ad_lead_entries).
     */
    public static function availableCategories(): array
    {
        $categories = self::CATEGORIES;

        if (Schema::hasTable('ad_categories')) {
            $stored = AdCategory::orderBy('id')->pluck('name')->all();
            $categories = array_merge($categories, $stored);
        }

        if (Schema::hasTable('ad_lead_entries')) {
            $logged = self::query()
                ->whereNotNull('category')
                ->distinct()
                ->pluck('category')
                ->all();
            $categories = array_merge($categories, $logged);
        }

        return array_values(array_unique(array_filter(array_map('trim', $categories))));
    }
}
