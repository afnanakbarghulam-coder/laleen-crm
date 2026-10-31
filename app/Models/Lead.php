<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Lead extends Model
{
    const CATEGORIES = [
        'inquiry' => 'Inquiry',
        'follow_up' => 'Follow up',
        'no_show' => 'No Show',
        'cancel' => 'Cancelled',
    ];

    /**
     * Strict urgency priority order for determining the primary `category`
     * column when a lead holds multiple simultaneous categories.
     */
    const CATEGORY_PRIORITY = [
        'no_show',
        'cancel',
        'follow_up',
        'inquiry',
    ];

    /**
     * Categories staff can pick when adding/editing a lead.
     */
    const MANUAL_CATEGORIES = [
        'inquiry' => 'Inquiry',
        'follow_up' => 'Follow up',
        'no_show' => 'No Show',
        'cancel' => 'Cancelled',
    ];

    const NEEDFUL_STATUSES = [
        'booked' => '✅ Booked Appointment',
        'yes' => '💬 Contacted / In Progress',
        'no' => '❌ Not Interested / Lost',
    ];

    /**
     * Statuses agents can manually select in the Needful Done dropdown.
     * 'booked' is reserved for automatic Calendar booking verification.
     */
    const MANUAL_NEEDFUL_STATUSES = [
        'yes' => '💬 Contacted / In Progress',
        'no' => '❌ Not Interested / Lost',
    ];

    const CLOSED_STATUSES = [
        'booked',
        'no',
    ];

    const COUNTRY_CODES = [
        '974' => 'Qatar',
        '91' => 'India',
        '971' => 'UAE',
        '966' => 'Saudi Arabia',
        '968' => 'Oman',
        '973' => 'Bahrain',
        '965' => 'Kuwait',
        '20' => 'Egypt',
        '63' => 'Philippines',
        '94' => 'Sri Lanka',
        '92' => 'Pakistan',
        '44' => 'UK',
    ];

    protected $fillable = [
        'phone',
        'customer_id',
        'appointment_id',
        'assigned_agent_id',
        'category',
        'categories',
        'customer_remarks',
        'service_interest',
        'needful_done',
        'next_followup_date',
    ];

    protected $casts = [
        'next_followup_date' => 'date',
        'service_interest' => 'array',
        'categories' => 'array',
    ];

    protected static function booted(): void
    {
        static::saving(function (Lead $lead) {
            $rawCategories = is_array($lead->categories) ? $lead->categories : [];

            if ($lead->isDirty('categories') && !empty($rawCategories)) {
                $normalized = self::normalizeCategories($rawCategories);
                $lead->categories = $normalized;
                $lead->category = self::resolvePrimaryCategory($normalized);
            } elseif ($lead->category || !empty($rawCategories)) {
                $combined = array_merge($rawCategories, $lead->category ? [$lead->category] : []);
                $normalized = self::normalizeCategories($combined);
                $lead->categories = !empty($normalized) ? $normalized : null;
                $lead->category = self::resolvePrimaryCategory($normalized) ?? $lead->category;
            }
        });
    }

    /**
     * Normalize, deduplicate, and sort category keys by strict urgency priority:
     * no_show > cancel > follow_up > inquiry.
     */
    public static function normalizeCategories(array|string|null $categories): array
    {
        $items = is_array($categories) ? $categories : ($categories ? [$categories] : []);
        $cleaned = array_values(array_unique(array_filter(array_map(
            fn($c) => is_string($c) ? trim($c) : '',
            $items
        ))));

        usort($cleaned, function (string $a, string $b) {
            $posA = array_search($a, self::CATEGORY_PRIORITY, true);
            $posB = array_search($b, self::CATEGORY_PRIORITY, true);
            $posA = $posA === false ? 999 : $posA;
            $posB = $posB === false ? 999 : $posB;

            return $posA <=> $posB;
        });

        return $cleaned;
    }

    /**
     * Return the highest-urgency category from a list of categories.
     */
    public static function resolvePrimaryCategory(array $categories): ?string
    {
        foreach (self::CATEGORY_PRIORITY as $priorityCat) {
            if (in_array($priorityCat, $categories, true)) {
                return $priorityCat;
            }
        }

        return $categories[0] ?? null;
    }

    /**
     * Merge new category status(es) into this lead's `categories` array and
     * update the primary `category` column to the highest-priority status.
     */
    public function mergeCategories(array|string|null $newCategories): array
    {
        $existing = is_array($this->categories) && !empty($this->categories)
            ? $this->categories
            : ($this->category ? [$this->category] : []);
        $incoming = is_array($newCategories) ? $newCategories : ($newCategories ? [$newCategories] : []);

        $merged = self::normalizeCategories(array_merge($existing, $incoming));
        $this->categories = !empty($merged) ? $merged : null;
        $this->category = self::resolvePrimaryCategory($merged);

        return $merged;
    }

    public function getCategoriesListAttribute(): array
    {
        return self::normalizeCategories(
            (is_array($this->categories) && !empty($this->categories))
                ? $this->categories
                : ($this->category ? [$this->category] : [])
        );
    }

    public function hasCategory(string|array $category): bool
    {
        $targets = (array) $category;
        $current = $this->categories_list;

        foreach ($targets as $target) {
            if (in_array($target, $current, true)) {
                return true;
            }
        }

        return false;
    }

    public function scopeWhereHasCategory($query, string|array $categories)
    {
        $cats = array_values(array_filter((array) $categories));

        return $query->where(function ($q) use ($cats) {
            $q->whereIn('category', $cats);
            foreach ($cats as $cat) {
                $q->orWhereJsonContains('categories', $cat);
            }
        });
    }

    public function agent()
    {
        return $this->belongsTo(User::class, 'assigned_agent_id');
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * The specific appointment that auto-generated this lead (No Show/Cancel
     * only - manually created leads never have this set). Null for those.
     */
    public function appointment()
    {
        return $this->belongsTo(Appointment::class);
    }

    /**
     * Combine a country code + local number into the digits-only phone format
     * used everywhere else in the app (Customers, Appointments, Calendar all
     * store bare local numbers with no country code - e.g. "55532347", not
     * "97455532347"). Qatar is the default/legacy convention, so it's left
     * unprefixed to match every existing client record; only a non-Qatar code
     * is actually prepended, since there's no existing data to conflict with
     * there.
     */
    public static function normalizePhone(?string $countryCode, ?string $number): string
    {
        $digits = preg_replace('/\D+/', '', (string) $number);

        if ($countryCode && $countryCode !== '974') {
            $digits = preg_replace('/\D+/', '', $countryCode) . $digits;
        }

        return $digits;
    }

    /**
     * Split a stored digits-only phone number into [country_code, local_number]
     * for pre-filling the country code select + number input on edit. Falls
     * back to Qatar with the full number if no known code matches.
     */
    public static function splitPhone(?string $phone): array
    {
        $phone = (string) $phone;
        $codes = array_keys(self::COUNTRY_CODES);
        usort($codes, fn($a, $b) => strlen($b) - strlen($a));

        foreach ($codes as $code) {
            if (str_starts_with($phone, $code)) {
                return [$code, substr($phone, strlen($code))];
            }
        }

        return ['974', $phone];
    }
}
